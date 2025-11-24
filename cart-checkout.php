<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/delivery.php';

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

$errors = [];
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['select_method'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    }
    
    if (empty($errors)) {
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        
        if (empty($customerName)) $errors[] = 'Please enter your full name';
        if (empty($customerPhone)) $errors[] = 'Please enter your WhatsApp number';
        if (empty($paymentMethod)) $errors[] = 'Please select a payment method';
        
        // Revalidate cart
        $validation = validateCart();
        if (!$validation['valid']) {
            $errors[] = 'Some items in your cart are no longer available.';
        }
    }
    
    if (empty($errors)) {
        // Re-fetch fresh data
        $cartItems = getCart();
        $totals = getCartTotal(null, $affiliateCode);
        
        if (empty($cartItems)) {
            header('Location: /?' . ($affiliateCode ? 'aff=' . urlencode($affiliateCode) : '') . '#products');
            exit;
        }
        
        // Build order items
        $orderItems = [];
        foreach ($cartItems as $item) {
            $productType = $item['product_type'] ?? 'tool';
            $itemSubtotal = $item['price_at_add'] * $item['quantity'];
            
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
        
        // Determine order type
        $hasTemplates = false;
        $hasTools = false;
        foreach ($cartItems as $item) {
            $productType = $item['product_type'] ?? 'tool';
            if ($productType === 'template') $hasTemplates = true;
            if ($productType === 'tool') $hasTools = true;
        }
        
        $orderType = 'template';
        if ($hasTemplates && $hasTools) $orderType = 'mixed';
        elseif (!$hasTemplates && $hasTools) $orderType = 'tool';
        
        // Build message for WhatsApp (manual payment only)
        $messageForAdmin = "🛒 *NEW ORDER* (#" . time() . ")\n";
        $messageForAdmin .= "━━━━━━━━━━━━━━━━━━━\n";
        $messageForAdmin .= "📋 Customer: " . $customerName . "\n";
        $messageForAdmin .= "📱 WhatsApp: " . $customerPhone . "\n";
        if ($customerEmail) $messageForAdmin .= "📧 Email: " . $customerEmail . "\n";
        $messageForAdmin .= "💳 Amount: " . formatCurrency($totals['total']) . "\n";
        $messageForAdmin .= "📦 Items: " . count($cartItems) . "\n";
        $messageForAdmin .= "💳 Payment: " . ucfirst($paymentMethod) . "\n";
        
        // Create order
        $orderData = [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'affiliate_code' => $totals['affiliate_code'],
            'session_id' => session_id(),
            'message_text' => $messageForAdmin,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'original_price' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'final_amount' => $totals['total'],
            'payment_method' => $paymentMethod,
            'cart_snapshot' => json_encode(['items' => $cartItems, 'totals' => $totals])
        ];
        
        $orderId = createOrderWithItems($orderData, $orderItems);
        
        if (!$orderId) {
            $errors[] = 'Failed to create order. Please try again or contact support.';
        } else {
            // Clear cart
            clearCart();
            
            // Redirect to confirmation
            header('Location: /cart-checkout.php?confirmed=' . $orderId . '&method=' . urlencode($paymentMethod) . ($affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''));
            exit;
        }
    }
}

// CONFIRMATION PAGE
$confirmationData = null;
$paymentMethodUsed = isset($_GET['method']) ? $_GET['method'] : null;

