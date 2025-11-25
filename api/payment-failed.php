<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order_id'])) {
        throw new Exception('Missing order_id');
    }
    
    $orderId = (int)$input['order_id'];
    $reason = $input['reason'] ?? 'Payment cancelled by customer';
    
    $db = getDb();
    
    // Check if order exists
    $stmt = $db->prepare("SELECT id, status, payment_method FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Only update if not already paid
    if ($order['status'] !== 'paid') {
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'failed' WHERE id = ?");
        $stmt->execute([$orderId]);
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment marked as failed'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
