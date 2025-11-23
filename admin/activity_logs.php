<?php
$pageTitle = 'Activity Logs';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get filter parameters
$filterAction = $_GET['action'] ?? '';
$filterUser = $_GET['user'] ?? '';
$period = $_GET['period'] ?? '30days';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// Build date filter
$dateFilter = '';
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
    case 'all':
        $dateFilter = '';
        break;
}

// Build action filter
$actionFilter = '';
if ($filterAction) {
    $actionFilter = "AND action = '" . $db->quote($filterAction) . "'";
}

// Build user filter
$userFilter = '';
if ($filterUser) {
    $userFilter = "AND user_id = " . intval($filterUser);
}

// Get unique actions for filter dropdown
$actionsStmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique users for filter dropdown
$usersStmt = $db->query("SELECT DISTINCT u.id, u.username FROM activity_logs al 
                         JOIN users u ON al.user_id = u.id 
                         ORDER BY u.username");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$countQuery = "SELECT COUNT(*) FROM activity_logs WHERE 1=1 $dateFilter $actionFilter $userFilter";
$totalCount = $db->query($countQuery)->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Get logs with pagination
$offset = ($page - 1) * $perPage;
$logsQuery = "
    SELECT 
        al.id,
        al.user_id,
        al.action,
        al.details,
        al.ip_address,
        al.user_agent,
        al.created_at,
        u.username,
        u.email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1 $dateFilter $actionFilter $userFilter
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$logs = $db->query($logsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Export CSV if requested
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Date', 'Time', 'User', 'Action', 'Details', 'IP Address', 'User Agent']);
    
    $exportQuery = "
        SELECT 
            DATE(al.created_at) as date,
            TIME(al.created_at) as time,
            COALESCE(u.username, 'System') as username,
            al.action,
            al.details,
            al.ip_address,
            al.user_agent
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1 $dateFilter $actionFilter $userFilter
        ORDER BY al.created_at DESC
    ";
    $exportLogs = $db->query($exportQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($exportLogs as $log) {
        fputcsv($output, [
            $log['date'],
            $log['time'],
            $log['username'],
            $log['action'],
            $log['details'],
            $log['ip_address'],
            substr($log['user_agent'], 0, 50)
        ]);
    }
    fclose($output);
    exit;
}

// Handle clear old logs
if ($_POST['action'] ?? '' === 'clear_old_logs' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $db->prepare("DELETE FROM activity_logs WHERE created_at < datetime('now', '-90 days')")->execute();
        $successMsg = "Old activity logs (older than 90 days) have been deleted.";
        // Refresh page to show updated data
        header("Location: /admin/activity_logs.php?" . http_build_query([
            'action' => $filterAction,
            'user' => $filterUser,
            'period' => $period
        ]));
        exit;
    } catch (Exception $e) {
        $errorMsg = "Error clearing logs: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - WebDaddy Admin</title>
    <link rel="icon" href="/assets/images/favicon.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-gray-50">
    <?php require_once 'includes/header.php'; ?>
    
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Activity Logs</h1>
                <p class="text-gray-600 mt-2">Monitor all user activities and system events</p>
            </div>

            <!-- Messages -->
            <?php if (isset($successMsg)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 flex items-center gap-3">
                <i class="bi bi-check-circle-fill text-lg"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
            <?php endif; ?>
            <?php if (isset($errorMsg)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 flex items-center gap-3">
                <i class="bi bi-exclamation-circle-fill text-lg"></i>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
            <?php endif; ?>

            <!-- Filters & Controls -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <!-- Period Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                        <select name="period" onchange="updateFilters('period', this.value)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                        </select>
                    </div>

                    <!-- Action Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action Type</label>
                        <select name="action" onchange="updateFilters('action', this.value)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                            <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $filterAction === $act ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- User Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                        <select name="user" onchange="updateFilters('user', this.value)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Export Button -->
                    <div class="flex items-end">
                        <form method="GET" class="w-full">
                            <input type="hidden" name="action" value="<?php echo htmlspecialchars($filterAction); ?>">
                            <input type="hidden" name="user" value="<?php echo htmlspecialchars($filterUser); ?>">
                            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                            <input type="hidden" name="export_csv" value="1">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Clear Old Logs -->
                <div class="pt-4 border-t border-gray-200">
                    <form method="POST" onsubmit="return confirm('Delete activity logs older than 90 days? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="clear_old_logs">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg border border-red-300 text-sm font-medium transition-colors">
                            <i class="bi bi-trash"></i> Clear Logs Older than 90 Days
                        </button>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Date & Time</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">User</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Action</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Details</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <i class="bi bi-inbox text-2xl opacity-50 block mb-2"></i>
                                    No activity logs found
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xs font-bold">
                                                <?php echo strtoupper(substr($log['username'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(substr($log['details'] ?? '', 0, 50)); ?>
                                        <?php if (strlen($log['details'] ?? '') > 50): ?>...<?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-xs">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                        (<?php echo $totalCount; ?> total logs)
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge(['page' => 1], ['action' => $filterAction, 'user' => $filterUser, 'period' => $period])); ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">First</a>
                        <a href="?<?php echo http_build_query(array_merge(['page' => $page - 1], ['action' => $filterAction, 'user' => $filterUser, 'period' => $period])); ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge(['page' => $i], ['action' => $filterAction, 'user' => $filterUser, 'period' => $period])); ?>" 
                           class="px-3 py-1 rounded text-sm <?php echo $i === $page ? 'bg-primary-600 text-white' : 'border border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge(['page' => $page + 1], ['action' => $filterAction, 'user' => $filterUser, 'period' => $period])); ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Next</a>
                        <a href="?<?php echo http_build_query(array_merge(['page' => $totalPages], ['action' => $filterAction, 'user' => $filterUser, 'period' => $period])); ?>" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Last</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Logs (Period)</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalCount; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                            <i class="bi bi-file-earmark-text text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Unique Users</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                <?php 
                                $userCountQuery = "SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE 1=1 $dateFilter $actionFilter $userFilter";
                                echo $db->query($userCountQuery)->fetchColumn();
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
                            <i class="bi bi-people-fill text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Action Types</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                <?php 
                                $actionCountQuery = "SELECT COUNT(DISTINCT action) FROM activity_logs WHERE 1=1 $dateFilter $actionFilter $userFilter";
                                echo $db->query($actionCountQuery)->fetchColumn();
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="bi bi-diagram-3-fill text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateFilters(param, value) {
            const params = new URLSearchParams(window.location.search);
            params.set(param, value);
            params.delete('page'); // Reset to first page when filtering
            window.location.href = '?' + params.toString();
        }
    </script>
</body>
</html>
