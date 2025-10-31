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

<div class="page-header">
    <h1><i class="bi bi-clock-history"></i> Activity Logs</h1>
    <p class="text-muted">System activity and audit trail</p>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Action Type</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $actionFilter === $type ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">User</label>
                <select name="user" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="/admin/activity_logs.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-2">Total Logs</h6>
                <div class="h3 mb-0"><?php echo number_format($totalLogs); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-2">Today's Activities</h6>
                <?php
                $todayCount = $db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURRENT_DATE")->fetchColumn();
                ?>
                <div class="h3 mb-0"><?php echo number_format($todayCount); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-2">Unique Users</h6>
                <?php
                $uniqueUsers = $db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL")->fetchColumn();
                ?>
                <div class="h3 mb-0"><?php echo number_format($uniqueUsers); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-2">Action Types</h6>
                <div class="h3 mb-0"><?php echo count($actionTypes); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Activity Logs (<?php echo number_format($totalLogs); ?> total)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
            <p class="mt-2">No activity logs found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 180px;">Timestamp</th>
                        <th style="width: 150px;">Action Type</th>
                        <th style="width: 200px;">User</th>
                        <th>Details</th>
                        <th style="width: 120px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small class="text-muted">#<?php echo $log['id']; ?></small></td>
                        <td>
                            <small>
                                <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                <span class="text-muted"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></span>
                            </small>
                        </td>
                        <td>
                            <?php
                            $badgeClass = 'bg-secondary';
                            if (strpos($log['action'], 'login') !== false) $badgeClass = 'bg-success';
                            if (strpos($log['action'], 'logout') !== false) $badgeClass = 'bg-warning';
                            if (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'reject') !== false) $badgeClass = 'bg-danger';
                            if (strpos($log['action'], 'create') !== false || strpos($log['action'], 'register') !== false) $badgeClass = 'bg-primary';
                            if (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) $badgeClass = 'bg-info';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['user_name']): ?>
                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($log['user_email']); ?></small>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($log['details'] ?: '-'); ?></small>
                        </td>
                        <td>
                            <small class="text-muted font-monospace">
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
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user=' . urlencode($userFilter) : ''; ?><?php echo $dateFilter ? '&date=' . urlencode($dateFilter) : ''; ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="text-center text-muted mt-2">
            <small>Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo number_format($totalLogs); ?> logs)</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
