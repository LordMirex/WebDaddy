<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/tools.php';

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
        
        if (empty($customerName)) {
            $errors[] = 'Please enter your full name';
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
            'cart_snapshot' => $cartSnapshot
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
            
            // Send new order notification to admin
            sendNewOrderNotificationToAdmin(
                $orderId,
                $customerName,
                $customerPhone,
                $productNamesString,
                formatCurrency($totals['total']),
                $totals['affiliate_code'],
                $orderType
            );
            
            // Clear cart only on successful order creation
            clearCart();
            
            // Redirect to confirmation page with order ID
            header('Location: /cart-checkout.php?confirmed=' . $orderId . ($affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''));
            exit;
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
        
        // Build WhatsApp message - Conversion-focused
        $message = "🛒 *NEW ORDER REQUEST*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📋 *Order ID:* #" . $order['id'] . "\n\n";
        
        // Categorize items
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
        
        // Display templates section
        if ($templateCount > 0) {
            $message .= "🎨 *TEMPLATES* (" . $templateCount . "):\n";
            $message .= implode("\n", $templates) . "\n";
            if ($toolCount > 0) {
                $message .= "\n";
            }
        }
        
        // Display tools section
        if ($toolCount > 0) {
            $message .= "🔧 *TOOLS* (" . $toolCount . "):\n";
            $message .= implode("\n", $tools) . "\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💳 *Amount to Pay:* " . formatCurrency($order['final_amount']);
        
        if (!empty($order['affiliate_code'])) {
            $message .= "\n🎁 *Discount Applied:* 20% OFF";
        }
        
        $message .= "\n\nPlease share your payment account details so I can complete this order. Thank you! 🚀";
        
        // Generate WhatsApp link
        $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER));
        $encodedMessage = rawurlencode($message);
        $whatsappUrl = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessage;
        
        $confirmationData = [
            'order' => $order,
            'orderItems' => $orderItems,
            'hasTemplates' => $hasTemplates,
            'hasTools' => $hasTools,
            'orderTypeText' => $orderTypeText,
            'whatsappUrl' => $whatsappUrl
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
                        <span class="text-xl font-bold text-primary-900"><?php echo SITE_NAME; ?></span>
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
                                <h3 class="font-bold text-white"><?php echo $confirmationData['orderTypeText']; ?></h3>
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
                
                <!-- Customer Details Card -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 p-6">
                    <h4 class="font-bold text-white mb-4">Customer Details</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-300">Name:</span>
                            <span class="font-medium text-white"><?php echo htmlspecialchars($confirmationData['order']['customer_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-300">WhatsApp:</span>
                            <span class="font-medium text-white"><?php echo htmlspecialchars($confirmationData['order']['customer_phone']); ?></span>
                        </div>
                        <?php if (!empty($confirmationData['order']['customer_email'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-300">Email:</span>
                            <span class="font-medium text-white"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 mb-6">
                    <h4 class="font-bold text-white mb-2">📱 Next Steps</h4>
                    <p class="text-sm text-gray-100 mb-3">
                        Click the button below to send your order details via WhatsApp. Our team will confirm and provide payment instructions.
                    </p>
                </div>
                
                <!-- WhatsApp Button -->
                <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrl']); ?>" 
                   class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-6 rounded-lg transition-colors shadow-lg hover:shadow-xl text-center mb-4">
                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    Send Order via WhatsApp
                </a>
                
                <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                   class="block text-center text-primary-600 hover:text-primary-700 font-medium py-2">
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
                
                <form method="POST" action="" id="orderForm" data-validate data-loading>
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
                        </div>
                        
                        <div class="mt-6">
                            <label for="customer_email" class="block text-sm font-bold text-gray-100 mb-2">
                                Email Address (Optional)
                            </label>
                            <input type="email" 
                                   class="w-full px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" 
                                   placeholder="john@example.com">
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Order Summary -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-6">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-600 text-white font-bold mr-3">2</span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-white">Order Summary</h3>
                        </div>
                        
                        <div class="space-y-4 mb-6">
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
                            <div class="flex items-start gap-3 pb-4 border-b border-gray-700">
                                <img src="<?php echo htmlspecialchars($item['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-16 h-16 object-cover rounded"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <h3 class="font-semibold text-white"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="<?php echo $badgeColor; ?> px-2 py-0.5 text-xs font-semibold rounded whitespace-nowrap">
                                            <?php echo $badgeIcon; ?> <?php echo $badgeText; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['category'])): ?>
                                    <p class="text-xs text-gray-200 mb-1"><?php echo htmlspecialchars($item['category']); ?></p>
                                    <?php endif; ?>
                                    <div class="text-sm space-y-0.5">
                                        <p class="text-gray-100"><?php echo formatCurrency($item['price_at_add']); ?> × <?php echo $item['quantity']; ?> = <?php echo formatCurrency($itemSubtotal); ?></p>
                                        <?php if ($itemDiscount > 0): ?>
                                        <p class="text-green-600 text-xs">Discount: -<?php echo formatCurrency($itemDiscount); ?></p>
                                        <?php endif; ?>
                                        <p class="font-semibold text-primary-600"><?php echo $itemDiscount > 0 ? 'Final: ' : ''; ?><?php echo formatCurrency($itemFinal); ?></p>
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
                
                <!-- Submit Button -->
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                        </svg>
                        <p class="text-sm text-gray-100">
                            You'll be redirected to WhatsApp to complete your order. Our team will confirm your purchase and provide payment details.
                        </p>
                    </div>
                </div>
                
                <button type="submit" 
                        <?php echo !$validation['valid'] ? 'disabled' : ''; ?>
                        class="w-full bg-primary-600 hover:bg-primary-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-lg transition-colors shadow-lg hover:shadow-xl">
                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    Proceed to WhatsApp
                </button>
                
                <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                   class="block text-center text-primary-600 hover:text-primary-700 font-medium py-2 mt-4">
                    ← Continue Shopping
                </a>
                
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Preserve customer form fields when applying affiliate code
        document.addEventListener('DOMContentLoaded', function() {
            const affiliateForm = document.getElementById('affiliateForm');
            if (affiliateForm) {
                affiliateForm.addEventListener('submit', function(e) {
                    // Get current values from customer fields
                    const customerName = document.getElementById('customer_name');
                    const customerEmail = document.getElementById('customer_email');
                    const customerPhone = document.getElementById('customer_phone');
                    
                    // Update hidden fields in affiliate form
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
        });
    </script>
</body>
</html>
