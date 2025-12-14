<?php
/**
 * User Orders List
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'orders';
$pageTitle = 'My Orders';

$status = isset($_GET['status']) && in_array($_GET['status'], ['all', 'pending', 'paid', 'completed']) ? $_GET['status'] : 'all';
$confirmedOrderId = isset($_GET['confirmed']) ? (int)$_GET['confirmed'] : null;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

$totalOrders = getCustomerOrderCount($customer['id'], $status);
$totalPages = ceil($totalOrders / $perPage);
$orders = getCustomerOrders($customer['id'], $perPage, $offset, $status);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <?php if ($confirmedOrderId): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start space-x-3">
        <i class="bi-check-circle-fill text-green-600 text-xl mt-0.5"></i>
        <div>
            <h3 class="font-semibold text-green-800">Order Confirmed!</h3>
            <p class="text-sm text-green-700 mt-1">Your order #<?= $confirmedOrderId ?> has been placed successfully. You will receive updates via email.</p>
            <a href="/user/order-detail.php?id=<?= $confirmedOrderId ?>" class="inline-block mt-2 text-sm font-medium text-green-800 underline hover:no-underline">View Order Details &rarr;</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-4 border-b">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="font-bold text-gray-900">Order History</h2>
                
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all" 
                       class="px-3 py-1.5 text-sm rounded-lg transition <?= $status === 'all' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        All
                    </a>
                    <a href="?status=pending" 
                       class="px-3 py-1.5 text-sm rounded-lg transition <?= $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Pending
                    </a>
                    <a href="?status=paid" 
                       class="px-3 py-1.5 text-sm rounded-lg transition <?= $status === 'paid' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Paid
                    </a>
                    <a href="?status=completed" 
                       class="px-3 py-1.5 text-sm rounded-lg transition <?= $status === 'completed' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        Completed
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
        <div class="p-8 text-center">
            <i class="bi-bag text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500 mb-4">No orders found<?= $status !== 'all' ? ' with status "' . ucfirst($status) . '"' : '' ?>.</p>
            <a href="/" class="inline-block px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">Browse Products</a>
        </div>
        <?php else: ?>
        <div class="divide-y">
            <?php foreach ($orders as $order): ?>
            <?php 
                $isHighlighted = ($confirmedOrderId && $confirmedOrderId == $order['id']);
                $statusColor = match($order['status']) {
                    'paid' => 'bg-blue-100 text-blue-700',
                    'completed' => 'bg-green-100 text-green-700',
                    'pending' => 'bg-yellow-100 text-yellow-700',
                    'cancelled' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-700'
                };
            ?>
            <div class="p-4 <?= $isHighlighted ? 'bg-green-50 ring-2 ring-green-500 ring-inset' : 'hover:bg-gray-50' ?> transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="bi-bag text-gray-400 text-xl"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-gray-900">Order #<?= $order['id'] ?></h3>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statusColor ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                                <?php if ($isHighlighted): ?>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-500 text-white">
                                    <i class="bi-check-circle-fill mr-1"></i>Just Confirmed
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="bi-calendar3 mr-1"></i><?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                                <span class="mx-2">&middot;</span>
                                <i class="bi-box mr-1"></i><?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?>
                            </p>
                            <?php if (!empty($order['final_amount'])): ?>
                            <p class="text-sm font-semibold text-gray-700 mt-1">
                                ₦<?= number_format($order['final_amount'], 2) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 sm:flex-shrink-0">
                        <a href="/user/order-detail.php?id=<?= $order['id'] ?>" 
                           class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition inline-flex items-center">
                            <i class="bi-eye mr-1.5"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <p class="text-sm text-gray-500">
                    Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalOrders) ?> of <?= $totalOrders ?> orders
                </p>
                <div class="flex items-center gap-2">
                    <?php if ($currentPage > 1): ?>
                    <a href="?status=<?= $status ?>&page=<?= $currentPage - 1 ?>" 
                       class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                        <i class="bi-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?status=<?= $status ?>&page=<?= $i ?>" 
                       class="px-3 py-1.5 rounded-lg text-sm transition <?= $i === $currentPage ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?status=<?= $status ?>&page=<?= $currentPage + 1 ?>" 
                       class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                        Next <i class="bi-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
