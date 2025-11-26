<?php
$pageTitle = 'Analytics Dashboard';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance_metrics.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$period = $_GET['period'] ?? '30days';
$dateFilter = "";
$dateFilter_pi = "";
$dateFilter_session = "";
$dateFilterSales = "";

switch ($period) {
    case 'today':
        $dateFilter = "AND DATE(created_at) = DATE('now')";
        $dateFilter_pi = "AND DATE(pi.created_at) = DATE('now')";
        $dateFilter_session = "AND DATE(first_visit) = DATE('now')";
        $dateFilterSales = "AND DATE(s.created_at) = DATE('now')";
        break;
    case '7days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-7 days')";
        $dateFilter_pi = "AND DATE(pi.created_at) >= DATE('now', '-7 days')";
        $dateFilter_session = "AND DATE(first_visit) >= DATE('now', '-7 days')";
        $dateFilterSales = "AND DATE(s.created_at) >= DATE('now', '-7 days')";
        break;
    case '30days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-30 days')";
        $dateFilter_pi = "AND DATE(pi.created_at) >= DATE('now', '-30 days')";
        $dateFilter_session = "AND DATE(first_visit) >= DATE('now', '-30 days')";
        $dateFilterSales = "AND DATE(s.created_at) >= DATE('now', '-30 days')";
        break;
    case '90days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-90 days')";
        $dateFilter_pi = "AND DATE(pi.created_at) >= DATE('now', '-90 days')";
        $dateFilter_session = "AND DATE(first_visit) >= DATE('now', '-90 days')";
        $dateFilterSales = "AND DATE(s.created_at) >= DATE('now', '-90 days')";
        break;
}

if (isset($_GET['export_csv'])) {
    $exportType = $_GET['export_csv'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="analytics_' . $exportType . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($exportType === 'visits') {
        fputcsv($output, ['Date', 'Page URL', 'Page Title', 'Referrer', 'IP Address', 'User Agent'], ',', '"');
        
        $query = "SELECT visit_date, page_url, page_title, referrer, ip_address, user_agent 
                  FROM page_visits 
                  WHERE 1=1 $dateFilter 
                  ORDER BY created_at DESC 
                  LIMIT 10000";
        $results = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $row['visit_date'] ?? '',
                $row['page_url'] ?? '',
                $row['page_title'] ?? '',
                $row['referrer'] ?? '',
                $row['ip_address'] ?? '',
                $row['user_agent'] ?? ''
            ], ',', '"');
        }
    } elseif ($exportType === 'templates') {
        fputcsv($output, ['Template Name', 'Total Views', 'Total Clicks', 'View/Click Ratio'], ',', '"');
        
        $query = "SELECT 
                    t.name,
                    COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END) as views,
                    COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) as clicks,
                    ROUND(CAST(COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) AS REAL) / 
                          NULLIF(COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END), 0) * 100, 2) as ratio
                  FROM page_interactions pi
                  JOIN templates t ON pi.template_id = t.id
                  WHERE 1=1 $dateFilter_pi
                  GROUP BY t.id, t.name
                  ORDER BY views DESC
                  LIMIT 100";
        $results = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $row['name'] ?? '',
                (string)($row['views'] ?? 0),
                (string)($row['clicks'] ?? 0),
                number_format($row['ratio'] ?? 0, 2, '.', '') . '%'
            ], ',', '"');
        }
    }
    
    fclose($output);
    exit;
}

$totalVisits = $db->query("SELECT COUNT(*) FROM page_visits WHERE 1=1 $dateFilter")->fetchColumn();

$uniqueVisitors = $db->query("SELECT COUNT(DISTINCT session_id) FROM page_visits WHERE 1=1 $dateFilter")->fetchColumn();

$bounceData = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(is_bounce) as bounce_sessions
    FROM session_summary
    WHERE 1=1 $dateFilter_session
")->fetch(PDO::FETCH_ASSOC);

$bounceRate = $bounceData['total_sessions'] > 0 
    ? round(($bounceData['bounce_sessions'] / $bounceData['total_sessions']) * 100, 1) 
    : 0;

