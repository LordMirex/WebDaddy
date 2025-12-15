<?php
/**
 * User Order Detail Page - Enhanced UX
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

$templateItems = array_filter($orderItems, fn($i) => $i['product_type'] === 'template');
$toolItems = array_filter($orderItems, fn($i) => $i['product_type'] === 'tool');
$itemCount = count($orderItems);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <a href="/user/orders.php" class="text-amber-600 hover:text-amber-700 inline-flex items-center text-sm font-medium">
            <i class="bi-arrow-left mr-2"></i>Back to Orders
        </a>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
            <span class="px-3 py-1 text-sm font-medium rounded-full border <?= $statusColor ?>">
                <?= ucfirst($order['status']) ?>
            </span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Order #<?= $orderId ?></h1>
                <p class="text-sm text-gray-500"><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
            </div>
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
                <h2 class="font-bold text-gray-900">Website Templates (<?= count($templateItems) ?>)</h2>
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
                                    <h3 class="font-semibold text-gray-900 text-lg">
                                        <?= htmlspecialchars($item['product_name'] ?? 'Template') ?>
                                    </h3>
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
                            <div class="mt-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="bi-check-circle-fill text-green-600"></i>
                                    <span class="font-semibold text-green-800">Delivery Complete</span>
                                </div>
                                
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <?php if (!empty($item['hosted_domain'])): ?>
                                    <div class="bg-white rounded-lg p-3 border">
                                        <p class="text-xs text-gray-500 mb-1 font-medium">YOUR WEBSITE</p>
                                        <a href="https://<?= htmlspecialchars($item['hosted_domain']) ?>" target="_blank" 
                                           class="text-amber-600 hover:text-amber-700 font-semibold inline-flex items-center break-all">
                                            <?= htmlspecialchars($item['hosted_domain']) ?>
                                            <i class="bi-box-arrow-up-right ml-2 text-sm flex-shrink-0"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['domain_login_url'])): ?>
                                    <div class="bg-white rounded-lg p-3 border">
                                        <p class="text-xs text-gray-500 mb-1 font-medium">ADMIN PANEL</p>
                                        <a href="<?= htmlspecialchars($item['domain_login_url']) ?>" target="_blank" 
                                           class="text-amber-600 hover:text-amber-700 font-semibold inline-flex items-center">
                                            Login to Admin
                                            <i class="bi-box-arrow-up-right ml-2 text-sm"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($item['admin_username']) || !empty($item['admin_password'])): ?>
                                <div class="mt-4 bg-white rounded-lg p-3 border" x-data="{ showPassword: false }">
                                    <p class="text-xs text-gray-500 mb-2 font-medium">LOGIN CREDENTIALS</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <?php if (!empty($item['admin_username'])): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="bi-person text-gray-400"></i>
                                            <span class="text-sm text-gray-600">Username:</span>
                                            <code class="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono"><?= htmlspecialchars($item['admin_username']) ?></code>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['admin_password'])): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="bi-key text-gray-400"></i>
                                            <span class="text-sm text-gray-600">Password:</span>
                                            <code x-show="showPassword" class="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono"><?= htmlspecialchars($item['admin_password']) ?></code>
                                            <span x-show="!showPassword" class="text-gray-400">••••••••</span>
                                            <button @click="showPassword = !showPassword" class="text-amber-600 hover:text-amber-700 text-xs ml-1">
                                                <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-xs text-gray-500 mt-3 flex items-center gap-1">
                                    <i class="bi-envelope"></i>
                                    Login credentials were sent to your email
                                </p>
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
                <h2 class="font-bold text-gray-900">Digital Products (<?= count($toolItems) ?>)</h2>
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
                                    <h3 class="font-semibold text-gray-900 text-lg">
                                        <?= htmlspecialchars($item['product_name'] ?? 'Digital Product') ?>
                                    </h3>
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
                                
                                <div class="space-y-2">
                                    <?php foreach ($downloadFiles as $file): ?>
                                    <div class="bg-white rounded-lg p-3 border flex items-center justify-between gap-3 hover:border-blue-300 transition">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="bi-file-earmark-arrow-down text-blue-600"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-medium text-gray-900 truncate" title="<?= htmlspecialchars($file['name'] ?? 'File') ?>">
                                                    <?= htmlspecialchars($file['name'] ?? 'File') ?>
                                                </p>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <?php if (!empty($file['file_size_formatted'])): ?>
                                                    <span><?= htmlspecialchars($file['file_size_formatted']) ?></span>
                                                    <span>•</span>
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
                                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium flex-shrink-0">
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
                    
                    <?php if ($order['status'] !== 'pending' && $order['status'] !== 'cancelled'): ?>
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
                        <span class="<?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'text-green-600 font-medium' : 'text-yellow-600' ?>">
                            <?= $order['status'] === 'paid' || $order['status'] === 'completed' ? 'Paid' : 'Pending' ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
