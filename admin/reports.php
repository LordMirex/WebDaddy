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
$totalCommission = $revenueMetrics['total_commission'];
$totalOrders = $revenueMetrics['total_sales'];
$netRevenue = $revenueMetrics['net_revenue'];
$avgOrderValue = $revenueMetrics['avg_order_value'];

// Use standardized discount metrics function
$discountMetrics = getDiscountMetrics($db, $dateFilter, $params);
$totalDiscount = $discountMetrics['total_discount'];

// For display purposes (these are not in standardized metrics yet)
$totalOriginal = $totalRevenue;

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

// Map pending_orders discount to sales records
$recentSales = [];
foreach ($recentSalesRaw as $sale) {
    $sale['sale_discount'] = 0;
    $sale['sale_original'] = $sale['amount_paid'];
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

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-graph-up text-primary-600"></i> Sales Reports & Analytics
    </h1>
    <p class="text-gray-600 mt-2">Comprehensive sales and revenue analytics</p>
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
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Template</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Original</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Discount</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Final</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Commission</th>
                        <th class="text-right py-3 px-2 font-semibold text-gray-700 text-sm">Platform</th>
                        <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentSales as $sale): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $sale['id']; ?></td>
                        <td class="py-3 px-2">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sale['customer_email']); ?></div>
                            <?php if ($sale['affiliate_code']): ?>
                            <div class="text-xs text-blue-600 font-medium mt-1">via <?php echo htmlspecialchars($sale['affiliate_code']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 text-gray-700"><?php echo htmlspecialchars($sale['template_name']); ?></td>
                        <td class="py-3 px-2 text-right text-gray-600"><?php echo formatCurrency($sale['sale_original']); ?></td>
                        <td class="py-3 px-2 text-right">
                            <?php if ($sale['sale_discount'] > 0): ?>
                            <span class="text-orange-600 font-medium">-<?php echo formatCurrency($sale['sale_discount']); ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 text-right font-bold text-gray-900"><?php echo formatCurrency($sale['sale_final']); ?></td>
                        <td class="py-3 px-2 text-right">
                            <?php if ($sale['commission_amount'] > 0): ?>
                            <span class="text-purple-600 font-medium">-<?php echo formatCurrency($sale['commission_amount']); ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 text-right font-bold text-blue-600"><?php echo formatCurrency($sale['platform_revenue']); ?></td>
                        <td class="py-3 px-2 text-gray-700 text-sm whitespace-nowrap"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
