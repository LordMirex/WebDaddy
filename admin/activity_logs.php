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

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Build query
$where = [];
$params = [];

if ($actionFilter) {
    $where[] = "action = ?";
    $params[] = $actionFilter;
}

if ($userFilter) {
    $where[] = "user_id = ?";
    $params[] = $userFilter;
}

if ($dateFilter) {
    $where[] = "DATE(created_at) = ?";
    $params[] = $dateFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM activity_logs $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalLogs = $stmt->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Get logs
$query = "
    SELECT 
        al.*,
        u.name as user_name,
        u.email as user_email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types for filter
$actionTypes = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get users who have activity
$users = $db->query("
    SELECT DISTINCT u.id, u.name, u.email 
    FROM users u 
    JOIN activity_logs al ON u.id = al.user_id 
    ORDER BY u.name
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-clock-history text-primary-600"></i> Activity Logs
    </h1>
    <p class="text-gray-600 mt-2">System activity and audit trail</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="p-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Action Type</label>
                <select name="action" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $actionFilter === $type ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">User</label>
                <select name="user" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                <input type="date" name="date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/activity_logs.php" class="px-4 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Total Logs</h6>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($totalLogs); ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Today's Activities</h6>
        <?php
        $todayCount = $db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(datetime(created_at)) = DATE('now')")->fetchColumn();
        ?>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($todayCount); ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Unique Users</h6>
        <?php
        $uniqueUsers = $db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL")->fetchColumn();
        ?>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($uniqueUsers); ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Action Types</h6>
        <div class="text-3xl font-bold text-gray-900"><?php echo count($actionTypes); ?></div>
    </div>
</div>

<!-- Logs Table -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-list-ul text-primary-600"></i> Activity Logs (<?php echo number_format($totalLogs); ?> total)
        </h5>
    </div>
    <div>
        <?php if (empty($logs)): ?>
        <div class="p-12 text-center text-gray-400">
            <i class="bi bi-inbox text-8xl opacity-30"></i>
            <p class="mt-4 text-gray-500">No activity logs found</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm w-16">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Timestamp</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Action Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">User</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Details</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4"><small class="text-gray-500">#<?php echo $log['id']; ?></small></td>
                        <td class="py-3 px-4">
                            <small class="block text-gray-900">
                                <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                            </small>
                            <small class="text-gray-500"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></small>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $badgeClass = 'bg-gray-100 text-gray-800';
                            if (strpos($log['action'], 'login') !== false) $badgeClass = 'bg-green-100 text-green-800';
                            if (strpos($log['action'], 'logout') !== false) $badgeClass = 'bg-yellow-100 text-yellow-800';
                            if (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'reject') !== false) $badgeClass = 'bg-red-100 text-red-800';
                            if (strpos($log['action'], 'create') !== false || strpos($log['action'], 'register') !== false) $badgeClass = 'bg-blue-100 text-blue-800';
                            if (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) $badgeClass = 'bg-cyan-100 text-cyan-800';
                            ?>
                            <span class="px-2 py-1 <?php echo $badgeClass; ?> rounded-full text-xs font-semibold">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($log['user_name']): ?>
                            <strong class="block text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></strong>
                            <small class="text-gray-500"><?php echo htmlspecialchars($log['user_email']); ?></small>
                            <?php else: ?>
                            <span class="text-gray-500 italic">System</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <small class="text-gray-700"><?php echo htmlspecialchars($log['details'] ?: '-'); ?></small>
                        </td>
                        <td class="py-3 px-4">
                            <small class="text-gray-500 font-mono text-xs">
                                <?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <nav>
            <ul class="flex items-center justify-center gap-2">
                <?php if ($page > 1): ?>
                <li>
                    <a class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium" href="?page=<?php echo $page - 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li>
                    <a class="px-4 py-2 border rounded-lg font-medium transition-colors <?php echo $i === $page ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>" href="?page=<?php echo $i; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li>
                    <a class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium" href="?page=<?php echo $page + 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="text-center text-gray-500 mt-3">
            <small>Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo number_format($totalLogs); ?> logs)</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
