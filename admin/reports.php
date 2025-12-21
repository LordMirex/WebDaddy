<?php
$pageTitle = 'Sales Reports & Analytics';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance_metrics.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get filter parameters
$period = $_GET['period'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build date filter
$dateFilter = '';
$params = [];

if ($period === 'today') {
    $dateFilter = "AND DATE(datetime(s.created_at)) = DATE('now')";
} elseif ($period === 'week') {
    $dateFilter = "AND datetime(s.created_at) >= datetime('now', '-7 days')";
} elseif ($period === 'month') {
    $dateFilter = "AND datetime(s.created_at) >= datetime('now', '-30 days')";
} elseif ($period === 'custom' && $startDate && $endDate) {
    $dateFilter = "AND DATE(datetime(s.created_at)) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
}

// Use standardized financial metrics (calculates from sales table - single source of truth)
$revenueMetrics = getRevenueMetrics($db, $dateFilter, $params);
$totalRevenue = $revenueMetrics['total_revenue'];
$totalAffiliateCommission = $revenueMetrics['total_commission'];
$totalOrders = $revenueMetrics['total_sales'];
$netRevenue = $revenueMetrics['net_revenue'];
$avgOrderValue = $revenueMetrics['avg_order_value'];

// Use standardized discount metrics function
$discountMetrics = getDiscountMetrics($db, $dateFilter, $params);
$totalDiscount = $discountMetrics['total_discount'];

// Get commission breakdown (affiliate vs user referral)
$commissionBreakdown = getCommissionBreakdown($db, $dateFilter, $params);
$totalUserReferralCommission = $commissionBreakdown['user_referral']['total_commission'];
$totalCommission = $totalAffiliateCommission + $totalUserReferralCommission;

// For display purposes (these are not in standardized metrics yet)
$totalOriginal = $totalRevenue;

// YOUR ACTUAL PROFIT = What customers paid you - (affiliate commissions + user referral commissions)
$yourActualProfit = $totalRevenue - $totalCommission;

// NOTE: All commission amounts use sales table as single source of truth
// This ensures consistency across all admin pages and affiliate dashboard

// Use standardized top products function
$topProducts = getTopProducts($db, $dateFilter, $params, 10);

// Use standardized top affiliates function  
$topAffiliates = getTopAffiliates($db, $dateFilter, $params, 5);

// Recent Sales with full breakdown (all order types)
$query = "
    SELECT 
        s.*,
        po.customer_name,
        po.customer_email,
        po.order_type,
        t.name as template_name,
        t.price as template_price,
        tool.name as tool_name,
        tool.price as tool_price,
        a.code as affiliate_code,
        COALESCE(s.original_price, 0) as sale_original,
        COALESCE(s.discount_amount, 0) as sale_discount,
        COALESCE(s.final_amount, s.amount_paid) as sale_final,
        COALESCE(s.final_amount, s.amount_paid) - COALESCE(s.commission_amount, 0) as platform_revenue,
        (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
    FROM sales s
    JOIN pending_orders po ON s.pending_order_id = po.id
    LEFT JOIN templates t ON po.template_id = t.id
    LEFT JOIN tools tool ON po.tool_id = tool.id
    LEFT JOIN affiliates a ON s.affiliate_id = a.id
    WHERE 1=1 $dateFilter
    ORDER BY s.created_at DESC
    LIMIT 20
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$recentSalesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Batch fetch all data for efficiency (avoiding N+1 queries)
$orderIds = array_column($recentSalesRaw, 'pending_order_id');
$orderProductNames = [];
$orderDiscounts = [];

if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    
    // Batch fetch product names from order_items
    $itemsQuery = $db->prepare("
        SELECT 
            oi.pending_order_id,
            oi.product_type,
            COALESCE(t.name, tool.name, 'Unknown Product') as product_name,
            ROW_NUMBER() OVER (PARTITION BY oi.pending_order_id ORDER BY oi.id ASC) as rn
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tool ON oi.product_type = 'tool' AND oi.product_id = tool.id
        WHERE oi.pending_order_id IN ($placeholders)
    ");
    $itemsQuery->execute($orderIds);
    $allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by order ID and get first item name for each
    foreach ($allItems as $item) {
        $orderId = $item['pending_order_id'];
        if (!isset($orderProductNames[$orderId]) || $item['rn'] == 1) {
            $orderProductNames[$orderId] = [
                'product_type' => $item['product_type'],
                'product_name' => $item['product_name']
            ];
        }
    }
    
    // Batch fetch discounts from pending_orders (avoiding N+1 queries)
    $discountQuery = $db->prepare("
        SELECT id, discount_amount, original_price
        FROM pending_orders
        WHERE id IN ($placeholders)
    ");
    $discountQuery->execute($orderIds);
    $discountRows = $discountQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($discountRows as $row) {
        $orderDiscounts[$row['id']] = [
            'discount_amount' => floatval($row['discount_amount'] ?? 0),
            'original_price' => floatval($row['original_price'] ?? 0)
        ];
    }
}

// Process sales with batch-fetched data
$recentSales = [];
foreach ($recentSalesRaw as $sale) {
    $orderId = $sale['pending_order_id'];
    
    // Use batch-fetched discount data
    if (empty($sale['sale_discount']) || $sale['sale_discount'] == 0) {
        if (isset($orderDiscounts[$orderId])) {
            $sale['sale_discount'] = $orderDiscounts[$orderId]['discount_amount'];
            $sale['sale_original'] = $orderDiscounts[$orderId]['original_price'] ?: ($sale['amount_paid'] ?? 0);
        } else {
            $sale['sale_discount'] = 0;
            $sale['sale_original'] = $sale['amount_paid'];
        }
    }
    
    // Use batch-fetched product names for accurate display
    if (empty($sale['template_name']) && empty($sale['tool_name'])) {
        if (isset($orderProductNames[$orderId])) {
            $productInfo = $orderProductNames[$orderId];
            if ($productInfo['product_type'] === 'template') {
                $sale['template_name'] = $productInfo['product_name'];
            } else {
                $sale['tool_name'] = $productInfo['product_name'];
            }
        } else {
            $sale['tool_name'] = 'Unknown Product';
        }
    }
    
    $recentSales[] = $sale;
}

// Sales by day (last 30 days for chart)
$query = "
    SELECT 
        DATE(datetime(created_at)) as sale_date,
        COUNT(*) as orders,
        SUM(amount_paid) as revenue
    FROM sales
    WHERE datetime(created_at) >= datetime('now', '-30 days')
    GROUP BY DATE(datetime(created_at))
    ORDER BY sale_date ASC
";
$salesByDay = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Payment reconciliation data
$reconciliation = getPaymentReconciliation($db);

// Customer Analytics
$customersByMonth = $db->query("
    SELECT 
        strftime('%Y-%m', created_at) as month,
        COUNT(*) as new_customers
    FROM customers
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$customerLTV = $db->query("
    SELECT 
        c.id,
        c.email,
        c.username,
        COUNT(po.id) as order_count,
        COALESCE(SUM(po.final_amount), 0) as total_spent,
        MIN(po.created_at) as first_order,
        MAX(po.created_at) as last_order
    FROM customers c
    JOIN pending_orders po ON po.customer_id = c.id
    WHERE po.status = 'paid'
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$totalCustomersWithOrders = $db->query("
    SELECT COUNT(DISTINCT customer_id) 
    FROM pending_orders 
    WHERE customer_id IS NOT NULL AND status = 'paid'
")->fetchColumn();

$repeatCustomers = $db->query("
    SELECT COUNT(*) FROM (
        SELECT customer_id, COUNT(*) as orders
        FROM pending_orders
        WHERE customer_id IS NOT NULL AND status = 'paid'
        GROUP BY customer_id
        HAVING orders > 1
    )
")->fetchColumn();

$repeatCustomerRate = $totalCustomersWithOrders > 0 ? round(($repeatCustomers / $totalCustomersWithOrders) * 100, 1) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-graph-up text-primary-600"></i> Sales Reports & Analytics
    </h1>
    <p class="text-gray-600 mt-2">Comprehensive sales and revenue analytics</p>
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

<!-- Filter Section -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="p-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Period</label>
                <select name="period" id="periodSelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div id="customDates" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            
            <div id="customDatesEnd" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            
            <div class="flex items-end gap-2">
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                    <i class="bi bi-filter mr-2"></i> Apply Filter
                </button>
                <a href="/admin/reports.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Revenue</h6>
            <i class="bi bi-currency-dollar text-2xl text-green-600"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($totalRevenue); ?></div>
        <small class="text-xs text-green-600 flex items-center gap-1"><i class="bi bi-check-circle"></i> Customer Paid</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Discount</h6>
            <i class="bi bi-percent text-2xl text-orange-600"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($totalDiscount); ?></div>
        <small class="text-xs text-gray-500">Given to customers</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Commission Paid</h6>
            <i class="bi bi-people text-2xl text-purple-600"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($totalCommission); ?></div>
        <small class="text-xs text-gray-500">To affiliates</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Platform Revenue</h6>
            <i class="bi bi-graph-up text-2xl text-blue-600"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($netRevenue); ?></div>
        <small class="text-xs text-gray-500">After all costs</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Orders</h6>
            <i class="bi bi-cart-check text-2xl text-primary-600"></i>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo $totalOrders; ?></div>
        <small class="text-xs text-gray-500">Completed sales</small>
    </div>
</div>

<!-- Charts Row -->
<div class="mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-bar-chart text-primary-600"></i> Sales Trend (Last 30 Days)
            </h5>
        </div>
        <div class="p-3 sm:p-6" style="position: relative; height: 350px; min-height: 300px; max-height: 500px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-trophy text-yellow-500"></i> Top Selling Products
            </h5>
        </div>
        <div class="p-6">
            <?php if (empty($topProducts)): ?>
            <p class="text-gray-500">No sales data available</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Product</th>
                            <th class="text-center py-3 px-2 font-semibold text-gray-700 text-sm">Type</th>
                            <th class="text-center py-3 px-2 font-semibold text-gray-700 text-sm">Sales</th>
                            <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($topProducts as $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-2 text-gray-900"><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td class="text-center py-3 px-2">
                                <?php 
                                $typeColors = [
                                    'template' => 'bg-green-100 text-green-800',
                                    'tool' => 'bg-blue-100 text-blue-800'
                                ];
                                $typeIcons = [
                                    'template' => 'palette',
                                    'tool' => 'tools'
                                ];
                                $color = $typeColors[$product['product_type']] ?? 'bg-gray-100 text-gray-800';
                                $icon = $typeIcons[$product['product_type']] ?? 'box';
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 <?php echo $color; ?> rounded-full text-xs font-semibold">
                                    <i class="bi bi-<?php echo $icon; ?> mr-1"></i><?php echo ucfirst($product['product_type']); ?>
                                </span>
                            </td>
                            <td class="text-center py-3 px-2">
                                <span class="px-3 py-1 bg-primary-100 text-primary-800 rounded-full text-xs font-semibold"><?php echo $product['sales_count']; ?></span>
                            </td>
                            <td class="text-right py-3 px-2 font-bold text-gray-900">
                                <?php echo formatCurrency($product['revenue']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-star text-yellow-500"></i> Top Affiliates
            </h5>
        </div>
        <div class="p-6">
            <?php if (empty($topAffiliates)): ?>
            <p class="text-gray-500">No affiliate sales data available</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Affiliate</th>
                            <th class="text-center py-3 px-2 font-semibold text-gray-700 text-sm">Sales</th>
                            <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($topAffiliates as $affiliate): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-2">
                                <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($affiliate['affiliate_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($affiliate['code']); ?></div>
                            </td>
                            <td class="text-center py-3 px-2">
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold"><?php echo $affiliate['sales_count']; ?></span>
                            </td>
                            <td class="text-right py-3 px-2 font-bold text-gray-900">
                                <?php echo formatCurrency($affiliate['total_commission']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Sales Table -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-4 border-b border-gray-200 gap-3">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-clock-history text-primary-600"></i> Recent Sales
        </h5>
        <a href="/admin/orders.php?export=csv" class="w-full sm:w-auto px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-sm text-center whitespace-nowrap">
            <i class="bi bi-download mr-1"></i> Export CSV
        </a>
    </div>
    <div class="p-6">
        <?php if (empty($recentSales)): ?>
        <p class="text-gray-500">No sales found for the selected period</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Sale ID</th>
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Customer</th>
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Type</th>
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Products</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Original</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Discount</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Final</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Commission</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Platform</th>
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm whitespace-nowrap">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentSales as $sale): 
                        // Determine order type badge
                        $orderType = $sale['order_type'] ?? 'template';
                        $typeColors = [
                            'template' => 'bg-green-100 text-green-800',
                            'tool' => 'bg-blue-100 text-blue-800',
                            'tools' => 'bg-blue-100 text-blue-800',
                            'mixed' => 'bg-purple-100 text-purple-800'
                        ];
                        $typeIcons = [
                            'template' => 'palette',
                            'tool' => 'tools',
                            'tools' => 'tools',
                            'mixed' => 'box'
                        ];
                        $typeColor = $typeColors[$orderType] ?? 'bg-gray-100 text-gray-800';
                        $typeIcon = $typeIcons[$orderType] ?? 'box';
                        $typeLabel = ucfirst($orderType === 'tools' ? 'tool' : $orderType);
                        
                        // Get product name(s) - check for non-empty values (not just null)
                        $productName = 'Unknown Product';
                        if (!empty($sale['template_name']) && trim($sale['template_name']) !== '') {
                            $productName = $sale['template_name'];
                        } elseif (!empty($sale['tool_name']) && trim($sale['tool_name']) !== '') {
                            $productName = $sale['tool_name'];
                        }
                        
                        // Add indicator for multi-item orders
                        $itemCount = intval($sale['item_count'] ?? 0);
                        if ($itemCount > 1) {
                            $productName .= ' (+' . ($itemCount - 1) . ' more)';
                        }
                        
                        // Ensure numeric values for financial columns (prevent NaN)
                        $saleOriginal = floatval($sale['sale_original'] ?? 0);
                        $saleDiscount = floatval($sale['sale_discount'] ?? 0);
                        $saleFinal = floatval($sale['sale_final'] ?? $sale['amount_paid'] ?? 0);
                        $commissionAmount = floatval($sale['commission_amount'] ?? 0);
                        $platformRevenue = $saleFinal - $commissionAmount;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $sale['id']; ?></td>
                        <td class="py-3 px-2">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sale['customer_email']); ?></div>
                            <?php if ($sale['affiliate_code']): ?>
                            <div class="text-xs text-blue-600 font-medium mt-1">via <?php echo htmlspecialchars($sale['affiliate_code']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2">
                            <span class="inline-flex items-center px-2 py-0.5 <?php echo $typeColor; ?> rounded-full text-xs font-semibold">
                                <i class="bi bi-<?php echo $typeIcon; ?> mr-1"></i><?php echo $typeLabel; ?>
                            </span>
                        </td>
                        <td class="py-3 px-2 text-gray-700 text-sm"><?php echo htmlspecialchars($productName); ?></td>
                        <td class="py-3 px-2 text-right text-gray-600 whitespace-nowrap"><?php echo formatCurrency($saleOriginal); ?></td>
                        <td class="py-3 px-2 text-right whitespace-nowrap">
                            <?php if ($saleDiscount > 0): ?>
                            <span class="text-orange-600 font-medium">-<?php echo formatCurrency($saleDiscount); ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 text-right font-bold text-gray-900 whitespace-nowrap"><?php echo formatCurrency($saleFinal); ?></td>
                        <td class="py-3 px-2 text-right whitespace-nowrap">
                            <?php if ($commissionAmount > 0): ?>
                            <span class="text-purple-600 font-medium">-<?php echo formatCurrency($commissionAmount); ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 text-right font-bold text-blue-600 whitespace-nowrap"><?php echo formatCurrency($platformRevenue); ?></td>
                        <td class="py-3 px-2 text-gray-700 text-sm whitespace-nowrap"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/chart.umd.min.js"></script>
<script>
// Toggle custom date fields
document.getElementById('periodSelect').addEventListener('change', function() {
    const isCustom = this.value === 'custom';
    document.getElementById('customDates').style.display = isCustom ? 'block' : 'none';
    document.getElementById('customDatesEnd').style.display = isCustom ? 'block' : 'none';
});

// Sales Chart with Enhanced Styling
const ctx = document.getElementById('salesChart').getContext('2d');
const salesData = <?php echo json_encode($salesByDay); ?>;

const labels = salesData.map(d => {
    const date = new Date(d.sale_date);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});
const orders = salesData.map(d => parseInt(d.orders));
const revenue = salesData.map(d => parseFloat(d.revenue));

// Create gradients
const ordersGradient = ctx.createLinearGradient(0, 0, 0, 400);
ordersGradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
ordersGradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

const revenueGradient = ctx.createLinearGradient(0, 0, 0, 400);
revenueGradient.addColorStop(0, 'rgba(16, 185, 129, 0.5)');
revenueGradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Orders',
                data: orders,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: ordersGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: 'rgb(59, 130, 246)',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3,
                yAxisID: 'y',
            },
            {
                label: 'Revenue (₦)',
                data: revenue,
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: revenueGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgb(16, 185, 129)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: 'rgb(16, 185, 129)',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 15,
                    font: {
                        size: 13,
                        weight: '600'
                    }
                }
            },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(255, 255, 255, 0.3)',
                borderWidth: 1,
                padding: 12,
                displayColors: true,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed.y !== null) {
                            if (context.dataset.label.includes('Revenue')) {
                                label += '₦' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            } else {
                                label += context.parsed.y;
                            }
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false,
                },
                ticks: {
                    font: {
                        size: 12,
                        weight: '500'
                    },
                    color: '#6b7280',
                    padding: 8
                },
                title: {
                    display: true,
                    text: 'Orders',
                    font: {
                        size: 13,
                        weight: '600'
                    },
                    color: '#374151',
                    padding: 10
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    font: {
                        size: 12,
                        weight: '500'
                    },
                    color: '#6b7280',
                    padding: 8,
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                },
                title: {
                    display: true,
                    text: 'Revenue (₦)',
                    font: {
                        size: 13,
                        weight: '600'
                    },
                    color: '#374151',
                    padding: 10
                }
            },
            x: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false,
                },
                ticks: {
                    font: {
                        size: 11,
                        weight: '500'
                    },
                    color: '#6b7280',
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        },
        animation: {
            duration: 1500,
            easing: 'easeInOutQuart'
        }
    }
});
</script>

<!-- Customer Analytics Section -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-people text-primary-600"></i> Customer Analytics
        </h5>
        <p class="text-sm text-gray-600 mt-1">Customer acquisition, lifetime value and retention metrics</p>
    </div>
    <div class="p-6">
        <!-- Customer Metrics Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h6 class="text-xs font-semibold text-blue-600 uppercase mb-1">Paying Customers</h6>
                <div class="text-xl font-bold text-blue-900"><?php echo number_format($totalCustomersWithOrders); ?></div>
                <div class="text-sm text-blue-700">With completed orders</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h6 class="text-xs font-semibold text-green-600 uppercase mb-1">Repeat Customers</h6>
                <div class="text-xl font-bold text-green-900"><?php echo number_format($repeatCustomers); ?></div>
                <div class="text-sm text-green-700">2+ orders</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <h6 class="text-xs font-semibold text-purple-600 uppercase mb-1">Repeat Rate</h6>
                <div class="text-xl font-bold text-purple-900"><?php echo $repeatCustomerRate; ?>%</div>
                <div class="text-sm text-purple-700">Customer retention</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Customer Acquisition by Month -->
            <div>
                <h6 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="bi bi-graph-up-arrow text-green-600"></i> Customer Acquisition by Month
                </h6>
                <?php if (empty($customersByMonth)): ?>
                <p class="text-gray-500">No customer data available</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 px-2 font-semibold text-gray-700">Month</th>
                                <th class="text-right py-2 px-2 font-semibold text-gray-700">New Customers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach (array_slice($customersByMonth, 0, 6) as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-2 text-gray-900"><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                <td class="py-2 px-2 text-right">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                        +<?php echo number_format($row['new_customers']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Top Customers by Lifetime Value -->
            <div>
                <h6 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="bi bi-trophy text-yellow-500"></i> Top Customers by Lifetime Value
                </h6>
                <?php if (empty($customerLTV)): ?>
                <p class="text-gray-500">No customer purchase data available</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 px-2 font-semibold text-gray-700">Customer</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-700">Orders</th>
                                <th class="text-right py-2 px-2 font-semibold text-gray-700">Total Spent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach (array_slice($customerLTV, 0, 10) as $customer): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-2">
                                    <a href="/admin/customer-detail.php?id=<?php echo $customer['id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                                        <?php echo htmlspecialchars($customer['username'] ?: $customer['email']); ?>
                                    </a>
                                </td>
                                <td class="py-2 px-2 text-center">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                        <?php echo $customer['order_count']; ?>
                                    </span>
                                </td>
                                <td class="py-2 px-2 text-right font-bold text-gray-900">
                                    <?php echo formatCurrency($customer['total_spent']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Reconciliation Section -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-check2-all text-primary-600"></i> Payment Reconciliation
            </h5>
            <p class="text-sm text-gray-600">Compare payments with sales records to detect discrepancies</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
            <?php echo $reconciliation['status'] === 'ok' ? 'bg-green-100 text-green-800' : 
                ($reconciliation['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
            <?php echo strtoupper($reconciliation['status']); ?>
        </span>
    </div>
    
    <div class="p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h6 class="text-xs font-semibold text-blue-600 uppercase mb-1">Payments Completed</h6>
                <div class="text-xl font-bold text-blue-900"><?php echo number_format($reconciliation['summary']['payments_completed']['count']); ?></div>
                <div class="text-sm text-blue-700"><?php echo formatCurrency($reconciliation['summary']['payments_completed']['total']); ?></div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h6 class="text-xs font-semibold text-green-600 uppercase mb-1">Sales Recorded</h6>
                <div class="text-xl font-bold text-green-900"><?php echo number_format($reconciliation['summary']['sales_recorded']['count']); ?></div>
                <div class="text-sm text-green-700"><?php echo formatCurrency($reconciliation['summary']['sales_recorded']['total']); ?></div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <h6 class="text-xs font-semibold text-purple-600 uppercase mb-1">Orders Paid</h6>
                <div class="text-xl font-bold text-purple-900"><?php echo number_format($reconciliation['summary']['orders_paid']['count']); ?></div>
                <div class="text-sm text-purple-700"><?php echo formatCurrency($reconciliation['summary']['orders_paid']['total']); ?></div>
            </div>
            <div class="<?php echo $reconciliation['summary']['difference'] > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'; ?> rounded-lg p-4 border">
                <h6 class="text-xs font-semibold <?php echo $reconciliation['summary']['difference'] > 0 ? 'text-red-600' : 'text-gray-600'; ?> uppercase mb-1">Difference</h6>
                <div class="text-xl font-bold <?php echo $reconciliation['summary']['difference'] > 0 ? 'text-red-900' : 'text-gray-900'; ?>"><?php echo formatCurrency($reconciliation['summary']['difference']); ?></div>
                <div class="text-sm <?php echo $reconciliation['summary']['difference'] > 0 ? 'text-red-700' : 'text-gray-700'; ?>">Payment vs Sales</div>
            </div>
        </div>
        
        <!-- Issues Section -->
        <?php if (!empty($reconciliation['issues'])): ?>
        <div class="space-y-4">
            <h6 class="font-bold text-gray-700">Detected Issues</h6>
            <?php foreach ($reconciliation['issues'] as $issue): ?>
            <div class="p-4 rounded-lg border-l-4 
                <?php echo $issue['severity'] === 'error' ? 'bg-red-50 border-red-500' : 
                    ($issue['severity'] === 'warning' ? 'bg-yellow-50 border-yellow-500' : 'bg-blue-50 border-blue-500'); ?>">
                <div class="flex items-center gap-2 mb-2">
                    <i class="bi <?php echo $issue['severity'] === 'error' ? 'bi-x-circle-fill text-red-600' : 
                        ($issue['severity'] === 'warning' ? 'bi-exclamation-triangle-fill text-yellow-600' : 'bi-info-circle-fill text-blue-600'); ?>"></i>
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($issue['message']); ?></span>
                </div>
                <?php if (!empty($issue['items']) && count($issue['items']) <= 5): ?>
                <div class="mt-2 text-sm text-gray-600">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1">ID</th>
                                <th class="py-1">Order ID</th>
                                <th class="py-1">Amount</th>
                                <th class="py-1">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($issue['items'], 0, 5) as $item): ?>
                            <tr class="border-t border-gray-200">
                                <td class="py-1"><?php echo $item['id'] ?? '-'; ?></td>
                                <td class="py-1"><?php echo $item['pending_order_id'] ?? '-'; ?></td>
                                <td class="py-1">
                                    <?php 
                                    if (isset($item['difference'])) {
                                        echo formatCurrency($item['payment_amount']) . ' vs ' . formatCurrency($item['sale_amount']);
                                    } else {
                                        echo formatCurrency($item['amount_paid'] ?? 0);
                                    }
                                    ?>
                                </td>
                                <td class="py-1"><?php echo substr($item['created_at'] ?? '', 0, 10); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif (!empty($issue['items'])): ?>
                <div class="mt-2 text-sm text-gray-600">
                    Showing first 5 of <?php echo count($issue['items']); ?> items. Check database for full list.
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
            <i class="bi bi-check-circle-fill text-green-600 text-2xl"></i>
            <p class="text-green-700 font-semibold mt-2"><?php echo $reconciliation['message'] ?? 'All payments are properly reconciled'; ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
