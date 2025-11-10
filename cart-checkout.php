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

// Get cart items
$cartItems = getCart();
$totals = getCartTotal(null, $affiliateCode);

// If cart is empty, redirect to homepage
if (empty($cartItems)) {
    header('Location: /?' . ($affiliateCode ? 'aff=' . urlencode($affiliateCode) : '') . '#products');
    exit;
}

// Validate cart
$validation = validateCart();
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Generate WhatsApp message
        $message = "Hello! I would like to order the following tools:\n\n";
        
        $itemNumber = 1;
        foreach ($cartItems as $item) {
            $itemTotal = $item['price_at_add'] * $item['quantity'];
            $message .= "{$itemNumber}. {$item['name']}\n";
            $message .= "   Price: " . formatCurrency($item['price_at_add']) . " × {$item['quantity']} = " . formatCurrency($itemTotal) . "\n\n";
            $itemNumber++;
        }
        
        $message .= "-------------------\n";
        $message .= "Subtotal: " . formatCurrency($totals['subtotal']) . "\n";
        
        if ($totals['has_discount']) {
            $message .= "Affiliate Discount (20%): -" . formatCurrency($totals['discount']) . "\n";
            $message .= "Affiliate Code: " . $totals['affiliate_code'] . "\n";
        }
        
        $message .= "Total: " . formatCurrency($totals['total']) . "\n\n";
        
        $message .= "Customer Details:\n";
        $message .= "Name: " . $customerName . "\n";
        $message .= "WhatsApp: " . $customerPhone . "\n";
        if ($customerEmail) {
            $message .= "Email: " . $customerEmail . "\n";
        }
        
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
        
        // Create pending order (for admin tracking)
        $db = getDb();
        $stmt = $db->prepare("INSERT INTO pending_orders (
            template_id, order_type, cart_snapshot, customer_name, customer_email, 
            customer_phone, affiliate_code, session_id, message_text, ip_address,
            original_price, discount_amount, final_amount, created_at
        ) VALUES (NULL, 'tools', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $cartSnapshot,
            $customerName,
            $customerEmail,
            $customerPhone,
            $totals['affiliate_code'],
            session_id(),
            $message,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $totals['subtotal'],
            $totals['discount'],
            $totals['total']
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Log activity
        logActivity('cart_checkout', 'Cart order #' . $orderId . ' initiated with ' . count($cartItems) . ' items');
        
        // Generate WhatsApp link
        $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER));
        $encodedMessage = rawurlencode($message);
        $whatsappUrl = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessage;
        
        // Clear cart
        clearCart();
        
        // Redirect to WhatsApp
        header('Location: ' . $whatsappUrl);
        exit;
    }
}

$pageTitle = 'Checkout - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
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
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <a href="/" class="inline-block mb-4">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?php echo SITE_NAME; ?>" class="h-16 mx-auto" onerror="this.style.display='none'">
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
                <p class="text-gray-600 mt-2">Review your order and complete your purchase</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                        <p class="text-red-800"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$validation['valid']): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-yellow-900 mb-2">Cart Issues:</h3>
                <?php foreach ($validation['issues'] as $issue): ?>
                <p class="text-yellow-800">• <?php echo htmlspecialchars($issue['tool_name'] . ': ' . $issue['issue']); ?></p>
                <?php endforeach; ?>
                <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                   class="text-primary-600 hover:text-primary-700 font-medium mt-2 inline-block">← Return to shopping</a>
            </div>
            <?php endif; ?>
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Order Summary -->
                <div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
                        
                        <div class="space-y-4 mb-6">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="flex items-start gap-3 pb-4 border-b border-gray-200">
                                <img src="<?php echo htmlspecialchars($item['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-16 h-16 object-cover rounded"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo formatCurrency($item['price_at_add']); ?> × <?php echo $item['quantity']; ?></p>
                                    <p class="text-sm font-semibold text-primary-600"><?php echo formatCurrency($item['price_at_add'] * $item['quantity']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4 space-y-2">
                            <div class="flex justify-between text-gray-700">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($totals['subtotal']); ?></span>
                            </div>
                            
                            <?php if ($totals['has_discount']): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Affiliate Discount (20%)</span>
                                <span>-<?php echo formatCurrency($totals['discount']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t border-gray-200">
                                <span>Total</span>
                                <span><?php echo formatCurrency($totals['total']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information Form -->
                <div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Your Information</h2>
                        
                        <form method="POST" action="">
                            <?php echo getCsrfTokenInput(); ?>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="customer_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                        Full Name <span class="text-red-600">*</span>
                                    </label>
                                    <input type="text" 
                                           id="customer_name" 
                                           name="customer_name" 
                                           required
                                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="John Doe">
                                </div>
                                
                                <div>
                                    <label for="customer_phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                        WhatsApp Number <span class="text-red-600">*</span>
                                    </label>
                                    <input type="tel" 
                                           id="customer_phone" 
                                           name="customer_phone" 
                                           required
                                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="+234 800 000 0000">
                                </div>
                                
                                <div>
                                    <label for="customer_email" class="block text-sm font-semibold text-gray-700 mb-2">
                                        Email Address (Optional)
                                    </label>
                                    <input type="email" 
                                           id="customer_email" 
                                           name="customer_email"
                                           value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                           placeholder="john@example.com">
                                </div>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                        </svg>
                                        <p class="text-sm text-blue-800">
                                            You'll be redirected to WhatsApp to complete your order. Our team will confirm your purchase and provide payment details.
                                        </p>
                                    </div>
                                </div>
                                
                                <button type="submit" 
                                        <?php echo !$validation['valid'] ? 'disabled' : ''; ?>
                                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 rounded-lg transition-colors <?php echo !$validation['valid'] ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    Proceed to WhatsApp
                                </button>
                                
                                <a href="/?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                                   class="block text-center text-primary-600 hover:text-primary-700 font-medium py-2">
                                    ← Continue Shopping
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
