<?php
/**
 * API: Get delivery status for an order
 * GET /api/customer/delivery-status.php?order_id=123
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/delivery_state.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$customerId = requireCustomerAuth();
if (!$customerId) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);

if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

$db = getDb();
$orderCheck = $db->prepare("SELECT id FROM pending_orders WHERE id = ? AND customer_id = ?");
$orderCheck->execute([$orderId, $customerId]);
if (!$orderCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$deliveries = getDeliveryStatusForOrder($orderId, $customerId);

$overallHealth = 'good';
foreach ($deliveries as $d) {
    if (in_array($d['delivery_state'], ['failed', 'stalled', 'issue'])) {
        $overallHealth = 'warning';
        break;
    }
    if ($d['delivery_state'] === 'expired') {
        $overallHealth = 'attention';
    }
}

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'overall_health' => $overallHealth,
    'deliveries' => $deliveries
]);
