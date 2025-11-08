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

$totalSearches = $db->query("SELECT COUNT(*) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

$uniqueSearchTerms = $db->query("SELECT COUNT(DISTINCT action_target) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

$zeroResultSearches = $db->query("SELECT COUNT(*) FROM page_interactions WHERE action_type = 'search' AND time_spent = 0 $dateFilter")->fetchColumn();

$avgResultsPerSearch = $db->query("SELECT AVG(time_spent) FROM page_interactions WHERE action_type = 'search' $dateFilter")->fetchColumn();

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

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Search Analytics</h1>
            <p class="text-muted mb-0">See what users are searching for on your website</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?export_csv=1&period=<?php echo htmlspecialchars($period); ?>" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="mb-4">
        <div class="btn-group" role="group">
            <a href="?period=today" class="btn btn-<?php echo $period === 'today' ? 'primary' : 'outline-primary'; ?>">Today</a>
            <a href="?period=7days" class="btn btn-<?php echo $period === '7days' ? 'primary' : 'outline-primary'; ?>">7 Days</a>
            <a href="?period=30days" class="btn btn-<?php echo $period === '30days' ? 'primary' : 'outline-primary'; ?>">30 Days</a>
            <a href="?period=90days" class="btn btn-<?php echo $period === '90days' ? 'primary' : 'outline-primary'; ?>">90 Days</a>
            <a href="?period=all" class="btn btn-<?php echo $period === 'all' ? 'primary' : 'outline-primary'; ?>">All Time</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Searches</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalSearches); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-search fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Unique Search Terms</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($uniqueSearchTerms); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Zero Result Searches</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($zeroResultSearches); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Avg Results/Search</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgResultsPerSearch, 1); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Top Search Terms</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($topSearches)): ?>
                        <p class="text-center text-muted py-4">No search data available for this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Search Term</th>
                                        <th class="text-center">Times Searched</th>
                                        <th class="text-center">Avg Results</th>
                                        <th class="text-center">Zero Results</th>
                                        <th>Last Searched</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topSearches as $search): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($search['search_term']); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary badge-pill"><?php echo number_format($search['search_count']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($search['avg_results'], 1); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($search['zero_results'] > 0): ?>
                                                <span class="badge badge-warning"><?php echo $search['zero_results']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
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

        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-warning">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-exclamation-circle"></i> Searches with No Results
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">These searches returned 0 results. Consider adding templates for these topics!</p>
                    <?php if (empty($zeroResultSearchTerms)): ?>
                        <p class="text-center text-success py-3">
                            <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                            All searches are returning results!
                        </p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($zeroResultSearchTerms as $search): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($search['search_term']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('M j, g:ia', strtotime($search['last_searched'])); ?></small>
                                    </div>
                                    <span class="badge badge-warning badge-pill"><?php echo $search['search_count']; ?>x</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Searches (Last 100)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentSearches)): ?>
                        <p class="text-center text-muted py-4">No recent searches.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Search Term</th>
                                        <th class="text-center">Results Found</th>
                                        <th>Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSearches as $search): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($search['search_term']); ?></td>
                                        <td class="text-center">
                                            <?php if ($search['results_count'] == 0): ?>
                                                <span class="badge badge-warning">0</span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><?php echo $search['results_count']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small"><?php echo date('M j, Y g:i:s a', strtotime($search['created_at'])); ?></td>
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
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
