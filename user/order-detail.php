<?php
/**
 * User Order Detail Page - Enhanced UX
 * Track orders, make payments, retry failed payments
 * Allows incomplete accounts to view orders (modal prompts setup)
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer(true);

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: /user/orders.php');
    exit;
}

$order = getOrderForCustomer($orderId, $customer['id']);

if (!$order) {
    header('Location: /user/orders.php');
    exit;
}

$orderItems = getOrderItemsWithDelivery($orderId);

$db = getDb();
$bankSettings = [
    'account_number' => $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_account_number'")->fetchColumn() ?: '7043609930',
    'bank_name' => $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_bank_name'")->fetchColumn() ?: 'OPay (OPay Digital Services)',
    'account_name' => $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_bank_number'")->fetchColumn() ?: 'WebDaddy Empire'
];
$whatsappNumber = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_number'")->fetchColumn() ?: '+2349132672126';
$whatsappNumberClean = preg_replace('/[^0-9]/', '', $whatsappNumber);

$paymentStmt = $db->prepare("SELECT * FROM payments WHERE pending_order_id = ? ORDER BY created_at DESC LIMIT 1");
$paymentStmt->execute([$orderId]);
$lastPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
$isFailedPayment = $lastPayment && $lastPayment['status'] === 'failed';

$page = 'orders';
$pageTitle = 'Order #' . $orderId;

$statusColor = match($order['status']) {
    'paid' => 'bg-blue-100 text-blue-700 border-blue-200',
    'completed' => 'bg-green-100 text-green-700 border-green-200',
    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'failed' => 'bg-red-100 text-red-700 border-red-200',
    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200'
};

$templateItems = array_filter($orderItems, fn($i) => $i['product_type'] === 'template');
$toolItems = array_filter($orderItems, fn($i) => $i['product_type'] === 'tool');
$itemCount = count($orderItems);

$itemNames = [];
foreach ($orderItems as $item) {
    $itemNames[] = $item['product_name'] ?? ($item['product_type'] === 'template' ? 'Template' : 'Digital Product');
}
$itemsDescription = implode(', ', array_slice($itemNames, 0, 3));
if (count($itemNames) > 3) {
    $itemsDescription .= ' + ' . (count($itemNames) - 3) . ' more';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header with Context -->
    <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl border border-amber-200 p-4 sm:p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <a href="/user/orders.php" class="text-amber-600 hover:text-amber-700 inline-flex items-center text-sm font-medium mb-2">
                    <i class="bi-arrow-left mr-2"></i>Back to Orders
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Order #<?= $orderId ?></h1>
                <p class="text-sm text-gray-600 mt-1"><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span class="px-4 py-1.5 text-sm font-semibold rounded-full border <?= $statusColor ?>">
                    <?= $order['status'] === 'failed' ? 'Payment Failed' : ucfirst($order['status']) ?>
                </span>
                <span class="text-sm text-gray-500"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </div>

    <?php if ($order['status'] === 'pending' || $order['status'] === 'failed'): ?>
    <!-- Payment Section - Prominent for pending/failed orders -->
    <div class="bg-white rounded-2xl shadow-lg border-2 border-amber-400 overflow-hidden" x-data="orderPayment()">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-4">
            <h2 class="text-white font-bold text-lg flex items-center gap-2">
                <i class="bi-credit-card-2-front"></i>
                <?php if ($order['status'] === 'failed'): ?>
                Payment Failed - Retry Now
                <?php else: ?>
                Complete Your Payment
                <?php endif; ?>
            </h2>
            <p class="text-amber-100 text-sm mt-1">
                <?php if ($order['status'] === 'failed'): ?>
                Your previous payment attempt didn't go through. You can try again below.
                <?php else: ?>
                Choose how you'd like to pay for your order.
                <?php endif; ?>
            </p>
        </div>
        
        <div class="p-4 sm:p-6 space-y-6">
            <!-- Amount Due -->
            <div class="text-center py-4 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500 uppercase tracking-wide">Amount Due</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">₦<?= number_format($order['final_amount'], 2) ?></p>
            </div>
            
            <!-- Payment Options Tabs -->
            <div class="flex border-b border-gray-200">
                <button @click="paymentTab = 'card'" 
                        :class="paymentTab === 'card' ? 'border-amber-500 text-amber-600 bg-amber-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-3 px-4 text-sm font-medium border-b-2 transition-all flex items-center justify-center gap-2">
                    <i class="bi-credit-card"></i>
                    Pay with Card
                </button>
                <button @click="paymentTab = 'bank'" 
                        :class="paymentTab === 'bank' ? 'border-amber-500 text-amber-600 bg-amber-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-3 px-4 text-sm font-medium border-b-2 transition-all flex items-center justify-center gap-2">
                    <i class="bi-bank"></i>
                    Bank Transfer
                </button>
            </div>
            
            <!-- Card Payment Option -->
            <div x-show="paymentTab === 'card'" x-transition class="space-y-4">
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="bi-shield-check text-green-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-green-800">Instant & Secure</h4>
                            <p class="text-sm text-green-700 mt-1">
                                Pay instantly with your debit/credit card via Paystack. 
                                Your payment is secured and your order will be processed immediately.
                            </p>
                        </div>
                    </div>
                </div>
                
                <button @click="payWithCard()" :disabled="loading"
                        class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-4 rounded-xl font-bold text-lg hover:from-green-700 hover:to-green-800 transition disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg">
                    <span x-show="!loading">
                        <i class="bi-credit-card mr-2"></i>
                        <?= $order['status'] === 'failed' ? 'Retry Payment with Card' : 'Pay ₦' . number_format($order['final_amount'], 2) . ' Now' ?>
                    </span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                
                <p class="text-center text-xs text-gray-500">
                    <i class="bi-lock-fill mr-1"></i>
                    Secured by Paystack. We never store your card details.
                </p>
            </div>
            
            <!-- Bank Transfer Option -->
            <div x-show="paymentTab === 'bank'" x-transition class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="bi-bank text-blue-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-blue-800">Manual Bank Transfer</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                Transfer the exact amount to our account below. 
                                After transfer, notify us and we'll verify within 1-24 hours.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Bank Details Card -->
                <div class="bg-white border-2 border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">Transfer to this Account</p>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500">Bank Name</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($bankSettings['bank_name']) ?></p>
                            </div>
                            <button @click="copyToClipboard('<?= htmlspecialchars($bankSettings['bank_name']) ?>', 'bank')" 
                                    class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition">
                                <i x-show="copied !== 'bank'" class="bi-clipboard"></i>
                                <i x-show="copied === 'bank'" class="bi-check text-green-600"></i>
                            </button>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500">Account Number</p>
                                <p class="font-bold text-2xl text-gray-900 font-mono tracking-wide"><?= htmlspecialchars($bankSettings['account_number']) ?></p>
                            </div>
                            <button @click="copyToClipboard('<?= htmlspecialchars($bankSettings['account_number']) ?>', 'account')" 
                                    class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition">
                                <i x-show="copied !== 'account'" class="bi-clipboard"></i>
                                <i x-show="copied === 'account'" class="bi-check text-green-600"></i>
                            </button>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500">Account Name</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($bankSettings['account_name']) ?></p>
                            </div>
                            <button @click="copyToClipboard('<?= htmlspecialchars($bankSettings['account_name']) ?>', 'name')" 
                                    class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition">
                                <i x-show="copied !== 'name'" class="bi-clipboard"></i>
                                <i x-show="copied === 'name'" class="bi-check text-green-600"></i>
                            </button>
                        </div>
                        
                        <hr class="my-2">
                        
                        <div class="flex items-center justify-between bg-amber-50 -mx-4 -mb-4 p-4 border-t border-amber-100">
                            <div>
                                <p class="text-xs text-amber-600 font-semibold uppercase">Amount to Transfer</p>
                                <p class="font-bold text-2xl text-amber-700">₦<?= number_format($order['final_amount'], 2) ?></p>
                            </div>
                            <button @click="copyToClipboard('<?= $order['final_amount'] ?>', 'amount')" 
                                    class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition text-sm font-medium flex items-center gap-2">
                                <i x-show="copied !== 'amount'" class="bi-clipboard"></i>
                                <i x-show="copied === 'amount'" class="bi-check"></i>
                                Copy Amount
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Reference Note -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p class="text-sm text-amber-800 flex items-start gap-2">
                        <i class="bi-exclamation-triangle flex-shrink-0 mt-0.5"></i>
                        <span>
                            <strong>Important:</strong> Include <span class="font-mono font-bold bg-amber-100 px-2 py-0.5 rounded">Order #<?= $orderId ?></span> 
                            in your transfer narration/remark so we can identify your payment quickly.
                        </span>
                    </p>
                </div>
                
                <!-- After Transfer Actions -->
                <div class="space-y-3 pt-2">
                    <p class="text-sm font-medium text-gray-700 text-center">After you've made the transfer:</p>
                    
                    <?php if (empty($order['payment_notified'])): ?>
                    <!-- Two Action Buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- I Have Sent the Funds Button -->
                        <button @click="confirmPayment()" :disabled="notifyLoading"
                                class="bg-green-600 text-white py-3 px-4 rounded-xl font-semibold hover:bg-green-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
                            <span x-show="!notifyLoading">
                                <i class="bi-check-circle mr-1"></i>
                                I Have Sent the Funds
                            </span>
                            <span x-show="notifyLoading" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Sending...
                            </span>
                        </button>
                        
                        <!-- Process via WhatsApp Button -->
                        <a href="https://wa.me/<?= $whatsappNumberClean ?>?text=<?= urlencode("Hello WebDaddy Empire!\n\nI just made a payment for my order and would like to confirm.\n\n📋 ORDER DETAILS:\n• Order ID: #$orderId\n• Amount Paid: ₦" . number_format($order['final_amount'], 2) . "\n• Items: $itemsDescription\n• Email: " . $order['customer_email'] . "\n\nPlease verify and process my order.\n\nThank you!") ?>" 
                           target="_blank"
                           class="bg-green-500 text-white py-3 px-4 rounded-xl font-semibold hover:bg-green-600 transition flex items-center justify-center gap-2">
                            <i class="bi-whatsapp text-lg"></i>
                            Process via WhatsApp
                        </a>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 space-y-2">
                        <p class="flex items-start gap-2">
                            <i class="bi-check-circle text-green-600 flex-shrink-0 mt-0.5"></i>
                            <span><strong>"I Have Sent the Funds":</strong> Click this to notify our team that you've made the transfer. We'll verify and process your order within 1-24 hours.</span>
                        </p>
                        <p class="flex items-start gap-2">
                            <i class="bi-whatsapp text-green-600 flex-shrink-0 mt-0.5"></i>
                            <span><strong>"Process via WhatsApp":</strong> Opens WhatsApp with a pre-filled message. Use this for faster verification or if you have questions about your payment.</span>
                        </p>
                    </div>
                    <?php else: ?>
                    <!-- Already Notified -->
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <i class="bi-clock-history text-blue-600 text-xl flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold">Payment Notification Sent!</p>
                                <p class="text-sm mt-1">We received your payment notification and are verifying it now. This usually takes 1-24 hours.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- WhatsApp Follow-up -->
                    <a href="https://wa.me/<?= $whatsappNumberClean ?>?text=<?= urlencode("Hello WebDaddy Empire!\n\nI sent a payment notification for Order #$orderId earlier and would like to follow up on the verification status.\n\n📋 ORDER DETAILS:\n• Order ID: #$orderId\n• Amount: ₦" . number_format($order['final_amount'], 2) . "\n• Email: " . $order['customer_email'] . "\n\nPlease let me know the status.\n\nThank you!") ?>" 
                       target="_blank"
                       class="w-full bg-green-500 text-white py-3 px-4 rounded-xl font-semibold hover:bg-green-600 transition flex items-center justify-center gap-2">
                        <i class="bi-whatsapp text-lg"></i>
                        Follow Up via WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Error/Success Messages -->
            <div x-show="error" x-transition class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
                <div class="flex items-start gap-2">
                    <i class="bi-exclamation-circle flex-shrink-0 mt-0.5"></i>
                    <span x-text="error"></span>
                </div>
            </div>
            <div x-show="success" x-transition class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4">
                <div class="flex items-start gap-2">
                    <i class="bi-check-circle flex-shrink-0 mt-0.5"></i>
                    <span x-text="success"></span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function orderPayment() {
        return {
            paymentTab: '<?= $order['status'] === 'failed' ? 'card' : 'card' ?>',
            loading: false,
            notifyLoading: false,
            error: '',
            success: '',
            copied: '',
            
            copyToClipboard(text, field) {
                navigator.clipboard.writeText(text);
                this.copied = field;
                setTimeout(() => this.copied = '', 2000);
            },
            
            async payWithCard() {
                this.loading = true;
                this.error = '';
                this.success = '';
                
                try {
                    const response = await fetch('/api/customer/order-pay.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_id: <?= $orderId ?>,
                            action: '<?= $order['status'] === 'failed' ? 'retry_payment' : 'pay_with_paystack' ?>'
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success && data.authorization_url) {
                        window.location.href = data.authorization_url;
                    } else {
                        this.error = data.error || 'Failed to initialize payment. Please try again.';
                        this.loading = false;
                    }
                } catch (e) {
                    this.error = 'Connection error. Please check your internet and try again.';
                    this.loading = false;
                }
            },
            
            async confirmPayment() {
                if (!confirm('Please confirm that you have transferred ₦<?= number_format($order['final_amount'], 2) ?> to our bank account.\n\nWe will verify and process your order within 1-24 hours.')) {
                    return;
                }
                
                this.notifyLoading = true;
                this.error = '';
                this.success = '';
                
                try {
                    const response = await fetch('/api/customer/order-detail.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'payment_notification',
                            order_id: <?= $orderId ?>
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        this.success = 'Thank you! We will verify your payment and process your order shortly.';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        this.error = data.error || 'Failed to send notification. Please try again.';
                    }
                } catch (e) {
                    this.error = 'Connection error. Please try again.';
                }
                
                this.notifyLoading = false;
            }
        };
    }
    </script>
    <?php endif; ?>

    <!-- Order Items -->
    <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
            <h2 class="text-lg font-bold text-gray-900">Order Items</h2>
        </div>

        <?php if (empty($orderItems)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="bi-box-seam text-4xl mb-3 block"></i>
            <p>No items found for this order.</p>
        </div>
        <?php else: ?>
        
        <?php if (!empty($templateItems)): ?>
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-4">
                <i class="bi-layout-wtf text-amber-600"></i>
                <h3 class="font-bold text-gray-900">Website Templates (<?= count($templateItems) ?>)</h3>
            </div>
            <div class="grid gap-4">
                <?php foreach ($templateItems as $item): ?>
                <?php
                    $deliveryStatus = $item['delivery_status'] ?? 'pending';
                    $deliveryColor = match($deliveryStatus) {
                        'delivered' => 'bg-green-100 text-green-700 border-green-200',
                        'sent' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'ready' => 'bg-purple-100 text-purple-700 border-purple-200',
                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                        'failed' => 'bg-red-100 text-red-700 border-red-200',
                        default => 'bg-gray-100 text-gray-700 border-gray-200'
                    };
                ?>
                <div class="border rounded-xl p-4 hover:shadow-md transition">
                    <div class="flex gap-4">
                        <div class="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                            <?php if (!empty($item['product_thumbnail'])): ?>
                            <img src="<?= htmlspecialchars($item['product_thumbnail']) ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="bi-layout-wtf text-gray-400 text-2xl"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-lg">
                                        <?= htmlspecialchars($item['product_name'] ?? 'Template') ?>
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full border <?= $deliveryColor ?>">
                                            <i class="bi-<?= $deliveryStatus === 'delivered' ? 'check-circle-fill' : ($deliveryStatus === 'pending' ? 'clock' : 'truck') ?> mr-1"></i>
                                            <?= ucfirst($deliveryStatus) ?>
                                        </span>
                                        <?php if ($deliveryStatus === 'delivered' && !empty($item['delivered_at'])): ?>
                                        <span class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($item['delivered_at'])) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="font-bold text-gray-900 text-lg">₦<?= number_format($item['price'], 2) ?></p>
                            </div>
                            
                            <?php if ($deliveryStatus === 'delivered'): ?>
                            <?php 
                                $adminUrl = $item['login_url'] ?? '';
                                $displayLoginUrl = $adminUrl;
                                if (!empty($adminUrl) && strlen($adminUrl) > 50) {
                                    $displayLoginUrl = 'Login to Admin';
                                }
                            ?>
                            <div class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4" x-data="{ showPassword: false, copied: '' }">
                                <div class="flex items-center justify-between gap-2 mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="bi-check-circle-fill text-green-600"></i>
                                        <span class="font-semibold text-green-800">Delivery Complete</span>
                                    </div>
                                    <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">Website</span>
                                </div>
                                
                                <?php 
                                    $siteUrl = $item['domain_login_url'] ?? '';
                                    $hostingType = $item['hosting_provider'] ?? '';
                                    $hostingLabel = match($hostingType) {
                                        'wordpress' => 'WordPress',
                                        'cpanel' => 'cPanel',
                                        'static' => 'Static Site',
                                        'custom' => 'Custom Admin',
                                        default => ''
                                    };
                                ?>
                                <div class="bg-white rounded-lg border divide-y">
                                    <?php if (!empty($item['hosted_domain'])): ?>
                                    <div class="p-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Your Website</p>
                                        <div class="flex items-start gap-1">
                                            <a href="<?= !empty($siteUrl) ? htmlspecialchars($siteUrl) : 'https://' . htmlspecialchars($item['hosted_domain']) ?>" target="_blank" 
                                               class="text-amber-600 hover:text-amber-700 font-medium block flex-1 break-all">
                                                <?= htmlspecialchars($item['hosted_domain']) ?>
                                            </a>
                                            <i class="bi-box-arrow-up-right text-xs flex-shrink-0 text-amber-600 mt-0.5"></i>
                                        </div>
                                        <?php if (!empty($hostingLabel)): ?>
                                        <span class="inline-block mt-1 text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded"><?= $hostingLabel ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($adminUrl)): ?>
                                    <div class="p-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Admin Panel</p>
                                        <div class="flex items-start gap-1">
                                            <a href="<?= htmlspecialchars($adminUrl) ?>" target="_blank" 
                                               class="text-amber-600 hover:text-amber-700 font-medium block flex-1 break-words overflow-wrap-break-word">
                                                <?= htmlspecialchars($displayLoginUrl) ?>
                                            </a>
                                            <i class="bi-box-arrow-up-right text-xs flex-shrink-0 text-amber-600 mt-0.5"></i>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="p-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">Login Credentials</p>
                                        <div class="space-y-2">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                                    <i class="bi-person text-gray-400 flex-shrink-0 mt-0.5"></i>
                                                    <span class="text-gray-600 text-sm flex-shrink-0">Username:</span>
                                                    <span class="font-medium text-gray-900 break-all"><?= htmlspecialchars($item['admin_username'] ?? 'Not set') ?></span>
                                                </div>
                                                <?php if (!empty($item['admin_username'])): ?>
                                                <button @click="navigator.clipboard.writeText('<?= htmlspecialchars($item['admin_username']) ?>'); copied = 'user'; setTimeout(() => copied = '', 2000)"
                                                        class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition flex-shrink-0" title="Copy username">
                                                    <i x-show="copied !== 'user'" class="bi-clipboard"></i>
                                                    <i x-show="copied === 'user'" class="bi-check text-green-600"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                                    <i class="bi-key text-gray-400 flex-shrink-0 mt-0.5"></i>
                                                    <span class="text-gray-600 text-sm flex-shrink-0">Password:</span>
                                                    <?php if (!empty($item['admin_password'])): ?>
                                                    <span x-show="showPassword" class="font-medium text-gray-900 break-all"><?= htmlspecialchars($item['admin_password']) ?></span>
                                                    <span x-show="!showPassword" class="text-gray-400">••••••••</span>
                                                    <?php else: ?>
                                                    <span class="text-gray-400">Not set</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($item['admin_password'])): ?>
                                                <div class="flex items-center gap-1 flex-shrink-0">
                                                    <button @click="showPassword = !showPassword" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition" title="Show/Hide password">
                                                        <i x-show="!showPassword" class="bi-eye"></i>
                                                        <i x-show="showPassword" class="bi-eye-slash"></i>
                                                    </button>
                                                    <button @click="navigator.clipboard.writeText('<?= htmlspecialchars($item['admin_password']) ?>'); copied = 'pass'; setTimeout(() => copied = '', 2000)"
                                                            class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition" title="Copy password">
                                                        <i x-show="copied !== 'pass'" class="bi-clipboard"></i>
                                                        <i x-show="copied === 'pass'" class="bi-check text-green-600"></i>
                                                    </button>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($item['admin_notes'])): ?>
                                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <i class="bi-info-circle text-blue-600 mt-0.5"></i>
                                        <div>
                                            <p class="font-medium text-blue-800 text-sm">Important Notes</p>
                                            <p class="text-sm text-gray-700 mt-1"><?= nl2br(htmlspecialchars($item['admin_notes'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php elseif ($deliveryStatus === 'pending' && $order['status'] === 'paid'): ?>
                            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
                                <div class="flex items-center gap-2">
                                    <div class="animate-spin w-4 h-4 border-2 border-amber-600 border-t-transparent rounded-full"></div>
                                    <span class="font-medium text-amber-800">Setting up your website...</span>
                                </div>
                                <p class="text-sm text-amber-600 mt-2">We're configuring your template. This usually takes 24-48 hours.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($toolItems)): ?>
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="bi-tools text-amber-600"></i>
                <h3 class="font-bold text-gray-900">Digital Products (<?= count($toolItems) ?>)</h3>
            </div>
            <div class="grid gap-4">
                <?php foreach ($toolItems as $item): ?>
                <?php
                    $deliveryStatus = $item['delivery_status'] ?? 'pending';
                    $deliveryColor = match($deliveryStatus) {
                        'delivered' => 'bg-green-100 text-green-700 border-green-200',
                        'sent' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'ready' => 'bg-purple-100 text-purple-700 border-purple-200',
                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                        'failed' => 'bg-red-100 text-red-700 border-red-200',
                        default => 'bg-gray-100 text-gray-700 border-gray-200'
                    };
                    $downloadFiles = [];
                    if (!empty($item['download_link'])) {
                        $downloadFiles = json_decode($item['download_link'], true) ?: [];
                    }
                ?>
                <div class="border rounded-xl p-4 hover:shadow-md transition">
                    <div class="flex gap-4">
                        <div class="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                            <?php if (!empty($item['product_thumbnail'])): ?>
                            <img src="<?= htmlspecialchars($item['product_thumbnail']) ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="bi-tools text-gray-400 text-2xl"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-lg">
                                        <?= htmlspecialchars($item['product_name'] ?? 'Digital Product') ?>
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full border <?= $deliveryColor ?>">
                                            <i class="bi-<?= $deliveryStatus === 'delivered' ? 'check-circle-fill' : ($deliveryStatus === 'pending' ? 'clock' : 'truck') ?> mr-1"></i>
                                            <?= ucfirst($deliveryStatus) ?>
                                        </span>
                                        <?php if ($deliveryStatus === 'delivered' && !empty($item['delivered_at'])): ?>
                                        <span class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($item['delivered_at'])) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="font-bold text-gray-900 text-lg">₦<?= number_format($item['price'], 2) ?></p>
                            </div>
                            
                            <?php if ($deliveryStatus === 'delivered' && !empty($downloadFiles)): ?>
                            <div class="mt-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="bi-download text-blue-600"></i>
                                    <span class="font-semibold text-blue-800">Download Files (<?= count($downloadFiles) ?>)</span>
                                </div>
                                
                                <div class="space-y-3">
                                    <?php foreach ($downloadFiles as $file): ?>
                                    <div class="bg-white rounded-lg p-4 border hover:border-blue-300 transition">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="bi-file-earmark-arrow-down text-blue-600"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-gray-900 break-words" title="<?= htmlspecialchars($file['name'] ?? 'File') ?>">
                                                    <?= htmlspecialchars($file['name'] ?? 'File') ?>
                                                </p>
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 mt-1">
                                                    <?php if (!empty($file['file_size_formatted'])): ?>
                                                    <span><?= htmlspecialchars($file['file_size_formatted']) ?></span>
                                                    <span class="hidden sm:inline">•</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($file['expires_formatted'])): ?>
                                                    <span>Expires: <?= htmlspecialchars($file['expires_formatted']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php 
                                            $downloadUrl = '#';
                                            if (!empty($file['url'])) {
                                                if (preg_match('/token=([a-f0-9]+)/', $file['url'], $m)) {
                                                    $downloadUrl = '/download.php?token=' . $m[1];
                                                } else {
                                                    $downloadUrl = $file['url'];
                                                }
                                            }
                                        ?>
                                        <a href="<?= htmlspecialchars($downloadUrl) ?>" 
                                           class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                                            <i class="bi-download mr-2"></i>
                                            Download
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (!empty($downloadFiles[0]['max_downloads'])): ?>
                                <p class="text-xs text-gray-500 mt-3 flex items-center gap-1">
                                    <i class="bi-info-circle"></i>
                                    Each file can be downloaded up to <?= $downloadFiles[0]['max_downloads'] ?> times
                                </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['delivery_note'])): ?>
                                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="bi-journal-text text-amber-600"></i>
                                        <span class="font-semibold text-amber-800">Instructions & Guide</span>
                                    </div>
                                    <div class="text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($item['delivery_note'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php elseif ($deliveryStatus === 'pending' && $order['status'] === 'paid'): ?>
                            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
                                <div class="flex items-center gap-2">
                                    <div class="animate-spin w-4 h-4 border-2 border-amber-600 border-t-transparent rounded-full"></div>
                                    <span class="font-medium text-amber-800">Preparing your files...</span>
                                </div>
                                <p class="text-sm text-amber-600 mt-2">Download links will appear here once ready.</p>
                            </div>
                            <?php elseif ($deliveryStatus === 'delivered' && empty($downloadFiles)): ?>
                            <div class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-4">
                                <p class="text-sm text-gray-600 flex items-center gap-2">
                                    <i class="bi-envelope"></i>
                                    Download links were sent to your email
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
                <h3 class="font-bold text-gray-900 mb-4">Order Timeline</h3>
                <div class="relative pl-6 border-l-2 border-gray-200 space-y-6">
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">Order Placed</p>
                            <p class="text-sm text-gray-500"><?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($order['status'] !== 'pending' && $order['status'] !== 'cancelled' && $order['status'] !== 'failed'): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">Payment Confirmed</p>
                            <p class="text-sm text-gray-500">
                                <?php if (!empty($order['paid_at'])): ?>
                                <?= date('M j, Y \a\t g:i A', strtotime($order['paid_at'])) ?>
                                <?php elseif (!empty($order['payment_verified_at'])): ?>
                                <?= date('M j, Y \a\t g:i A', strtotime($order['payment_verified_at'])) ?>
                                <?php else: ?>
                                Payment received
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    $deliveredCount = 0;
                    $pendingCount = 0;
                    foreach ($orderItems as $item) {
                        if (($item['delivery_status'] ?? '') === 'delivered') {
                            $deliveredCount++;
                        } else {
                            $pendingCount++;
                        }
                    }
                    ?>
                    
                    <?php if ($deliveredCount > 0 && $pendingCount === 0): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">All Items Delivered</p>
                            <p class="text-sm text-gray-500">Your order is complete!</p>
                        </div>
                    </div>
                    <?php elseif ($deliveredCount > 0 && $pendingCount > 0): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-amber-500 rounded-full border-2 border-white animate-pulse"></div>
                        <div>
                            <p class="font-medium text-gray-900">Partial Delivery</p>
                            <p class="text-sm text-gray-500"><?= $deliveredCount ?> of <?= count($orderItems) ?> items delivered</p>
                        </div>
                    </div>
                    <?php elseif ($order['status'] === 'paid'): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-amber-500 rounded-full border-2 border-white animate-pulse"></div>
                        <div>
                            <p class="font-medium text-gray-900">Processing Delivery</p>
                            <p class="text-sm text-gray-500">We're preparing your items</p>
                        </div>
                    </div>
                    <?php elseif ($order['status'] === 'pending'): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-gray-300 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-400">Awaiting Payment</p>
                            <p class="text-sm text-gray-400">Complete payment to proceed</p>
                        </div>
                    </div>
                    <?php elseif ($order['status'] === 'failed'): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-red-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-red-600">Payment Failed</p>
                            <p class="text-sm text-red-500">Please retry your payment above</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Order Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal (<?= $itemCount ?> items)</span>
                        <span class="text-gray-900">₦<?= number_format($order['original_price'] ?? $order['final_amount'], 2) ?></span>
                    </div>
                    
                    <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Discount</span>
                        <span class="text-green-600">-₦<?= number_format($order['discount_amount'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-2">
                    
                    <div class="flex justify-between font-bold text-lg">
                        <span class="text-gray-900">Total</span>
                        <span class="text-gray-900">₦<?= number_format($order['final_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Payment</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="<?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'text-green-600 font-medium' : ($order['status'] === 'failed' ? 'text-red-600 font-medium' : 'text-yellow-600') ?>">
                            <?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'Paid' : ($order['status'] === 'failed' ? 'Failed' : 'Pending') ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Method</span>
                        <span class="text-gray-900"><?= ucfirst($order['payment_method'] ?? 'Online') ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl border border-amber-200 p-4">
                <h3 class="font-bold text-gray-900 mb-2">Need Help?</h3>
                <p class="text-sm text-gray-600 mb-4">Having issues with this order? We're here to help.</p>
                <a href="/user/new-ticket.php?order_id=<?= $orderId ?>" 
                   class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition text-sm font-medium">
                    <i class="bi-chat-dots mr-2"></i>Create Support Ticket
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
$needsSetup = empty($customer['account_complete']) || empty($customer['password_hash']);
$autoUsername = $customer['username'] ?? '';

$existingWhatsApp = $customer['whatsapp_number'] ?? '';
if (empty($existingWhatsApp) && !empty($order['customer_phone'])) {
    $existingWhatsApp = $order['customer_phone'];
}

$skipWhatsAppStep = !empty($existingWhatsApp) && strlen(preg_replace('/[^0-9]/', '', $existingWhatsApp)) >= 10;
?>

<?php if ($needsSetup): ?>
<!-- Account Completion Modal -->
<div x-data="accountSetupModal()" x-init="showModal = true">
    <div x-show="showModal" x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         @keydown.escape.window="null">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/80" @click.stop></div>
            
            <div class="relative z-50 w-full max-w-md p-6 mx-auto bg-white rounded-2xl shadow-xl">
                <?php if ($skipWhatsAppStep): ?>
                <div class="flex items-center justify-center space-x-3 mb-6">
                    <div :class="step >= 1 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-500'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">1</div>
                    <div class="w-8 h-0.5 bg-gray-200"></div>
                    <div :class="step >= 3 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-500'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">2</div>
                </div>
                <?php else: ?>
                <div class="flex items-center justify-center space-x-3 mb-6">
                    <div :class="step >= 1 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-500'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">1</div>
                    <div class="w-8 h-0.5 bg-gray-200"></div>
                    <div :class="step >= 2 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-500'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">2</div>
                    <div class="w-8 h-0.5 bg-gray-200"></div>
                    <div :class="step >= 3 ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-500'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">3</div>
                </div>
                <?php endif; ?>
                
                <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    <span x-text="error"></span>
                </div>
                
                <div x-show="step === 1">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi-person-check text-3xl text-amber-600"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900">Complete Your Account</h2>
                        <p class="text-gray-600 text-sm mt-1">Set up your login credentials</p>
                    </div>
                    
                    <form @submit.prevent="saveCredentials" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" x-model="username" required minlength="3"
                                   class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            <p class="text-xs text-gray-500 mt-1">Auto-generated. You can edit it.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" x-model="password" required minlength="6"
                                   class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                   placeholder="At least 6 characters">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input type="password" x-model="confirmPassword" required
                                   class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <button type="submit" :disabled="loading"
                                class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            <span x-show="!loading">Continue</span>
                            <span x-show="loading">Saving...</span>
                        </button>
                    </form>
                </div>
                
                <?php if (!$skipWhatsAppStep): ?>
                <div x-show="step === 2">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi-whatsapp text-3xl text-green-600"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900">WhatsApp Number</h2>
                        <p class="text-gray-600 text-sm mt-1">We'll send order updates here</p>
                    </div>
                    
                    <form @submit.prevent="saveWhatsApp" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                            <input type="tel" x-model="whatsappNumber" required
                                   class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                   placeholder="e.g., 08012345678">
                            <p class="text-xs text-gray-500 mt-1">Required for order updates and support</p>
                        </div>
                        <button type="submit" :disabled="loading"
                                class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            <span x-show="!loading">Complete Setup</span>
                            <span x-show="loading">Saving...</span>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div x-show="step === 3">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi-check-lg text-4xl text-green-600"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-2">You're All Set!</h2>
                        <p class="text-gray-600 text-sm mb-6">Your account is now complete. You can log in anytime.</p>
                        <button @click="showModal = false; location.reload()" 
                                class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition">
                            View Order Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function accountSetupModal() {
    return {
        showModal: true,
        step: 1,
        loading: false,
        error: '',
        username: '<?= htmlspecialchars($autoUsername) ?>',
        password: '',
        confirmPassword: '',
        whatsappNumber: '<?= htmlspecialchars($existingWhatsApp) ?>',
        skipWhatsApp: <?= $skipWhatsAppStep ? 'true' : 'false' ?>,
        
        async saveCredentials() {
            if (this.password !== this.confirmPassword) {
                this.error = 'Passwords do not match';
                return;
            }
            if (this.password.length < 6) {
                this.error = 'Password must be at least 6 characters';
                return;
            }
            
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'complete_registration_step1',
                        username: this.username,
                        password: this.password,
                        confirm_password: this.confirmPassword
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    if (this.skipWhatsApp) {
                        this.step = 3;
                    } else {
                        this.step = 2;
                    }
                } else {
                    this.error = data.error || 'Failed to save. Please try again.';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        async saveWhatsApp() {
            if (!this.whatsappNumber || this.whatsappNumber.length < 10) {
                this.error = 'Please enter a valid WhatsApp number';
                return;
            }
            
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'complete_registration_whatsapp',
                        whatsapp_number: this.whatsappNumber
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.step = 3;
                } else {
                    this.error = data.error || 'Failed to save. Please try again.';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        }
    };
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
