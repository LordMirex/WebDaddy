<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/tool_files.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/email_queue.php';
require_once __DIR__ . '/includes/delivery.php';
require_once __DIR__ . '/includes/bonus_codes.php';

// CRITICAL: Disable all caching for checkout page
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: -1');

startSecureSession();
handleAffiliateTracking();
handleUserReferralTracking();

// Initialize undefined variables to prevent LSP errors
$confirmationData = null;
$isManualPayment = false;
$isAutomatic = false;
$isPaid = false;
$isFailed = false;
$confirmedOrderId = null;
$confirmationStatus = null;

// Get affiliate code and referral code
$affiliateCode = getAffiliateCode();
$userReferralCode = getUserReferralCode();

// Get active bonus code (to display on checkout page)
$activeBonusCode = getActiveBonusCode();

// Get applied discount code from session (could be bonus code or affiliate code)
$appliedBonusCode = $_SESSION['applied_bonus_code'] ?? null;

// DO NOT AUTO-APPLY bonus codes - users must manually enter them
// Bonus codes should only be applied when user explicitly enters them in the form

// Get cart items
$cartItems = getCart();
// Pass user referral code to getCartTotal - it handles priority (bonus > affiliate > referral)
$totals = getCartTotal(null, $affiliateCode, $appliedBonusCode, $userReferralCode);

// Check if this is an AJAX request (multiple detection methods for reliability)
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                 (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false && $_SERVER['REQUEST_METHOD'] === 'POST');

// If cart is empty, redirect to homepage
if (empty($cartItems)) {
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Your cart is empty. Please add items before checkout.']);
        exit;
    }
    header('Location: /' . ($affiliateCode ? '?aff=' . urlencode($affiliateCode) : '') . '#products');
    exit;
}

// Validate cart
$validation = validateCart();
$errors = [];
$success = '';

// Track submitted affiliate code for error display
$submittedAffiliateCode = '';

// Handle discount code removal - ONLY allow removal of bonus codes
// Affiliate and referral codes are PERMANENT once set and cannot be removed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_discount'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        // Remove bonus code (PRIORITY) - this is allowed
        if ($appliedBonusCode) {
            $appliedBonusCode = null;
            unset($_SESSION['applied_bonus_code']);
            $success = 'Bonus code removed.';
        } else {
            // Cannot remove affiliate or referral codes
            $errors[] = 'This discount code cannot be removed once applied.';
        }
        
        // Recalculate totals
        $totals = getCartTotal(null, $affiliateCode, $appliedBonusCode, $userReferralCode);
    }
}

// Handle discount code application (bonus codes, affiliate codes, or user referral codes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_affiliate'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $submittedAffiliateCode = strtoupper(trim($_POST['affiliate_code'] ?? ''));
        if (!empty($submittedAffiliateCode)) {
            if ($submittedAffiliateCode === $appliedBonusCode || $submittedAffiliateCode === $affiliateCode) {
                $errors[] = 'Code already applied.';
            } else {
                $bonusCodeData = getBonusCodeByCode($submittedAffiliateCode);
                if ($bonusCodeData && $bonusCodeData['is_active'] && (!$bonusCodeData['expires_at'] || strtotime($bonusCodeData['expires_at']) >= time())) {
                    $appliedBonusCode = $submittedAffiliateCode;
                    $_SESSION['applied_bonus_code'] = $appliedBonusCode;
                    $affiliateCode = null; unset($_SESSION['affiliate_code']);
                    $userReferralCode = null; unset($_SESSION['referral_code']);
                    $totals = getCartTotal(null, null, $appliedBonusCode, null);
                    $success = $bonusCodeData['discount_percent'] . '% discount applied!';
                    $submittedAffiliateCode = '';
                } else {
                    $lookupAffiliate = getAffiliateByCode($submittedAffiliateCode);
                    if ($lookupAffiliate && $lookupAffiliate['status'] === 'active') {
                        $affiliateCode = $submittedAffiliateCode; $appliedBonusCode = null;
                        $_SESSION['affiliate_code'] = $affiliateCode;
                        if (function_exists('incrementAffiliateClick')) incrementAffiliateClick($affiliateCode);
                        $totals = getCartTotal(null, $affiliateCode, null, null);
                        $success = 'Affiliate discount applied!';
                    } else {
                        $lookupReferral = getUserReferralByCode($submittedAffiliateCode);
                        if ($lookupReferral && $lookupReferral['status'] === 'active') {
                            $userReferralCode = $submittedAffiliateCode; $appliedBonusCode = null;
                            $_SESSION['referral_code'] = $userReferralCode;
                            $totals = getCartTotal(null, null, null, $userReferralCode);
                            $success = 'Referral discount applied!';
                        } else { $errors[] = 'Invalid code.'; }
                    }
                }
            }
        } else { $errors[] = 'Please enter a code.'; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['apply_affiliate']) && !isset($_POST['remove_discount'])) {
    // DEBUG: Log session state at checkout submit
    $debugSessionId = session_id();
    $debugCustomerId = $_SESSION['customer_id'] ?? 'NOT_SET';
    error_log("CHECKOUT DEBUG: session_id={$debugSessionId}, customer_id={$debugCustomerId}");
    
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    }
    
    if (empty($errors)) {
        $customerName = trim($_POST['customer_name'] ?? ''); // Optional now - username auto-generated
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'manual');
        
        // Handle logged-in user data
        if (isLoggedIn()) {
            $customer = requireCustomer();
            if (empty($customerEmail)) $customerEmail = $customer['email'];
            if (empty($customerName)) $customerName = $customer['name'];
            if (empty($customerPhone)) $customerPhone = $customer['phone'];
        }
        
        // Email validation: Only require email exists (OTP already verified it)
        // Don't block with validation errors - if we have an email, proceed
        if (empty($customerEmail)) {
            // Try to get from session if available
            if (isset($_SESSION['customer_id'])) {
                $sessionCustomer = getCustomerById($_SESSION['customer_id']);
                if ($sessionCustomer) {
                    $customerEmail = $sessionCustomer['email'];
                }
            }
            // Only error if still empty after trying session
            if (empty($customerEmail)) {
                $errors[] = 'Email required';
            }
        }
        // Skip FILTER_VALIDATE_EMAIL - if OTP was verified, email is valid
        
        // Revalidate cart
        $validation = validateCart();
        if (!$validation['valid']) {
            $errors[] = 'Some items in your cart are no longer available. Please review your cart.';
        }
    }
    
    // Return JSON errors for AJAX requests
    if (!empty($errors) && $isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $errors)
        ]);
        exit;
    }
    
    if (empty($errors)) {
        // Re-fetch cart items and totals after validation to ensure we have fresh data
        $cartItems = getCart();
        $totals = getCartTotal(null, $affiliateCode, $appliedBonusCode, $userReferralCode, $customerEmail);
        
        // DEBUG: Log cart state
        error_log("CHECKOUT CART: items=" . count($cartItems) . ", customer_id=" . ($_SESSION['customer_id'] ?? 'NOT_SET'));
        
        // Double-check cart is still not empty
        if (empty($cartItems)) {
            // For AJAX requests, return JSON error instead of redirect
            if ($isAjaxRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Your cart is empty. Please add items before checkout.',
                    'debug' => ['session_id' => session_id(), 'customer_id' => $_SESSION['customer_id'] ?? null]
                ]);
                exit;
            }
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
            $discountLabel = 'Discount';
            if ($totals['discount_type'] === 'bonus_code') {
                $discountLabel = 'Bonus Code';
            } elseif ($totals['discount_type'] === 'affiliate') {
                $discountLabel = 'Affiliate';
            } elseif ($totals['discount_type'] === 'user_referral') {
                $discountLabel = 'Referral';
            }
            $message .= $discountLabel . " Discount (" . number_format($totals['discount_percent'], 0) . "%): -" . formatCurrency($totals['discount']) . "\n";
            $message .= "Discount Code: *" . $totals['discount_code'] . "*\n";
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
            'referral_code' => $totals['referral_code'] ?? null,
            'bonus_code_id' => $totals['bonus_code_id'] ?? null,
            'discount_type' => $totals['discount_type'],
            'discount_code' => $totals['discount_code'],
            'discount_percent' => $totals['discount_percent'],
            'session_id' => session_id(),
            'message_text' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'original_price' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'final_amount' => $totals['total'],
            'cart_snapshot' => $cartSnapshot,
            'payment_method' => $paymentMethod
        ];
        
        // Populate customer phone from session if missing (for returning users)
        if (empty($orderData['customer_phone']) && isLoggedIn()) {
            $orderData['customer_phone'] = $customer['phone'] ?? '';
        }
        
        $orderId = createOrderWithItems($orderData, $orderItems);
        
        if (!$orderId) {
            error_log('CRITICAL: Failed to create order for customer: ' . $customerName . ' with ' . count($cartItems) . ' items');
            global $lastDbError;
            if (isset($lastDbError) && !empty($lastDbError)) {
                error_log('Order creation error details: ' . $lastDbError);
            }
            $errors[] = 'Failed to create order. Please try again or contact support.';
        } else {
            // Track bonus code usage if applicable
            if (!empty($totals['bonus_code_id'])) {
                incrementBonusCodeUsage($totals['bonus_code_id'], $totals['total']);
                // Clear applied bonus code from session after order is placed
                unset($_SESSION['applied_bonus_code']);
            }
            
            // Log activity
            logActivity('cart_checkout', 'Cart order #' . $orderId . ' initiated with ' . count($cartItems) . ' items');
            
            // NO "Order Received" email for automatic payments - customer only receives "Payment Confirmed" email AFTER payment is verified
            // For manual payments: Customer receives "Payment Confirmed" email when admin marks as paid
            if ($paymentMethod === 'automatic') {
                error_log("📌 Automatic payment order #$orderId created. Customer will receive payment confirmation email AFTER Paystack verification.");
            } else if (!empty($customerEmail) && $paymentMethod === 'manual') {
                error_log("📌 Manual payment order #$orderId created. Customer will receive payment confirmation email when admin confirms payment.");
            }
            
            // CRITICAL FIX: Generate download tokens ONLY for tools marked as upload_complete
            // This ensures download links only appear when the tool is ready for delivery
            foreach ($cartItems as $item) {
                if (($item['product_type'] ?? 'tool') === 'tool') {
                    $toolId = $item['product_id'];
                    
                    // Check if tool is marked as upload_complete before generating tokens
                    $checkTool = getToolById($toolId, false);
                    $isToolComplete = ($checkTool && !empty($checkTool['upload_complete']));
                    
                    if ($isToolComplete) {
                        $toolFiles = getToolFiles($toolId);
                        if (!empty($toolFiles)) {
                            foreach ($toolFiles as $file) {
                                $existingToken = getDb()->prepare("SELECT id FROM download_tokens WHERE file_id = ? AND pending_order_id = ? LIMIT 1");
                                $existingToken->execute([$file['id'], $orderId]);
                                if (!$existingToken->fetch()) {
                                    $link = generateDownloadLink($file['id'], $orderId);
                                    if ($link) {
                                        error_log("✅ Generated download token for Order #$orderId, File #{$file['id']} ({$file['file_name']})");
                                    }
                                }
                            }
                        }
                    } else {
                        error_log("⏳ Tool #$toolId not marked as complete - tokens will be generated when marked upload_complete");
                    }
                }
            }
            
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
            
            // Send admin notification for MANUAL payments (payment not verified yet)
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
            
            // IMMEDIATELY send affiliate invitation email to NEW customers on FIRST purchase
            if (!empty($customerEmail)) {
                if (!isEmailAffiliate($customerEmail) && !hasAffiliateInvitationBeenSent($customerEmail)) {
                    sendAffiliateOpportunityEmail($customerName, $customerEmail);
                    error_log("✅ Affiliate invitation queued for: $customerEmail");
                }
            }
            
            // SECURITY: For AUTOMATIC payments, DO NOT send confirmation emails yet!
            // Emails will only be sent AFTER payment is verified in paystack-verify.php
            // This prevents fraud where customers cancel payment but receive confirmation
            
            // Only process queued emails for non-automatic orders (affiliate invitations)
            if ($paymentMethod !== 'automatic') {
                processEmailQueue();
            }
            
            // IMPORTANT: Do NOT clear cart here! 
            // Cart will be cleared on order-detail.php AFTER successful page load
            // This prevents empty cart if redirect fails (e.g., iframe/popup blocker issues)
            
            if ($paymentMethod === 'manual') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'payment_method' => 'manual',
                    'message' => 'Order created successfully!',
                    'redirect' => '/user/order-detail.php?id=' . $orderId
                ]);
                exit;
            }
            
            // NEW FLOW: Redirect to user order detail page, not checkout confirmation
            $orderDetailUrl = '/user/order-detail.php?id=' . $orderId;
            $fallbackUrl = '/cart-checkout.php?confirmed=' . $orderId . ($affiliateCode ? '&aff=' . urlencode($affiliateCode) : '');
            
            if ($paymentMethod === 'automatic') {
                // Automatic payment: Return payment data to trigger Paystack popup immediately
                // Payment will be initialized by Paystack JavaScript
                // Admin will be notified AFTER payment verification (success or failure)
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'payment_method' => 'automatic',
                    'order_id' => $orderId,
                    'amount' => (int)($totals['total'] * 100), // Paystack uses cents
                    'customer_email' => $customerEmail,
                    'redirect_url' => $orderDetailUrl,
                    'redirect_on_failure' => $fallbackUrl
                ]);
                exit;
            }
        }
    }
}

