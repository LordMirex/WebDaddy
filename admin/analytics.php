<?php
$pageTitle = 'Analytics Dashboard';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$period = $_GET['period'] ?? '30days';
$dateFilter = "";

switch ($period) {
    case 'today':
        $dateFilter = "AND DATE(pv.created_at) = DATE('now')";
        break;
    case '7days':
        $dateFilter = "AND DATE(pv.created_at) >= DATE('now', '-7 days')";
        break;
    case '30days':
        $dateFilter = "AND DATE(pv.created_at) >= DATE('now', '-30 days')";
        break;
    case '90days':
        $dateFilter = "AND DATE(pv.created_at) >= DATE('now', '-90 days')";
        break;
}

if (isset($_GET['export_csv'])) {
    $exportType = $_GET['export_csv'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analytics_' . $exportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($exportType === 'visits') {
        fputcsv($output, ['Date', 'Page URL', 'Page Title', 'Referrer', 'IP Address', 'User Agent']);
        
        $query = "SELECT visit_date, page_url, page_title, referrer, ip_address, user_agent 
                  FROM page_visits 
                  WHERE 1=1 $dateFilter 
                  ORDER BY created_at DESC 
                  LIMIT 10000";
        $results = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
    } elseif ($exportType === 'templates') {
        fputcsv($output, ['Template Name', 'Total Views', 'Total Clicks', 'View/Click Ratio']);
        
        $query = "SELECT 
                    t.name,
                    COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END) as views,
                    COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) as clicks,
                    ROUND(CAST(COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) AS REAL) / 
                          NULLIF(COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END), 0) * 100, 2) as ratio
                  FROM page_interactions pi
                  JOIN templates t ON pi.template_id = t.id
                  WHERE 1=1 $dateFilter
                  GROUP BY t.id, t.name
                  ORDER BY views DESC
                  LIMIT 100";
        $results = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            fputcsv($output, [$row['name'], $row['views'], $row['clicks'], $row['ratio'] . '%']);
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
    WHERE DATE(first_visit) >= DATE('now', '-30 days')
")->fetch(PDO::FETCH_ASSOC);

$bounceRate = $bounceData['total_sessions'] > 0 
    ? round(($bounceData['bounce_sessions'] / $bounceData['total_sessions']) * 100, 1) 
    : 0;

$avgTimeData = $db->query("
    SELECT AVG(
        CAST((julianday(last_visit) - julianday(first_visit)) * 24 * 60 * 60 AS INTEGER)
    ) as avg_time
    FROM session_summary
    WHERE DATE(first_visit) >= DATE('now', '-30 days')
    AND is_bounce = 0
")->fetchColumn();

$avgTimeOnSite = $avgTimeData ? gmdate('i:s', round($avgTimeData)) : '00:00';

$visitsOverTime = $db->query("
    SELECT 
        visit_date,
        COUNT(*) as visits,
        COUNT(DISTINCT session_id) as unique_visitors
    FROM page_visits
    WHERE DATE(created_at) >= DATE('now', '-30 days')
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
    WHERE pi.action_type = 'view' $dateFilter
    GROUP BY t.id, t.name, t.price
    ORDER BY view_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$topTemplateClicks = $db->query("
    SELECT 
        t.id,
        t.name,
        t.price,
        COUNT(pi.id) as click_count
    FROM page_interactions pi
    JOIN templates t ON pi.template_id = t.id
    WHERE pi.action_type = 'click' $dateFilter
    GROUP BY t.id, t.name, t.price
    ORDER BY click_count DESC
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

$recentVisits = $db->query("
    SELECT 
        page_url,
        page_title,
        visit_date,
        visit_time,
        referrer,
        ip_address
    FROM page_visits
    WHERE 1=1 $dateFilter
    ORDER BY created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

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

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-bar-chart-line text-primary-600"></i> Visits Over Time
        </h5>
    </div>
    <div class="p-6">
        <canvas id="visitsChart" height="80"></canvas>
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
                <i class="bi bi-hand-index-thumb text-primary-600"></i> Top 10 Most Clicked Templates
            </h5>
        </div>
        <div class="p-6">
            <?php if (empty($topTemplateClicks)): ?>
                <p class="text-gray-500 text-center py-8">No template clicks recorded yet</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($topTemplateClicks as $index => $template): ?>
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <h6 class="font-semibold text-gray-900"><?php echo htmlspecialchars($template['name']); ?></h6>
                                <small class="text-gray-600">₦<?php echo number_format($template['price']); ?></small>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-green-600"><?php echo number_format($template['click_count']); ?></div>
                                <small class="text-gray-500">clicks</small>
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

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-clock-history text-primary-600"></i> Recent Visits
        </h5>
        <a href="?period=<?php echo $period; ?>&export_csv=visits" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
    <div class="p-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Date & Time</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Page</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">Referrer</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentVisits as $visit): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-2 px-3 text-gray-600 whitespace-nowrap"><?php echo htmlspecialchars($visit['visit_date'] . ' ' . $visit['visit_time']); ?></td>
                        <td class="py-2 px-3">
                            <div class="font-mono text-blue-600 text-xs"><?php echo htmlspecialchars($visit['page_url']); ?></div>
                            <div class="text-gray-600 text-xs"><?php echo htmlspecialchars($visit['page_title'] ?: 'Untitled'); ?></div>
                        </td>
                        <td class="py-2 px-3 text-gray-600 text-xs max-w-xs truncate"><?php echo htmlspecialchars($visit['referrer'] ?: 'Direct'); ?></td>
                        <td class="py-2 px-3 font-mono text-gray-700 text-xs"><?php echo htmlspecialchars($visit['ip_address']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
