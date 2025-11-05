<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

startSecureSession();
handleAffiliateTracking();

$templateId = (int)($_GET['template'] ?? 0);
$template = getTemplateById($templateId);

if (!$template) {
    header('Location: /');
    exit;
}

$availableDomains = getAvailableDomains($templateId);

$customFields = !empty($template['custom_fields']) ? json_decode($template['custom_fields'], true) : [];

$errors = [];
$error = '';
$success = false;

$affiliateDiscountRate = 0.20; // 20% discount
$affiliateDiscountPercent = (int)($affiliateDiscountRate * 100);
$originalPrice = $template['price'];

$affiliateCode = getAffiliateCode();
$affiliateData = null;
$hasAffiliate = false;

if (!empty($affiliateCode)) {
    $affiliateData = getAffiliateByCode($affiliateCode);
    if ($affiliateData) {
        $hasAffiliate = true;
    } else {
        $affiliateCode = null;
    }
}

// Track submitted affiliate code for error display
$submittedAffiliateCode = '';
$affiliateInvalid = false;

$discountedPrice = $originalPrice;
$discountAmount = 0;

if ($hasAffiliate) {
    $discountedPrice = round($originalPrice * (1 - $affiliateDiscountRate), 2);
    $discountAmount = max(0, $originalPrice - $discountedPrice);
}

