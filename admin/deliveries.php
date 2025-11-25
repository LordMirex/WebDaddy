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
    <h1 class="text-3xl font-bold text-white flex items-center gap-3">
        <i class="bi bi-box-seam text-primary-400"></i> Delivery Management
    </h1>
    <p class="text-gray-300 mt-2">Monitor and manage product deliveries</p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <a href="?status=pending" class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-xl shadow-lg p-4 border border-yellow-500/20 hover:scale-105 transition-transform">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-xs font-semibold text-yellow-100 uppercase">Pending</h6>
            <i class="bi bi-hourglass-split text-xl text-yellow-200"></i>
        </div>
        <div class="text-2xl font-bold text-white"><?php echo $pendingCount; ?></div>
    </a>
    
    <a href="?status=pending_retry" class="bg-gradient-to-br from-orange-600 to-orange-700 rounded-xl shadow-lg p-4 border border-orange-500/20 hover:scale-105 transition-transform">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-xs font-semibold text-orange-100 uppercase">Retrying</h6>
            <i class="bi bi-arrow-repeat text-xl text-orange-200"></i>
        </div>
        <div class="text-2xl font-bold text-white"><?php echo $pendingRetryCount; ?></div>
    </a>
    
    <a href="?status=failed" class="bg-gradient-to-br from-red-600 to-red-700 rounded-xl shadow-lg p-4 border border-red-500/20 hover:scale-105 transition-transform">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-xs font-semibold text-red-100 uppercase">Failed</h6>
            <i class="bi bi-x-circle text-xl text-red-200"></i>
        </div>
        <div class="text-2xl font-bold text-white"><?php echo $failedCount; ?></div>
    </a>
    
    <a href="?status=delivered" class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl shadow-lg p-4 border border-green-500/20 hover:scale-105 transition-transform">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-xs font-semibold text-green-100 uppercase">Completed</h6>
            <i class="bi bi-check-circle text-xl text-green-200"></i>
        </div>
        <div class="text-2xl font-bold text-white"><?php echo $completedCount; ?></div>
    </a>
    
    <a href="?" class="bg-gradient-to-br from-gray-600 to-gray-700 rounded-xl shadow-lg p-4 border border-gray-500/20 hover:scale-105 transition-transform">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-xs font-semibold text-gray-100 uppercase">Total</h6>
            <i class="bi bi-box text-xl text-gray-200"></i>
        </div>
        <div class="text-2xl font-bold text-white"><?php echo count($deliveries); ?></div>
    </a>
</div>

