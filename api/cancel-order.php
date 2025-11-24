<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order_id'])) {
        throw new Exception('Order ID required');
    }
    
    $orderId = (int)$input['order_id'];
    $db = getDb();
    
    // Get order to verify it belongs to this session
    $stmt = $db->prepare("SELECT session_id, status FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Only allow cancellation if order is pending or unpaid
    if (!in_array($order['status'], ['pending', 'failed'])) {
        throw new Exception('Cannot cancel paid order');
    }
    
    // Delete order and its items
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        $stmt = $db->prepare("DELETE FROM pending_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled and deleted'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