// Handle affiliate code application (separate from order submission)
$isApplyAffiliate = isset($_POST['apply_affiliate']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isApplyAffiliate) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
        $affiliateInvalid = true;
    } else {
        $submittedAffiliateCode = strtoupper(trim($_POST['affiliate_code'] ?? ''));
        
        if (!empty($submittedAffiliateCode)) {
            $lookupAffiliate = getAffiliateByCode($submittedAffiliateCode);
            if ($lookupAffiliate) {
                $affiliateData = $lookupAffiliate;
                $affiliateCode = $submittedAffiliateCode;
                $hasAffiliate = true;
                $_SESSION['affiliate_code'] = $affiliateCode;
                setcookie(
                    'affiliate_code',
                    $affiliateCode,
                    time() + ((defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400),
                    '/',
                    '',
                    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    true
                );
                
                // Recalculate prices with affiliate discount
                $discountedPrice = round($originalPrice * (1 - $affiliateDiscountRate), 2);
                $discountAmount = max(0, $originalPrice - $discountedPrice);
                
                // Redirect back to apply the discount
                $redirectUrl = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
                $params = ['template' => $templateId];
                
                header('Location: ' . $redirectUrl . '?' . http_build_query($params));
                exit;
            } else {
                $affiliateInvalid = true;
            }
        }
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isApplyAffiliate) {
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
    }
    
    if (empty($errors)) {
        $payableAmount = $discountedPrice;
        $activeAffiliateCode = $hasAffiliate ? $affiliateCode : null;
        
        $message = "Hello! I would like to order:\n\n";
        $message .= "Template: " . $template['name'] . "\n";
        if ($hasAffiliate) {
            $message .= "Original Price: " . formatCurrency($originalPrice) . "\n";
            $message .= "Affiliate Discount ({$affiliateDiscountPercent}%): -" . formatCurrency($discountAmount) . "\n";
            $message .= "Price to Pay: " . formatCurrency($payableAmount) . "\n";
            $message .= "Affiliate Code: " . $activeAffiliateCode . "\n\n";
        } else {
            $message .= "Price: " . formatCurrency($payableAmount) . "\n\n";
        }
        $message .= "Customer Details:\n";
        $message .= "Name: " . $customerName . "\n";
        $message .= "WhatsApp: " . $customerPhone . "\n";
        
        $orderData = [
            'template_id' => $templateId,
            'chosen_domain_id' => null,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'business_name' => '',
            'custom_fields' => null,
            'affiliate_code' => $activeAffiliateCode,
            'session_id' => session_id(),
            'message_text' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $orderId = createPendingOrder($orderData);
        
        if ($orderId && empty($errors)) {
            logActivity('order_initiated', 'Order #' . $orderId . ' for template ' . $template['name']);
            
            // Send order confirmation email to customer (if email provided)
            if (!empty($customerEmail)) {
                sendOrderConfirmationEmail(
                    $orderId,
                    $customerName,
                    $customerEmail,
                    $template['name'],
                    formatCurrency($payableAmount)
                );
            }
            
            // Send new order notification to admin
            sendNewOrderNotificationToAdmin(
                $orderId,
                $customerName,
                $customerPhone,
                $template['name'],
                formatCurrency($payableAmount),
                $activeAffiliateCode
            );
            
            // Build WhatsApp link
            $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126'));
            $encodedMessage = rawurlencode($message);
            $whatsappLink = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessage;
            
            // Set flag for redirect
            $redirectToWhatsApp = true;
        } else if ($orderId) {
            $errors[] = 'Order created but there was an issue. Please contact support.';
        } else {
            global $lastDbError;
            if (isset($lastDbError) && !empty($lastDbError)) {
                error_log('Order creation failed: ' . $lastDbError);
                $errors[] = 'Failed to create order. Please try again or contact support.';
            } else {
                $errors[] = 'Failed to create order. Please try again or contact support.';
            }
        }
    }
}

$pageTitle = 'Order ' . htmlspecialchars($template['name']);
$features = $template['features'] ? explode(',', $template['features']) : [];

// If we need to redirect to WhatsApp, do it before any output
if (isset($redirectToWhatsApp) && $redirectToWhatsApp) {
    // Try header redirect first
    if (!headers_sent()) {
        header('Location: ' . $whatsappLink);
        exit;
    }
    // If headers already sent, use JavaScript fallback
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
    echo '<script>window.location.href = ' . json_encode($whatsappLink) . ';</script>';
    echo '<p>Redirecting to WhatsApp... <a href="' . htmlspecialchars($whatsappLink) . '">Click here if not redirected</a></p>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <!-- Preconnect to external resources for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
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
        /* Prevent horizontal overflow on mobile */
        * {
            box-sizing: border-box;
        }
        
        body {
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        /* Optimize spinner animation */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        /* Better touch targets on mobile */
        @media (max-width: 640px) {
            button, a[role="button"], input[type="button"] {
                min-height: 44px;
                min-width: 44px;
            }
        }
        
        /* Smooth transitions */
        input, button {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-14 mr-3" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-primary-900"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="/" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
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
                <p class="text-xs sm:text-sm lg:text-base text-white/90">One step away from launching your website</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <div class="lg:col-span-2">
                <?php if (!empty($error)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="text-yellow-800"><?php echo htmlspecialchars($error); ?></p>
                            <div class="mt-3 pt-3 border-t border-yellow-200">
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" class="font-semibold text-yellow-700 hover:text-yellow-900" target="_blank">
                                    <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    Contact us on WhatsApp
                                </a> for custom domain options
                            </div>
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
                
                <?php if (empty($error)): ?>
                    <!-- Discount Code Section -->
                    <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-orange-200 rounded-lg p-3 sm:p-4 mb-4 max-w-full overflow-hidden" id="discountSection">
                        <?php if ($hasAffiliate): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center min-w-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-600 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-xs sm:text-sm font-semibold text-green-800 break-words">20% OFF! Code: <?php echo htmlspecialchars($affiliateCode); ?></span>
                            </div>
                            <span class="text-xs sm:text-sm font-bold text-green-700 shrink-0">-<?php echo formatCurrency($discountAmount); ?></span>
                        </div>
                        <?php else: ?>
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-orange-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-xs sm:text-sm font-semibold text-gray-900">Save 20% with code</p>
                            </div>
                            <div class="w-full">
                                <div class="flex gap-2" id="affiliateForm">
                                    <input type="text" 
                                           class="flex-1 min-w-0 px-3 py-2 text-xs sm:text-sm border <?php echo $affiliateInvalid ? 'border-red-500' : 'border-gray-300'; ?> rounded-md focus:ring-2 focus:ring-orange-500 focus:border-transparent uppercase" 
                                           id="affiliate_code" 
                                           value="<?php echo htmlspecialchars($submittedAffiliateCode); ?>" 
                                           placeholder="ENTER AFFILIATE CODE"
                                           maxlength="20"
                                           autocomplete="off">
                                    <button class="px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 active:bg-orange-800 transition-colors shrink-0" 
                                            type="button"
                                            id="applyAffiliateBtn"
                                            onclick="applyAffiliateCode()">
                                        Apply
                                    </button>
                                </div>
                                <?php if ($affiliateInvalid): ?>
                                    <p class="mt-2 text-xs text-red-600 font-medium flex items-center" id="affiliateError">
                                        <svg class="w-4 h-4 mr-1 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        Invalid code. Please check and try again.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="" id="orderForm" data-validate data-loading>
                    <?php echo csrfTokenField(); ?>
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 mb-6 overflow-hidden">
                        <div class="p-6 sm:p-8">
                            <div class="flex items-center mb-6">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-600 text-white font-bold mr-3 shrink-0">1</span>
                                <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900">Your Information</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="customer_name" class="block text-sm font-bold text-gray-700 mb-2">
                                        Full Name <span class="text-red-600">*</span>
                                    </label>
                                    <input type="text" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                           id="customer_name" 
                                           name="customer_name" 
                                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $_GET['name'] ?? ''); ?>" 
                                           required
                                           placeholder="John Doe">
                                </div>
                                
                                <div>
                                    <label for="customer_phone" class="block text-sm font-bold text-gray-700 mb-2">
                                        WhatsApp Number <span class="text-red-600">*</span>
                                    </label>
                                    <input type="tel" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                           id="customer_phone" 
                                           name="customer_phone" 
                                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? $_GET['phone'] ?? ''); ?>" 
                                           required
                                           placeholder="+234...">
                                    <p class="mt-1 text-sm text-gray-500">For order updates and support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button type="submit" class="w-full inline-flex items-center justify-center px-6 py-4 border border-transparent text-base font-bold rounded-lg text-white bg-green-600 hover:bg-green-700 transition-all shadow-lg" id="submitBtn" data-loading-text="Processing...">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            <span class="hidden sm:inline">Continue to WhatsApp</span>
                            <span class="sm:hidden">Order Now</span>
                        </button>
                        <a href="template.php?id=<?php echo $template['id']; ?>" class="w-full inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back to Template Details
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 lg:sticky lg:top-24 overflow-hidden">
                    <div class="p-4 sm:p-6 lg:p-8">
                        <h5 class="font-extrabold text-gray-900 mb-4 text-base sm:text-lg">Order Summary</h5>
                        
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($template['name']); ?>" 
                                 class="w-full rounded-lg mb-3"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <h5 class="font-bold text-gray-900 mb-2 text-sm sm:text-base"><?php echo htmlspecialchars($template['name']); ?></h5>
                            <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?php echo htmlspecialchars($template['category']); ?></span>
                        </div>
                        
                        <?php if (!empty($features)): ?>
                        <div class="mb-4 hidden sm:block">
                            <h6 class="font-bold text-gray-900 mb-3 text-sm">Includes:</h6>
                            <ul class="space-y-2 text-xs sm:text-sm">
                                <?php foreach (array_slice($features, 0, 4) as $feature): ?>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-gray-700"><?php echo htmlspecialchars(trim($feature)); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="border-t border-gray-200 pt-3 mb-3"></div>
                        
                        <div class="flex justify-between mb-2 text-gray-700 text-xs sm:text-sm">
                            <span class="truncate mr-2">Template Price:</span>
                            <strong class="shrink-0"><?php echo formatCurrency($originalPrice); ?></strong>
                        </div>

                        <?php if ($hasAffiliate): ?>
                            <div class="flex justify-between mb-2 text-green-600 text-xs sm:text-sm">
                                <span class="truncate mr-2">Discount (<?php echo $affiliateDiscountPercent; ?>%):</span>
                                <strong class="shrink-0">-<?php echo formatCurrency($discountAmount); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="border-t border-gray-200 pt-3 mb-3"></div>
                        
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <h5 class="font-extrabold text-gray-900 text-sm sm:text-base">You Pay:</h5>
                            <h4 class="text-xl sm:text-2xl font-extrabold text-primary-600"><?php echo formatCurrency($discountedPrice); ?></h4>
                        </div>
                        <?php if ($hasAffiliate): ?>
                            <p class="text-xs sm:text-sm text-green-600 text-right mt-1">Savings applied!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm text-gray-400">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Affiliate code application with AJAX
        function applyAffiliateCode() {
            const affiliateInput = document.getElementById('affiliate_code');
            const applyBtn = document.getElementById('applyAffiliateBtn');
            const code = affiliateInput.value.trim().toUpperCase();
            
            if (!code) {
                showAffiliateError('Please enter a code');
                return;
            }
            
            // Clear previous error
            hideAffiliateError();
            
            // Show loading state
            applyBtn.disabled = true;
            const originalText = applyBtn.textContent;
            applyBtn.innerHTML = '<svg class="animate-spin h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            
            // Get CSRF token from the form
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            // Submit via AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&affiliate_code=${encodeURIComponent(code)}&apply_affiliate=1`
            })
            .then(response => response.text())
            .then(html => {
                // Check if the response contains the success indicator (discount applied)
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const hasDiscount = doc.querySelector('.text-green-600');
                
                if (hasDiscount) {
                    // Success - reload to show the discount
                    window.location.reload();
                } else {
                    // Invalid code
                    showAffiliateError('Invalid code. Please check and try again.');
                    applyBtn.disabled = false;
                    applyBtn.innerHTML = originalText;
                    affiliateInput.classList.add('border-red-500');
                    affiliateInput.focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAffiliateError('Something went wrong. Please try again.');
                applyBtn.disabled = false;
                applyBtn.innerHTML = originalText;
            });
        }
        
        function showAffiliateError(message) {
            const affiliateInput = document.getElementById('affiliate_code');
            affiliateInput.classList.add('border-red-500');
            
            let errorEl = document.getElementById('affiliateError');
            if (!errorEl) {
                errorEl = document.createElement('p');
                errorEl.id = 'affiliateError';
                errorEl.className = 'mt-2 text-xs text-red-600 font-medium flex items-center';
                errorEl.innerHTML = `
                    <svg class="w-4 h-4 mr-1 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span>${message}</span>
                `;
                // Append to the parent container of affiliateForm
                const formContainer = document.getElementById('affiliateForm').parentElement;
                formContainer.appendChild(errorEl);
            } else {
                // Update existing error
                const errorText = errorEl.querySelector('span');
                if (errorText) {
                    errorText.textContent = message;
                }
            }
        }
        
        function hideAffiliateError() {
            const affiliateInput = document.getElementById('affiliate_code');
            affiliateInput.classList.remove('border-red-500');
            
            const errorEl = document.getElementById('affiliateError');
            if (errorEl) {
                errorEl.remove();
            }
        }

        // Order form submission handling
        document.getElementById('orderForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
            }
        });
        
        // Affiliate code input - convert to uppercase and allow Enter key
        const affiliateInput = document.getElementById('affiliate_code');
        if (affiliateInput) {
            affiliateInput.addEventListener('input', function(e) {
                this.value = this.value.toUpperCase();
                // Remove error styling when user starts typing
                if (this.classList.contains('border-red-500')) {
                    hideAffiliateError();
                }
            });
            
            affiliateInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyAffiliateCode();
                }
            });
        }
        
        // Add visual feedback for button taps on mobile
        document.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.opacity = '0.8';
            });
            btn.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
    </script>
    
    <!-- Floating WhatsApp Button with Pulse Animation -->
    <style>
        @keyframes pulse-ring {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }
        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>?text=Hi%2C%20I%20have%20a%20question%20about%20my%20order" 
       target="_blank"
       class="fixed bottom-6 right-6 z-50 group"
       aria-label="Chat on WhatsApp">
        <!-- Pulsing Ring Effect -->
        <span class="absolute inset-0 rounded-full bg-green-500 pulse-ring"></span>
        <span class="absolute inset-0 rounded-full bg-green-500 pulse-ring" style="animation-delay: 1s;"></span>
        
        <!-- Button -->
        <span class="relative flex bg-green-500 hover:bg-green-600 text-white rounded-full p-4 shadow-2xl transition-all duration-300 hover:scale-110">
            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                Need Help? Chat Now
            </span>
        </span>
    </a>
</body>
</html>