if ($confirmedOrderId) {
    $order = getOrderById($confirmedOrderId);
    
    if ($order && $order['session_id'] === session_id()) {
        $orderItems = getOrderItems($confirmedOrderId);
        
        // Determine order type
        $hasTemplates = false;
        $hasTools = false;
        foreach ($orderItems as $item) {
            if ($item['product_type'] === 'template') $hasTemplates = true;
            if ($item['product_type'] === 'tool') $hasTools = true;
        }
        
        // Get bank details
        $bankAccountNumber = getSetting('site_account_number', '');
        $bankName = getSetting('site_bank_name', '');
        $bankNumber = getSetting('site_bank_number', '');
        
        // Generate WhatsApp messages for MANUAL payment
        if ($paymentMethodUsed === 'manual') {
            $messagePaymentProof = "🛒 *ORDER #" . $order['id'] . "*\n";
            $messagePaymentProof .= "━━━━━━━━━━━━━━━━━━━\n";
            $messagePaymentProof .= "💳 Total: " . formatCurrency($order['final_amount']) . "\n";
            if (!empty($order['affiliate_code'])) {
                $messagePaymentProof .= "🎁 Discount: 20% OFF\n";
            }
            $messagePaymentProof .= "\n🏦 *TRANSFER TO:*\n";
            if ($bankName) $messagePaymentProof .= "Bank: " . $bankName . "\n";
            if ($bankAccountNumber) $messagePaymentProof .= "Account: " . $bankAccountNumber . "\n";
            if ($bankNumber) $messagePaymentProof .= "Code: " . $bankNumber . "\n";
            $messagePaymentProof .= "\n📸 *I have sent the money* (with screenshot)";
            
            $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER));
            $whatsappUrlPaymentProof = "https://wa.me/" . $whatsappNumber . "?text=" . rawurlencode($messagePaymentProof);
        }
        
        $confirmationData = [
            'order' => $order,
            'orderItems' => $orderItems,
            'hasTemplates' => $hasTemplates,
            'hasTools' => $hasTools,
            'bankAccountNumber' => $bankAccountNumber,
            'bankName' => $bankName,
            'bankNumber' => $bankNumber,
            'whatsappUrlPaymentProof' => $whatsappUrlPaymentProof ?? null
        ];
    } else {
        if ($confirmedOrderId) {
            header('Location: /?view=tools#products');
            exit;
        }
    }
}