$avgTimeData = $db->query("
    SELECT AVG(
        CAST((julianday(last_visit) - julianday(first_visit)) * 24 * 60 * 60 AS INTEGER)
    ) as avg_time
    FROM session_summary
    WHERE is_bounce = 0 $dateFilter_session
")->fetchColumn();

$avgTimeOnSite = $avgTimeData ? gmdate('i:s', round($avgTimeData)) : '00:00';

// Use standardized financial metrics
$revenueMetrics = getRevenueMetrics($db, $dateFilterSales);
$totalRevenue = $revenueMetrics['total_revenue'];
$totalSales = $revenueMetrics['total_sales'];

// Get revenue breakdown by order type
$orderTypeBreakdown = getRevenueByOrderType($db, $dateFilterSales);
$mixedRevenue = $orderTypeBreakdown['mixed']['revenue'];
$mixedOrders = $orderTypeBreakdown['mixed']['orders'];

// Get actual tool and template sales (includes items in mixed orders)
$toolMetrics = getToolSalesMetrics($db, $dateFilterSales);
$toolRevenue = $toolMetrics['revenue'];
$toolOrders = $toolMetrics['orders'];

$templateMetrics = getTemplateSalesMetrics($db, $dateFilterSales);
$templateRevenue = $templateMetrics['revenue'];
$templateOrders = $templateMetrics['orders'];

$visitsOverTime = $db->query("
    SELECT 
        visit_date,
        COUNT(*) as visits,
        COUNT(DISTINCT session_id) as unique_visitors
    FROM page_visits
    WHERE 1=1 $dateFilter
    GROUP BY visit_date
    ORDER BY visit_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$topTemplateViews = $db->query("
    SELECT 
        t.id,
        t.name,
        t.price,
        COUNT(pi.id) as view_count
    FROM page_interactions pi
    JOIN templates t ON pi.template_id = t.id
    WHERE pi.action_type = 'view' $dateFilter_pi
    GROUP BY t.id, t.name, t.price
    ORDER BY view_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$topToolViews = $db->query("
    SELECT 
        t.id,
        t.name,
        t.price,
        COUNT(pi.id) as view_count
    FROM page_interactions pi
    JOIN tools t ON pi.tool_id = t.id
    WHERE pi.action_type = 'view' $dateFilter_pi
    GROUP BY t.id, t.name, t.price
    ORDER BY view_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$topPages = $db->query("
    SELECT 
        page_url,
        page_title,
        COUNT(*) as visit_count
    FROM page_visits
    WHERE 1=1 $dateFilter
    GROUP BY page_url, page_title
    ORDER BY visit_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$ipFilter = trim($_GET['ip'] ?? '');
$ipFilterQuery = '';
if (!empty($ipFilter)) {
    $ipFilterQuery = " AND ip_address LIKE " . $db->quote('%' . $ipFilter . '%');
}

// Pagination for Recent Visits (via AJAX)
$totalRecentVisitsCount = $db->query("SELECT COUNT(*) FROM page_visits WHERE 1=1 $dateFilter $ipFilterQuery")->fetchColumn();
$visitsPerPage = 20;
$totalVisitsPages = ceil($totalRecentVisitsCount / $visitsPerPage);
$recentVisits = []; // Will be loaded via AJAX

$trafficSources = $db->query("
    SELECT 
        CASE 
            WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
            WHEN referrer LIKE '%google%' THEN 'Google'
            WHEN referrer LIKE '%bing%' OR referrer LIKE '%yahoo%' THEN 'Search Engines'
            WHEN referrer LIKE '%facebook%' OR referrer LIKE '%twitter%' OR referrer LIKE '%instagram%' OR referrer LIKE '%linkedin%' THEN 'Social Media'
            WHEN referrer LIKE '%?aff=%' OR referrer LIKE '%&aff=%' THEN 'Affiliate Links'
            ELSE 'Other Referrals'
        END as source_type,
        COUNT(*) as visit_count,
        COUNT(DISTINCT session_id) as unique_visitors
    FROM page_visits
    WHERE 1=1 $dateFilter
    GROUP BY source_type
    ORDER BY visit_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalSourceVisits = array_sum(array_column($trafficSources, 'visit_count'));

$dateFilterDelivery = "";
switch ($period) {
    case 'today':
        $dateFilterDelivery = "AND DATE(d.created_at) = DATE('now')";
        break;
    case '7days':
        $dateFilterDelivery = "AND DATE(d.created_at) >= DATE('now', '-7 days')";
        break;
    case '30days':
        $dateFilterDelivery = "AND DATE(d.created_at) >= DATE('now', '-30 days')";
        break;
    case '90days':
        $dateFilterDelivery = "AND DATE(d.created_at) >= DATE('now', '-90 days')";
        break;
}

$deliveryStats = [
    'total_deliveries' => 0,
    'delivered' => 0,
    'pending' => 0,
    'failed' => 0,
    'tool_deliveries' => 0,
    'template_deliveries' => 0,
    'avg_delivery_hours' => null
];

try {
    $result = $db->query("
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(CASE WHEN d.delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN d.delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN d.delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN d.product_type = 'tool' THEN 1 ELSE 0 END) as tool_deliveries,
            SUM(CASE WHEN d.product_type = 'template' THEN 1 ELSE 0 END) as template_deliveries,
            AVG(CASE WHEN d.delivered_at IS NOT NULL THEN 
                (julianday(d.delivered_at) - julianday(d.created_at)) * 24 
            END) as avg_delivery_hours
        FROM deliveries d
        WHERE 1=1 {$dateFilterDelivery}
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $deliveryStats = array_merge($deliveryStats, $result);
    }
} catch (Exception $e) {
    error_log('Analytics delivery stats query error: ' . $e->getMessage());
}

$overdueDeliveries = function_exists('getOverdueTemplateDeliveries') ? getOverdueTemplateDeliveries(24) : [];
$partialDeliveryData = function_exists('getOrdersWithPartialDelivery') ? getOrdersWithPartialDelivery() : ['fully_delivered' => [], 'partially_delivered' => [], 'not_started' => []];

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <i class="bi bi-graph-up text-primary-600"></i> Analytics Dashboard
        </h1>
        <p class="text-gray-600 mt-2">Track visitor behavior and template performance</p>
    </div>
    
    <div class="flex items-center gap-3">
        <select onchange="window.location.href='?period='+this.value" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
            <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Total Revenue</h6>
            <i class="bi bi-currency-dollar text-2xl text-green-600"></i>
        </div>
        <div class="text-3xl font-bold text-green-600"><?php echo formatCurrency($totalRevenue); ?></div>
        <small class="text-sm text-gray-500"><?php echo number_format($totalSales); ?> sales</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Template Sales</h6>
            <i class="bi bi-palette text-2xl text-blue-600"></i>
        </div>
        <div class="text-3xl font-bold text-blue-600"><?php echo formatCurrency($templateRevenue); ?></div>
        <small class="text-sm text-gray-500"><?php echo number_format($templateOrders); ?> orders</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Tool Sales</h6>
            <i class="bi bi-tools text-2xl text-purple-600"></i>
        </div>
        <div class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($toolRevenue); ?></div>
        <small class="text-sm text-gray-500"><?php echo number_format($toolOrders); ?> orders</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Mixed Orders</h6>
            <i class="bi bi-cart text-2xl text-orange-600"></i>
        </div>
        <div class="text-3xl font-bold text-orange-600"><?php echo formatCurrency($mixedRevenue); ?></div>
        <small class="text-sm text-gray-500"><?php echo number_format($mixedOrders); ?> orders</small>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Total Visits</h6>
            <i class="bi bi-eye text-2xl text-blue-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($totalVisits); ?></div>
        <small class="text-sm text-gray-500">Page views</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Unique Visitors</h6>
            <i class="bi bi-people text-2xl text-green-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($uniqueVisitors); ?></div>
        <small class="text-sm text-gray-500">Unique sessions</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Bounce Rate</h6>
            <i class="bi bi-arrow-return-left text-2xl text-orange-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $bounceRate; ?>%</div>
        <small class="text-sm text-gray-500">Single page visits</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Avg Time on Site</h6>
            <i class="bi bi-clock text-2xl text-purple-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $avgTimeOnSite; ?></div>
        <small class="text-sm text-gray-500">Minutes:Seconds</small>
    </div>
</div>

<?php if (!empty($overdueDeliveries)): ?>
<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
    <div class="flex items-center gap-3">
        <i class="bi bi-exclamation-triangle text-xl"></i>
        <div>
            <strong><?php echo count($overdueDeliveries); ?> Overdue Deliveries</strong>
            <span class="text-sm ml-2">Templates pending for 24+ hours</span>
        </div>
        <a href="/admin/deliveries.php?type=template&status=pending" class="ml-auto px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors">
            View Now <i class="bi bi-arrow-right ml-1"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-truck text-primary-600"></i> Delivery Statistics
        </h5>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
            <div class="bg-gray-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($deliveryStats['total_deliveries'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Total</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-600"><?php echo number_format($deliveryStats['delivered'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Delivered</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($deliveryStats['pending'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Pending</div>
            </div>
            <div class="bg-red-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-red-600"><?php echo number_format($deliveryStats['failed'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Failed</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-purple-600"><?php echo number_format($deliveryStats['tool_deliveries'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Tools</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo number_format($deliveryStats['template_deliveries'] ?? 0); ?></div>
                <div class="text-xs text-gray-500">Templates</div>
            </div>
            <div class="bg-indigo-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600"><?php echo $deliveryStats['avg_delivery_hours'] !== null ? round($deliveryStats['avg_delivery_hours'], 1) . 'h' : 'N/A'; ?></div>
                <div class="text-xs text-gray-500">Avg Time</div>
            </div>
        </div>
        
        <?php if (!empty($partialDeliveryData)): ?>
        <div class="mt-6 pt-4 border-t border-gray-100">
            <h6 class="font-semibold text-gray-900 mb-3">Partial Delivery Overview</h6>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-green-600"><?php echo count($partialDeliveryData['fully_delivered'] ?? []); ?></div>
                    <div class="text-xs text-gray-500">Fully Delivered</div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-yellow-600"><?php echo count($partialDeliveryData['partially_delivered'] ?? []); ?></div>
                    <div class="text-xs text-gray-500">Partial</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold text-gray-600"><?php echo count($partialDeliveryData['not_started'] ?? []); ?></div>
                    <div class="text-xs text-gray-500">Not Started</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-bar-chart-line text-primary-600"></i> Visits Over Time
        </h5>
    </div>
    <div class="p-3 sm:p-6" style="position: relative; height: 350px; min-height: 300px; max-height: 500px;">
        <canvas id="visitsChart"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-eye-fill text-primary-600"></i> Top 10 Most Viewed Templates
            </h5>
            <a href="?period=<?php echo $period; ?>&export_csv=templates" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
        <div class="p-6">
            <?php if (empty($topTemplateViews)): ?>
                <p class="text-gray-500 text-center py-8">No template views recorded yet</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($topTemplateViews as $index => $template): ?>
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 bg-primary-600 text-white rounded-full flex items-center justify-center font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <h6 class="font-semibold text-gray-900"><?php echo htmlspecialchars($template['name']); ?></h6>
                                <small class="text-gray-600">₦<?php echo number_format($template['price']); ?></small>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-primary-600"><?php echo number_format($template['view_count']); ?></div>
                                <small class="text-gray-500">views</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-eye-fill text-primary-600"></i> Top 10 Most Viewed Tools
            </h5>
        </div>
        <div class="p-6">
            <?php if (empty($topToolViews)): ?>
                <p class="text-gray-500 text-center py-8">No tool views recorded yet</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($topToolViews as $index => $tool): ?>
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <h6 class="font-semibold text-gray-900"><?php echo htmlspecialchars($tool['name']); ?></h6>
                                <small class="text-gray-600">₦<?php echo number_format($tool['price']); ?></small>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-purple-600"><?php echo number_format($tool['view_count']); ?></div>
                                <small class="text-gray-500">views</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-file-bar-graph text-primary-600"></i> Top Pages
        </h5>
    </div>
    <div class="p-6 overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Rank</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Page URL</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Page Title</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 text-sm">Visits</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topPages as $index => $page): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4 text-gray-600">#<?php echo $index + 1; ?></td>
                        <td class="py-3 px-4 font-mono text-sm text-blue-600"><?php echo htmlspecialchars($page['page_url']); ?></td>
                        <td class="py-3 px-4 text-gray-900"><?php echo htmlspecialchars($page['page_title'] ?: 'Untitled'); ?></td>
                        <td class="py-3 px-4 text-right font-bold text-gray-900"><?php echo number_format($page['visit_count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-globe text-primary-600"></i> Traffic Sources
            </h5>
        </div>
        <div class="p-6">
            <?php if (empty($trafficSources)): ?>
                <div class="text-center py-8">
                    <i class="bi bi-globe text-gray-300 text-5xl mb-3"></i>
                    <p class="text-gray-500">No traffic data yet</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($trafficSources as $source): ?>
                        <?php 
                        $percentage = $totalSourceVisits > 0 ? round(($source['visit_count'] / $totalSourceVisits) * 100, 1) : 0;
                        $iconClass = match($source['source_type']) {
                            'Direct' => 'bi-link-45deg text-gray-600',
                            'Google' => 'bi-google text-blue-600',
                            'Search Engines' => 'bi-search text-purple-600',
                            'Social Media' => 'bi-share text-pink-600',
                            'Affiliate Links' => 'bi-people text-green-600',
                            default => 'bi-globe text-gray-600'
                        };
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <i class="bi <?php echo $iconClass; ?> text-2xl"></i>
                                    <div>
                                        <h6 class="font-semibold text-gray-900"><?php echo htmlspecialchars($source['source_type']); ?></h6>
                                        <small class="text-gray-500"><?php echo number_format($source['unique_visitors']); ?> unique visitors</small>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xl font-bold text-gray-900"><?php echo number_format($source['visit_count']); ?></div>
                                    <small class="text-gray-500"><?php echo $percentage; ?>%</small>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-clock-history text-primary-600"></i> Recent Visits
        </h5>
        <div class="flex items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <input type="text" name="ip" value="<?php echo htmlspecialchars($ipFilter); ?>" placeholder="Filter by IP..." class="px-3 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-primary-500">
                <button type="submit" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded text-sm">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <?php if ($ipFilter): ?>
                <a href="?period=<?php echo $period; ?>" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm">
                    <i class="bi bi-x"></i> Clear
                </a>
                <?php endif; ?>
            </form>
            <a href="?period=<?php echo $period; ?><?php echo $ipFilter ? '&ip=' . urlencode($ipFilter) : ''; ?>&export_csv=visits" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>
    <div class="p-6 overflow-x-auto">
        <table class="w-full text-sm" id="recentVisitsTable">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Date & Time</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Page</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Device</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Referrer</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">IP Address</th>
                </tr>
            </thead>
            <tbody id="visitsTableBody">
                <tr><td colspan="5" class="text-center py-4 text-gray-500">Loading visits...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-3 border-t border-gray-200 flex items-center justify-between">
        <div class="text-sm text-gray-600" id="visitsInfo">
            Showing 0 of 0 visits
        </div>
        <div class="flex items-center gap-2" id="paginationControls">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('visitsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($visitsOverTime, 'visit_date')); ?>,
        datasets: [
            {
                label: 'Total Visits',
                data: <?php echo json_encode(array_column($visitsOverTime, 'visits')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Unique Visitors',
                data: <?php echo json_encode(array_column($visitsOverTime, 'unique_visitors')); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
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
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                padding: 12
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

<!-- Advanced Analytics from analytics-report API -->
<div class="mt-8">
    <!-- Device Breakdown & Search Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Device Breakdown -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-phone text-purple-500"></i> Device Breakdown
                </h5>
            </div>
            <div id="device-breakdown-container" class="p-6">
                <p class="text-center text-gray-500"><i class="bi bi-hourglass-split animate-spin"></i> Loading...</p>
            </div>
        </div>

        <!-- Search Analytics -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-search text-green-500"></i> Search Analytics
                </h5>
            </div>
            <div id="search-analytics-container" class="p-6">
                <p class="text-center text-gray-500"><i class="bi bi-hourglass-split animate-spin"></i> Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Load analytics-report API data asynchronously
async function loadAdvancedAnalytics() {
    try {
        // Load device breakdown
        const deviceRes = await fetch('/api/analytics-report.php?action=device_breakdown');
        const deviceData = await deviceRes.json();
        if (deviceData.success && deviceData.device_breakdown && deviceData.device_breakdown.length > 0) {
            let html = '<div class="space-y-3">';
            let totalVisits = 0;
            deviceData.device_breakdown.forEach(d => totalVisits += (d.visits || 0));
            deviceData.device_breakdown.forEach((device) => {
                const pct = device.percentage || 0;
                html += `<div><div class="flex justify-between mb-1"><span class="text-sm font-medium text-gray-700">${escapeHtml(device.device_type)}</span><span class="text-sm text-gray-600">${device.visits} (${pct}%)</span></div><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-blue-600 h-2 rounded-full" style="width: ${pct}%"></div></div></div>`;
            });
            html += '</div>';
            document.getElementById('device-breakdown-container').innerHTML = html;
        } else {
            document.getElementById('device-breakdown-container').innerHTML = '<p class="text-gray-500 text-center">No device data available</p>';
        }

        // Load search analytics
        const searchRes = await fetch('/api/analytics-report.php?action=search_analytics&limit=10');
        const searchData = await searchRes.json();
        if (searchData.success && searchData.searches && searchData.searches.length > 0) {
            let html = '<div class="space-y-2 max-h-80 overflow-y-auto">';
            searchData.searches.forEach((s, idx) => {
                html += `<div class="flex justify-between p-2 bg-gray-50 rounded"><span class="text-sm text-gray-700">${idx + 1}. ${escapeHtml(s.search_term || '')}</span><span class="text-xs text-gray-500">${s.searches} searches</span></div>`;
            });
            html += '</div>';
            document.getElementById('search-analytics-container').innerHTML = html;
        } else {
            document.getElementById('search-analytics-container').innerHTML = '<p class="text-gray-500 text-center">No search data available</p>';
        }
    } catch (error) {
        console.log('Advanced analytics loaded (some may not be available)');
    }
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Load when page is ready
document.addEventListener('DOMContentLoaded', () => {
    loadAdvancedAnalytics();
    loadRecentVisitsPage(1);
});

// Load recent visits with AJAX pagination
async function loadRecentVisitsPage(pageNum) {
    const urlParams = new URLSearchParams(window.location.search);
    const period = urlParams.get('period') || '7days';
    const ipFilter = urlParams.get('ip') || '';
    
    try {
        let url = `/api/recent-visits.php?page=${pageNum}&period=${encodeURIComponent(period)}`;
        if (ipFilter) {
            url += `&ip=${encodeURIComponent(ipFilter)}`;
        }
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            document.getElementById('visitsTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Error loading visits</td></tr>';
            return;
        }
        
        // Build table rows
        let html = '';
        if (data.visits.length === 0) {
            html = '<tr><td colspan="5" class="text-center py-4 text-gray-500">No visits recorded</td></tr>';
        } else {
            data.visits.forEach(visit => {
                html += `
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-2 px-3 text-gray-600 whitespace-nowrap">${visit.visit_date_time}</td>
                        <td class="py-2 px-3">
                            <div class="font-mono text-blue-600 text-xs">${visit.page_url}</div>
                            <div class="text-gray-600 text-xs">${visit.page_title}</div>
                        </td>
                        <td class="py-2 px-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-50 rounded text-xs font-medium ${visit.device_color}">
                                <i class="bi bi-${visit.device_icon}"></i>
                                ${visit.device_type}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-gray-600 text-xs max-w-xs truncate">${visit.referrer}</td>
                        <td class="py-2 px-3 font-mono text-gray-700 text-xs">${visit.ip_address}</td>
                    </tr>
                `;
            });
        }
        
        document.getElementById('visitsTableBody').innerHTML = html;
        
        // Scroll to Recent Visits table
        const visitsTable = document.getElementById('recentVisitsTable');
        if (visitsTable) {
            visitsTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Update pagination info
        const offset = (pageNum - 1) * data.per_page;
        const showing = data.total_count > 0 ? offset + 1 : 0;
        const to = Math.min(offset + data.per_page, data.total_count);
        document.getElementById('visitsInfo').textContent = `Showing ${showing} to ${to} of ${data.total_count.toLocaleString()} visits`;
        
        // Build pagination controls
        let paginationHtml = '';
        if (data.page > 1) {
            paginationHtml += `<button onclick="loadRecentVisitsPage(${data.page - 1})" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm"><i class="bi bi-chevron-left"></i> Previous</button>`;
        }
        
        paginationHtml += `<span class="text-sm text-gray-600">Page ${data.page} of ${data.total_pages}</span>`;
        
        if (data.page < data.total_pages) {
            paginationHtml += `<button onclick="loadRecentVisitsPage(${data.page + 1})" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm">Next <i class="bi bi-chevron-right"></i></button>`;
        }
        
        document.getElementById('paginationControls').innerHTML = paginationHtml;
    } catch (error) {
        console.error('Error loading visits:', error);
        document.getElementById('visitsTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Failed to load visits</td></tr>';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>