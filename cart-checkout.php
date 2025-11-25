<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/mailer.php';

startSecureSession();
handleAffiliateTracking();

// Get affiliate code
$affiliateCode = getAffiliateCode();

// Check if this is an order confirmation page
$confirmedOrderId = isset($_GET['confirmed']) ? (int)$_GET['confirmed'] : null;

// Get cart items
$cartItems = getCart();
$totals = getCartTotal(null, $affiliateCode);

// If cart is empty and not showing confirmation, redirect to homepage
if (empty($cartItems) && !$confirmedOrderId) {
    header('Location: /?' . ($affiliateCode ? 'aff=' . urlencode($affiliateCode) : '') . '#products');
    exit;
}

// Validate cart
$validation = validateCart();
$errors = [];
$success = '';

// Track submitted affiliate code for error display
$submittedAffiliateCode = '';

// Handle affiliate code application (separate from order submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_affiliate'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $submittedAffiliateCode = strtoupper(trim($_POST['affiliate_code'] ?? ''));
        
        if (!empty($submittedAffiliateCode)) {
            $lookupAffiliate = getAffiliateByCode($submittedAffiliateCode);
            
            if ($lookupAffiliate && $lookupAffiliate['status'] === 'active') {
                $affiliateCode = $submittedAffiliateCode;
                
                $_SESSION['affiliate_code'] = $affiliateCode;
                setcookie(
                    'affiliate_code',
                    $affiliateCode,
                    time() + (defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400,
                    '/',
                    '',
                    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    true
                );
                
                // Increment affiliate clicks
                if (function_exists('incrementAffiliateClick')) {
                    incrementAffiliateClick($affiliateCode);
                }
                
                // Recalculate totals with discount
                $totals = getCartTotal(null, $affiliateCode);
                
                $success = '20% discount applied successfully!';
                $submittedAffiliateCode = '';
            } else {
                $errors[] = 'Invalid or inactive affiliate code.';
            }
        } else {
            $errors[] = 'Please enter an affiliate code.';
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['apply_affiliate'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    }
    
    if (empty($errors)) {
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'manual');
        
        if (empty($customerName)) {
            $errors[] = 'Please enter your full name';
        }
        
        if (empty($customerEmail)) {
            $errors[] = 'Please enter your email address';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($customerPhone)) {
            $errors[] = 'Please enter your WhatsApp number';
        }
        
        // Revalidate cart
        $validation = validateCart();
        if (!$validation['valid']) {
            $errors[] = 'Some items in your cart are no longer available. Please review your cart.';
        }
    }
    
    if (empty($errors)) {
        // Re-fetch cart items and totals after validation to ensure we have fresh data
        $cartItems = getCart();
        $totals = getCartTotal(null, $affiliateCode);
        
        // Double-check cart is still not empty
        if (empty($cartItems)) {
            header('Location: /?' . ($affiliateCode ? 'aff=' . urlencode($affiliateCode) : '') . '#products');
            exit;
        }
        
        // Generate WhatsApp message with detailed product descriptions
        $hasTemplates = false;
        $hasTools = false;
        foreach ($cartItems as $item) {
            $productType = $item['product_type'] ?? 'tool';
            if ($productType === 'template') $hasTemplates = true;
            if ($productType === 'tool') $hasTools = true;
        }
        
        $orderTypeText = '';
        if ($hasTemplates && $hasTools) {
            $orderTypeText = 'TEMPLATES & TOOLS ORDER';
        } elseif ($hasTemplates) {
            $orderTypeText = 'TEMPLATES ORDER';
        } else {
            $orderTypeText = 'TOOLS ORDER';
        }
        
        $message = "🛒 *NEW {$orderTypeText}*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*ITEMS ORDERED:*\n\n";
        
        $itemNumber = 1;
        foreach ($cartItems as $item) {
            $productType = $item['product_type'] ?? 'tool';
            $itemTotal = $item['price_at_add'] * $item['quantity'];
            $typeLabel = ($productType === 'template') ? '🎨 Template' : '🔧 Tool';
            
            $message .= "*{$itemNumber}. {$item['name']}* ({$typeLabel})\n";
            
            // Add category if available
            if (!empty($item['category'])) {
                $message .= "   Category: {$item['category']}\n";
            }
            
            // Add short description if available
            if (!empty($item['short_description'])) {
                $message .= "   " . substr($item['short_description'], 0, 80) . (strlen($item['short_description']) > 80 ? '...' : '') . "\n";
            }
            
            $message .= "   Unit Price: " . formatCurrency($item['price_at_add']) . "\n";
            if ($productType === 'tool' && $item['quantity'] > 1) {
                $message .= "   Quantity: {$item['quantity']}\n";
            }
            $message .= "   *Subtotal: " . formatCurrency($itemTotal) . "*\n\n";
            $itemNumber++;
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━\n";
        $message .= "*PRICE BREAKDOWN:*\n";
        $message .= "Subtotal: " . formatCurrency($totals['subtotal']) . "\n";
        
        if ($totals['has_discount']) {
            $message .= "Affiliate Discount (20%): -" . formatCurrency($totals['discount']) . "\n";
            $message .= "Affiliate Code: *" . $totals['affiliate_code'] . "*\n";
        }
        
        $message .= "*TOTAL TO PAY: " . formatCurrency($totals['total']) . "*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n";
        $message .= "*CUSTOMER DETAILS:*\n";
        $message .= "Name: " . $customerName . "\n";
        $message .= "WhatsApp: " . $customerPhone . "\n";
        if ($customerEmail) {
            $message .= "Email: " . $customerEmail . "\n";
        }
        $message .= "\n✅ Please confirm this order and provide payment details.";
        
        // Create cart snapshot for admin records
        $cartSnapshot = json_encode([
            'items' => $cartItems,
            'totals' => $totals,
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Build order items array for database
        $orderItems = [];
        foreach ($cartItems as $item) {
            $productType = $item['product_type'] ?? 'tool';
            $itemSubtotal = $item['price_at_add'] * $item['quantity'];
            
            // Calculate per-item discount (proportional to item subtotal)
            $itemDiscountAmount = 0;
            if ($totals['has_discount'] && $totals['subtotal'] > 0) {
                $itemDiscountAmount = ($itemSubtotal / $totals['subtotal']) * $totals['discount'];
            }
            
            $itemFinalAmount = $itemSubtotal - $itemDiscountAmount;
            
            $orderItems[] = [
                'product_type' => $productType,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price_at_add'],
                'discount_amount' => $itemDiscountAmount,
                'final_amount' => $itemFinalAmount,
                'metadata' => [
                    'name' => $item['name'],
                    'category' => $item['category'] ?? null,
                    'thumbnail_url' => $item['thumbnail_url'] ?? null
                ]
            ];
        }
        
        // Create order with items using new function
        $orderData = [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'affiliate_code' => $totals['affiliate_code'],
            'session_id' => session_id(),
            'message_text' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'original_price' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'final_amount' => $totals['total'],
            'cart_snapshot' => $cartSnapshot,
            'payment_method' => $paymentMethod
        ];
        
        $orderId = createOrderWithItems($orderData, $orderItems);
        
        if (!$orderId) {
            error_log('CRITICAL: Failed to create order for customer: ' . $customerName . ' with ' . count($cartItems) . ' items');
            global $lastDbError;
            if (isset($lastDbError) && !empty($lastDbError)) {
                error_log('Order creation error details: ' . $lastDbError);
            }
            $errors[] = 'Failed to create order. Please try again or contact support.';
        } else {
            // Log activity
            logActivity('cart_checkout', 'Cart order #' . $orderId . ' initiated with ' . count($cartItems) . ' items');
            
            // Build product names list and determine order type for admin notification
            $productNamesList = [];
            $hasTemplates = false;
            $hasTools = false;
            foreach ($cartItems as $item) {
                $productType = $item['product_type'] ?? 'tool';
                $productNamesList[] = $item['name'] . ($item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '');
                
                if ($productType === 'template') $hasTemplates = true;
                if ($productType === 'tool') $hasTools = true;
            }
            
            $orderType = 'template';
            if ($hasTemplates && $hasTools) {
                $orderType = 'mixed';
            } elseif (!$hasTemplates && $hasTools) {
                $orderType = 'tool';
            }
            
            $productNamesString = implode(', ', $productNamesList);
            
            // CRITICAL: Only send admin notification for MANUAL payments
            // For Paystack (automatic), wait for payment verification before notifying admin
            if ($paymentMethod === 'manual') {
                sendNewOrderNotificationToAdmin(
                    $orderId,
                    $customerName,
                    $customerPhone,
                    $productNamesString,
                    formatCurrency($totals['total']),
                    $totals['affiliate_code'],
                    $orderType
                );
            }
            
            // Send affiliate opportunity email to customer
            if (!empty($customerEmail)) {
                sendAffiliateOpportunityEmail($customerName, $customerEmail);
            }
            
            // Process email queue immediately after queuing emails
            require_once __DIR__ . '/includes/email_processor.php';
            ensureEmailProcessing();
            
            // Clear cart only on successful order creation
            clearCart();
            
            // CRITICAL: Different handling for payment methods - both return JSON for AJAX
            header('Content-Type: application/json');
            
            $confirmationUrl = '/cart-checkout.php?confirmed=' . $orderId . ($affiliateCode ? '&aff=' . urlencode($affiliateCode) : '');
            
            if ($paymentMethod === 'automatic') {
                // Automatic payment: Return payment data to trigger Paystack popup immediately
                // Admin will be notified AFTER payment verification (success or failure)
                echo json_encode([
                    'success' => true,
                    'payment_method' => 'automatic',
                    'order_id' => $orderId,
                    'amount' => (int)($totals['total'] * 100), // Paystack uses cents
                    'customer_email' => $customerEmail,
                    'redirect_on_failure' => $confirmationUrl
                ]);
                exit;
            } else {
                // Manual payment: Return redirect URL
                // Admin already notified above
                echo json_encode([
                    'success' => true,
                    'payment_method' => 'manual',
                    'redirect' => $confirmationUrl
                ]);
                exit;
            }
        }
    }
}

// Handle order confirmation page
$confirmationData = null;
if ($confirmedOrderId) {
    $order = getOrderById($confirmedOrderId);
    
    // SECURITY: Only allow viewing if this session created the order
    if ($order && $order['session_id'] === session_id()) {
        $orderItems = getOrderItems($confirmedOrderId);
        
        // Determine order type
        $hasTemplates = false;
        $hasTools = false;
        foreach ($orderItems as $item) {
            if ($item['product_type'] === 'template') $hasTemplates = true;
            if ($item['product_type'] === 'tool') $hasTools = true;
        }
        
        $orderTypeText = '';
        if ($hasTemplates && $hasTools) {
            $orderTypeText = 'TEMPLATES & TOOLS ORDER';
        } elseif ($hasTemplates) {
            $orderTypeText = 'TEMPLATES ORDER';
        } else {
            $orderTypeText = 'TOOLS ORDER';
        }
        
        // Categorize items (used for both messages)
        $templateCount = 0;
        $toolCount = 0;
        $templates = [];
        $tools = [];
        
        foreach ($orderItems as $item) {
            $productType = $item['product_type'];
            $productName = $productType === 'template' ? ($item['template_name'] ?? 'Product') : ($item['tool_name'] ?? 'Product');
            $qty = $item['quantity'] > 1 ? ' *(x' . $item['quantity'] . ')*' : '';
            
            if ($productType === 'template') {
                $templateCount++;
                $templates[] = "  ✅ " . $productName . $qty;
            } else {
                $toolCount++;
                $tools[] = "  ✅ " . $productName . $qty;
            }
        }
        
        // Get bank details from settings
        $bankAccountNumber = getSetting('site_account_number', '');
        $bankName = getSetting('site_bank_name', '');
        $bankNumber = getSetting('site_bank_number', '');
        
        // MESSAGE TYPE 1: Payment Proof Message (I have sent the money)
        $messagePaymentProof = "🛒 *NEW ORDER REQUEST*\n";
        $messagePaymentProof .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $messagePaymentProof .= "📋 *Order ID:* #" . $order['id'] . "\n\n";
        
        if ($templateCount > 0) {
            $messagePaymentProof .= "🎨 *TEMPLATES* (" . $templateCount . "):\n";
            $messagePaymentProof .= implode("\n", $templates) . "\n";
            if ($toolCount > 0) {
                $messagePaymentProof .= "\n";
            }
        }
        
        if ($toolCount > 0) {
            $messagePaymentProof .= "🔧 *TOOLS* (" . $toolCount . "):\n";
            $messagePaymentProof .= implode("\n", $tools) . "\n";
        }
        
        $messagePaymentProof .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $messagePaymentProof .= "💳 *Amount to Pay:* " . formatCurrency($order['final_amount']);
        
        if (!empty($order['affiliate_code'])) {
            $messagePaymentProof .= "\n🎁 *Discount Applied:* 20% OFF";
        }
        
        $messagePaymentProof .= "\n\n🏦 *PAYMENT DETAILS:*\n";
        if ($bankName) $messagePaymentProof .= "Bank: " . $bankName . "\n";
        if ($bankAccountNumber) $messagePaymentProof .= "Account: " . $bankAccountNumber . "\n";
        if ($bankNumber) $messagePaymentProof .= "Account Name: " . $bankNumber . "\n";
        $messagePaymentProof .= "\n📸 *Attached is the screenshot of my payment receipt*";
        
        // MESSAGE TYPE 2: Proceed with Payment Message (Formal structure with order details)
        $messageDiscussion = "🛒 *NEW ORDER REQUEST*\n";
        $messageDiscussion .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $messageDiscussion .= "📋 *Order ID:* #" . $order['id'] . "\n\n";
        
        if ($templateCount > 0) {
            $messageDiscussion .= "🎨 *TEMPLATES* (" . $templateCount . "):\n";
            $messageDiscussion .= implode("\n", $templates) . "\n";
            if ($toolCount > 0) {
                $messageDiscussion .= "\n";
            }
        }
        
        if ($toolCount > 0) {
            $messageDiscussion .= "🔧 *TOOLS* (" . $toolCount . "):\n";
            $messageDiscussion .= implode("\n", $tools) . "\n";
        }
        
        $messageDiscussion .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $messageDiscussion .= "💳 *Amount to Pay:* " . formatCurrency($order['final_amount']);
        
        if (!empty($order['affiliate_code'])) {
            $messageDiscussion .= "\n🎁 *Discount Applied:* 20% OFF";
        }
        
        $messageDiscussion .= "\n\n🏦 *PAYMENT DETAILS:*\n";
        if ($bankName) $messageDiscussion .= "Bank: " . $bankName . "\n";
        if ($bankAccountNumber) $messageDiscussion .= "Account: " . $bankAccountNumber . "\n";
        if ($bankNumber) $messageDiscussion .= "Account Name: " . $bankNumber . "\n";
        
        $messageDiscussion .= "\nI have some inquiries about these products before I complete the payment. Can you please help me confirm the details and answer any questions I have? Thank you! 🚀";
        
        // Generate WhatsApp links for both message types
        $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER));
        $encodedMessagePaymentProof = rawurlencode($messagePaymentProof);
        $encodedMessageDiscussion = rawurlencode($messageDiscussion);
        $whatsappUrlPaymentProof = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessagePaymentProof;
        $whatsappUrlDiscussion = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessageDiscussion;
        
        $confirmationData = [
            'order' => $order,
            'orderItems' => $orderItems,
            'hasTemplates' => $hasTemplates,
            'hasTools' => $hasTools,
            'orderTypeText' => $orderTypeText,
            'whatsappUrlPaymentProof' => $whatsappUrlPaymentProof,
            'whatsappUrlDiscussion' => $whatsappUrlDiscussion,
            'bankAccountNumber' => $bankAccountNumber,
            'bankName' => $bankName,
            'bankNumber' => $bankNumber
        ];
    } else {
        // Invalid order or unauthorized access - redirect to home
        if ($confirmedOrderId) {
            header('Location: /?view=tools#products');
            exit;
        }
    }
}

$pageTitle = $confirmedOrderId && $confirmationData ? 'Order Confirmed - ' . SITE_NAME : 'Checkout - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        // Flag to indicate if this is a confirmation page
        const isConfirmationPage = <?php echo json_encode($confirmedOrderId ? true : false); ?>;
        
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: '#d4af37',
                        navy: '#0f172a'
                    }
                }
            }
        }
    </script>
    <script src="/assets/js/forms.js" defer></script>
    <script src="/assets/js/cart-and-tools.js" defer></script>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        input, button {
            transition: all 0.2s ease;
        }
        
        @media (max-width: 640px) {
            button, a[role="button"], input[type="button"] {
                min-height: 44px;
                min-width: 44px;
            }
        }
    </style>
