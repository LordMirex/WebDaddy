<?php
$pageTitle = 'Customer Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_status') {
            $customerId = intval($_POST['customer_id']);
            $status = sanitizeInput($_POST['status']);
            
            $validStatuses = ['active', 'inactive', 'suspended', 'unverified'];
            if (!in_array($status, $validStatuses)) {
                $errorMessage = 'Invalid status.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE customers SET status = ?, updated_at = datetime('now') WHERE id = ?");
                    $stmt->execute([$status, $customerId]);
                    
                    // Revoke all sessions if suspended
                    if ($status === 'suspended') {
                        $stmt = $db->prepare("
                            UPDATE customer_sessions 
                            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'account_suspended'
                            WHERE customer_id = ? AND is_active = 1
                        ");
                        $stmt->execute([$customerId]);
                    }
                    
                    $successMessage = 'Customer status updated successfully!';
                    logActivity('customer_status_updated', "Customer #$customerId status changed to $status", getAdminId());
                } catch (PDOException $e) {
                    error_log('Customer status update error: ' . $e->getMessage());
                    $errorMessage = 'Database error occurred. Please try again.';
                }
            }
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($statusFilter)) {
    $whereConditions[] = "c.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(c.email LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(*) FROM customers c $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalCustomers = $countStmt->fetchColumn();
$totalPages = ceil($totalCustomers / $perPage);

// Get customers with order stats
$sql = "
    SELECT 
        c.*,
        COUNT(DISTINCT po.id) as order_count,
        COALESCE(SUM(CASE WHEN po.status = 'paid' THEN po.final_amount ELSE 0 END), 0) as total_spent,
        MAX(po.created_at) as last_order_date
    FROM customers c
    LEFT JOIN pending_orders po ON po.customer_id = c.id
    $whereClause
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
        SUM(CASE WHEN status = 'unverified' THEN 1 ELSE 0 END) as unverified,
        SUM(CASE WHEN DATE(created_at) = DATE('now') THEN 1 ELSE 0 END) as today
    FROM customers
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-people text-blue-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></div>
                <div class="text-xs text-gray-500">Total</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-check-circle text-green-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['active']); ?></div>
                <div class="text-xs text-gray-500">Active</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-x-circle text-red-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['suspended']); ?></div>
                <div class="text-xs text-gray-500">Suspended</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-exclamation-circle text-yellow-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['unverified']); ?></div>
                <div class="text-xs text-gray-500">Unverified</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-plus-circle text-purple-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900">+<?php echo number_format($stats['today']); ?></div>
                <div class="text-xs text-gray-500">Today</div>
            </div>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
    <i class="bi bi-x-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                   placeholder="Search by email, name, or phone..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        </div>
        <div class="w-48">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="unverified" <?php echo $statusFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                <i class="bi bi-search mr-1"></i> Filter
            </button>
            <a href="/admin/customers.php" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                <i class="bi bi-x-circle mr-1"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Customer List -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-people text-primary-600"></i> Customers
            <span class="text-sm font-normal text-gray-500">(<?php echo number_format($totalCustomers); ?> total)</span>
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-gray-300 bg-gray-50">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Customer</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Phone</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Orders</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Total Spent</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Joined</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="7" class="text-center py-12">
                        <i class="bi bi-people text-6xl text-gray-300"></i>
                        <p class="text-gray-500 mt-4">No customers found</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                <span class="text-primary-600 font-bold text-sm">
                                    <?php echo strtoupper(substr($customer['full_name'] ?? $customer['email'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($customer['full_name'] ?? 'Not set'); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($customer['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-gray-700 text-sm">
                        <?php echo htmlspecialchars($customer['phone'] ?? '-'); ?>
                    </td>
                    <td class="py-3 px-4">
                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                            <?php echo number_format($customer['order_count']); ?> orders
                        </span>
                    </td>
                    <td class="py-3 px-4 font-semibold text-green-600">
                        <?php echo formatCurrency($customer['total_spent']); ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php
                        $statusColors = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'suspended' => 'bg-red-100 text-red-800',
                            'unverified' => 'bg-yellow-100 text-yellow-800',
                            'pending_setup' => 'bg-purple-100 text-purple-800'
                        ];
                        $statusColor = $statusColors[$customer['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="inline-flex items-center px-2 py-1 <?php echo $statusColor; ?> rounded-full text-xs font-semibold">
                            <?php echo ucfirst($customer['status']); ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-gray-700 text-sm">
                        <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <a href="/admin/customer-detail.php?id=<?php echo $customer['id']; ?>" 
                               class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition-colors">
                                <i class="bi bi-eye"></i> View
                            </a>
                            
                            <?php if ($customer['status'] !== 'suspended'): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to suspend this customer?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                <input type="hidden" name="status" value="suspended">
                                <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm transition-colors">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm transition-colors">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
        <div class="text-sm text-gray-500">
            Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalCustomers); ?> of <?php echo $totalCustomers; ?>
        </div>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                <i class="bi bi-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 <?php echo $i === $page ? 'bg-primary-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700'; ?> rounded-lg transition-colors">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                Next <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
