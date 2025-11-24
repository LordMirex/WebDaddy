<?php
$pageTitle = 'Delivery Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$stmt = $db->query("
    SELECT d.*, 
           po.customer_name, 
           po.customer_email,
           po.id as order_id
    FROM deliveries d
    INNER JOIN pending_orders po ON d.pending_order_id = po.id
    ORDER BY d.created_at DESC
");
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = 0;
$inProgressCount = 0;
$completedCount = 0;

foreach ($deliveries as $d) {
    if ($d['delivery_status'] === 'pending') $pendingCount++;
    elseif ($d['delivery_status'] === 'in_progress') $inProgressCount++;
    elseif (in_array($d['delivery_status'], ['sent', 'delivered', 'ready'])) $completedCount++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-box-seam text-primary-600"></i> Delivery Management
    </h1>
    <p class="text-gray-600 mt-2">Monitor and manage product deliveries</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Pending Deliveries</h6>
            <i class="bi bi-hourglass-split text-2xl text-yellow-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $pendingCount; ?></div>
        <p class="text-sm text-gray-500 mt-1">Awaiting preparation</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">In Progress</h6>
            <i class="bi bi-arrow-repeat text-2xl text-blue-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $inProgressCount; ?></div>
        <p class="text-sm text-gray-500 mt-1">Being processed</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Completed</h6>
            <i class="bi bi-check-circle text-2xl text-green-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $completedCount; ?></div>
        <p class="text-sm text-gray-500 mt-1">Successfully delivered</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-list-check text-primary-600"></i> All Deliveries
        </h5>
    </div>
    <div class="p-6">
        <?php if (empty($deliveries)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No deliveries found. Deliveries are created automatically when orders are paid.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Customer</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Product</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Created</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4 text-sm font-medium text-gray-900">#<?php echo $d['id']; ?></td>
                        <td class="py-3 px-4 text-sm text-gray-700">
                            <a href="/admin/orders.php?search=<?php echo $d['order_id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                                #<?php echo $d['order_id']; ?>
                            </a>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($d['customer_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($d['customer_email']); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($d['product_name']); ?></td>
                        <td class="py-3 px-4 text-sm">
                            <?php if ($d['product_type'] === 'tool'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                    <i class="bi bi-tools mr-1"></i> Tool
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                    <i class="bi bi-palette mr-1"></i> Template
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php
                            $statusBadges = [
                                'pending' => ['color' => 'yellow', 'icon' => 'hourglass-split'],
                                'in_progress' => ['color' => 'blue', 'icon' => 'arrow-repeat'],
                                'ready' => ['color' => 'green', 'icon' => 'check-circle'],
                                'sent' => ['color' => 'green', 'icon' => 'send'],
                                'delivered' => ['color' => 'green', 'icon' => 'check-circle-fill'],
                                'failed' => ['color' => 'red', 'icon' => 'x-circle']
                            ];
                            $badge = $statusBadges[$d['delivery_status']] ?? ['color' => 'gray', 'icon' => 'question-circle'];
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $badge['color']; ?>-100 text-<?php echo $badge['color']; ?>-800">
                                <i class="bi bi-<?php echo $badge['icon']; ?> mr-1"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $d['delivery_status'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                            <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($d['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <button onclick="viewDeliveryDetails(<?php echo $d['id']; ?>)" class="text-primary-600 hover:text-primary-700 font-medium">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewDeliveryDetails(deliveryId) {
    alert('Delivery details view will be implemented. Delivery ID: ' + deliveryId);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