$pageTitle = $confirmedOrderId && $confirmationData ? 'Order Confirmed' : 'Checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="/assets/js/paystack-payment.js" defer></script>
    <style>
        body { overflow-x: hidden; max-width: 100vw; }
        input, button { transition: all 0.2s ease; }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <!-- Navigation -->
    <nav class="bg-gray-800 shadow sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/images/webdaddy-logo.png" alt="Logo" class="h-14" onerror="this.style.display='none'">
                <span class="font-bold text-lg"><?php echo SITE_NAME; ?></span>
            </a>
            <a href="/?view=tools#products" class="text-sm text-gray-300 hover:text-white">← Back</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <?php if ($confirmedOrderId && $confirmationData): ?>
            <!-- ✅ ORDER CONFIRMATION PAGE -->
            <div class="text-center mb-8">
                <div class="inline-flex justify-center w-20 h-20 bg-green-600 rounded-full mb-4">
                    <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold mb-2">Order Confirmed!</h1>
                <p class="text-gray-300">Order #<?php echo $confirmationData['order']['id']; ?></p>
            </div>

            <!-- Order Summary -->
            <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
                <h2 class="text-xl font-bold mb-4">Order Summary</h2>
                <div class="space-y-3 mb-4">
                    <?php foreach ($confirmationData['orderItems'] as $item): ?>
                    <div class="flex justify-between pb-2 border-b border-gray-700">
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($item['template_name'] ?? $item['tool_name'] ?? 'Product'); ?></p>
                            <p class="text-sm text-gray-400">×<?php echo $item['quantity']; ?> @ <?php echo formatCurrency($item['unit_price']); ?></p>
                        </div>
                        <p class="font-bold"><?php echo formatCurrency($item['final_amount']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-700 pt-4 space-y-2">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($confirmationData['order']['original_price']); ?></span>
                    </div>
                    <?php if ($confirmationData['order']['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-green-400">
                        <span>Discount (20%):</span>
                        <span>-<?php echo formatCurrency($confirmationData['order']['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold pt-4 border-t border-gray-700">
                        <span>TOTAL:</span>
                        <span><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Instructions for MANUAL Payment -->
            <?php if ($paymentMethodUsed === 'manual'): ?>
            <div class="bg-green-900/30 border border-green-500/50 rounded-lg p-6 mb-6">
                <h3 class="font-bold text-lg mb-4">💳 Bank Transfer Details</h3>
                <div class="space-y-2 mb-6 text-lg">
                    <?php if ($confirmationData['bankName']): ?>
                    <p><span class="font-semibold">Bank:</span> <?php echo htmlspecialchars($confirmationData['bankName']); ?></p>
                    <?php endif; ?>
                    <?php if ($confirmationData['bankAccountNumber']): ?>
                    <p><span class="font-semibold">Account:</span> <?php echo htmlspecialchars($confirmationData['bankAccountNumber']); ?></p>
                    <?php endif; ?>
                    <?php if ($confirmationData['bankNumber']): ?>
                    <p><span class="font-semibold">Code:</span> <?php echo htmlspecialchars($confirmationData['bankNumber']); ?></p>
                    <?php endif; ?>
                    <p class="text-xl font-bold text-green-400 mt-4">Amount: <?php echo formatCurrency($confirmationData['order']['final_amount']); ?></p>
                </div>
                
                <p class="mb-4 text-sm">After making the transfer, click the button below to confirm:</p>
                <a href="<?php echo htmlspecialchars($confirmationData['whatsappUrlPaymentProof']); ?>" 
                   class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                    ✅ I've Sent the Money - Confirm via WhatsApp
                </a>
            </div>
            <?php elseif ($paymentMethodUsed === 'paystack'): ?>
            <div class="bg-blue-900/30 border border-blue-500/50 rounded-lg p-6">
                <h3 class="font-bold text-lg mb-2">💳 Paystack Payment</h3>
                <p class="mb-4">Your payment is being processed. You'll receive your order details via email shortly.</p>
                <p class="text-sm text-gray-300">Amount charged: <span class="font-bold"><?php echo formatCurrency($confirmationData['order']['final_amount']); ?></span></p>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- 🔄 CHECKOUT FORM -->
            
            <?php if (!empty($errors)): ?>
            <div class="bg-red-900/50 border border-red-500 rounded-lg p-4 mb-6">
                <p class="font-bold text-red-300">Errors:</p>
                <ul class="mt-2 space-y-1">
                    <?php foreach ($errors as $err): ?>
                    <li class="text-sm">• <?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Step 1: Select Payment Method (only if not selected yet) -->
            <?php if (!$paymentMethod): ?>
            <div class="text-center mb-12">
                <h1 class="text-3xl font-bold mb-4">Choose Payment Method</h1>
                <p class="text-gray-300 mb-8">Select how you'd like to pay</p>
                
                <form method="POST" class="space-y-4">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="select_method" value="1">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Manual Payment Option -->
                        <button type="submit" name="payment_method" value="manual" 
                                class="p-8 bg-gray-800 hover:bg-primary-600 border-2 border-gray-700 hover:border-primary-600 rounded-lg transition group">
                            <div class="text-4xl mb-3">🏦</div>
                            <h3 class="font-bold text-lg mb-2">Bank Transfer</h3>
                            <p class="text-sm text-gray-300 group-hover:text-white">Transfer to our bank account & confirm via WhatsApp</p>
                        </button>
                        
                        <!-- Paystack Option -->
                        <button type="submit" name="payment_method" value="paystack" 
                                class="p-8 bg-gray-800 hover:bg-green-600 border-2 border-gray-700 hover:border-green-600 rounded-lg transition group">
                            <div class="text-4xl mb-3">💳</div>
                            <h3 class="font-bold text-lg mb-2">Card Payment</h3>
                            <p class="text-sm text-gray-300 group-hover:text-white">Pay instantly with Visa, Mastercard, Verve</p>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Step 2: Checkout Form (after payment method selected) -->
            <?php if ($paymentMethod): ?>
            <div class="mb-8">
                <form method="POST" action="" id="checkoutForm">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($paymentMethod); ?>">
                    
                    <!-- Customer Information -->
                    <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
                        <h2 class="text-xl font-bold mb-6">Your Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <input type="text" name="customer_name" placeholder="Full Name" required
                                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                   class="px-4 py-3 bg-gray-700 border border-gray-600 rounded text-white focus:border-primary-500 focus:outline-none">
                            <input type="tel" name="customer_phone" placeholder="WhatsApp Number" required
                                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                                   class="px-4 py-3 bg-gray-700 border border-gray-600 rounded text-white focus:border-primary-500 focus:outline-none">
                        </div>
                        <input type="email" name="customer_email" placeholder="Email (Optional)"
                               value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>"
                               class="w-full mt-4 px-4 py-3 bg-gray-700 border border-gray-600 rounded text-white focus:border-primary-500 focus:outline-none">
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
                        <h2 class="text-xl font-bold mb-4">Order Summary</h2>
                        <div class="space-y-3 mb-4 max-h-48 overflow-y-auto">
                            <?php foreach ($cartItems as $item): 
                                $subtotal = $item['price_at_add'] * $item['quantity'];
                            ?>
                            <div class="flex justify-between pb-2 border-b border-gray-700 text-sm">
                                <span><?php echo htmlspecialchars($item['name']); ?> ×<?php echo $item['quantity']; ?></span>
                                <span><?php echo formatCurrency($subtotal); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-700 pt-4 space-y-2">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span><?php echo formatCurrency($totals['subtotal']); ?></span>
                            </div>
                            <?php if ($totals['has_discount']): ?>
                            <div class="flex justify-between text-green-400">
                                <span>Discount (20%):</span>
                                <span>-<?php echo formatCurrency($totals['discount']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-2xl font-bold pt-2 border-t border-gray-700">
                                <span>TOTAL:</span>
                                <span class="text-green-400"><?php echo formatCurrency($totals['total']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Display -->
                    <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
                        <p class="text-gray-300">Payment Method: <span class="font-bold">
                            <?php echo $paymentMethod === 'manual' ? '🏦 Bank Transfer' : '💳 Card (Paystack)'; ?>
                        </span></p>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-4">
                        <form method="POST" class="flex-1">
                            <?php echo csrfTokenField(); ?>
                            <button type="submit" name="select_method" value="1" 
                                    class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
                                ← Change Payment Method
                            </button>
                        </form>
                        
                        <?php if ($paymentMethod === 'manual'): ?>
                        <button type="submit" class="flex-1 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition">
                            Proceed to Payment
                        </button>
                        <?php else: ?>
                        <button type="button" id="pay-with-paystack" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition">
                            Pay <?php echo formatCurrency($totals['total']); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        // Paystack payment handler
        document.getElementById('pay-with-paystack')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = document.getElementById('checkoutForm');
            const name = form.customer_name.value;
            const email = form.customer_email.value;
            const phone = form.customer_phone.value;
            const amount = <?php echo json_encode($totals['total'] * 100); ?>;
            
            if (!name || !phone) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Initialize Paystack payment
            const handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                email: email || 'customer@webdaddy.empire',
                amount: amount,
                currency: 'NGN',
                ref: '<?php echo 'ORDER-' . time(); ?>',
                onClose: function() {
                    alert('Payment window closed.');
                },
                onSuccess: function(response) {
                    // Verify payment on server
                    fetch('/api/paystack-verify.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            reference: response.reference,
                            customer_name: name,
                            customer_email: email,
                            customer_phone: phone,
                            csrf_token: document.querySelector('meta[name="csrf-token"]').content
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            alert('Payment successful!');
                            window.location.href = data.redirect_url;
                        } else {
                            alert('Payment verification failed: ' + (data.message || 'Unknown error'));
                        }
                    });
                }
            });
            handler.openIframe();
        });
    </script>
</body>
</html>