</head>
<body class="bg-gray-900">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-gray-800 shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-14 mr-3" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-primary-900 dark:text-white"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md text-gray-100 bg-gray-800 hover:bg-gray-900 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-4 sm:py-6 lg:py-8">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center">
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1">Complete Your Order</h1>
                <p class="text-xs sm:text-sm lg:text-base text-white/90">One step away from getting your tools</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="max-w-3xl mx-auto">
            
            <?php if ($confirmedOrderId && $confirmationData): ?>
                <!-- Order Confirmation Page -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Order Confirmed!</h2>
                    <p class="text-gray-300">Your order has been successfully created</p>
                    <p class="text-sm text-gray-400 mt-2">Order #<?php echo $confirmationData['order']['id']; ?></p>
                </div>
                
                <!-- Order Summary Card -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 border-b border-green-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl"><?php echo $confirmationData['hasTemplates'] && $confirmationData['hasTools'] ? '🎨🔧' : ($confirmationData['hasTemplates'] ? '🎨' : '🔧'); ?></span>
                                <h3 class="font-bold text-gray-900"><?php echo $confirmationData['orderTypeText']; ?></h3>
                            </div>
                            <span class="px-3 py-1 bg-green-600 text-white text-sm font-semibold rounded-full">Pending Payment</span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <h4 class="font-bold text-white mb-4">Order Items</h4>
                        <div class="space-y-3 mb-6">
                            <?php foreach ($confirmationData['orderItems'] as $item): 
                                $productType = $item['product_type'];
                                $badgeColor = $productType === 'template' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white';
                                $badgeIcon = $productType === 'template' ? '🎨' : '🔧';
                                $badgeText = $productType === 'template' ? 'Template' : 'Tool';
                                $productName = $productType === 'template' ? ($item['template_name'] ?? 'Product') : ($item['tool_name'] ?? 'Product');
                            ?>
                            <div class="flex items-start gap-3 pb-3 border-b border-gray-700 last:border-0">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <h5 class="font-semibold text-white"><?php echo htmlspecialchars($productName); ?></h5>
                                        <span class="<?php echo $badgeColor; ?> px-2 py-0.5 text-xs font-semibold rounded whitespace-nowrap">
                                            <?php echo $badgeIcon; ?> <?php echo $badgeText; ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-100">
                                        <p><?php echo formatCurrency($item['unit_price']); ?> × <?php echo $item['quantity']; ?></p>
                                        <p class="font-semibold text-primary-600 mt-1"><?php echo formatCurrency($item['final_amount']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-700 pt-4 space-y-2">
                            <div class="flex justify-between text-gray-100">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($confirmationData['order']['original_price']); ?></span>
                            </div>
                            
                            <?php if (!empty($confirmationData['order']['discount_amount']) && $confirmationData['order']['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Affiliate Discount (20%)</span>
                                <span>-<?php echo formatCurrency($confirmationData['order']['discount_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-white pt-2 border-t border-gray-700">
                                <span>Total</span>
                                <span><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bank Payment Details Card - Matches template dark theme -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-4 p-4">
                    <h4 class="font-bold text-white text-sm mb-3 flex items-center gap-2">
                        <span>🏦</span>Bank Payment Details
                    </h4>
                    <div class="space-y-2">
                        <?php if ($confirmationData['bankAccountNumber']): ?>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-xs text-gray-500 uppercase font-medium">Account Number:</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-mono text-white"><?php echo htmlspecialchars($confirmationData['bankAccountNumber']); ?></span>
                                <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($confirmationData['bankAccountNumber']); ?>'); this.textContent='✓'; setTimeout(() => this.textContent='📋', 1500);" class="text-xs bg-gray-700 hover:bg-gray-600 text-gray-200 px-2 py-1 rounded transition-colors">📋</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($confirmationData['bankName']): ?>
                        <div class="flex items-center justify-between py-2 border-t border-gray-700">
                            <span class="text-xs text-gray-500 uppercase font-medium">Bank Name:</span>
                            <span class="text-sm font-semibold text-white"><?php echo htmlspecialchars($confirmationData['bankName']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($confirmationData['bankNumber']): ?>
                        <div class="flex items-center justify-between py-2 border-t border-gray-700">
                            <span class="text-xs text-gray-500 uppercase font-medium">Account Name:</span>
                            <span class="text-sm font-semibold text-white"><?php echo htmlspecialchars($confirmationData['bankNumber']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Instructions - Dark Theme -->
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-3 mb-4">
                    <h5 class="font-bold text-white text-sm mb-2 flex items-center gap-2">
                        <span>📝</span>What to do next:
                    </h5>
                    <ul class="text-xs text-gray-300 space-y-1 ml-2">
                        <li>1. Send exactly <span class="text-primary-400 font-semibold"><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span> to account above</li>
                        <li>2. Screenshot your payment receipt</li>
                        <li>3. Choose an option below to contact us</li>
                    </ul>
                </div>
                
                <!-- Guide: What each button does -->
                <div class="bg-gray-900 border border-gray-700 rounded-xl p-3 mb-4 space-y-3">
                    <div class="flex gap-3">
                        <span class="text-lg flex-shrink-0">⚡</span>
                        <div>
                            <div class="text-xs font-bold text-white uppercase">Button 1: I've Sent the Money</div>
                            <div class="text-xs text-gray-300">Click this if you've already transferred the money to the account above. We'll verify your payment proof screenshot and process your order immediately via WhatsApp.</div>
                        </div>
                    </div>
                    <div class="border-t border-gray-700"></div>
                    <div class="flex gap-3">
                        <span class="text-lg flex-shrink-0">💬</span>
                        <div>
                            <div class="text-xs font-bold text-white uppercase">Button 2: Pay via WhatsApp</div>
                            <div class="text-xs text-gray-300">Click this to process your payment via WhatsApp. We'll guide you through the payment process and answer any questions before you pay.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Paystack Payment Button - ONLY IF AUTOMATIC PAYMENT METHOD -->
                <?php if (!empty($confirmationData['order']['payment_method']) && $confirmationData['order']['payment_method'] === 'automatic'): ?>
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl shadow-md border border-green-500 mb-4 p-4">
                    <h4 class="font-bold text-white text-sm mb-3 flex items-center gap-2">
                        <span>💳</span>Complete Payment Securely
                    </h4>
                    <p class="text-green-50 text-xs mb-4">Click below to pay instantly with your card via Paystack</p>
                    <button type="button" 
                            id="paystack-payment-btn" 
                            class="w-full px-4 py-3 bg-white hover:bg-gray-100 text-green-600 font-bold rounded-lg transition-colors shadow-lg">
                        💳 Pay <?php echo formatCurrency($confirmationData['order']['final_amount']); ?> with Card
                    </button>
                    <p class="text-green-100 text-xs text-center mt-3">🔒 Secure payment powered by Paystack</p>
                </div>
                <?php endif; ?>
                
                <!-- WhatsApp Buttons - ONLY IF MANUAL PAYMENT METHOD -->
                <?php if (empty($confirmationData['order']['payment_method']) || $confirmationData['order']['payment_method'] === 'manual'): ?>
                <!-- Two WhatsApp Buttons - matches site button convention -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <!-- Button 1: I have sent the money - PRIMARY ACTION -->
                    <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrlPaymentProof']); ?>" 
                       class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 border border-transparent rounded-lg transition-colors whitespace-nowrap">
                        <span>⚡</span>
                        <span>I've Sent the Money</span>
                    </a>
                    
                    <!-- Button 2: Pay via WhatsApp - SECONDARY ACTION -->
                    <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrlDiscussion']); ?>" 
                       class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-gray-100 bg-gray-800 hover:bg-gray-900 border border-gray-600 rounded-lg transition-colors whitespace-nowrap">
                        <span>💬</span>
                        <span>Pay via WhatsApp</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <a href="/?<?php echo $affiliateCode ? 'aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                   class="block text-center text-xs text-gray-400 hover:text-gray-200 font-medium transition-colors py-2">
                    ← Continue Shopping
                </a>
                
            <?php else: ?>
                <!-- Regular Checkout Form -->
                <?php if (!empty($success)): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-green-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold text-green-900"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <h5 class="font-bold text-red-900 mb-2">Please fix the following errors:</h5>
                            <ul class="list-disc list-inside text-red-800 space-y-1">
                                <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            
                <?php if (!$validation['valid']): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <h5 class="font-bold text-yellow-900 mb-2">Cart Issues Found:</h5>
                            <div class="space-y-2">
                                <?php foreach ($validation['issues'] as $issue): 
                                    $productType = $issue['product_type'] ?? 'tool';
                                    $icon = $productType === 'template' ? '🎨' : '🔧';
                                    $typeLabel = $productType === 'template' ? 'Template' : 'Tool';
                                ?>
                                <div class="bg-gray-800 rounded-lg p-3 border border-yellow-300">
                                    <div class="flex items-start gap-2">
                                        <span class="text-lg"><?php echo $icon; ?></span>
                                        <div class="flex-1">
                                            <p class="font-semibold text-white"><?php echo htmlspecialchars($issue['tool_name']); ?></p>
                                            <p class="text-sm text-yellow-800 mt-1">
                                                <span class="inline-block px-2 py-0.5 bg-yellow-100 text-yellow-900 text-xs font-medium rounded mr-1"><?php echo $typeLabel; ?></span>
                                                <?php echo htmlspecialchars($issue['issue']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                               class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-semibold mt-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Return to shopping
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Affiliate Discount Banner -->
                <?php if ($totals['has_discount']): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-3 sm:p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-xs sm:text-sm font-semibold text-green-900">20% OFF! Code: <?php echo htmlspecialchars($totals['affiliate_code']); ?></span>
                        </div>
                        <span class="text-xs sm:text-sm font-bold text-green-900">-<?php echo formatCurrency($totals['discount']); ?></span>
                    </div>
                </div>
                <?php else: ?>
                <!-- Affiliate Code Input -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-gray-700 rounded-lg p-3 sm:p-4 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center flex-1">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                            </svg>
                            <span class="text-xs sm:text-sm font-semibold text-gray-900">Have an affiliate code? Get 20% OFF!</span>
                        </div>
                        <form method="POST" action="" id="affiliateForm" class="flex gap-2 flex-1 sm:flex-initial">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="customer_name" id="aff_customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
                            <input type="hidden" name="customer_email" id="aff_customer_email" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>">
                            <input type="hidden" name="customer_phone" id="aff_customer_phone" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
                            <input type="text" 
                                   name="affiliate_code" 
                                   id="affiliate_code" 
                                   value="<?php echo htmlspecialchars($submittedAffiliateCode); ?>" 
                                   placeholder="ENTER CODE"
                                   class="flex-1 sm:flex-initial sm:w-40 px-3 py-1.5 text-sm text-gray-900 placeholder:text-gray-500 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase"
                                   style="min-width: 0;">
                            <button type="submit" 
                                    name="apply_affiliate"
                                    value="1"
                                    class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
                                Apply
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="orderForm" data-validate data-loading onsubmit="handleCheckoutSubmit(event); return false;">
                <?php echo csrfTokenField(); ?>
                
                <!-- Step 1: Your Information -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-6">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-600 text-white font-bold mr-3">1</span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-white">Your Information</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="customer_name" class="block text-sm font-bold text-gray-100 mb-2">
                                    Full Name <span class="text-red-600">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                       id="customer_name" 
                                       name="customer_name" 
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" 
                                       required
                                       placeholder="John Doe">
                            </div>
                            
                            <div>
                                <label for="customer_email" class="block text-sm font-bold text-gray-100 mb-2">
                                    Email Address <span class="text-red-600">*</span>
                                </label>
                                <input type="email" 
                                       class="w-full px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                       id="customer_email" 
                                       name="customer_email" 
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" 
                                       required
                                       placeholder="you@example.com">
                            </div>

                            <div>
                                <label for="customer_phone" class="block text-sm font-bold text-gray-100 mb-2">
                                    WhatsApp Number <span class="text-red-600">*</span>
                                </label>
                                <input type="tel" 
                                       class="w-full px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                       id="customer_phone" 
                                       name="customer_phone" 
                                       value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" 
                                       required
                                       placeholder="+234 800 000 0000">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-100 mb-3">
                                    Payment Method <span class="text-red-600">*</span>
                                </label>
                                <div class="space-y-3">
                                    <div class="flex items-center p-3 border border-gray-600 rounded-lg bg-gray-700 cursor-pointer hover:bg-gray-600 transition">
                                        <input type="radio" id="method_manual" name="payment_method" value="manual" checked class="w-4 h-4 cursor-pointer" />
                                        <label for="method_manual" class="ml-3 cursor-pointer flex-1">
                                            <div class="font-semibold text-gray-100">🏦 Manual Payment (Bank Transfer)</div>
                                            <div class="text-xs text-gray-400">24-hour setup via WhatsApp</div>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center p-3 border border-gray-600 rounded-lg bg-gray-700 cursor-pointer hover:bg-gray-600 transition">
                                        <input type="radio" id="method_automatic" name="payment_method" value="automatic" class="w-4 h-4 cursor-pointer" />
                                        <label for="method_automatic" class="ml-3 cursor-pointer flex-1">
                                            <div class="font-semibold text-gray-100">💳 Automatic Payment (Card)</div>
                                            <div class="text-xs text-gray-400">Instant - powered by Paystack</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Order Summary -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white font-bold text-sm mr-2">2</span>
                            <h3 class="text-lg font-bold text-white">Order Summary</h3>
                        </div>
                        
                        <div class="space-y-0 mb-3">
                            <?php foreach ($cartItems as $item): 
                                $productType = $item['product_type'] ?? 'tool';
                                $itemSubtotal = $item['price_at_add'] * $item['quantity'];
                                $itemDiscount = 0;
                                if ($totals['has_discount'] && $totals['subtotal'] > 0) {
                                    $itemDiscount = ($itemSubtotal / $totals['subtotal']) * $totals['discount'];
                                }
                                $itemFinal = $itemSubtotal - $itemDiscount;
                                $badgeColor = $productType === 'template' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white';
                                $badgeIcon = $productType === 'template' ? '🎨' : '🔧';
                                $badgeText = $productType === 'template' ? 'Template' : 'Tool';
                            ?>
                            <div class="flex items-start gap-2 py-2 border-b border-gray-700">
                                <img src="<?php echo htmlspecialchars($item['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-12 h-12 object-cover rounded flex-shrink-0"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-1">
                                        <h3 class="font-medium text-sm text-white truncate"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="<?php echo $badgeColor; ?> px-1.5 py-0.5 text-xs font-semibold rounded whitespace-nowrap flex-shrink-0">
                                            <?php echo $badgeIcon; ?>
                                        </span>
                                    </div>
                                    <div class="text-xs space-y-0">
                                        <p class="text-gray-100"><?php echo formatCurrency($item['price_at_add']); ?> × <?php echo $item['quantity']; ?> = <span class="font-medium"><?php echo formatCurrency($itemSubtotal); ?></span></p>
                                        <?php if ($itemDiscount > 0): ?>
                                        <p class="text-green-600">-<?php echo formatCurrency($itemDiscount); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-700 pt-4 space-y-2">
                            <div class="flex justify-between text-gray-100">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($totals['subtotal']); ?></span>
                            </div>
                            
                            <?php if ($totals['has_discount']): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Affiliate Discount (20%)</span>
                                <span>-<?php echo formatCurrency($totals['discount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-white pt-2 border-t border-gray-700">
                                <span>Total</span>
                                <span><?php echo formatCurrency($totals['total']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                
                <button type="submit" 
                        <?php echo !$validation['valid'] ? 'disabled' : ''; ?>
                        class="w-full bg-primary-600 hover:bg-primary-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-lg transition-colors shadow-lg hover:shadow-xl mb-2">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirm Order
                </button>
                
                </form>
                
                <a href="/<?php echo $affiliateCode ? '?aff=' . urlencode($affiliateCode) : ''; ?>" 
                   class="block text-center text-primary-600 hover:text-primary-700 font-medium py-3 mt-2">
                    ← Continue Shopping
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Load Paystack SDK -->
    <script src="https://js.paystack.co/v1/inline.js"></script>
    
    <script>
        // AJAX Checkout Form Handler
        function handleCheckoutSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Processing...';
            
            const formData = new FormData(form);
            
            fetch(form.action || '/cart-checkout.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.headers.get('Content-Type')?.includes('application/json')) {
                    return response.json().then(data => ({ data, isJson: true }));
                } else {
                    return response.text().then(text => ({ text, isJson: false }));
                }
            })
            .then(result => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (result.isJson && result.data.success) {
                    if (result.data.payment_method === 'automatic') {
                        // Automatic payment: Open Paystack popup immediately
                        const paymentData = result.data;
                        console.log('🔵 Paystack Payment Data:', {
                            amount: paymentData.amount,
                            email: paymentData.customer_email,
                            ref: 'ORDER-' + paymentData.order_id,
                            orderId: paymentData.order_id
                        });
                        const handler = PaystackPop.setup({
                            key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                            email: paymentData.customer_email,
                            amount: paymentData.amount,
                            currency: 'NGN',
                            ref: 'ORDER-' + paymentData.order_id,
                            onClose: function() {
                                // User cancelled payment - delete the order and reload checkout
                                fetch('/api/cancel-order.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        order_id: paymentData.order_id,
                                        csrf_token: document.querySelector('[name="csrf_token"]')?.value || ''
                                    })
                                }).then(() => {
                                    alert('Payment cancelled. Order deleted.');
                                    window.location.reload();
                                }).catch(err => {
                                    // Even if delete fails, reload to clear state
                                    window.location.reload();
                                });
                            },
                            onSuccess: function(response) {
                                // Verify payment on server
                                console.log('🟢 Paystack payment successful. Reference:', response.reference);
                                const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
                                
                                // Show loading state
                                alert('Processing your payment... Please wait...');
                                
                                fetch('/api/paystack-verify.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        reference: response.reference,
                                        order_id: paymentData.order_id,
                                        csrf_token: csrfToken
                                    })
                                })
                                .then(r => {
                                    console.log('📊 Verification response status:', r.status);
                                    return r.json();
                                })
                                .then(data => {
                                    console.log('📊 Verification response:', data);
                                    if (data.success) {
                                        console.log('✅ Payment verified successfully!');
                                        alert('✅ Payment successful! Redirecting to your order...');
                                        // Redirect to confirmation page after successful payment
                                        window.location.href = paymentData.redirect_on_failure + '&payment=success';
                                    } else {
                                        console.error('❌ Verification failed:', data.message);
                                        alert('❌ Payment verification failed: ' + (data.message || 'Unknown error'));
                                        // Reload to allow retry
                                        setTimeout(() => { window.location.reload(); }, 2000);
                                    }
                                })
                                .catch(err => {
                                    console.error('❌ Fetch error:', err);
                                    alert('Error verifying payment: ' + err.message);
                                    setTimeout(() => { window.location.reload(); }, 2000);
                                });
                            }
                        });
                        handler.openIframe();
                    } else if (result.data.payment_method === 'manual') {
                        // Manual payment: Redirect to confirmation page
                        window.location.href = result.data.redirect;
                    }
                } else {
                    alert('An error occurred. Please try again.');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                console.error('❌ Form submission error:', error);
                alert('Error: ' + error.message);
            });
        }
        
        // Paystack Payment Handler (for confirmation page)
        document.getElementById('paystack-payment-btn')?.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ Processing...';
            
            const handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                email: '<?php echo htmlspecialchars($confirmationData['order']['customer_email'] ?? ''); ?>',
                amount: <?php echo (int)(($confirmationData['order']['final_amount'] ?? 0) * 100); ?>,
                currency: 'NGN',
                ref: 'ORDER-<?php echo $confirmationData['order']['id'] ?? 0; ?>',
                onClose: function() {
                    btn.disabled = false;
                    btn.textContent = '💳 Pay <?php echo formatCurrency($confirmationData['order']['final_amount'] ?? 0); ?> with Card';
                    alert('Payment cancelled. You can try again.');
                },
                onSuccess: function(response) {
                    // Verify payment on server
                    fetch('/api/paystack-verify.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            reference: response.reference,
                            order_id: <?php echo $confirmationData['order']['id'] ?? 0; ?>,
                            customer_email: '<?php echo htmlspecialchars($confirmationData['order']['customer_email'] ?? ''); ?>'
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            alert('✅ Payment successful! Your order is being processed.');
                            location.reload();
                        } else {
                            btn.disabled = false;
                            btn.textContent = '💳 Pay <?php echo formatCurrency($confirmationData['order']['final_amount'] ?? 0); ?> with Card';
                            alert('Payment verification failed: ' + (data.message || 'Unknown error'));
                        }
                    }).catch(err => {
                        btn.disabled = false;
                        btn.textContent = '💳 Pay <?php echo formatCurrency($confirmationData['order']['final_amount'] ?? 0); ?> with Card';
                        alert('Error: ' + err.message);
                    });
                }
            });
            handler.openIframe();
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            // 1. PRE-FILL FORM WITH SAVED CUSTOMER INFO FOR REPEAT BUYERS
            const savedCustomer = localStorage.getItem('webdaddy_customer');
            if (savedCustomer) {
                try {
                    const customer = JSON.parse(savedCustomer);
                    const nameField = document.getElementById('customer_name');
                    const phoneField = document.getElementById('customer_phone');
                    const emailField = document.getElementById('customer_email');
                    
                    if (nameField && customer.name) nameField.value = customer.name;
                    if (phoneField && customer.phone) phoneField.value = customer.phone;
                    if (emailField && customer.email) emailField.value = customer.email;
                } catch (e) {
                    // Invalid stored data, skip
                }
            }
            
            // 2. AUTO-APPLY AFFILIATE CODE FROM URL PARAMETER
            const urlParams = new URLSearchParams(window.location.search);
            const affiliateCodeFromUrl = urlParams.get('aff');
            if (affiliateCodeFromUrl) {
                const affiliateInput = document.getElementById('affiliate_code');
                if (affiliateInput && !affiliateInput.value) {
                    affiliateInput.value = affiliateCodeFromUrl.toUpperCase();
                    // Auto-submit the affiliate form after short delay
                    setTimeout(() => {
                        const affiliateForm = document.getElementById('affiliateForm');
                        if (affiliateForm) {
                            affiliateForm.submit();
                        }
                    }, 500);
                }
            }
            
            // 3. PRESERVE CUSTOMER DATA WHEN APPLYING AFFILIATE CODE
            const affiliateForm = document.getElementById('affiliateForm');
            if (affiliateForm) {
                affiliateForm.addEventListener('submit', function(e) {
                    const customerName = document.getElementById('customer_name');
                    const customerEmail = document.getElementById('customer_email');
                    const customerPhone = document.getElementById('customer_phone');
                    
                    if (customerName) {
                        document.getElementById('aff_customer_name').value = customerName.value;
                    }
                    if (customerEmail) {
                        document.getElementById('aff_customer_email').value = customerEmail.value;
                    }
                    if (customerPhone) {
                        document.getElementById('aff_customer_phone').value = customerPhone.value;
                    }
                });
            }
            
            // 4. SAVE CUSTOMER INFO AFTER SUCCESSFUL ORDER
            const orderForm = document.getElementById('orderForm');
            if (orderForm) {
                orderForm.addEventListener('submit', function(e) {
                    const customerName = document.getElementById('customer_name')?.value;
                    const customerPhone = document.getElementById('customer_phone')?.value;
                    const customerEmail = document.getElementById('customer_email')?.value;
                    
                    if (customerName && customerPhone) {
                        localStorage.setItem('webdaddy_customer', JSON.stringify({
                            name: customerName,
                            phone: customerPhone,
                            email: customerEmail || ''
                        }));
                    }
                });
            }
            
            // 5. FLOATING BONUS OFFER BANNER - ONLY SHOW ON CHECKOUT FORM, NOT ON CONFIRMATION PAGE
            console.log('✅ Cart Recovery Features Initialized');
            
            if (!isConfirmationPage) {
                const floatingBanner = document.createElement('div');
                floatingBanner.innerHTML = `
                    <div style="position: fixed; top: 100px; right: 20px; z-index: 40; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 12px 16px; border-radius: 8px; max-width: 250px; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3); font-family: Arial, sans-serif; pointer-events: auto;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">💰</span>
                            <div>
                                <p style="margin: 0 0 4px 0; font-weight: bold; font-size: 13px;">Special Bonus</p>
                                <p style="margin: 0; font-size: 12px; opacity: 0.95;">Code: <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-weight: bold;">HUSTLE</span> = 20% OFF</p>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(floatingBanner);
            }
        });
    </script>
</body>
</html>