$pageTitle = 'Checkout - ' . SITE_NAME;
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
    <link rel="manifest" href="/site.webmanifest">
    <link rel="apple-touch-icon" href="/assets/images/favicon.png">
    
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://js.paystack.co">
    <link rel="preload" href="https://js.paystack.co/v1/inline.js" as="script">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/assets/alpine.csp.min.js"></script>
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
    <script src="/assets/js/cart-and-tools.js" defer></script>
    <style>
        /* CRITICAL: Hide Alpine.js elements until they're initialized to prevent flash of unstyled content */
        [x-cloak] { display: none !important; }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        .animate-slideDown {
            animation: slideDown 0.3s ease-out;
        }

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
        
        /* Fix checkout form input heights - prevent stretching */
        #checkout-auth-section input[type="email"],
        #checkout-auth-section input[type="password"],
        #checkout-auth-section input[type="tel"],
        #checkout-auth-section input[type="text"]:not(#otp-input) {
            height: 48px !important;
            max-height: 48px !important;
            min-height: 48px !important;
            flex-shrink: 0;
        }
        
        /* Fix checkout form buttons - prevent text breaking */
        #checkout-auth-section button {
            white-space: nowrap;
            min-width: 100px;
            height: 48px !important;
            max-height: 48px !important;
            flex-shrink: 0;
        }
        
        /* Ensure flex containers don't stretch children */
        #checkout-auth-section .flex.gap-2 {
            align-items: stretch;
        }
        
        @media (max-width: 640px) {
            button, a[role="button"], input[type="button"] {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Mobile specific fixes for checkout */
            #checkout-auth-section .flex.gap-2 {
                flex-direction: row;
                flex-wrap: nowrap;
            }
            
            #checkout-auth-section button {
                min-width: 90px;
                padding-left: 12px;
                padding-right: 12px;
            }
        }
    </style>
