<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$db = getDb();

$stmt = $db->query("
    SELECT d.*, po.customer_email, po.customer_name
    FROM deliveries d
    JOIN pending_orders po ON d.order_id = po.id
    WHERE d.status = 'pending'
    AND (
        (d.product_type = 'tool' AND julianday('now') - julianday(d.created_at) > 0.007)
        OR (d.product_type = 'template' AND julianday('now') - julianday(d.created_at) > 1)
    )
");

$breaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($breaches) > 0) {
    $message = "<h3>Delivery SLA Breaches Detected</h3><ul>";
    foreach ($breaches as $d) {
        $message .= "<li>Order #{$d['order_id']} ({$d['product_type']}): {$d['customer_email']}</li>";
    }
    $message .= "</ul><p>Please review these deliveries in the admin panel.</p>";
    
    if (!empty(SUPPORT_EMAIL)) {
        sendEmail(
            SUPPORT_EMAIL,
            'ALERT: ' . count($breaches) . ' Delivery SLA Breaches',
            createEmailTemplate('Delivery SLA Alert', $message, 'Admin')
        );
    }
    error_log("SLA Alert: " . count($breaches) . " breaches detected");
    echo "SLA Alert: " . count($breaches) . " breaches detected\n";
} else {
    echo "No SLA breaches found\n";
}
