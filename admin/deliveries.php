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
    <h1 class="text-3xl font-bold text-white flex items-center gap-3">
        <i class="bi bi-box-seam text-primary-400"></i> Delivery Management
    </h1>
    <p class="text-gray-300 mt-2">Monitor and manage product deliveries</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
    <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-xl shadow-lg p-6 border border-yellow-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-yellow-100 uppercase tracking-wide">Pending</h6>
            <i class="bi bi-hourglass-split text-2xl text-yellow-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo $pendingCount; ?></div>
        <p class="text-sm text-yellow-100 mt-1">Awaiting preparation</p>
    </div>
    
    <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg p-6 border border-blue-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-blue-100 uppercase tracking-wide">In Progress</h6>
            <i class="bi bi-arrow-repeat text-2xl text-blue-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo $inProgressCount; ?></div>
        <p class="text-sm text-blue-100 mt-1">Being processed</p>
    </div>
    
    <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl shadow-lg p-6 border border-green-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-green-100 uppercase tracking-wide">Completed</h6>
            <i class="bi bi-check-circle text-2xl text-green-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo $completedCount; ?></div>
        <p class="text-sm text-green-100 mt-1">Successfully delivered</p>
    </div>
</div>

<!-- Deliveries Table -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
        <h5 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-list-check text-primary-400"></i> All Deliveries
        </h5>
    </div>
    <div class="p-6">
        <?php if (empty($deliveries)): ?>
        <div class="bg-blue-900/30 border-l-4 border-blue-400 text-blue-200 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No deliveries found. Deliveries are created automatically when orders are paid.</span>
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
                            <a href="/admin/orders.php?search=<?php echo $d['order_id']; ?>" class="text-primary-400 hover:text-primary-300 font-medium">
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
                                'in_progress' => ['bg' => 'bg-blue-900/40', 'text' => 'text-blue-300', 'border' => 'border-blue-700', 'icon' => 'arrow-repeat'],
                                'ready' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'check-circle'],
                                'sent' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'send'],
                                'delivered' => ['bg' => 'bg-green-900/40', 'text' => 'text-green-300', 'border' => 'border-green-700', 'icon' => 'check-circle-fill'],
                                'failed' => ['bg' => 'bg-red-900/40', 'text' => 'text-red-300', 'border' => 'border-red-700', 'icon' => 'x-circle']
                            ];
                            $badge = $statusBadges[$d['delivery_status']] ?? ['bg' => 'bg-gray-900/40', 'text' => 'text-gray-300', 'border' => 'border-gray-700', 'icon' => 'question-circle'];
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badge['bg'] . ' ' . $badge['text'] . ' border ' . $badge['border']; ?>">
                                <i class="bi bi-<?php echo $badge['icon']; ?> mr-1"></i>
                                <span class="hidden sm:inline"><?php echo ucfirst(str_replace('_', ' ', $d['delivery_status'])); ?></span>
                                <span class="sm:hidden"><?php echo substr(ucfirst(str_replace('_', ' ', $d['delivery_status'])), 0, 3); ?></span>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-400 hidden lg:table-cell">
                            <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($d['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-center">
                            <button onclick="viewDeliveryDetails(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['customer_name']); ?>', '<?php echo htmlspecialchars($d['product_name']); ?>', '<?php echo $d['delivery_status']; ?>')" class="text-primary-400 hover:text-primary-300 font-medium transition-colors">
                                <i class="bi bi-eye"></i>
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
function viewDeliveryDetails(deliveryId, customerName, productName, status) {
    const statusMessages = {
        'pending': '⏳ Awaiting preparation',
        'in_progress': '🔄 Currently being prepared',
        'ready': '✅ Ready for delivery',
        'sent': '📤 Sent to customer',
        'delivered': '🎉 Successfully delivered',
        'failed': '❌ Delivery failed'
    };
    
    const message = `Delivery ID: #${deliveryId}\n\nCustomer: ${customerName}\nProduct: ${productName}\n\nStatus: ${statusMessages[status] || status}`;
    alert(message);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