<?php if (!empty($overdueTemplates)): ?>
<div class="bg-red-900/30 border border-red-700 rounded-xl p-6 mb-8">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center">
            <i class="bi bi-exclamation-triangle text-2xl text-white"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-red-300">Templates Requiring Attention</h3>
            <p class="text-red-400 text-sm"><?php echo count($overdueTemplates); ?> template(s) pending for over 24 hours</p>
        </div>
    </div>
    
    <div class="grid gap-3">
        <?php foreach ($overdueTemplates as $overdue): 
            $hoursOverdue = round((time() - strtotime($overdue['created_at'])) / 3600);
        ?>
        <div class="bg-red-950/50 border border-red-800 rounded-lg p-4 flex items-center justify-between flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-white font-semibold"><?php echo htmlspecialchars($overdue['product_name']); ?></span>
                    <span class="text-xs bg-red-700 text-red-100 px-2 py-0.5 rounded-full"><?php echo $hoursOverdue; ?>h overdue</span>
                </div>
                <div class="text-sm text-red-300">
                    Order #<?php echo $overdue['order_id']; ?> - <?php echo htmlspecialchars($overdue['customer_name']); ?>
                    <span class="text-red-400 ml-2"><?php echo htmlspecialchars($overdue['customer_email']); ?></span>
                </div>
            </div>
            <a href="/admin/orders.php?view=<?php echo $overdue['order_id']; ?>" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-lg text-sm transition-colors">
                <i class="bi bi-arrow-right mr-1"></i> Process Now
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 mb-6">
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
        <h5 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="bi bi-funnel text-primary-400"></i> Filters
        </h5>
    </div>
    <div class="p-4">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-300 mb-2">Product Type</label>
                <select name="type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                    <option value="">All Types</option>
                    <option value="template" <?php echo $filterType === 'template' ? 'selected' : ''; ?>>Templates</option>
                    <option value="tool" <?php echo $filterType === 'tool' ? 'selected' : ''; ?>>Tools</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="pending_retry" <?php echo $filterStatus === 'pending_retry' ? 'selected' : ''; ?>>Pending Retry</option>
                    <option value="delivered" <?php echo $filterStatus === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-300 mb-2">Time Period</label>
                <select name="days" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-100 focus:ring-2 focus:ring-primary-500">
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
                <a href="?" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white font-semibold rounded-lg transition-colors">
                    <i class="bi bi-x"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700 bg-gray-750 flex items-center justify-between">
            <h5 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="bi bi-palette text-purple-400"></i> Template Deliveries
            </h5>
            <span class="text-xs bg-purple-900/50 text-purple-300 px-2 py-1 rounded-full"><?php echo $templatesPending; ?> pending</span>
        </div>
        <div class="p-4 max-h-80 overflow-y-auto">
            <?php 
            $templateDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'template'; });
            if (empty($templateDeliveries)): 
            ?>
            <div class="text-center text-gray-400 py-8">
                <i class="bi bi-inbox text-4xl mb-2"></i>
                <p>No template deliveries</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($templateDeliveries, 0, 5) as $d): ?>
                <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="block bg-gray-750 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-100"><?php echo htmlspecialchars($d['product_name']); ?></span>
                        <?php if ($d['delivery_status'] === 'delivered'): ?>
                        <span class="text-xs bg-green-900/50 text-green-300 px-2 py-0.5 rounded-full">Delivered</span>
                        <?php elseif ($d['delivery_status'] === 'pending'): ?>
                        <span class="text-xs bg-yellow-900/50 text-yellow-300 px-2 py-0.5 rounded-full">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?php echo htmlspecialchars($d['customer_name']); ?> - Order #<?php echo $d['order_id']; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($templateDeliveries) > 5): ?>
            <div class="text-center mt-3">
                <a href="?type=template" class="text-primary-400 hover:text-primary-300 text-sm font-medium">
                    View all <?php echo count($templateDeliveries); ?> templates <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
        <div class="px-6 py-4 border-b border-gray-700 bg-gray-750 flex items-center justify-between">
            <h5 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="bi bi-tools text-blue-400"></i> Tool Deliveries
            </h5>
            <span class="text-xs bg-blue-900/50 text-blue-300 px-2 py-1 rounded-full"><?php echo $toolsPending; ?> pending</span>
        </div>
        <div class="p-4 max-h-80 overflow-y-auto">
            <?php 
            $toolDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'tool'; });
            if (empty($toolDeliveries)): 
            ?>
            <div class="text-center text-gray-400 py-8">
                <i class="bi bi-inbox text-4xl mb-2"></i>
                <p>No tool deliveries</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($toolDeliveries, 0, 5) as $d): ?>
                <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="block bg-gray-750 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-100"><?php echo htmlspecialchars($d['product_name']); ?></span>
                        <?php if ($d['delivery_status'] === 'delivered'): ?>
                        <span class="text-xs bg-green-900/50 text-green-300 px-2 py-0.5 rounded-full">Delivered</span>
                        <?php elseif ($d['delivery_status'] === 'pending_retry'): ?>
                        <span class="text-xs bg-orange-900/50 text-orange-300 px-2 py-0.5 rounded-full">Retrying</span>
                        <?php elseif ($d['delivery_status'] === 'failed'): ?>
                        <span class="text-xs bg-red-900/50 text-red-300 px-2 py-0.5 rounded-full">Failed</span>
                        <?php elseif ($d['delivery_status'] === 'pending'): ?>
                        <span class="text-xs bg-yellow-900/50 text-yellow-300 px-2 py-0.5 rounded-full">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?php echo htmlspecialchars($d['customer_name']); ?> - Order #<?php echo $d['order_id']; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($toolDeliveries) > 5): ?>
            <div class="text-center mt-3">
                <a href="?type=tool" class="text-primary-400 hover:text-primary-300 text-sm font-medium">
                    View all <?php echo count($toolDeliveries); ?> tools <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
        <h5 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-list-check text-primary-400"></i> All Deliveries
            <?php if (!empty($filterType) || !empty($filterStatus) || !empty($filterDays)): ?>
            <span class="text-sm font-normal text-gray-400">(filtered: <?php echo count($deliveries); ?> results)</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="p-6">
        <?php if (empty($deliveries)): ?>
        <div class="bg-blue-900/30 border-l-4 border-blue-400 text-blue-200 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No deliveries found matching your filters.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700 bg-gray-750">
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden sm:table-cell">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm">Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden md:table-cell">Customer</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden lg:table-cell">Product</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden lg:table-cell">Created</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-750/50 transition-colors">
                        <td class="py-3 px-4 text-sm font-medium text-gray-100 hidden sm:table-cell">#<?php echo $d['id']; ?></td>
                        <td class="py-3 px-4 text-sm text-gray-200">
                            <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="text-primary-400 hover:text-primary-300 font-medium">
                                #<?php echo $d['order_id']; ?>
                            </a>
                        </td>
                        <td class="py-3 px-4 text-sm hidden md:table-cell">
                            <div class="font-medium text-gray-100"><?php echo htmlspecialchars($d['customer_name']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($d['customer_email']); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-200 hidden lg:table-cell"><?php echo htmlspecialchars($d['product_name']); ?></td>
                        <td class="py-3 px-4 text-sm">
                            <?php if ($d['product_type'] === 'tool'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-900/40 text-blue-300 border border-blue-700">
                                    <i class="bi bi-tools mr-1"></i> Tool
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-900/40 text-purple-300 border border-purple-700">
                                    <i class="bi bi-palette mr-1"></i> Template
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php
                            $statusBadges = [
                                'pending' => ['bg' => 'bg-yellow-900/40', 'text' => 'text-yellow-300', 'border' => 'border-yellow-700', 'icon' => 'hourglass-split'],
                                'pending_retry' => ['bg' => 'bg-orange-900/40', 'text' => 'text-orange-300', 'border' => 'border-orange-700', 'icon' => 'arrow-repeat'],
                                'in_progress' => ['bg' => 'bg-blue-900/40', 'text' => 'text-blue-300', 'border' => 'border-blue-700', 'icon' => 'arrow-repeat'],
                                'ready' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'check-circle'],
                                'sent' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'send'],
                                'delivered' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'check-circle-fill'],
                                'failed' => ['bg' => 'bg-red-900/40', 'text' => 'text-red-300', 'border' => 'border-red-700', 'icon' => 'x-circle']
                            ];
                            $badge = $statusBadges[$d['delivery_status']] ?? ['bg' => 'bg-gray-900/40', 'text' => 'text-gray-300', 'border' => 'border-gray-700', 'icon' => 'question-circle'];
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
                        <td class="py-3 px-4 text-sm text-gray-400 hidden lg:table-cell">
                            <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($d['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-center">
                            <a href="/admin/orders.php?view=<?php echo $d['order_id']; ?>" class="inline-flex items-center px-3 py-1 bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold rounded-lg transition-colors">
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
