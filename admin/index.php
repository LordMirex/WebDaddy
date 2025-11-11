<?php
$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$totalTemplates = $db->query("SELECT COUNT(*) FROM templates")->fetchColumn();
$activeTemplates = $db->query("SELECT COUNT(*) FROM templates WHERE active = true")->fetchColumn();
$totalTools = $db->query("SELECT COUNT(*) FROM tools")->fetchColumn();
$activeTools = $db->query("SELECT COUNT(*) FROM tools WHERE active = true")->fetchColumn();
$totalOrders = $db->query("SELECT COUNT(*) FROM pending_orders")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM pending_orders WHERE status = 'pending'")->fetchColumn();
$totalSales = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM sales")->fetchColumn();
$totalAffiliates = $db->query("SELECT COUNT(*) FROM affiliates WHERE status = 'active'")->fetchColumn();
$pendingWithdrawals = $db->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();

$ordersByType = $db->query("
    SELECT order_type, COUNT(*) as count 
    FROM pending_orders 
    GROUP BY order_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

$templateOrders = $ordersByType['template'] ?? 0;
$toolOrders = $ordersByType['tool'] ?? 0;
$mixedOrders = $ordersByType['mixed'] ?? 0;

$lowStockTools = $db->query("
    SELECT name, stock_quantity 
    FROM tools 
    WHERE active = true 
    AND stock_type = 'limited' 
    AND stock_quantity <= 5 
    AND stock_quantity > 0
    ORDER BY stock_quantity ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$outOfStockTools = $db->query("
    SELECT COUNT(*) 
    FROM tools 
    WHERE active = true 
    AND stock_type = 'limited' 
    AND stock_quantity = 0
")->fetchColumn();

$recentOrders = getOrders('pending');
$recentOrders = array_slice($recentOrders, 0, 5);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-speedometer2 text-primary-600"></i> Dashboard
    </h1>
    <p class="text-gray-600 mt-2">Welcome back, <?php echo htmlspecialchars(getAdminName()); ?>!</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Templates</h6>
            <i class="bi bi-grid text-xl sm:text-2xl text-primary-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($activeTemplates); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatNumber($totalTemplates); ?> total</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Tools</h6>
            <i class="bi bi-tools text-xl sm:text-2xl text-purple-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($activeTools); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatNumber($totalTools); ?> total</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Orders</h6>
            <i class="bi bi-cart text-xl sm:text-2xl text-blue-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($pendingOrders); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatNumber($totalOrders); ?> total</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Sales</h6>
            <i class="bi bi-check-circle text-xl sm:text-2xl text-green-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($totalSales); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatCurrency($totalRevenue); ?> revenue</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Affiliates</h6>
            <i class="bi bi-people text-xl sm:text-2xl text-purple-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($totalAffiliates); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatNumber($pendingWithdrawals); ?> pending withdrawals</small>
    </div>
</div>

<!-- Order Type Breakdown & Inventory Alerts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Order Type Breakdown -->
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-pie-chart text-primary-600"></i> Orders by Type
            </h5>
        </div>
        <div class="p-6">
            <?php if ($totalOrders > 0): ?>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="bi bi-palette text-2xl text-green-600"></i>
                        <span class="font-semibold text-gray-700">Template Orders</span>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-600"><?php echo $templateOrders; ?></div>
                        <div class="text-xs text-gray-500"><?php echo $totalOrders > 0 ? round(($templateOrders / $totalOrders) * 100, 1) : 0; ?>%</div>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="bi bi-tools text-2xl text-blue-600"></i>
                        <span class="font-semibold text-gray-700">Tool Orders</span>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $toolOrders; ?></div>
                        <div class="text-xs text-gray-500"><?php echo $totalOrders > 0 ? round(($toolOrders / $totalOrders) * 100, 1) : 0; ?>%</div>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="bi bi-collection text-2xl text-purple-600"></i>
                        <span class="font-semibold text-gray-700">Mixed Orders</span>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $mixedOrders; ?></div>
                        <div class="text-xs text-gray-500"><?php echo $totalOrders > 0 ? round(($mixedOrders / $totalOrders) * 100, 1) : 0; ?>%</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-4">No orders yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Alerts -->
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-exclamation-triangle text-yellow-500"></i> Inventory Alerts
            </h5>
        </div>
        <div class="p-6">
            <?php if ($outOfStockTools > 0): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded-lg mb-4 flex items-center gap-3">
                <i class="bi bi-x-circle text-xl"></i>
                <span><strong><?php echo $outOfStockTools; ?></strong> tool(s) out of stock</span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($lowStockTools)): ?>
            <h6 class="text-sm font-semibold text-gray-700 mb-3">Low Stock Tools:</h6>
            <div class="space-y-2">
                <?php foreach ($lowStockTools as $tool): ?>
                <div class="flex items-center justify-between p-2 bg-yellow-50 rounded">
                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($tool['name']); ?></span>
                    <span class="px-2 py-1 bg-yellow-200 text-yellow-800 rounded text-xs font-bold">
                        <?php echo $tool['stock_quantity']; ?> left
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($outOfStockTools == 0): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-3 rounded-lg flex items-center gap-3">
                <i class="bi bi-check-circle text-xl"></i>
                <span>All tools have adequate stock</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-clock-history text-primary-600"></i> Recent Pending Orders
        </h5>
        <a href="/admin/orders.php" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors text-sm">
            View All
        </a>
    </div>
    <div class="p-6">
        <?php if (empty($recentOrders)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No pending orders at the moment.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto relative">
            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white to-transparent pointer-events-none lg:hidden"></div>
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Order ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Customer</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Template</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Domain</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Date</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentOrders as $order): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4">
                            <strong class="text-primary-600">#<?php echo $order['id']; ?></strong>
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                        </td>
                        <td class="py-3 px-4 text-gray-900"><?php echo htmlspecialchars($order['template_name']); ?></td>
                        <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($order['domain_name'] ?? 'Not selected'); ?></td>
                        <td class="py-3 px-4 text-gray-700"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td class="py-3 px-4">
                            <a href="/admin/orders.php?view=<?php echo $order['id']; ?>" class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors text-sm">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
