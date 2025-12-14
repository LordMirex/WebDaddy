<?php
/**
 * Cron Job: Check Delivery SLAs and Auto-Recovery (Update 17)
 * Run every 5 minutes: */5 * * * * php /path/to/cron/check_delivery_sla.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/delivery_state.php';

error_log("[CRON] check_delivery_sla.php started at " . date('Y-m-d H:i:s'));

$slaResults = checkDeliverySLAs();
error_log("[CRON] SLA Check: Escalated {$slaResults['escalated']}, Warned {$slaResults['warned']}");

$recoveryResults = processFailedDeliveries();
error_log("[CRON] Recovery: Processed {$recoveryResults['processed']}, Recovered {$recoveryResults['recovered']}");

$db = getDb();

$stmt = $db->query("
    SELECT id FROM deliveries 
    WHERE delivery_state = 'ready'
    AND datetime(state_changed_at) < datetime('now', '-7 days')
");
$expiredDeliveries = $stmt->fetchAll(PDO::FETCH_COLUMN);

$expiredCount = 0;
foreach ($expiredDeliveries as $id) {
    markDeliveryState($id, 'expired', 'Download link expired (7 days)');
    $expiredCount++;
}

$stmt = $db->query("
    SELECT id FROM deliveries 
    WHERE delivery_state = 'downloaded'
    AND datetime(state_changed_at) < datetime('now', '-30 days')
");
$completedDeliveries = $stmt->fetchAll(PDO::FETCH_COLUMN);

$completedCount = 0;
foreach ($completedDeliveries as $id) {
    markDeliveryState($id, 'completed', 'Auto-completed after 30 days');
    $completedCount++;
}

$stmt = $db->query("
    SELECT d.*, po.customer_email, po.customer_name
    FROM deliveries d
    JOIN pending_orders po ON d.pending_order_id = po.id
    WHERE (d.delivery_state = 'pending' OR d.delivery_state = 'processing')
    AND (
        (d.product_type = 'tool' AND julianday('now') - julianday(d.created_at) > 0.007)
        OR (d.product_type = 'template' AND julianday('now') - julianday(d.created_at) > 1)
    )
");
$breaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($breaches) > 0) {
    $message = "<h3>Delivery SLA Breaches Detected</h3><ul>";
    foreach ($breaches as $d) {
        $message .= "<li>Order #{$d['pending_order_id']} ({$d['product_type']}): {$d['customer_email']}</li>";
    }
    $message .= "</ul><p>Please review these deliveries in the admin panel.</p>";
    
    if (defined('SUPPORT_EMAIL') && !empty(SUPPORT_EMAIL)) {
        sendEmail(
            SUPPORT_EMAIL,
            'ALERT: ' . count($breaches) . ' Delivery SLA Breaches',
            createEmailTemplate('Delivery SLA Alert', $message, 'Admin')
        );
    }
    error_log("[CRON] SLA Alert: " . count($breaches) . " breaches detected");
    echo "SLA Alert: " . count($breaches) . " breaches detected\n";
} else {
    echo "No SLA breaches found\n";
}

error_log("[CRON] Expired: {$expiredCount}, Auto-completed: {$completedCount}");
error_log("[CRON] check_delivery_sla.php completed");
