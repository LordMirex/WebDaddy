<?php
$pageTitle = 'Payment Logs';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$logs = $db->query("
    SELECT pl.*, po.id as order_id, po.customer_name
    FROM payment_logs pl
    LEFT JOIN pending_orders po ON pl.pending_order_id = po.id
    ORDER BY pl.created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-receipt text-primary-600"></i> Payment Logs
    </h1>
    <p class="text-gray-600 mt-2">Monitor all payment events and transactions</p>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-clock-history text-primary-600"></i> Recent Events (Last 100)
        </h5>
    </div>
    <div class="p-6">
        <?php if (empty($logs)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No payment logs found. Logs are created when payment events occur.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Time</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Event</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Provider</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Order</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Amount</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($log['event_type']); ?></span>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $providerColors = [
                                'paystack' => 'blue',
                                'manual' => 'purple',
                                'system' => 'gray'
                            ];
                            $color = $providerColors[$log['provider']] ?? 'gray';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800">
                                <?php echo ucfirst($log['provider']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($log['order_id']): ?>
                                <a href="/admin/orders.php?view=<?php echo $log['order_id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                                    #<?php echo $log['order_id']; ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($log['amount']): ?>
                                <span class="font-medium text-gray-900">₦<?php echo number_format($log['amount'], 2); ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $statusColors = [
                                'success' => 'green',
                                'failed' => 'red',
                                'error' => 'red',
                                'pending' => 'yellow',
                                'received' => 'blue',
                                'ignored' => 'gray'
                            ];
                            $statusColor = $statusColors[$log['status']] ?? 'gray';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-600">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
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
