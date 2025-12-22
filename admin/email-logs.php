<?php
$pageTitle = 'Email Logs (Mailtrap)';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/mailtrap.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

ensureMailtrapLogsTable($db);

$stats = getMailtrapEmailStats();

$statusFilter = $_GET['status'] ?? null;
$typeFilter = $_GET['type'] ?? null;

$logs = getMailtrapEmailLogs(100, 0, $statusFilter, $typeFilter);

$webhookLogs = [];
try {
    $webhookLogs = $db->query("
        SELECT * FROM mailtrap_webhook_logs 
        ORDER BY created_at DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-envelope-paper text-primary-600"></i> Email Logs (Mailtrap)
    </h1>
    <p class="text-gray-600 mt-2">Monitor OTP email delivery status via Mailtrap</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Sent</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-envelope text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Delivered</p>
                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['delivered']); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Opened</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['opened']); ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-eye text-purple-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Failed/Bounced</p>
                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['failed'] + $stats['bounced']); ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-x-circle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-8">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-envelope-check text-primary-600"></i> Email Delivery Logs
        </h5>
        <div class="flex gap-2">
            <a href="?status=" class="px-3 py-1 text-sm rounded-full <?php echo !$statusFilter ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">All</a>
            <a href="?status=delivered" class="px-3 py-1 text-sm rounded-full <?php echo $statusFilter === 'delivered' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">Delivered</a>
            <a href="?status=sent" class="px-3 py-1 text-sm rounded-full <?php echo $statusFilter === 'sent' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">Sent</a>
            <a href="?status=failed" class="px-3 py-1 text-sm rounded-full <?php echo $statusFilter === 'failed' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">Failed</a>
            <a href="?status=bounced" class="px-3 py-1 text-sm rounded-full <?php echo $statusFilter === 'bounced' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">Bounced</a>
        </div>
    </div>
    <div class="p-6">
        <?php if (empty($logs)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No email logs found. Logs will appear when OTP emails are sent via Mailtrap.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Time</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Recipient</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Subject</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Email ID</th>
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
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($log['recipient_email']); ?></span>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $typeColors = [
                                'otp' => 'blue',
                                'recovery_otp' => 'purple',
                                'verification' => 'green'
                            ];
                            $color = $typeColors[$log['email_type']] ?? 'gray';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800">
                                <?php echo ucfirst(str_replace('_', ' ', $log['email_type'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 max-w-xs truncate" title="<?php echo htmlspecialchars($log['subject'] ?? ''); ?>">
                            <?php echo htmlspecialchars($log['subject'] ?? '-'); ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $statusColors = [
                                'sent' => 'blue',
                                'delivered' => 'green',
                                'opened' => 'purple',
                                'clicked' => 'indigo',
                                'failed' => 'red',
                                'bounced' => 'orange',
                                'complained' => 'yellow',
                                'delayed' => 'amber',
                                'pending' => 'gray'
                            ];
                            $statusColor = $statusColors[$log['status']] ?? 'gray';
                            $statusIcons = [
                                'sent' => 'bi-send',
                                'delivered' => 'bi-check-circle',
                                'opened' => 'bi-eye',
                                'clicked' => 'bi-link',
                                'failed' => 'bi-x-circle',
                                'bounced' => 'bi-arrow-return-left',
                                'complained' => 'bi-exclamation-triangle',
                                'delayed' => 'bi-clock',
                                'pending' => 'bi-hourglass'
                            ];
                            $statusIcon = $statusIcons[$log['status']] ?? 'bi-question-circle';
                            ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                <i class="<?php echo $statusIcon; ?>"></i>
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                            <?php if (!empty($log['error_message'])): ?>
                            <div class="text-xs text-red-600 mt-1" title="<?php echo htmlspecialchars($log['error_message']); ?>">
                                <?php echo htmlspecialchars(substr($log['error_message'], 0, 50)); ?>...
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-xs font-mono">
                            <?php echo htmlspecialchars(substr($log['mailtrap_email_id'] ?? '-', 0, 20)); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-broadcast text-primary-600"></i> Webhook Events (Last 50)
        </h5>
    </div>
    <div class="p-6">
        <?php if (empty($webhookLogs)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <div>
                <p class="font-medium">No webhook events received yet.</p>
                <p class="text-sm mt-1">Webhook URL: <code class="bg-blue-100 px-2 py-0.5 rounded"><?php echo SITE_URL; ?>/api/mailtrap-webhook.php</code></p>
                <p class="text-sm mt-1">Configure this URL in your Mailtrap dashboard to receive delivery events.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Time</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Event</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Recipient</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Email ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhookLogs as $log): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $eventColors = [
                                'email.sent' => 'blue',
                                'email.delivered' => 'green',
                                'email.opened' => 'purple',
                                'email.clicked' => 'indigo',
                                'email.bounced' => 'orange',
                                'email.complained' => 'yellow',
                                'email.delivery_delayed' => 'amber'
                            ];
                            $eventColor = $eventColors[$log['event_type']] ?? 'gray';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-<?php echo $eventColor; ?>-100 text-<?php echo $eventColor; ?>-800">
                                <?php echo htmlspecialchars($log['event_type']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-700">
                            <?php echo htmlspecialchars($log['recipient_email'] ?? '-'); ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-xs font-mono">
                            <?php echo htmlspecialchars(substr($log['mailtrap_email_id'] ?? '-', 0, 20)); ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-xs">
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