</head>
<body class="bg-gray-900">
    <!-- Beautiful Payment Processing Overlay -->
    <style>
        #payment-processing-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease-out;
        }
        
        #payment-processing-overlay.show {
            display: flex;
        }
        
        #payment-processing-overlay.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.9));
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .payment-modal {
            background: white;
            padding: 60px 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            max-width: 420px;
            width: 90%;
            animation: slideUp 0.4s ease-out;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .spinner-container {
            margin-bottom: 30px;
        }
        
        .animated-spinner {
            width: 60px;
            height: 60px;
            margin: 0 auto;
            position: relative;
        }
        
        .spinner-ring {
            width: 60px;
            height: 60px;
            border: 4px solid #e5e7eb;
            border-top-color: #1e40af;
            border-radius: 50%;
            animation: spin 1.2s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            position: relative;
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.3);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .success-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .checkmark {
            width: 50px;
            height: 50px;
            color: white;
            font-size: 32px;
            animation: popIn 0.6s ease-out 0.3s both;
        }
        
        @keyframes popIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .payment-status-title {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 22px;
            font-weight: 700;
        }
        
        .payment-status-message {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .success-message {
            color: #059669;
            font-weight: 600;
        }
        
        @media (max-width: 640px) {
            .payment-modal {
                padding: 40px 30px;
                max-width: 100%;
            }
            
            .payment-status-title {
                font-size: 20px;
            }
            
            .payment-status-message {
                font-size: 14px;
                word-break: break-word;
                overflow-wrap: break-word;
            }
        }
        
        /* Fix long file names in download modals */
        .file-name, [class*="file"], [class*="link"], 
        .payment-modal * {
            word-break: break-word;
            overflow-wrap: break-word;
        }
        
        /* Ensure both template and tools sections display properly on mobile mixed orders */
        .mb-6 {
            word-break: break-word;
        }
        
        @media (max-width: 768px) {
            body {
                word-wrap: break-word;
            }
            
            * {
                word-break: break-word;
                overflow-wrap: break-word;
            }
        }
    </style>
    
    <script>
        // Global state
        window.isConfirmationPage = false;
        
        // Form validation - button enabled when customer provides name and email (phone is optional)
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            function updateSubmitButtonState() {
                const nameInput = document.getElementById('customer_name');
                const emailInput = document.getElementById('customer_email');
                
                // Button enabled when name and email have values (phone is optional)
                const formValid = (nameInput && nameInput.value.trim()) && 
                                 (emailInput && emailInput.value.trim());
                
                submitBtn.disabled = !formValid;
            }
            
            document.addEventListener('input', updateSubmitButtonState);
            updateSubmitButtonState();
        });
        
        // Determine back URL based on referrer or stored session value - persists through form submissions
        window.getBackUrl = function() {
            // First check if we already stored the referrer in this session
            const storedReferrer = sessionStorage.getItem('checkout_referrer_url');
            if (storedReferrer) {
                return storedReferrer;
            }
            
            // If not stored yet, get from document.referrer
            const referrer = document.referrer;
            if (referrer && referrer.includes(window.location.hostname)) {
                // Store it for future form submissions on this page
                sessionStorage.setItem('checkout_referrer_url', referrer);
                return referrer;
            }
            
            // Default to home if no referrer found
            return '/';
        };
        
        // Store for cart drawer to use - will be updated on every page load
        window.previousPageUrl = window.getBackUrl();
    </script>
    
    <div id="payment-processing-overlay">
        <div class="payment-modal">
            <div class="spinner-container">
                <div class="animated-spinner">
                    <div class="spinner-ring"></div>
                </div>
            </div>
            <h3 class="payment-status-title">Processing Payment</h3>
            <p id="payment-processing-message" class="payment-status-message">Opening payment form...</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav id="mainNav" class="bg-navy border-b border-navy-light/50 sticky top-0 z-50 overflow-visible">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-visible">
            <div class="flex justify-between h-16 overflow-visible">
                <div class="flex items-center">
                    <a href="/" class="flex items-center group" aria-label="<?= SITE_NAME ?> Home">
                        <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" class="h-12 mr-3 group-hover:scale-110 transition-transform duration-300" loading="eager" decoding="async">
                        <span class="text-lg sm:text-2xl font-black bg-gradient-to-r from-yellow-300 via-gold to-yellow-400 bg-clip-text text-transparent tracking-wider" style="letter-spacing: 0.08em; text-shadow: 0 4px 12px rgba(217, 119, 6, 0.4), 0 0 20px rgba(217, 119, 6, 0.2); filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));"><?= SITE_NAME ?></span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a id="backBtn" href="/" 
                       class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md text-gray-100 bg-gray-800 hover:bg-gray-900 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                </div>
                <script>
                    document.getElementById('backBtn').href = window.getBackUrl();
                </script>
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
            
            <!-- Checkout Form -->
                <?php if ($isManualPayment): ?>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-4">
                            <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Order Created!</h2>
                        <p class="text-gray-300">Your order is awaiting payment</p>
                        <p class="text-sm text-gray-400 mt-2">Order #<?php echo $confirmationData['order']['id']; ?></p>
                    </div>
                    
                    <!-- ORDER SUMMARY - SIMPLE FOR MANUAL -->
                    <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between text-gray-100">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($confirmationData['order']['original_price']); ?></span>
                            </div>
                            
                            <?php if (!empty($confirmationData['order']['discount_amount']) && $confirmationData['order']['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span><?php 
                                    $discountLabel = !empty($confirmationData['order']['discount_type']) && $confirmationData['order']['discount_type'] === 'bonus_code' ? 'Bonus Code' : 'Affiliate';
                                    $discountPct = !empty($confirmationData['order']['discount_percent']) ? $confirmationData['order']['discount_percent'] : 20;
                                    echo $discountLabel . ' Discount (' . number_format($discountPct, 0) . '%)';
                                ?></span>
                                <span>-<?php echo formatCurrency($confirmationData['order']['discount_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-white pt-4 border-t border-gray-700">
                                <span>Total Amount</span>
                                <span><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Payment Details Card -->
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
                                    <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($confirmationData['bankAccountNumber']); ?>'); this.textContent='✓'; setTimeout(() => this.textContent='📋', 1000);" class="text-xs bg-gray-700 hover:bg-gray-600 text-gray-200 px-2 py-1 rounded transition-colors">📋</button>
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
                    
                    <!-- Payment Instructions -->
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
                    
                    <!-- Two WhatsApp Buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrlPaymentProof']); ?>" 
                           class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 border border-transparent rounded-lg transition-colors whitespace-nowrap">
                            <span>⚡</span>
                            <span>I've Sent the Money</span>
                        </a>
                        
                        <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrlDiscussion']); ?>" 
                           class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-gray-100 bg-gray-800 hover:bg-gray-900 border border-gray-600 rounded-lg transition-colors whitespace-nowrap">
                            <span>💬</span>
                            <span>Pay via WhatsApp</span>
                        </a>
                    </div>
                    
                    <!-- WhatsApp Number Display for Non-WhatsApp Users -->
                    <div class="text-center p-3 bg-gray-900/50 rounded-lg border border-green-600/30">
                        <p class="text-gray-400 text-sm mb-1">💬 Don't have WhatsApp?</p>
                        <p class="text-gray-300 text-sm">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" 
                               target="_blank" rel="noopener noreferrer"
                               class="text-green-400 hover:text-green-300 font-semibold transition-colors">
                                Call or WhatsApp: <?= WHATSAPP_NUMBER ?>
                            </a>
                        </p>
                    </div>
                
                <!-- ========================================
                     AUTOMATIC PAYMENT - PAID (Success)
                     Shows: Order summary + products + delivery
                     ======================================== -->
                <?php elseif ($isAutomatic && $isPaid): ?>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Payment Successful!</h2>
                        <p class="text-gray-300">Your order has been approved and processed</p>
                        <p class="text-sm text-gray-400 mt-2">Order #<?php echo $confirmationData['order']['id']; ?></p>
                    </div>
                    
                    <!-- SPAM FOLDER WARNING -->
                    <div class="bg-amber-900/30 border border-amber-600/50 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl flex-shrink-0">📧</span>
                            <div>
                                <h4 class="font-bold text-amber-300 mb-2">Important: Check Your Email</h4>
                                <p class="text-amber-200/90 text-sm leading-relaxed mb-2">
                                    We've sent confirmation and download emails to <strong class="text-amber-100"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></strong>
                                </p>
                                <div class="bg-amber-900/40 rounded-lg p-3 border border-amber-700/50">
                                    <p class="text-amber-200 text-xs font-semibold mb-1">⚠️ Emails may land in your spam/junk folder!</p>
                                    <ul class="text-amber-200/80 text-xs space-y-1 ml-3 list-disc">
                                        <li>Check your <strong>Spam</strong> or <strong>Junk</strong> folder</li>
                                        <li>If you find our email there, mark it as <strong>"Not Spam"</strong></li>
                                        <li>This ensures you receive future messages properly</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ORDER SUMMARY -->
                    <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between text-gray-100">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($confirmationData['order']['original_price']); ?></span>
                            </div>
                            
                            <?php if (!empty($confirmationData['order']['discount_amount']) && $confirmationData['order']['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span><?php 
                                    $discountLabel = !empty($confirmationData['order']['discount_type']) && $confirmationData['order']['discount_type'] === 'bonus_code' ? 'Bonus Code' : 'Affiliate';
                                    $discountPct = !empty($confirmationData['order']['discount_percent']) ? $confirmationData['order']['discount_percent'] : 20;
                                    echo $discountLabel . ' Discount (' . number_format($discountPct, 0) . '%)';
                                ?></span>
                                <span>-<?php echo formatCurrency($confirmationData['order']['discount_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-white pt-4 border-t border-gray-700">
                                <span>Total Paid</span>
                                <span class="text-green-400"><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PRODUCTS SECTION - ONLY FOR PAID AUTOMATIC -->
                    <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 p-6">
                        <h4 class="font-bold text-white mb-4">📦 Your Products</h4>
                        
                        <!-- TEMPLATES SECTION -->
                        <?php 
                        $templates = array_filter($confirmationData['orderItems'], fn($item) => $item['product_type'] === 'template');
                        if (!empty($templates)): 
                        ?>
                        <div class="mb-6 pb-6 border-b border-gray-700 block">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-xl">🎨</span>
                                <h5 class="font-semibold text-white">Website Templates</h5>
                                <span class="px-2 py-0.5 bg-blue-600/20 text-blue-400 text-xs font-semibold rounded">⏱️ Available within 24 hours</span>
                            </div>
                            
                            <!-- EMAIL NOTIFICATION MESSAGE -->
                            <div class="text-xs text-blue-300 mb-3 p-3 bg-blue-900/20 rounded border border-blue-600/50">
                                📧 We'll send your domain details to:
                                <br/><span class="font-semibold text-blue-200"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></span>
                                <br/>⏱️ <strong>Within 24 hours</strong> after admin assigns your domain
                            </div>
                            
                            <div class="text-xs text-gray-400 mb-3 p-3 bg-gray-900 rounded border border-gray-700">
                                ✓ Admin will assign your premium domain after payment confirmation
                                <br/>✓ You'll receive domain details via email & WhatsApp
                            </div>
                            <?php foreach ($templates as $item): ?>
                            <?php 
                            $delivery = $confirmationData['deliveriesByProductId'][$item['product_id']] ?? null;
                            $hasDomain = !empty($delivery['hosted_domain']);
                            ?>
                            <div class="flex items-start gap-3 pb-3 border-b border-gray-700 last:border-0 mb-2">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-white"><?php echo htmlspecialchars($item['template_name'] ?? 'Template'); ?></h5>
                                    <div class="text-sm text-gray-100">
                                        <p><?php echo formatCurrency($item['unit_price']); ?> × <?php echo $item['quantity']; ?> = <span class="text-primary-400"><?php echo formatCurrency($item['final_amount']); ?></span></p>
                                    </div>
                                    
                                    <!-- SHOW DOMAIN IF ASSIGNED -->
                                    <?php if ($hasDomain): ?>
                                    <div class="mt-2 p-2 bg-green-900/30 border border-green-600/50 rounded">
                                        <p class="text-xs text-green-300 font-semibold mb-1">✅ Your Domain is Ready!</p>
                                        <p class="text-sm font-bold text-green-400">🌐 <?php echo htmlspecialchars($delivery['hosted_domain']); ?></p>
                                        <?php if (!empty($delivery['hosted_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($delivery['hosted_url']); ?>" target="_blank" 
                                           class="text-xs text-blue-300 hover:text-blue-200 underline mt-1 inline-block">
                                            🔗 Visit Your Website
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['admin_notes'])): ?>
                                        <p class="text-xs text-gray-300 mt-2 italic">📝 Note: <?php echo htmlspecialchars($delivery['admin_notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- TOOLS SECTION -->
                        <?php 
                        $tools = array_filter($confirmationData['orderItems'], fn($item) => $item['product_type'] === 'tool');
                        if (!empty($tools)): 
                        ?>
                        <?php 
                        // Check if all tools have files ready for download AND are marked as upload_complete
                        $toolsWithFilesCount = 0;
                        $toolsWithoutFilesCount = 0;
                        foreach ($tools as $checkItem) {
                            // CRITICAL FIX: Check upload_complete status first - only show if tool is marked complete
                            $checkTool = getToolById($checkItem['product_id'], false);
                            $isUploadComplete = ($checkTool && !empty($checkTool['upload_complete']));
                            
                            if ($isUploadComplete) {
                                $checkTokens = getDownloadTokens($confirmationData['order']['id'], $checkItem['product_id']);
                                $checkFiltered = filterBestDownloadTokens($checkTokens);
                                if (!empty($checkFiltered)) {
                                    $toolsWithFilesCount++;
                                } else {
                                    $toolsWithoutFilesCount++;
                                }
                            } else {
                                // Tool not marked as complete - count as pending
                                $toolsWithoutFilesCount++;
                            }
                        }
                        $allToolsReady = ($toolsWithoutFilesCount === 0 && $toolsWithFilesCount > 0);
                        ?>
                        <div class="mb-6 block">
                            <div class="flex items-center gap-2 mb-3 flex-wrap">
                                <span class="text-xl">🔧</span>
                                <h5 class="font-semibold text-white">Tools & Resources</h5>
                                <?php if ($allToolsReady): ?>
                                <span class="px-2 py-0.5 bg-green-600/20 text-green-400 text-xs font-semibold rounded">⚡ Ready to download now</span>
                                <?php elseif ($toolsWithFilesCount > 0): ?>
                                <span class="px-2 py-0.5 bg-yellow-600/20 text-yellow-400 text-xs font-semibold rounded">⏳ Partially ready</span>
                                <?php else: ?>
                                <span class="px-2 py-0.5 bg-blue-600/20 text-blue-400 text-xs font-semibold rounded">📧 Delivered via email & WhatsApp</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($allToolsReady): ?>
                            <div class="text-xs text-gray-400 mb-3 p-3 bg-gray-900 rounded border border-gray-700">
                                ✓ Your tools are ready for instant download
                                <br/>✓ Download links sent to your email
                            </div>
                            <?php elseif ($toolsWithoutFilesCount > 0): ?>
                            <div class="text-xs text-blue-300 mb-3 p-3 bg-blue-900/20 rounded border border-blue-600/50">
                                📧 <?php echo $toolsWithoutFilesCount; ?> tool(s) - files being sent via email & WhatsApp within 24 hours
                                <?php if ($toolsWithFilesCount > 0): ?>
                                <br/>✓ <?php echo $toolsWithFilesCount; ?> tool(s) - ready to download now
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($tools as $item): 
                                // CRITICAL FIX: Check if tool is marked as upload_complete
                                $toolInfo = getToolById($item['product_id'], false);
                                $isToolUploadComplete = ($toolInfo && !empty($toolInfo['upload_complete']));
                                
                                $toolFiles = getToolFiles($item['product_id']);
                                $downloadTokens = getDownloadTokens($confirmationData['order']['id'], $item['product_id']);
                                $filteredTokens = filterBestDownloadTokens($downloadTokens);
                                $hasToolFilesUploaded = !empty($toolFiles);
                                
                                // DYNAMIC FIX: Only generate tokens if tool is marked complete
                                if ($isToolUploadComplete && $hasToolFilesUploaded && empty($filteredTokens)) {
                                    foreach ($toolFiles as $file) {
                                        $link = generateDownloadLink($file['id'], $confirmationData['order']['id']);
                                        if ($link) {
                                            error_log("✅ Dynamic: Generated download token for Order #{$confirmationData['order']['id']}, File #{$file['id']}");
                                        }
                                    }
                                    // Re-fetch tokens after generation
                                    $downloadTokens = getDownloadTokens($confirmationData['order']['id'], $item['product_id']);
                                    $filteredTokens = filterBestDownloadTokens($downloadTokens);
                                }
                                
                                // CRITICAL: Only show files if tool is marked as upload_complete
                                $hasFiles = $isToolUploadComplete && !empty($filteredTokens);
                            ?>
                            <div class="pb-4 border-b border-gray-700 last:border-0 mb-4">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-white text-lg mb-1"><?php echo htmlspecialchars($item['tool_name'] ?? 'Tool'); ?></h5>
                                    <div class="text-sm text-gray-100 mb-3">
                                        <p><?php echo formatCurrency($item['unit_price']); ?> × <?php echo $item['quantity']; ?> = <span class="text-primary-400 font-bold"><?php echo formatCurrency($item['final_amount']); ?></span></p>
                                    </div>
                                    
                                    <?php if ($hasFiles): ?>
                                    <div class="space-y-2">
                                        <?php foreach ($filteredTokens as $token): 
                                            $expiryTime = strtotime($token['expires_at']);
                                            $nowTime = time();
                                            $daysLeft = max(1, ceil(($expiryTime - $nowTime) / 86400));
                                            $isLink = preg_match('/^https?:\/\//i', $token['file_path'] ?? '');
                                        ?>
                                        <div class="flex items-center gap-2 p-3 bg-gray-900/50 rounded-lg border border-green-600/30 hover:border-green-500 transition-colors">
                                            <?php if ($isLink): ?>
                                            <a href="<?php echo SITE_URL . '/download.php?token=' . htmlspecialchars($token['token']); ?>" 
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="flex-1 text-sm text-blue-400 hover:text-blue-300 underline flex items-center gap-2 min-w-0">
                                                <span class="flex-shrink-0">🔗</span>
                                                <span class="truncate"><?php echo htmlspecialchars($token['file_name']); ?></span>
                                                <span class="text-gray-500 text-xs flex-shrink-0">(external)</span>
                                            </a>
                                            <?php else: ?>
                                            <a href="<?php echo SITE_URL . '/download.php?token=' . htmlspecialchars($token['token']); ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="flex-1 text-sm text-green-400 hover:text-green-300 underline flex items-center gap-2 min-w-0 cursor-pointer"
                                               onclick="event.stopPropagation(); this.classList.add('opacity-50'); var icon = this.querySelector('.download-icon'); if(icon) icon.innerHTML = '⏳'; return true;">
                                                <span class="download-icon flex-shrink-0">📥</span>
                                                <span class="truncate"><?php echo htmlspecialchars($token['file_name']); ?></span>
                                                <span class="text-gray-500 text-xs flex-shrink-0">(<?php echo formatFileSize($token['file_size']); ?>)</span>
                                            </a>
                                            <?php endif; ?>
                                            <span class="text-xs text-gray-400 flex-shrink-0">Valid<?php echo $daysLeft > 1 ? ' ' . $daysLeft . 'd' : ' 1d'; ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php elseif (!$isToolUploadComplete): ?>
                                    <!-- Tool not marked as complete by admin - files being prepared -->
                                    <div class="p-4 bg-amber-900/20 border border-amber-600/50 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <span class="text-xl">⏳</span>
                                            <div>
                                                <p class="text-amber-300 font-semibold text-sm mb-1">Files Being Prepared</p>
                                                <p class="text-amber-200/80 text-xs leading-relaxed">
                                                    Your files are currently being prepared and will be sent to your email at 
                                                    <strong class="text-amber-100"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></strong> 
                                                    as soon as they're ready. We'll notify you immediately!
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php elseif (!$hasToolFilesUploaded): ?>
                                    <!-- Tool marked complete but no files yet -->
                                    <div class="p-4 bg-blue-900/20 border border-blue-600/50 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <span class="text-xl">📧</span>
                                            <div>
                                                <p class="text-blue-300 font-semibold text-sm mb-1">Files on the Way!</p>
                                                <p class="text-blue-200/80 text-xs leading-relaxed">
                                                    Your tool files will be sent to your email at 
                                                    <strong class="text-blue-100"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></strong> 
                                                    and WhatsApp within 24 hours. We'll notify you as soon as they're available!
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Tool files exist but no download tokens yet -->
                                    <div class="p-4 bg-blue-900/20 border border-blue-600/50 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <span class="text-xl">⏳</span>
                                            <div>
                                                <p class="text-blue-300 font-semibold text-sm mb-1">Processing Your Download Links</p>
                                                <p class="text-blue-200/80 text-xs leading-relaxed">
                                                    Your download links are being generated. Please check your email at 
                                                    <strong class="text-blue-100"><?php echo htmlspecialchars($confirmationData['order']['customer_email']); ?></strong> 
                                                    or refresh this page in a few moments.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                
                <!-- ========================================
                     AUTOMATIC PAYMENT - FAILED
                     Shows: Error message + reason
                     NO products shown
                     ======================================== -->
                <?php elseif ($isAutomatic && $isFailed): ?>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-4">
                            <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Payment Failed</h2>
                        <p class="text-gray-300">Your payment could not be processed</p>
                        <p class="text-sm text-gray-400 mt-2">Order #<?php echo $confirmationData['order']['id']; ?></p>
                    </div>
                    
                    <!-- ERROR MESSAGE CARD -->
                    <div class="bg-red-900/20 border border-red-600 rounded-xl shadow-md mb-6 p-6">
                        <h4 class="font-bold text-red-400 text-lg mb-3 flex items-center gap-2">
                            <span>⚠️</span>Payment Declined
                        </h4>
                        <p class="text-red-200 mb-4">Unfortunately, your payment could not be processed. This may be due to:</p>
                        <ul class="text-red-200 text-sm space-y-2 mb-6 ml-4 list-disc">
                            <li>Insufficient funds on your card</li>
                            <li>Card has expired or been blocked</li>
                            <li>Incorrect card details</li>
                            <li>3D Secure verification failed</li>
                            <li>Transaction limit exceeded</li>
                        </ul>
                        <p class="text-red-300 font-semibold">Your order is still reserved. Please try again or contact support.</p>
                    </div>
                    
                    <!-- ORDER SUMMARY ONLY -->
                    <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between text-gray-100">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($confirmationData['order']['original_price']); ?></span>
                            </div>
                            
                            <?php if (!empty($confirmationData['order']['discount_amount']) && $confirmationData['order']['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span><?php 
                                    $discountLabel = !empty($confirmationData['order']['discount_type']) && $confirmationData['order']['discount_type'] === 'bonus_code' ? 'Bonus Code' : 'Affiliate';
                                    $discountPct = !empty($confirmationData['order']['discount_percent']) ? $confirmationData['order']['discount_percent'] : 20;
                                    echo $discountLabel . ' Discount (' . number_format($discountPct, 0) . '%)';
                                ?></span>
                                <span>-<?php echo formatCurrency($confirmationData['order']['discount_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-white pt-4 border-t border-gray-700">
                                <span>Amount to Pay</span>
                                <span class="text-red-400"><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RETRY BUTTON -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        <button type="button" 
                                id="paystack-payment-btn" 
                                class="px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors shadow-lg">
                            💳 Try Payment Again
                        </button>
                        
                        <a href="#" onclick="window.location.href = window.previousPageUrl; return false;" 
                           class="inline-flex items-center justify-center px-4 py-3 text-white bg-gray-700 hover:bg-gray-600 font-bold rounded-lg transition-colors">
                            ← Back to Shop
                        </a>
                    </div>
                    
                    <!-- WhatsApp Number Display for Non-WhatsApp Users -->
                    <div class="text-center p-3 bg-gray-900/50 rounded-lg border border-green-600/30">
                        <p class="text-gray-400 text-sm mb-1">💬 Don't have WhatsApp?</p>
                        <p class="text-gray-300 text-sm">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" 
                               target="_blank" rel="noopener noreferrer"
                               class="text-green-400 hover:text-green-300 font-semibold transition-colors">
                                Call or WhatsApp: <?= WHATSAPP_NUMBER ?>
                            </a>
                        </p>
                    </div>
                
                <!-- Navigation Links with User Dashboard Option -->
                <div class="mt-6 p-4 bg-gray-800/50 border border-gray-700 rounded-xl">
                    <p class="text-center text-gray-300 text-sm mb-3">Track your order and manage downloads in your dashboard:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="/user/orders.php?confirmed=<?php echo $confirmationData['order']['id']; ?>" 
                           class="inline-flex items-center justify-center gap-2 px-4 py-3 text-white bg-amber-600 hover:bg-amber-700 font-semibold rounded-lg transition-colors">
                            <i class="bi-person-badge"></i>
                            <span>Go to My Account</span>
                        </a>
                        <a href="#" onclick="window.location.href = window.previousPageUrl; return false;" 
                           class="inline-flex items-center justify-center gap-2 px-4 py-3 text-gray-100 bg-gray-700 hover:bg-gray-600 font-semibold rounded-lg transition-colors">
                            <i class="bi-shop"></i>
                            <span>Continue Shopping</span>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Regular Checkout Form -->
                <?php if (!empty($success)): ?>
                <div id="success-message" class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 transition-all duration-300 animate-slideDown">
                    <div class="flex">
                        <svg class="w-5 h-5 text-green-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold text-green-900"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                </div>
                <script>
                    // Auto-hide success message after 4 seconds with slide animation
                    setTimeout(() => {
                        const msg = document.getElementById('success-message');
                        if (msg) {
                            msg.style.animation = 'slideUp 0.3s ease-out forwards';
                            setTimeout(() => msg.remove(), 300);
                        }
                    }, 4000);
                </script>
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
                            <a id="returnLink" href="/" 
                               class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-semibold mt-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Return to shopping
                            </a>
                            <script>
                                document.getElementById('returnLink').href = window.getBackUrl();
                            </script>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Discount Code Section -->
                <?php if ($totals['has_discount']): ?>
                <!-- Applied Discount Banner with Cancel Option (only for bonus codes) -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded p-2 mb-3 text-xs sm:text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-semibold text-green-900 truncate">
                                <?php 
                                    $displayDiscountPct = number_format($totals['discount_percent'], 0);
                                    $displayDiscountType = $totals['discount_type'] === 'bonus_code' ? 'Bonus Code' : 'Affiliate';
                                    echo $displayDiscountPct . '% OFF! ' . htmlspecialchars($totals['discount_code']);
                                ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="font-bold text-green-900">-<?php echo formatCurrency($totals['discount']); ?></span>
                            <?php if ($totals['discount_type'] === 'bonus_code'): ?>
                            <button type="button" onclick="document.getElementById('removeDiscountForm').submit();" 
                                    class="p-1 text-red-600 hover:text-red-700 font-bold text-lg leading-none"
                                    title="Remove this bonus code">×</button>
                            <form method="POST" action="" id="removeDiscountForm" style="display:none;">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="remove_discount" value="1">
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ERROR MESSAGES DISPLAY -->
                <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border-2 border-red-500 rounded-lg p-4 sm:p-6 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <h3 class="font-bold text-red-900 mb-2">⚠️ Error - Please Review:</h3>
                            <ul class="text-red-800 text-sm space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li>• <?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SUCCESS MESSAGE DISPLAY -->
                <?php if (!empty($success)): ?>
                <div class="bg-green-50 border border-green-300 rounded p-2 mb-4 text-xs sm:text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="font-semibold text-green-900">✓ <?php echo htmlspecialchars($success); ?></p>
                    <button type="button" onclick="this.parentElement.remove();" class="ml-auto text-green-600 hover:text-green-800 font-bold">×</button>
                </div>
                <?php endif; ?>

                <!-- Discount Code Input Form (Hidden when discount already applied) -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-gray-700 rounded-lg p-3 sm:p-4 mb-6 <?php echo $totals['has_discount'] ? 'hidden' : ''; ?>" id="discountCodeSection">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center flex-1">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                            </svg>
                            <span class="text-xs sm:text-sm font-semibold text-gray-900">
                                <?php echo $totals['has_discount'] ? 'Have a different code?' : 'Have a discount code?'; ?>
                            </span>
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
                
                <!-- Payment Status Banner -->
                <?php if ($confirmedOrderId && $confirmationStatus !== 'none'): ?>
                <div class="mb-6 p-4 sm:p-6 rounded-lg border-2 <?php echo ($confirmationStatus === 'paid') ? 'bg-green-50 border-green-500 text-green-900' : 'bg-red-50 border-red-500 text-red-900'; ?>">
                    <div class="flex items-start gap-3">
                        <?php if ($confirmationStatus === 'paid'): ?>
                            <svg class="w-6 h-6 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="font-bold text-lg">Payment Successful!</h3>
                                <p class="text-sm mt-1">Your payment has been verified. You'll receive a confirmation email shortly with your order details and download links.</p>
                            </div>
                        <?php else: ?>
                            <svg class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="font-bold text-lg">Payment Not Completed</h3>
                                <p class="text-sm mt-1">Your payment was not processed or was cancelled. No confirmation email has been sent. Please try again or contact support.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="orderForm" data-loading onsubmit="syncAuthDataBeforeSubmit(); handleCheckoutSubmit(event); return false;">
                <?php echo csrfTokenField(); ?>
                
                <script>
                // Sync Alpine.js auth data to hidden form fields before submission
                function syncAuthDataBeforeSubmit() {
                    const authComponent = document.querySelector('[x-data="checkoutAuth"]')?.__x?.$data;
                    if (authComponent) {
                        const emailField = document.querySelector('[name="customer_email"]');
                        const nameField = document.querySelector('[name="customer_name"]');
                        const phoneField = document.querySelector('[name="customer_phone"]');
                        
                        if (emailField) emailField.value = authComponent.email || '';
                        if (nameField) nameField.value = authComponent.customerUsername || '';
                        if (phoneField) phoneField.value = authComponent.customerPhone || authComponent.phone || '';
                    }
                }
                </script>
                
                <!-- Step 1: Your Information -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-6">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-600 text-white font-bold mr-3">1</span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-white">Your Information</h3>
                        </div>
                        
                        <div id="checkout-auth-section" x-data="checkoutAuth">
                            <!-- Email Input Step -->
                            <div x-show="step === 'email'" x-cloak class="mb-6">
                                <label class="block text-sm font-bold text-gray-100 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2 flex-nowrap items-stretch">
                                    <input 
                                        type="email" 
                                        x-model="email"
                                        @keydown.enter.prevent="checkEmail()"
                                        class="flex-1 px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all min-w-0"
                                        placeholder="your@email.com"
                                        autocomplete="email"
                                    >
                                    <button 
                                        type="button"
                                        @click="checkEmail()"
                                        :disabled="loading || !email"
                                        class="px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 disabled:opacity-50 transition-all flex-shrink-0 whitespace-nowrap"
                                    >
                                        <span x-show="!loading">Continue</span>
                                        <span x-show="loading" class="inline-flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                                <p x-show="error" x-cloak class="text-red-400 text-sm mt-2 break-words" x-text="error"></p>
                            </div>

                            <!-- Password Step (returning user with password) -->
                            <div x-show="step === 'password'" x-cloak class="mb-6">
                                <div class="bg-blue-900/30 border border-blue-700 p-4 rounded-lg mb-4">
                                    <p class="text-blue-200">
                                        Welcome back! Login as <strong class="text-blue-100" x-text="email"></strong>
                                        <button type="button" @click="changeEmail()" class="text-blue-400 underline ml-2 text-sm hover:text-blue-300">change</button>
                                    </p>
                                </div>
                                <label class="block text-sm font-bold text-gray-100 mb-2">Password</label>
                                <div class="flex gap-2 flex-nowrap items-stretch">
                                    <input 
                                        type="password" 
                                        x-model="password"
                                        @keydown.enter.prevent="login()"
                                        class="flex-1 px-4 py-3 text-gray-900 placeholder:text-gray-500 border border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all min-w-0"
                                        placeholder="Enter your password"
                                    >
                                    <button 
                                        type="button"
                                        @click="login()"
                                        :disabled="loading"
                                        class="px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 disabled:opacity-50 transition-all flex-shrink-0 whitespace-nowrap"
                                    >
                                        <span x-show="!loading">Login</span>
                                        <span x-show="loading" class="inline-flex items-center">
                                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <a href="/user/forgot-password.php" class="text-sm text-primary-400 hover:text-primary-300">Forgot password?</a>
                                    <button type="button" @click="requestOTP()" class="text-sm text-primary-400 hover:text-primary-300">Use OTP instead</button>
                                </div>
                                <p x-show="error" x-cloak class="text-red-400 text-sm mt-2 break-words" x-text="error"></p>
                            </div>

                            <!-- OTP Step (new user or OTP login) -->
                            <div x-show="step === 'otp'" x-cloak class="mb-6">
                                <div class="bg-green-900/30 border border-green-700 p-4 rounded-lg mb-4">
                                    <p class="text-green-200">
                                        <svg class="w-5 h-5 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        Verification code sent to <strong class="text-green-100" x-text="email"></strong>
                                        <button type="button" @click="changeEmail()" class="text-green-400 underline ml-2 text-sm hover:text-green-300">change</button>
                                    </p>
                                </div>
                                
                                <label class="block text-sm font-bold text-gray-100 mb-3 text-center">Enter 6-digit code</label>
                                
                                <!-- Single OTP Input - Pasteable -->
                                <div class="flex justify-center mb-4">
                                    <input 
                                        type="text" 
                                        inputmode="numeric"
                                        maxlength="6"
                                        x-model="otpCode"
                                        @input="handleOTPInput($event)"
                                        @paste="handleOTPPaste($event)"
                                        id="otp-input"
                                        placeholder="000000"
                                        class="w-48 h-14 text-center text-2xl sm:text-3xl font-bold text-gray-900 border-2 border-gray-500 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all tracking-[0.5em] placeholder:tracking-[0.5em] placeholder:text-gray-400"
                                        autocomplete="one-time-code"
                                    >
                                </div>
                                
                                <!-- Prominent Spam Warning -->
                                <div class="bg-amber-900/50 border-2 border-amber-500 p-3 rounded-lg mb-4 animate-pulse">
                                    <p class="text-amber-200 text-center font-medium flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <span>Can't find the code? <strong class="text-amber-100">CHECK YOUR SPAM/JUNK FOLDER!</strong></span>
                                    </p>
                                </div>
                                
                                <p class="text-sm text-gray-400 text-center">
                                    <span x-show="!canResend">Resend in <span x-text="resendTimer"></span>s</span>
                                    <button type="button" x-show="canResend" @click="resendOTP()" class="text-primary-400 underline hover:text-primary-300">
                                        Resend code
                                    </button>
                                </p>
                                
                                <p x-show="error" class="text-red-400 text-sm text-center mt-2" x-text="error"></p>
                            </div>

                            <!-- Authenticated State -->
                            <div x-show="step === 'authenticated'" x-cloak class="mb-6">
                                <div class="bg-green-900/40 border border-green-600 p-4 rounded-lg flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        <div>
                                            <p class="font-semibold text-green-100" x-text="customerUsername || email"></p>
                                            <p class="text-sm text-green-300" x-text="email"></p>
                                        </div>
                                    </div>
                                    <button type="button" @click="logout()" class="text-green-400 hover:text-green-300 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                        Switch
                                    </button>
                                </div>
                                
                            </div>
                            
                            <!-- Hidden fields for form submission - ALWAYS present regardless of step -->
                            <input type="hidden" name="customer_id" :value="customerId">
                            <input type="hidden" name="customer_email" id="customer_email" :value="email">
                            <input type="hidden" name="customer_name" id="customer_name" :value="customerUsername || ''">
                            <input type="hidden" name="customer_phone" id="customer_phone_hidden" :value="customerPhone || phone">
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method Section - ALWAYS VISIBLE -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-6">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-600 text-white font-bold mr-3">2</span>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-white">Payment Method</h3>
                        </div>
                        
                        <label class="block text-sm font-bold text-gray-100 mb-3">
                            Select Payment Method <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-3" id="payment-method-container">
                            <div class="flex items-center p-4 border-2 border-gray-600 rounded-lg bg-gray-700 cursor-pointer hover:bg-gray-600 transition" id="manual-option">
                                <input type="radio" id="method_manual" name="payment_method" value="manual" checked class="w-5 h-5 cursor-pointer" />
                                <label for="method_manual" class="ml-4 cursor-pointer flex-1">
                                    <div class="font-bold text-lg text-gray-100">🏦 Manual Payment</div>
                                    <div class="text-sm text-gray-300 mt-1">Bank Transfer • Get account details via WhatsApp • 24-hour setup</div>
                                </label>
                            </div>
                            
                            <div class="flex items-center p-4 border-2 border-gray-600 rounded-lg bg-gray-700 cursor-pointer hover:bg-gray-600 transition" id="automatic-option">
                                <input type="radio" id="method_automatic" name="payment_method" value="automatic" class="w-5 h-5 cursor-pointer" />
                                <label for="method_automatic" class="ml-4 cursor-pointer flex-1">
                                    <div class="font-bold text-lg text-gray-100">💳 Automatic Payment</div>
                                    <div class="text-sm text-gray-300 mt-1">Card Payment • Instant approval • Immediate access</div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Order Summary -->
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-6 overflow-hidden">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center mb-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white font-bold text-sm mr-2">3</span>
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
                                <span><?php 
                                    $summaryDiscountLabel = $totals['discount_type'] === 'bonus_code' ? 'Bonus Code' : 'Affiliate';
                                    echo $summaryDiscountLabel . ' Discount (' . number_format($totals['discount_percent'], 0) . '%)';
                                ?></span>
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
                
                
                <!-- Submit Button Container - Form validity controls button state -->
                <div id="submit-btn-container">
                    
                    <!-- Auth required message - shown until user authenticates -->
                    <div id="auth-required-msg" class="bg-amber-900/50 border border-amber-500 rounded-lg p-4 mb-4 text-center">
                        <p class="text-amber-200 font-medium">
                            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                            Complete email verification above to continue
                        </p>
                    </div>
                    
                    <!-- Submit button - Disabled until user authenticates -->
                    <button type="submit" 
                            id="submit-btn"
                            disabled
                            class="w-full disabled:bg-gray-500 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-lg transition-colors shadow-lg hover:shadow-xl mb-2 bg-primary-600 hover:bg-primary-700">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span id="submit-text">Complete Email Verification First</span>
                    </button>
                </div>
                
                </form>
                
                <a href="#" onclick="window.location.href = window.previousPageUrl; return false;" 
                   class="block text-center text-primary-600 hover:text-primary-700 font-medium py-3 mt-2">
                    ← Continue Shopping
                </a>
                
                <!-- Need Help? Contact Section -->
                <div class="mt-6 p-4 bg-gray-800/50 border border-gray-700 rounded-xl text-center">
                    <p class="text-gray-400 text-sm mb-2">💬 Need Help With Your Order?</p>
                    <p class="text-gray-300 text-sm">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" 
                           target="_blank" rel="noopener noreferrer"
                           class="text-green-400 hover:text-green-300 font-semibold transition-colors">
                            WhatsApp: <?= WHATSAPP_NUMBER ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Load Paystack SDK -->
    <script src="https://js.paystack.co/v1/inline.js"></script>
    
    <script>
        // DIRECT radio button selection
        const methodManual = document.getElementById('method_manual');
        const methodAutomatic = document.getElementById('method_automatic');
        
        // Make payment method boxes clickable
        if (document.getElementById('manual-option')) {
            document.getElementById('manual-option').addEventListener('click', function(e) {
                console.log('🔘 Manual option clicked');
                if (methodManual) {
                    methodManual.checked = true;
                    methodManual.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        
        if (document.getElementById('automatic-option')) {
            document.getElementById('automatic-option').addEventListener('click', function(e) {
                console.log('🔘 Automatic option clicked');
                if (methodAutomatic) {
                    methodAutomatic.checked = true;
                    methodAutomatic.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        
        // Direct change listeners on radios
        if (methodManual) {
            methodManual.addEventListener('change', function() {
                console.log('✅ Manual radio changed - checked:', this.checked);
                if (this.checked) {
                    document.getElementById('submit-text').textContent = 'Confirm Order - Manual Payment';
                }
            });
        }
        
        if (methodAutomatic) {
            methodAutomatic.addEventListener('change', function() {
                console.log('✅ Automatic radio changed - checked:', this.checked);
                if (this.checked) {
                    const submitText = document.getElementById('submit-text');
                    const submitBtn = document.getElementById('submit-btn');
                    if (submitText && !submitBtn.disabled) {
                        submitText.textContent = 'Proceed to Card Payment →';
                    }
                }
            });
        }
        
        // Listen for auth state changes and enable/disable submit button
        window.addEventListener('checkout-auth-ready', function(e) {
            const submitBtn = document.getElementById('submit-btn');
            const submitText = document.getElementById('submit-text');
            const authMsg = document.getElementById('auth-required-msg');
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'manual';
            
            if (e.detail && e.detail.step === 'authenticated') {
                // User is authenticated - enable the button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled:bg-gray-500');
                }
                if (submitText) {
                    submitText.textContent = paymentMethod === 'automatic' ? 'Proceed to Card Payment →' : 'Confirm Order - Manual Payment';
                }
                if (authMsg) {
                    authMsg.style.display = 'none';
                }
                console.log('✅ User authenticated - submit button enabled');
            } else {
                // User not authenticated yet - button disabled but with friendly text
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (submitText) {
                    submitText.textContent = 'Enter OTP to Continue';
                }
                if (authMsg) {
                    authMsg.style.display = 'none'; // Hide auth message - OTP step shows enough info
                }
                console.log('⏳ Waiting for OTP verification');
            }
        });
        
        // AJAX Checkout Form Handler
        function handleCheckoutSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Get email from hidden field - if OTP verified, this is populated
            const customerEmail = form.querySelector('input[name="customer_email"]')?.value;
            
            // Only require that email exists (OTP verification already validated it)
            if (!customerEmail || !customerEmail.includes('@')) {
                // Just return - don't show error, button stays disabled until OTP verified
                return false;
            }
            
            const paymentMethod = form.querySelector('input[name="payment_method"]:checked')?.value || 'manual';
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Processing Order...';
            
            const formData = new FormData(form);
            // ENSURE payment_method is definitely in FormData
            formData.set('payment_method', paymentMethod);
            
            fetch(form.action || '/cart-checkout.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
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
                        // Automatic payment: Show overlay THEN open Paystack
                        const overlay = document.getElementById('payment-processing-overlay');
                        const msg = document.getElementById('payment-processing-message');
                        if (msg) msg.textContent = 'Opening payment form...';
                        if (overlay) overlay.classList.add('show');
                        
                        const paymentData = result.data;
                        console.log('💳 Opening Paystack payment for Order #' + paymentData.order_id);
                        
                        setTimeout(async () => {
                            // Generate UNIQUE reference for each attempt (prevents duplicate transaction errors)
                            const uniqueRef = 'ORDER-' + paymentData.order_id + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                            
                            // CRITICAL: Create payment record BEFORE opening Paystack popup
                            // This allows webhook to find the payment when it arrives
                            try {
                                if (msg) msg.textContent = 'Preparing payment...';
                                const createPaymentResponse = await fetch('/api/create-payment-record.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        order_id: paymentData.order_id,
                                        reference: uniqueRef,
                                        amount: paymentData.amount,
                                        email: paymentData.customer_email
                                    })
                                });
                                const paymentRecordResult = await createPaymentResponse.json();
                                console.log('📝 Payment record created:', paymentRecordResult);
                                
                                if (paymentRecordResult.already_paid) {
                                    window.location.href = '/user/order-detail.php?id=' + paymentData.order_id;
                                    return;
                                }
                            } catch (err) {
                                console.error('Failed to create payment record:', err);
                                // Continue anyway - webhook handling will still work through order lookup
                            }
                            
                            if (msg) msg.textContent = 'Opening payment form...';
                            
                            const handler = PaystackPop.setup({
                                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                                email: paymentData.customer_email,
                                amount: paymentData.amount,
                                currency: 'NGN',
                                ref: uniqueRef,
                                onClose: function() {
                                    console.log('Payment canceled');
                                    // Mark payment as failed and refresh
                                    fetch('/api/payment-failed.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({
                                            order_id: paymentData.order_id,
                                            reason: 'Payment cancelled by customer'
                                        })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        window.location.href = '/user/order-detail.php?id=' + paymentData.order_id;
                                    })
                                    .catch(err => {
                                        console.error('Error marking payment as failed:', err);
                                        if (overlay) overlay.classList.remove('show');
                                        submitBtn.disabled = false;
                                        submitBtn.innerHTML = originalText;
                                    });
                                },
                                callback: function(response) {
                                    console.log('💳 Payment submitted. Verifying... Reference:', response);
                                    const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
                                    
                                    if (msg) msg.textContent = 'Verifying payment...';
                                    
                                    fetch('/api/paystack-verify.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({
                                            reference: response.reference,
                                            order_id: paymentData.order_id,
                                            csrf_token: csrfToken
                                        })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.success) {
                                            console.log('✅ Payment verified!');
                                            // Show success animation
                                            const overlay = document.getElementById('payment-processing-overlay');
                                            const modalContent = overlay.querySelector('.payment-modal');
                                            
                                            // Replace spinner with success checkmark
                                            const spinnerContainer = modalContent.querySelector('.spinner-container');
                                            spinnerContainer.innerHTML = `
                                                <div class="success-checkmark">
                                                    <div class="success-circle">
                                                        <div class="checkmark">✓</div>
                                                    </div>
                                                </div>
                                            `;
                                            
                                            // Update text
                                            const title = modalContent.querySelector('.payment-status-title');
                                            title.textContent = 'Payment Successful!';
                                            
                                            if (msg) msg.innerHTML = '<span class="success-message">Order approved • Redirecting to your order...</span>';
                                            
                                            setTimeout(() => {
                                                window.location.href = '/user/order-detail.php?id=' + data.order_id;
                                            }, 1200);
                                        } else {
                                            // Verification failed - redirect to order details
                                            // User can see order status and retry from there
                                            console.log('⚠️ Verification failed, redirecting to order details');
                                            window.location.href = '/user/order-detail.php?id=' + paymentData.order_id;
                                        }
                                    })
                                    .catch(err => {
                                        // On error, redirect to order details so user can see their order
                                        console.error('Verification error:', err);
                                        window.location.href = '/user/order-detail.php?id=' + paymentData.order_id;
                                    });
                                }
                            });
                            handler.openIframe();
                        }, 100);
                    } else if (result.data.payment_method === 'manual') {
                        // Manual payment: Direct redirect, no overlay
                        console.log('Manual payment selected - redirecting to order...');
                        window.location.href = result.data.redirect;
                    }
                } else {
                    console.error('❌ Order error:', result.text || result.data?.message || 'Unknown error');
                    alert('An error occurred: ' + (result.data?.message || 'Please try again.'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                console.error('❌ Form error:', error);
                alert('Error: ' + error.message);
            });
        }
        
        // Paystack Payment Handler (for confirmation page - manual payment page retry)
        document.getElementById('paystack-payment-btn')?.addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ Processing...';
            
            // Show loading overlay
            const overlay = document.getElementById('payment-processing-overlay');
            const msg = document.getElementById('payment-processing-message');
            if (overlay) overlay.classList.add('show');
            
            // Generate UNIQUE reference for each retry attempt (prevents duplicate transaction errors)
            const uniqueRef = 'ORDER-<?php echo $confirmationData['order']['id'] ?? 0; ?>-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const orderId = <?php echo $confirmationData['order']['id'] ?? 0; ?>;
            const amount = <?php echo (int)(($confirmationData['order']['final_amount'] ?? 0) * 100); ?>;
            const email = '<?php echo htmlspecialchars($confirmationData['order']['customer_email'] ?? ''); ?>';
            
            // CRITICAL: Create payment record BEFORE opening Paystack popup
            try {
                if (msg) msg.textContent = 'Preparing payment...';
                const createPaymentResponse = await fetch('/api/create-payment-record.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_id: orderId,
                        reference: uniqueRef,
                        amount: amount,
                        email: email
                    })
                });
                const paymentRecordResult = await createPaymentResponse.json();
                console.log('📝 Payment record created for retry:', paymentRecordResult);
                
                if (paymentRecordResult.already_paid) {
                    window.location.href = '/user/order-detail.php?id=' + orderId;
                    return;
                }
            } catch (err) {
                console.error('Failed to create payment record:', err);
            }
            
            if (msg) msg.textContent = 'Opening payment form...';
            
            const handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                email: email,
                amount: amount,
                currency: 'NGN',
                ref: uniqueRef,
                onClose: function() {
                    console.log('Payment cancelled from retry button');
                    fetch('/api/payment-failed.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            order_id: orderId,
                            reason: 'Payment cancelled by customer on retry'
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        window.location.href = '/user/order-detail.php?id=' + orderId;
                    })
                    .catch(err => {
                        // On error, still redirect to order details
                        console.error('Error marking payment failed:', err);
                        window.location.href = '/user/order-detail.php?id=' + orderId;
                    });
                },
                callback: function(response) {
                    fetch('/api/paystack-verify.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            reference: response.reference,
                            order_id: orderId,
                            customer_email: email
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            if (msg) msg.textContent = 'Payment confirmed! Redirecting to your order...';
                            setTimeout(() => {
                                window.location.href = '/user/order-detail.php?id=' + data.order_id;
                            }, 1000);
                        } else {
                            // Verification failed - redirect to order details
                            console.log('Verification failed, redirecting to order details');
                            window.location.href = '/user/order-detail.php?id=' + orderId;
                        }
                    }).catch(err => {
                        // On error, redirect to order details
                        console.error('Verification error:', err);
                        window.location.href = '/user/order-detail.php?id=' + orderId;
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
            
            // 2. AUTO-APPLY AFFILIATE CODE FROM URL PARAMETER (only if no discount already applied)
            const urlParams = new URLSearchParams(window.location.search);
            const affiliateCodeFromUrl = urlParams.get('aff');
            const discountAlreadyApplied = <?php echo ($totals['has_discount'] ? 'true' : 'false'); ?>;
            const autoApplyDone = sessionStorage.getItem('affiliate_auto_applied_' + (affiliateCodeFromUrl || ''));
            
            if (affiliateCodeFromUrl && !discountAlreadyApplied && !autoApplyDone) {
                const affiliateInput = document.getElementById('affiliate_code');
                if (affiliateInput && !affiliateInput.value) {
                    affiliateInput.value = affiliateCodeFromUrl.toUpperCase();
                    // Mark as applied to prevent refresh loop
                    sessionStorage.setItem('affiliate_auto_applied_' + affiliateCodeFromUrl, 'true');
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
            
            // 5. POPULATE REMOVE DISCOUNT FORM WITH CUSTOMER DATA & SHOW DISCOUNT INPUT WHEN REMOVED
            const removeDiscountForm = document.getElementById('removeDiscountForm');
            if (removeDiscountForm) {
                removeDiscountForm.addEventListener('submit', function(e) {
                    const customerName = document.getElementById('customer_name');
                    const customerEmail = document.getElementById('customer_email');
                    const customerPhone = document.getElementById('customer_phone');
                    
                    if (customerName) {
                        document.getElementById('remove_customer_name').value = customerName.value;
                    }
                    if (customerEmail) {
                        document.getElementById('remove_customer_email').value = customerEmail.value;
                    }
                    if (customerPhone) {
                        document.getElementById('remove_customer_phone').value = customerPhone.value;
                    }
                    
                    // Show the discount code input after form submission
                    setTimeout(() => {
                        const discountSection = document.getElementById('discountCodeSection');
                        if (discountSection) {
                            discountSection.classList.remove('hidden');
                        }
                    }, 100);
                });
            }
            
            // 5B. AUTO-INSERT BONUS CODE ON INPUT FOCUS (ENHANCED UX)
            <?php if (!empty($activeBonusCode)): ?>
            const affiliateInput = document.getElementById('affiliate_code');
            // REMOVED: Auto-fill bonus code on input focus was intrusive and annoying to users
            // Users should deliberately type or click the bonus code banner to apply discounts
            <?php endif; ?>
            
            // 6. FLOATING BONUS OFFER BANNER - SHOW ON CHECKOUT FORM IF BETTER DISCOUNT AVAILABLE
            console.log('✅ Cart Recovery Features Initialized');
            
            <?php 
            // Show bonus code banner if:
            // 1. There's an active bonus code AND
            // 2. User does NOT have affiliate code AND does NOT have referral code AND
            // 3. Either no discount is applied yet OR the bonus code offers a better discount than current affiliate
            $hasAffiliateCode = !empty($affiliateCode);
            $hasReferralCode = !empty($userReferralCode);
            $showBonusBanner = !empty($activeBonusCode) && !$hasAffiliateCode && !$hasReferralCode && (
                !$totals['has_discount'] || 
                ($totals['discount_type'] === 'bonus_code' && $activeBonusCode['discount_percent'] > $totals['discount_percent'])
            );
            
            // Debug info
            if (!empty($activeBonusCode)) {
                error_log('DEBUG: Active Bonus Code Found - ' . $activeBonusCode['code'] . ' (' . $activeBonusCode['discount_percent'] . '%)');
            } else {
                error_log('DEBUG: No Active Bonus Code found - check if bonus_codes table has active codes');
            }
            ?>
            <?php if ($showBonusBanner): ?>
            if (!isConfirmationPage) {
                const floatingBanner = document.createElement('div');
                const bonusCodeToApply = '<?php echo htmlspecialchars($activeBonusCode['code']); ?>';
                floatingBanner.innerHTML = `
                    <div style="position: fixed; top: 100px; right: 20px; z-index: 40; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 12px 16px; border-radius: 8px; max-width: 260px; box-shadow: 0 8px 24px rgba(249, 115, 22, 0.3); font-family: Arial, sans-serif; pointer-events: auto; cursor: pointer; transition: all 0.3s ease; user-select: none;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 32px rgba(249, 115, 22, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 24px rgba(249, 115, 22, 0.3)'">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 22px;">🎁</span>
                            <div>
                                <p style="margin: 0 0 4px 0; font-weight: bold; font-size: 14px;">Special Offer!</p>
                                <p style="margin: 0; font-size: 12px; opacity: 0.95;">Click to apply: <span style="background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 4px; font-weight: bold;"><?php echo htmlspecialchars($activeBonusCode['code']); ?></span></p>
                                <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: bold;"><?php echo number_format($activeBonusCode['discount_percent'], 0); ?>% OFF!</p>
                                <?php if ($totals['has_discount'] && $totals['discount_type'] === 'affiliate'): ?>
                                <p style="margin: 4px 0 0 0; font-size: 11px; opacity: 0.8;">Better than your current <?php echo $totals['discount_percent']; ?>%!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(floatingBanner);
                
                // Make banner clickable to apply bonus code (instant with no validation)
                floatingBanner.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Animate banner away
                    floatingBanner.style.opacity = '0';
                    floatingBanner.style.transform = 'translateX(400px)';
                    floatingBanner.style.transition = 'all 0.3s ease';
                    
                    // Get CSRF token
                    const csrfInput = document.querySelector('input[name="csrf_token"]');
                    const csrfToken = csrfInput ? csrfInput.value : '';
                    
                    // Get optional customer data (only if filled by user)
                    const customerName = document.getElementById('customer_name')?.value || '';
                    const customerEmail = document.getElementById('customer_email')?.value || '';
                    const customerPhone = document.getElementById('customer_phone')?.value || '';
                    
                    // Build form data - ONLY send filled fields
                    const formData = new FormData();
                    formData.append('apply_affiliate', '1');
                    formData.append('affiliate_code', bonusCodeToApply);
                    formData.append('csrf_token', csrfToken);
                    
                    // Only add customer data if user filled it
                    if (customerName.trim()) formData.append('customer_name', customerName);
                    if (customerEmail.trim()) formData.append('customer_email', customerEmail);
                    if (customerPhone.trim()) formData.append('customer_phone', customerPhone);
                    
                    // Send AJAX request (no form validation!)
                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData
                    }).then(() => {
                        // Reload page to show applied discount
                        setTimeout(() => {
                            window.location.reload();
                        }, 200);
                    }).catch(err => {
                        console.error('Error applying bonus code:', err);
                        window.location.reload();
                    });
                });
            }
            <?php endif; ?>
        });
    </script>
    
    <!-- Customer Auth Module -->
    <script src="/assets/js/customer-auth.js"></script>
    
    <!-- Checkout Auth Alpine Component -->
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('checkoutAuth', () => ({
            // State
            step: 'email', // 'email', 'password', 'otp', 'authenticated'
            email: '',
            password: '',
            phone: '',
            otpCode: '',
            loading: false,
            error: '',
            
            // Customer data (populated on auth)
            customerId: null,
            customerUsername: '', // Use username instead of full_name
            customerPhone: '',
            accountComplete: false,
            
            // OTP resend timer
            resendTimer: 60,
            canResend: false,
            resendInterval: null,
            
            // Initialize - check if already logged in
            async init() {
                try {
                    const customer = await CustomerAuth.checkSession();
                    if (customer) {
                        this.setAuthenticatedState(customer);
                    } else {
                        // Dispatch initial state
                        window.dispatchEvent(new CustomEvent('checkout-auth-ready', { detail: { step: 'email' } }));
                    }
                } catch (e) {
                    console.error('Init error:', e);
                    window.dispatchEvent(new CustomEvent('checkout-auth-ready', { detail: { step: 'email' } }));
                }
            },
            
            // Go back to email step (CSP-safe helper)
            changeEmail() {
                this.step = 'email';
                this.error = '';
            },
            
            // Set authenticated state with customer data
            setAuthenticatedState(customer) {
                this.customerId = customer.id;
                this.email = customer.email;
                this.customerUsername = customer.username || '';
                // Use whatsapp_number from API (fallback to phone for compatibility)
                this.customerPhone = customer.whatsapp_number || customer.phone || '';
                this.accountComplete = customer.account_complete || false;
                this.step = 'authenticated';
                this.error = '';
                
                // CRITICAL: Populate hidden form fields directly
                const emailField = document.getElementById('customer_email');
                const nameField = document.getElementById('customer_name');
                const phoneField = document.getElementById('customer_phone_hidden');
                if (emailField) emailField.value = this.email;
                if (nameField) nameField.value = this.customerUsername;
                if (phoneField) phoneField.value = this.customerPhone;
                
                // Dispatch event to notify submit button
                window.dispatchEvent(new CustomEvent('checkout-auth-ready', { detail: { step: 'authenticated', email: this.email } }));
            },
            
            // Check email - determine if new or existing user
            async checkEmail() {
                if (!this.email || !this.email.includes('@')) {
                    this.error = 'Please enter a valid email address';
                    return;
                }
                
                this.loading = true;
                this.error = '';
                
                try {
                    const result = await CustomerAuth.checkEmail(this.email);
                    
                    if (result.success) {
                        if (result.exists && result.has_password) {
                            // Existing user with password - show login
                            this.customerUsername = result.username || '';
                            this.step = 'password';
                        } else {
                            // New user or user without password - send OTP
                            await this.requestOTP();
                        }
                    } else {
                        this.error = result.error || 'Failed to check email';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                    console.error('checkEmail error:', e);
                } finally {
                    this.loading = false;
                }
            },
            
            // Request OTP for email verification
            async requestOTP() {
                this.loading = true;
                this.error = '';
                
                try {
                    const result = await CustomerAuth.requestOTP(this.email, this.phone || null);
                    
                    if (result.success) {
                        this.step = 'otp';
                        this.otpCode = '';
                        this.startResendTimer();
                        // Focus OTP input
                        this.$nextTick(() => {
                            document.getElementById('otp-input')?.focus();
                        });
                    } else {
                        this.error = result.error || 'Failed to send verification code';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                    console.error('requestOTP error:', e);
                } finally {
                    this.loading = false;
                }
            },
            
            // Resend OTP
            async resendOTP() {
                if (!this.canResend) return;
                await this.requestOTP();
            },
            
            // Start resend timer
            startResendTimer() {
                this.resendTimer = 60;
                this.canResend = false;
                
                if (this.resendInterval) clearInterval(this.resendInterval);
                
                this.resendInterval = setInterval(() => {
                    this.resendTimer--;
                    if (this.resendTimer <= 0) {
                        this.canResend = true;
                        clearInterval(this.resendInterval);
                    }
                }, 1000);
            },
            
            // Handle OTP input - enforce numeric only
            handleOTPInput(event) {
                // Force numeric only and max 6 digits
                const value = event.target.value.replace(/\D/g, '').slice(0, 6);
                this.otpCode = value;
                event.target.value = value;
                
                // Auto-verify when all 6 digits entered
                if (value.length === 6) {
                    this.$nextTick(() => this.verifyOTP());
                }
            },
            
            // Handle OTP paste
            handleOTPPaste(event) {
                setTimeout(() => {
                    const value = event.target.value.replace(/\D/g, '').slice(0, 6);
                    this.otpCode = value;
                    event.target.value = value;
                    
                    // Auto-verify if 6 digits pasted
                    if (value.length === 6) {
                        this.$nextTick(() => this.verifyOTP());
                    }
                }, 10);
            },
            
            // Verify OTP
            async verifyOTP() {
                const code = this.otpCode.replace(/\D/g, '');
                if (code.length !== 6) {
                    this.error = 'Please enter all 6 digits';
                    return;
                }
                
                this.loading = true;
                this.error = '';
                
                try {
                    const result = await CustomerAuth.verifyOTP(this.email, code);
                    
                    if (result.success) {
                        // Keep OTP code visible after verification
                        this.setAuthenticatedState(result.customer);
                        if (this.resendInterval) clearInterval(this.resendInterval);
                    } else {
                        // Clear OTP only on error
                        this.error = result.message || result.error || 'Invalid verification code';
                        this.otpCode = '';
                        document.getElementById('otp-input')?.focus();
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                    console.error('verifyOTP error:', e);
                } finally {
                    this.loading = false;
                }
            },
            
            // Login with password
            async login() {
                if (!this.password) {
                    this.error = 'Please enter your password';
                    return;
                }
                
                this.loading = true;
                this.error = '';
                
                try {
                    const result = await CustomerAuth.login(this.email, this.password);
                    
                    if (result.success) {
                        this.setAuthenticatedState(result.customer);
                    } else {
                        this.error = result.error || 'Invalid password';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                    console.error('login error:', e);
                } finally {
                    this.loading = false;
                }
            },
            
            // Logout / switch account
            async logout() {
                try {
                    await CustomerAuth.logout();
                } catch (e) {
                    console.error('logout error:', e);
                }
                
                // Reset state
                this.step = 'email';
                this.email = '';
                this.password = '';
                this.phone = '';
                this.otpCode = '';
                this.customerId = null;
                this.customerUsername = '';
                this.customerPhone = '';
                this.accountComplete = false;
                this.error = '';
                
                if (this.resendInterval) clearInterval(this.resendInterval);
                
                // Dispatch event to notify submit button
                window.dispatchEvent(new CustomEvent('checkout-auth-ready', { detail: { step: 'email' } }));
            }
        }));
    });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupCartDrawer();
            updateCartBadge();
            setInterval(updateCartBadge, 5000);
        });
    </script>
</body>
</html>
