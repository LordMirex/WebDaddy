<?php
/**
 * Delivery Management Dashboard
 * Phase 4.3: Enhanced with template status dashboard, filters, and quick actions
 */
$pageTitle = 'Delivery Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/delivery.php';

startSecureSession();
requireAdmin();

$db = getDb();

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDays = $_GET['days'] ?? '';

$sql = "
    SELECT d.*, 
           po.customer_name, 
           po.customer_email,
           po.customer_phone,
           po.id as order_id,
           po.created_at as order_date,
           CASE WHEN d.product_type = 'template' THEN 
               (CASE 
                   WHEN d.hosted_domain IS NOT NULL AND d.template_admin_username IS NOT NULL AND d.template_admin_password IS NOT NULL AND d.credentials_sent_at IS NOT NULL THEN 'complete'
                   WHEN d.hosted_domain IS NOT NULL THEN 'in_progress'
                   ELSE 'pending'
               END)
           ELSE 'n/a' END as template_progress
    FROM deliveries d
    INNER JOIN pending_orders po ON d.pending_order_id = po.id
    WHERE 1=1
";
$params = [];

if (!empty($filterType) && in_array($filterType, ['template', 'tool'])) {
    $sql .= " AND d.product_type = ?";
    $params[] = $filterType;
}

if (!empty($filterStatus)) {
    $sql .= " AND d.delivery_status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterDays) && is_numeric($filterDays)) {
    $sql .= " AND d.created_at >= datetime('now', '-{$filterDays} days')";
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = 0;
$pendingRetryCount = 0;
$failedCount = 0;
$completedCount = 0;
$templatesPending = 0;
$toolsPending = 0;

foreach ($deliveries as $d) {
    if ($d['delivery_status'] === 'pending') {
        $pendingCount++;
        if ($d['product_type'] === 'template') $templatesPending++;
        if ($d['product_type'] === 'tool') $toolsPending++;
    }
    elseif ($d['delivery_status'] === 'pending_retry') $pendingRetryCount++;
    elseif ($d['delivery_status'] === 'failed') $failedCount++;
    elseif (in_array($d['delivery_status'], ['sent', 'delivered', 'ready'])) $completedCount++;
}

$overdueTemplates = [];
$twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
foreach ($deliveries as $d) {
    if ($d['product_type'] === 'template' && 
        $d['delivery_status'] === 'pending' && 
        $d['created_at'] < $twentyFourHoursAgo) {
        $overdueTemplates[] = $d;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-box-seam text-primary-400"></i> Delivery Management
    </h1>
    <p class="text-gray-600 mt-2">Monitor and manage product deliveries</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-8">
    <a href="?status=pending" class="bg-yellow-50 rounded-xl shadow-md p-5 sm:p-4 border border-yellow-200 hover:shadow-lg transition-all">
        <div class="flex items-center justify-between mb-3 sm:mb-2">
            <h6 class="text-xs font-semibold text-yellow-700 uppercase">Pending</h6>
            <i class="bi bi-hourglass-split text-lg text-yellow-600"></i>
        </div>
        <div class="text-3xl sm:text-2xl font-bold text-yellow-900"><?php echo $pendingCount; ?></div>
    </a>
    
    <a href="?status=pending_retry" class="bg-orange-50 rounded-xl shadow-md p-5 sm:p-4 border border-orange-200 hover:shadow-lg transition-all">
        <div class="flex items-center justify-between mb-3 sm:mb-2">
            <h6 class="text-xs font-semibold text-orange-700 uppercase">Retrying</h6>
            <i class="bi bi-arrow-repeat text-lg text-orange-600"></i>
        </div>
        <div class="text-3xl sm:text-2xl font-bold text-orange-900"><?php echo $pendingRetryCount; ?></div>
    </a>
    
    <a href="?status=failed" class="bg-red-50 rounded-xl shadow-md p-5 sm:p-4 border border-red-200 hover:shadow-lg transition-all">
        <div class="flex items-center justify-between mb-3 sm:mb-2">
            <h6 class="text-xs font-semibold text-red-700 uppercase">Failed</h6>
            <i class="bi bi-x-circle text-lg text-red-600"></i>
        </div>
        <div class="text-3xl sm:text-2xl font-bold text-red-900"><?php echo $failedCount; ?></div>
    </a>
    
    <a href="?status=delivered" class="bg-green-50 rounded-xl shadow-md p-5 sm:p-4 border border-green-200 hover:shadow-lg transition-all">
        <div class="flex items-center justify-between mb-3 sm:mb-2">
            <h6 class="text-xs font-semibold text-green-700 uppercase">Completed</h6>
            <i class="bi bi-check-circle text-lg text-green-600"></i>
        </div>
        <div class="text-3xl sm:text-2xl font-bold text-green-900"><?php echo $completedCount; ?></div>
    </a>
    
    <a href="?" class="bg-blue-50 rounded-xl shadow-md p-5 sm:p-4 border border-blue-200 hover:shadow-lg transition-all">
        <div class="flex items-center justify-between mb-3 sm:mb-2">
            <h6 class="text-xs font-semibold text-blue-700 uppercase">Total</h6>
            <i class="bi bi-box text-lg text-blue-600"></i>
        </div>
        <div class="text-3xl sm:text-2xl font-bold text-blue-900"><?php echo count($deliveries); ?></div>
    </a>
</div>

<?php if (!empty($overdueTemplates)): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 sm:p-6 mb-8">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
            <i class="bi bi-exclamation-triangle text-2xl text-red-600"></i>
        </div>
        <div>
            <h3 class="text-lg sm:text-xl font-bold text-red-900">Templates Requiring Attention</h3>
            <p class="text-red-700 text-sm"><?php echo count($overdueTemplates); ?> template(s) pending for over 24 hours</p>
        </div>
    </div>
    
    <div class="grid gap-3">
        <?php foreach ($overdueTemplates as $overdue): 
            $hoursOverdue = round((time() - strtotime($overdue['created_at'])) / 3600);
        ?>
        <div class="bg-white border border-red-200 rounded-lg p-4 flex items-center justify-between flex-wrap gap-3 hover:shadow-md transition-shadow">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-gray-900 font-semibold"><?php echo htmlspecialchars($overdue['product_name']); ?></span>
                    <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full font-semibold"><?php echo $hoursOverdue; ?>h overdue</span>
                </div>
                <div class="text-sm text-gray-600">
                    Order #<?php echo $overdue['order_id']; ?> - <?php echo htmlspecialchars($overdue['customer_name']); ?> 
                    <span class="text-gray-500 ml-1"><?php echo htmlspecialchars($overdue['customer_email']); ?></span>
                </div>
            </div>
            <a href="/admin/orders.php?view=<?php echo $overdue['order_id']; ?>" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg text-sm transition-colors">
                <i class="bi bi-arrow-right mr-1"></i> Process Now
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-200 mb-6">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-funnel text-primary-600"></i> Filters
        </h5>
    </div>
    <div class="p-4 sm:p-6">
        <form method="GET" class="flex flex-wrap gap-3 sm:gap-4 items-end">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Product Type</label>
                <select name="type" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">All Types</option>
                    <option value="template" <?php echo $filterType === 'template' ? 'selected' : ''; ?>>Templates</option>
                    <option value="tool" <?php echo $filterType === 'tool' ? 'selected' : ''; ?>>Tools</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="pending_retry" <?php echo $filterStatus === 'pending_retry' ? 'selected' : ''; ?>>Pending Retry</option>
                    <option value="delivered" <?php echo $filterStatus === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                <select name="days" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">All Time</option>
                    <option value="1" <?php echo $filterDays === '1' ? 'selected' : ''; ?>>Last 24 Hours</option>
                    <option value="7" <?php echo $filterDays === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30" <?php echo $filterDays === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-colors">
                    <i class="bi bi-search mr-1"></i> Filter
                </button>
                <a href="?" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-900 font-semibold rounded-lg transition-colors">
                    <i class="bi bi-x"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-palette text-purple-600"></i> Template Deliveries
            </h5>
            <span class="text-xs bg-purple-100 text-purple-700 px-3 py-1 rounded-full font-semibold"><?php echo $templatesPending; ?> pending</span>
        </div>
        <div class="p-4 max-h-80 overflow-y-auto">
            <?php 
            $templateDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'template'; });
            if (empty($templateDeliveries)): 
            ?>
            <div class="text-center text-gray-500 py-8">
                <i class="bi bi-inbox text-4xl mb-2"></i>
                <p>No template deliveries</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($templateDeliveries, 0, 5) as $d): ?>
                <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="block bg-gray-50 hover:bg-gray-100 rounded-lg p-3 transition-colors border border-gray-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($d['product_name']); ?></span>
                        <?php if ($d['delivery_status'] === 'delivered'): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Delivered</span>
                        <?php elseif ($d['delivery_status'] === 'pending'): ?>
                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-semibold">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-600">
                        <?php echo htmlspecialchars($d['customer_name']); ?> - Order #<?php echo $d['order_id']; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($templateDeliveries) > 5): ?>
            <div class="text-center mt-3">
                <a href="?type=template" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                    View all <?php echo count($templateDeliveries); ?> templates <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-tools text-blue-600"></i> Tool Deliveries
            </h5>
            <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-semibold"><?php echo $toolsPending; ?> pending</span>
        </div>
        <div class="p-4 max-h-80 overflow-y-auto">
            <?php 
            $toolDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'tool'; });
            if (empty($toolDeliveries)): 
            ?>
            <div class="text-center text-gray-500 py-8">
                <i class="bi bi-inbox text-4xl mb-2"></i>
                <p>No tool deliveries</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($toolDeliveries, 0, 5) as $d): ?>
                <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="block bg-gray-50 hover:bg-gray-100 rounded-lg p-3 transition-colors border border-gray-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($d['product_name']); ?></span>
                        <?php if ($d['delivery_status'] === 'delivered'): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Delivered</span>
                        <?php elseif ($d['delivery_status'] === 'pending_retry'): ?>
                        <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">Retrying</span>
                        <?php elseif ($d['delivery_status'] === 'failed'): ?>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-semibold">Failed</span>
                        <?php elseif ($d['delivery_status'] === 'pending'): ?>
                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-semibold">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-600">
                        <?php echo htmlspecialchars($d['customer_name']); ?> - Order #<?php echo $d['order_id']; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($toolDeliveries) > 5): ?>
            <div class="text-center mt-3">
                <a href="?type=tool" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                    View all <?php echo count($toolDeliveries); ?> tools <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-200">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-list-check text-primary-600"></i> All Deliveries
            <?php if (!empty($filterType) || !empty($filterStatus) || !empty($filterDays)): ?>
            <span class="text-sm font-normal text-gray-600">(filtered: <?php echo count($deliveries); ?> results)</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="p-4 sm:p-6">
        <?php if (empty($deliveries)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-gray-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl text-blue-600"></i>
            <span>No deliveries found matching your filters.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden sm:table-cell">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden md:table-cell">Customer</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden lg:table-cell">Product</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden lg:table-cell">Created</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4 text-sm font-medium text-gray-600 hidden sm:table-cell">#<?php echo $d['id']; ?></td>
                        <td class="py-3 px-4 text-sm text-gray-900">
                            <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                                #<?php echo $d['order_id']; ?>
                            </a>
                        </td>
                        <td class="py-3 px-4 text-sm hidden md:table-cell">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($d['customer_name']); ?></div>
                            <div class="text-xs text-gray-600"><?php echo htmlspecialchars($d['customer_email']); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-900 hidden lg:table-cell"><?php echo htmlspecialchars($d['product_name']); ?></td>
                        <td class="py-3 px-4 text-sm">
                            <?php if ($d['product_type'] === 'tool'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-300">
                                    <i class="bi bi-tools mr-1"></i> Tool
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700 border border-purple-300">
                                    <i class="bi bi-palette mr-1"></i> Template
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php
                            $statusBadges = [
                                'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'border' => 'border-yellow-300', 'icon' => 'hourglass-split'],
                                'pending_retry' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-300', 'icon' => 'arrow-repeat'],
                                'in_progress' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-300', 'icon' => 'arrow-repeat'],
                                'ready' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-300', 'icon' => 'check-circle'],
                                'sent' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-300', 'icon' => 'send'],
                                'delivered' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-300', 'icon' => 'check-circle-fill'],
                                'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-300', 'icon' => 'x-circle']
                            ];
                            $badge = $statusBadges[$d['delivery_status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-gray-300', 'icon' => 'question-circle'];
                            $retryInfo = '';
                            if (isset($d['retry_count']) && $d['retry_count'] > 0) {
                                $retryInfo = ' (' . $d['retry_count'] . 'x)';
                            }
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badge['bg'] . ' ' . $badge['text'] . ' border ' . $badge['border']; ?>">
                                <i class="bi bi-<?php echo $badge['icon']; ?> mr-1"></i>
                                <span class="hidden sm:inline"><?php echo ucfirst(str_replace('_', ' ', $d['delivery_status'])) . $retryInfo; ?></span>
                                <span class="sm:hidden"><?php echo substr(ucfirst(str_replace('_', ' ', $d['delivery_status'])), 0, 3); ?></span>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-700 hidden lg:table-cell">
                            <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                            <div class="text-xs text-gray-600"><?php echo date('H:i', strtotime($d['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-center">
                            <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="inline-flex items-center px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-lg transition-colors">
                                <i class="bi bi-eye mr-1"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
