<?php
/**
 * API: Request credential reset for template (self-service)
 * POST /api/customer/reset-credentials.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

$customerId = requireCustomerAuth();
if (!$customerId) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$deliveryId = (int)($input['delivery_id'] ?? 0);

if (!$deliveryId) {
    echo json_encode(['success' => false, 'error' => 'Delivery ID required']);
    exit;
}

$db = getDb();

$stmt = $db->prepare("
    SELECT d.*, po.customer_id, d.template_admin_username
    FROM deliveries d
    JOIN pending_orders po ON d.pending_order_id = po.id
    WHERE d.id = ? AND po.customer_id = ? AND d.product_type = 'template'
");
$stmt->execute([$deliveryId, $customerId]);
$delivery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$delivery) {
    echo json_encode(['success' => false, 'error' => 'Template delivery not found']);
    exit;
}

if (empty($delivery['template_admin_username'])) {
    echo json_encode(['success' => false, 'error' => 'No credentials have been set up yet for this template']);
    exit;
}

$stmt = $db->prepare("
    SELECT COUNT(*) FROM credential_reset_requests
    WHERE delivery_id = ? AND created_at > datetime('now', '-7 days')
");
$stmt->execute([$deliveryId]);
$resetCount = $stmt->fetchColumn();

if ($resetCount >= 3) {
    echo json_encode(['success' => false, 'error' => 'Too many reset requests this week. Please contact support.']);
    exit;
}

$stmt = $db->prepare("
    INSERT INTO credential_reset_requests 
    (delivery_id, customer_id, status, created_at)
    VALUES (?, ?, 'pending', datetime('now'))
");
$stmt->execute([$deliveryId, $customerId]);
$requestId = $db->lastInsertId();

sendAdminNotification("Credential reset requested for delivery #{$deliveryId} by customer #{$customerId}");

echo json_encode([
    'success' => true,
    'request_id' => $requestId,
    'message' => 'Credential reset requested. You\'ll receive new login details within 2 hours.',
    'eta' => '2 hours',
    'resets_remaining' => 3 - $resetCount - 1
]);
