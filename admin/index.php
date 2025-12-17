<?php
$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance_metrics.php';
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

// Use standardized revenue metrics from finance_metrics.php (single source of truth: sales table)
$revenueMetrics = getRevenueMetrics($db, '');
$totalRevenue = $revenueMetrics['total_revenue'];

$totalAffiliates = $db->query("SELECT COUNT(*) FROM affiliates WHERE status = 'active'")->fetchColumn();
$pendingWithdrawals = $db->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();

// Query from sales table (source of truth) for ALL payment methods
$paystackPaymentsCount = $db->query("
    SELECT COUNT(*) FROM sales WHERE id IN (
        SELECT id FROM sales WHERE pending_order_id IN (
            SELECT id FROM pending_orders WHERE payment_method = 'paystack'
        )
    )
")->fetchColumn();

$paystackRevenue = $db->query("
    SELECT COALESCE(SUM(s.amount_paid), 0) FROM sales s
    INNER JOIN pending_orders po ON s.pending_order_id = po.id
    WHERE po.payment_method = 'paystack'
")->fetchColumn();

$manualPaymentsCount = $db->query("
    SELECT COUNT(*) FROM sales s
    INNER JOIN pending_orders po ON s.pending_order_id = po.id
    WHERE po.payment_method = 'manual'
")->fetchColumn();

$pendingDeliveriesCount = $db->query("
    SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'pending'
")->fetchColumn();

$ordersByType = $db->query("
    SELECT order_type, COUNT(*) as count 
    FROM pending_orders 
    GROUP BY order_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

$templateOrders = $ordersByType['template'] ?? 0;
$toolOrders = ($ordersByType['tool'] ?? 0) + ($ordersByType['tools'] ?? 0);
$mixedOrders = $ordersByType['mixed'] ?? 0;

$lowStockTools = $db->query("
    SELECT name, stock_quantity 
    FROM tools 
    WHERE active = true 
    AND stock_unlimited = 0 
    AND stock_quantity <= 5 
    AND stock_quantity > 0
    ORDER BY stock_quantity ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$outOfStockTools = $db->query("
    SELECT COUNT(*) 
    FROM tools 
    WHERE active = true 
    AND stock_unlimited = 0 
    AND stock_quantity = 0
")->fetchColumn();

$recentOrders = getOrders('pending');
$recentOrders = array_slice($recentOrders, 0, 5);

// Commission statistics using standardized metrics (single source of truth: sales table)
$commissionBreakdown = getCommissionBreakdown($db, '', []);
$totalAffiliateCommission = $commissionBreakdown['affiliate']['total_commission'];
$totalUserReferralCommission = $commissionBreakdown['user_referral']['total_commission'];
$totalCommissionEarned = $totalAffiliateCommission + $totalUserReferralCommission;

$totalPaid = $db->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'paid'")->fetchColumn();
$totalUserRefPaid = $db->query("SELECT COALESCE(SUM(amount), 0) FROM user_referral_withdrawals WHERE status = 'paid'")->fetchColumn();
$totalCommissionPaid = (float)$totalPaid + (float)$totalUserRefPaid;
$totalCommissionPending = $totalCommissionEarned - $totalCommissionPaid;

// YOUR ACTUAL PROFIT = What customers paid you - All commissions (affiliate + user referral)
$yourActualProfit = $totalRevenue - $totalCommissionEarned;

// Customer statistics
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$newCustomersToday = $db->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = DATE('now')")->fetchColumn();
$customersWithOrders = $db->query("SELECT COUNT(DISTINCT customer_id) FROM pending_orders WHERE customer_id IS NOT NULL AND status = 'paid'")->fetchColumn();

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

<!-- YOUR ACTUAL PROFIT - MOST IMPORTANT -->
<div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-xl shadow-lg p-4 sm:p-6 border-2 border-green-500 mb-8">
    <div class="flex items-center justify-between mb-3">
        <h6 class="text-sm sm:text-base font-bold text-green-700 uppercase tracking-wide">💰 YOUR ACTUAL PROFIT</h6>
        <i class="bi bi-cash-coin text-3xl text-green-600"></i>
    </div>
    <div class="text-4xl sm:text-5xl font-bold text-green-700 mb-3"><?php echo formatCurrency($yourActualProfit); ?></div>
    <div class="bg-white rounded-lg p-3 text-sm space-y-2">
        <div class="flex justify-between">
            <span class="text-gray-700">Total Customer Payments:</span>
            <span class="font-semibold text-gray-900"><?php echo formatCurrency($totalRevenue); ?></span>
        </div>
        <div class="border-t pt-2 flex justify-between">
            <span class="text-gray-700">Minus Affiliate Commissions (30%):</span>
            <span class="font-semibold text-red-600">-<?php echo formatCurrency($totalAffiliateCommission); ?></span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-700">Minus User Referral Commissions (30%):</span>
            <span class="font-semibold text-red-600">-<?php echo formatCurrency($totalUserReferralCommission); ?></span>
        </div>
        <div class="border-t pt-2 flex justify-between bg-green-50 -mx-3 px-3 py-2 rounded font-bold">
            <span class="text-green-700">= Money You Keep:</span>
            <span class="text-green-700"><?php echo formatCurrency($yourActualProfit); ?></span>
        </div>
    </div>
</div>

<!-- Customer Overview -->
<div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-8 border border-gray-100">
    <h5 class="text-lg font-bold mb-4 flex items-center gap-2">
        <i class="bi bi-people text-primary-600"></i> Customer Accounts
    </h5>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo number_format($totalCustomers); ?></div>
            <div class="text-gray-500 text-sm">Total Customers</div>
        </div>
        <div class="bg-green-50 rounded-lg p-4">
            <div class="text-2xl sm:text-3xl font-bold text-green-600">+<?php echo $newCustomersToday; ?></div>
            <div class="text-gray-500 text-sm">New Today</div>
        </div>
        <div class="bg-blue-50 rounded-lg p-4">
            <div class="text-2xl sm:text-3xl font-bold text-blue-600"><?php echo number_format($customersWithOrders); ?></div>
            <div class="text-gray-500 text-sm">Paying Customers</div>
        </div>
    </div>
    <div class="mt-4 text-right">
        <a href="/admin/customers.php" class="text-primary-600 hover:text-primary-700 text-sm font-semibold">
            View All Customers <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Commission Statistics -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Affiliate Commission</h6>
            <i class="bi bi-people text-xl sm:text-2xl text-purple-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatCurrency($totalAffiliateCommission); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">30% of final amount</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">User Referral Commission</h6>
            <i class="bi bi-person-heart text-xl sm:text-2xl text-pink-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatCurrency($totalUserReferralCommission); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">30% of final amount</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Total Paid Out</h6>
            <i class="bi bi-check-circle text-xl sm:text-2xl text-blue-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatCurrency($totalCommissionPaid); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">Already paid to referrers</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Commission Pending</h6>
            <i class="bi bi-hourglass-split text-xl sm:text-2xl text-yellow-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatCurrency($totalCommissionPending); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">Awaiting payout</small>
    </div>
</div>

<!-- Payments & Delivery Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Paystack Payments</h6>
            <i class="bi bi-credit-card text-xl sm:text-2xl text-blue-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($paystackPaymentsCount); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php echo formatCurrency($paystackRevenue); ?> revenue</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Manual Payments</h6>
            <i class="bi bi-bank text-xl sm:text-2xl text-purple-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($manualPaymentsCount); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block"><?php 
            $manualRevenue = $db->query("
                SELECT COALESCE(SUM(s.amount_paid), 0) FROM sales s
                INNER JOIN pending_orders po ON s.pending_order_id = po.id
                WHERE po.payment_method = 'manual'
            ")->fetchColumn();
            echo formatCurrency($manualRevenue); 
        ?> revenue</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Pending Deliveries</h6>
            <i class="bi bi-box-seam text-xl sm:text-2xl text-yellow-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($pendingDeliveriesCount); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">Awaiting preparation</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide truncate">Total Payments</h6>
            <i class="bi bi-cash-stack text-xl sm:text-2xl text-green-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 truncate"><?php echo formatNumber($paystackPaymentsCount + $manualPaymentsCount); ?></div>
        <small class="text-xs sm:text-sm text-gray-500 truncate block">All payment methods</small>
    </div>
</div>

<!-- Quick Access to Key Analytics -->
<div class="mb-8">
    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="bi bi-lightning-fill text-yellow-500"></i> Quick Analytics Access
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <a href="/admin/analytics.php" class="bg-white rounded-lg shadow hover:shadow-lg transition-all p-4 border-l-4 border-blue-500 hover:border-blue-700 group">
            <div class="flex items-center justify-between mb-2">
                <i class="bi bi-graph-up text-2xl text-blue-600 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-semibold">Visitor Data</span>
            </div>
            <h6 class="font-bold text-gray-900 group-hover:text-blue-700">Analytics</h6>
            <p class="text-xs text-gray-600">Visits, bounce rate, time on site</p>
        </a>
        
        <a href="/admin/reports.php" class="bg-white rounded-lg shadow hover:shadow-lg transition-all p-4 border-l-4 border-green-500 hover:border-green-700 group">
            <div class="flex items-center justify-between mb-2">
                <i class="bi bi-bar-chart text-2xl text-green-600 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-semibold">Sales Data</span>
            </div>
            <h6 class="font-bold text-gray-900 group-hover:text-green-700">Reports</h6>
            <p class="text-xs text-gray-600">Revenue, products, affiliates</p>
        </a>
        
        <a href="/admin/search_analytics.php" class="bg-white rounded-lg shadow hover:shadow-lg transition-all p-4 border-l-4 border-purple-500 hover:border-purple-700 group">
            <div class="flex items-center justify-between mb-2">
                <i class="bi bi-search text-2xl text-purple-600 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-semibold">Searches</span>
            </div>
            <h6 class="font-bold text-gray-900 group-hover:text-purple-700">Search Analytics</h6>
            <p class="text-xs text-gray-600">Customer search terms</p>
        </a>
        
        <a href="/admin/monitoring.php" class="bg-white rounded-lg shadow hover:shadow-lg transition-all p-4 border-l-4 border-orange-500 hover:border-orange-700 group">
            <div class="flex items-center justify-between mb-2">
                <i class="bi bi-speedometer2 text-2xl text-orange-600 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded-full font-semibold">System</span>
            </div>
            <h6 class="font-bold text-gray-900 group-hover:text-orange-700">System Monitoring</h6>
            <p class="text-xs text-gray-600">Database, performance stats</p>
        </a>
        
        <a href="/admin/export.php" class="bg-white rounded-lg shadow hover:shadow-lg transition-all p-4 border-l-4 border-indigo-500 hover:border-indigo-700 group">
            <div class="flex items-center justify-between mb-2">
                <i class="bi bi-download text-2xl text-indigo-600 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-semibold">Export</span>
            </div>
            <h6 class="font-bold text-gray-900 group-hover:text-indigo-700">Export Data</h6>
            <p class="text-xs text-gray-600">CSV, reports, records</p>
        </a>
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
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Products</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Type</th>
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
                        <td class="py-3 px-4">
                            <div class="text-gray-900"><?php echo htmlspecialchars($order['product_names_display']); ?></div>
                            <?php if ($order['product_count'] > 1): ?>
                            <div class="text-sm text-gray-500"><?php echo $order['product_count']; ?> items</div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $orderType = $order['order_type'] ?? 'template';
                            $typeColors = [
                                'template' => 'bg-blue-100 text-blue-800',
                                'tool' => 'bg-purple-100 text-purple-800',
                                'tools' => 'bg-purple-100 text-purple-800',
                                'mixed' => 'bg-green-100 text-green-800'
                            ];
                            $typeIcons = [
                                'template' => 'grid',
                                'tool' => 'tools',
                                'tools' => 'tools',
                                'mixed' => 'stack'
                            ];
                            $color = $typeColors[$orderType] ?? 'bg-gray-100 text-gray-800';
                            $icon = $typeIcons[$orderType] ?? 'box';
                            ?>
                            <span class="inline-flex items-center px-2 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold">
                                <i class="bi bi-<?php echo $icon; ?> mr-1"></i><?php echo ucfirst($orderType); ?>
                            </span>
                        </td>
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
