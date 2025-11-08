<?php
$pageTitle = 'Search Analytics';

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
        $dateFilter = "AND DATE(created_at) = DATE('now')";
        break;
    case '7days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-7 days')";
        break;
    case '30days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-30 days')";
        break;
    case '90days':
        $dateFilter = "AND DATE(created_at) >= DATE('now', '-90 days')";
        break;
    case 'all':
        $dateFilter = "";
        break;
}

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="search_analytics_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Search Term', 'Search Count', 'Total Results Found', 'Avg Results', 'Zero Results Count', 'Last Searched'], ',', '"');
    
    // NOTE: For search actions, time_spent column stores result count, not duration
    $query = "SELECT 
                action_target as search_term,
                COUNT(*) as search_count,
                SUM(time_spent) as total_results,
                AVG(time_spent) as avg_results,
                SUM(CASE WHEN time_spent = 0 THEN 1 ELSE 0 END) as zero_results,
                MAX(created_at) as last_searched
              FROM page_interactions 
              WHERE action_type = 'search' $dateFilter
              GROUP BY action_target
              ORDER BY search_count DESC
              LIMIT 1000";
    
    $results = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        fputcsv($output, [
            $row['search_term'] ?? '',
            (string)($row['search_count'] ?? 0),
            (string)($row['total_results'] ?? 0),
            number_format($row['avg_results'] ?? 0, 1, '.', ''),
            (string)($row['zero_results'] ?? 0),
            $row['last_searched'] ?? ''
        ], ',', '"');
    }
    
    fclose($output);
    exit;
}

// NOTE: For search action_type, time_spent stores result count (not duration)
$totalSearches = $db->query("SELECT COUNT(*) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

$uniqueSearchTerms = $db->query("SELECT COUNT(DISTINCT action_target) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

$zeroResultSearches = $db->query("SELECT COUNT(*) FROM page_interactions WHERE action_type = 'search' AND time_spent = 0 $dateFilter")->fetchColumn();

$avgResultsPerSearch = $db->query("SELECT AVG(time_spent) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

// NOTE: For search action_type, time_spent stores result count (not duration)
$topSearches = $db->query("
    SELECT 
        action_target as search_term,
        COUNT(*) as search_count,
        SUM(time_spent) as total_results,
        AVG(time_spent) as avg_results,
        SUM(CASE WHEN time_spent = 0 THEN 1 ELSE 0 END) as zero_results,
        MAX(created_at) as last_searched
    FROM page_interactions 
    WHERE action_type = 'search' $dateFilter
    GROUP BY action_target
    ORDER BY search_count DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$zeroResultSearchTerms = $db->query("
    SELECT 
        action_target as search_term,
        COUNT(*) as search_count,
        MAX(created_at) as last_searched
    FROM page_interactions 
    WHERE action_type = 'search' 
    AND time_spent = 0 
    $dateFilter
    GROUP BY action_target
    ORDER BY search_count DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$recentSearches = $db->query("
    SELECT 
        action_target as search_term,
        time_spent as results_count,
        created_at
    FROM page_interactions 
    WHERE action_type = 'search' $dateFilter
    ORDER BY created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-search text-primary-600"></i> Search Analytics
            </h1>
            <p class="text-gray-600 mt-1">See what users are searching for on your website</p>
        </div>
        <div>
            <a href="?export_csv=1&period=<?php echo htmlspecialchars($period); ?>" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors shadow">
                <i class="bi bi-file-earmark-arrow-down mr-2"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="mb-6">
        <div class="inline-flex rounded-lg shadow-sm overflow-hidden" role="group">
            <a href="?period=today" class="px-4 py-2 text-sm font-medium <?php echo $period === 'today' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300">Today</a>
            <a href="?period=7days" class="px-4 py-2 text-sm font-medium <?php echo $period === '7days' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border-t border-b border-gray-300">7 Days</a>
            <a href="?period=30days" class="px-4 py-2 text-sm font-medium <?php echo $period === '30days' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border-t border-b border-gray-300">30 Days</a>
            <a href="?period=90days" class="px-4 py-2 text-sm font-medium <?php echo $period === '90days' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border-t border-b border-gray-300">90 Days</a>
            <a href="?period=all" class="px-4 py-2 text-sm font-medium <?php echo $period === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300">All Time</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-primary-600">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-xs font-bold text-primary-600 uppercase tracking-wide mb-2">Total Searches</div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($totalSearches); ?></div>
                </div>
                <div class="flex-shrink-0">
                    <i class="bi bi-search text-4xl text-gray-300"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-600">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-xs font-bold text-blue-600 uppercase tracking-wide mb-2">Unique Search Terms</div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($uniqueSearchTerms); ?></div>
                </div>
                <div class="flex-shrink-0">
                    <i class="bi bi-list-ul text-4xl text-gray-300"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-600">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-xs font-bold text-yellow-600 uppercase tracking-wide mb-2">Zero Result Searches</div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($zeroResultSearches); ?></div>
                </div>
                <div class="flex-shrink-0">
                    <i class="bi bi-exclamation-triangle text-4xl text-gray-300"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="text-xs font-bold text-green-600 uppercase tracking-wide mb-2">Avg Results/Search</div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($avgResultsPerSearch, 1); ?></div>
                </div>
                <div class="flex-shrink-0">
                    <i class="bi bi-graph-up text-4xl text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">Top Search Terms</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($topSearches)): ?>
                        <p class="text-center text-gray-500 py-8">No search data available for this period.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Search Term</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Times Searched</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Avg Results</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Zero Results</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Last Searched</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($topSearches as $search): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($search['search_term']); ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800"><?php echo number_format($search['search_count']); ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700">
                                            <?php echo number_format($search['avg_results'], 1); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($search['zero_results'] > 0): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800"><?php echo $search['zero_results']; ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?php echo date('M j, Y g:ia', strtotime($search['last_searched'])); ?>
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

        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-yellow-500">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="bi bi-exclamation-circle"></i> Searches with No Results
                    </h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-4">These searches returned 0 results. Consider adding templates for these topics!</p>
                    <?php if (empty($zeroResultSearchTerms)): ?>
                        <div class="text-center text-green-600 py-6">
                            <i class="bi bi-check-circle text-5xl mb-3"></i>
                            <p class="font-medium">All searches are returning results!</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($zeroResultSearchTerms as $search): ?>
                            <div class="border-b border-gray-200 pb-3 last:border-0">
                                <div class="flex justify-between items-center">
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($search['search_term']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, g:ia', strtotime($search['last_searched'])); ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 ml-2"><?php echo $search['search_count']; ?>x</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Recent Searches (Last 100)</h3>
        </div>
        <div class="p-6">
            <?php if (empty($recentSearches)): ?>
                <p class="text-center text-gray-500 py-8">No recent searches.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Search Term</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Results Found</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentSearches as $search): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5 text-sm text-gray-900"><?php echo htmlspecialchars($search['search_term']); ?></td>
                                <td class="px-4 py-2.5 text-center">
                                    <?php if ($search['results_count'] == 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">0</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800"><?php echo $search['results_count']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2.5 text-sm text-gray-600"><?php echo date('M j, Y g:i:s a', strtotime($search['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
