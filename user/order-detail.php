<?php
/**
 * User Order Detail Page
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

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

$page = 'orders';
$pageTitle = 'Order #' . $orderId;

$statusColor = match($order['status']) {
    'paid' => 'bg-blue-100 text-blue-700 border-blue-200',
    'completed' => 'bg-green-100 text-green-700 border-green-200',
    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200'
};

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="/user/orders.php" class="text-amber-600 hover:text-amber-700 inline-flex items-center text-sm font-medium">
            <i class="bi-arrow-left mr-2"></i>Back to Orders
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-4 border-b flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-gray-900">Order #<?= $orderId ?></h2>
                        <p class="text-sm text-gray-500"><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
                    </div>
                    <span class="px-3 py-1 text-sm font-medium rounded-full border <?= $statusColor ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
                
                <div class="divide-y">
                    <?php if (empty($orderItems)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="bi-box-seam text-3xl mb-2"></i>
                        <p>No items found for this order.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($orderItems as $item): ?>
                    <?php
                        $deliveryStatus = $item['delivery_status'] ?? 'pending';
                        $deliveryColor = match($deliveryStatus) {
                            'delivered' => 'bg-green-100 text-green-700',
                            'sent' => 'bg-blue-100 text-blue-700',
                            'ready' => 'bg-purple-100 text-purple-700',
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'failed' => 'bg-red-100 text-red-700',
                            default => 'bg-gray-100 text-gray-700'
                        };
                    ?>
                    <div class="p-4">
                        <div class="flex gap-4">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                <?php if (!empty($item['product_thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($item['product_thumbnail']) ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="bi-<?= $item['product_type'] === 'template' ? 'layout-wtf' : 'tools' ?> text-gray-400 text-xl"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            <?= htmlspecialchars($item['product_name'] ?? ($item['template_name'] ?: $item['tool_name'] ?: 'Product')) ?>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            <?= ucfirst($item['product_type']) ?>
                                            <?php if ($item['quantity'] > 1): ?>
                                            &times; <?= $item['quantity'] ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <p class="font-semibold text-gray-900">₦<?= number_format($item['price'], 2) ?></p>
                                </div>
                                
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $deliveryColor ?>">
                                        <i class="bi-<?= $deliveryStatus === 'delivered' ? 'check-circle-fill' : ($deliveryStatus === 'pending' ? 'clock' : 'truck') ?> mr-1"></i>
                                        <?= ucfirst($deliveryStatus) ?>
                                    </span>
                                    
                                    <?php if ($deliveryStatus === 'delivered' && !empty($item['delivered_at'])): ?>
                                    <span class="text-xs text-gray-500">
                                        Delivered <?= date('M j, Y', strtotime($item['delivered_at'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($deliveryStatus === 'delivered'): ?>
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg space-y-2">
                                    <?php if ($item['product_type'] === 'template' && !empty($item['hosted_domain'])): ?>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Hosted Domain</p>
                                        <a href="https://<?= htmlspecialchars($item['hosted_domain']) ?>" target="_blank" 
                                           class="text-amber-600 hover:text-amber-700 font-medium text-sm inline-flex items-center">
                                            <?= htmlspecialchars($item['hosted_domain']) ?>
                                            <i class="bi-box-arrow-up-right ml-1.5 text-xs"></i>
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($item['domain_login_url'])): ?>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Admin Login</p>
                                        <a href="<?= htmlspecialchars($item['domain_login_url']) ?>" target="_blank" 
                                           class="text-amber-600 hover:text-amber-700 font-medium text-sm inline-flex items-center">
                                            Access Admin Panel
                                            <i class="bi-box-arrow-up-right ml-1.5 text-xs"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="bi-info-circle mr-1"></i>
                                        Login credentials were sent to your email.
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($item['product_type'] === 'tool' && !empty($item['download_link'])): ?>
                                    <div>
                                        <a href="<?= htmlspecialchars($item['download_link']) ?>" 
                                           class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition text-sm font-medium">
                                            <i class="bi-download mr-2"></i>Download Files
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Order Timeline</h3>
                <div class="relative pl-6 border-l-2 border-gray-200 space-y-6">
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">Order Placed</p>
                            <p class="text-sm text-gray-500"><?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($order['status'] !== 'pending' && $order['status'] !== 'cancelled'): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">Payment Confirmed</p>
                            <p class="text-sm text-gray-500">
                                <?php if (!empty($order['payment_verified_at'])): ?>
                                <?= date('M j, Y \a\t g:i A', strtotime($order['payment_verified_at'])) ?>
                                <?php else: ?>
                                Payment received
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    $hasDelivered = false;
                    foreach ($orderItems as $item) {
                        if (($item['delivery_status'] ?? '') === 'delivered') {
                            $hasDelivered = true;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($hasDelivered): ?>
                    <div class="relative">
                        <div class="absolute -left-[25px] w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        <div>
                            <p class="font-medium text-gray-900">Items Delivered</p>
                            <p class="text-sm text-gray-500">Your items have been delivered</p>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Order Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="text-gray-900">₦<?= number_format($order['original_price'] ?? $order['final_amount'], 2) ?></span>
                    </div>
                    
                    <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Discount</span>
                        <span class="text-green-600">-₦<?= number_format($order['discount_amount'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-2">
                    
                    <div class="flex justify-between font-bold">
                        <span class="text-gray-900">Total</span>
                        <span class="text-gray-900">₦<?= number_format($order['final_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Payment Details</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="<?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'text-green-600 font-medium' : 'text-yellow-600' ?>">
                            <?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'Paid' : 'Pending' ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">Method</span>
                        <span class="text-gray-900"><?= ucfirst($order['payment_method'] ?? 'Online') ?></span>
                    </div>
                    
                    <?php if (!empty($order['paystack_payment_id'])): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Reference</span>
                        <span class="text-gray-900 text-xs font-mono"><?= htmlspecialchars(substr($order['paystack_payment_id'], 0, 15)) ?>...</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Need Help?</h3>
                <p class="text-sm text-gray-500 mb-4">Having issues with this order? Our support team is here to help.</p>
                <a href="/user/new-ticket.php?order_id=<?= $orderId ?>" 
                   class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    <i class="bi-chat-dots mr-2"></i>Create Support Ticket
                </a>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Customer Info</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-gray-500">Name</p>
                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($order['customer_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email</p>
                        <p class="text-gray-900"><?= htmlspecialchars($order['customer_email']) ?></p>
                    </div>
                    <?php if (!empty($order['customer_phone'])): ?>
                    <div>
                        <p class="text-gray-500">Phone</p>
                        <p class="text-gray-900"><?= htmlspecialchars($order['customer_phone']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
