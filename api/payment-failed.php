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
    
    error_log("⚠️ PAYMENT FAILED: Order #$orderId - Reason: $reason");
    
    // Check if order exists
    $stmt = $db->prepare("SELECT id, status, payment_method, customer_email FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // SECURITY: Only update if not already paid - prevent race conditions
    if ($order['status'] !== 'paid') {
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'failed' WHERE id = ? AND status != 'paid'");
        $stmt->execute([$orderId]);
        $affectedRows = $stmt->rowCount();
        
        error_log("⚠️ PAYMENT FAILED: Order #$orderId status updated (rows affected: $affectedRows)");
        
        // Clear any pending email queue for this order to prevent confirmation emails
        if (!empty($order['customer_email'])) {
            $clearStmt = $db->prepare("DELETE FROM email_queue WHERE pending_order_id = ? AND email_type IN ('payment_confirmed', 'tool_delivery', 'template_delivery')");
            $clearStmt->execute([$orderId]);
            error_log("⚠️ PAYMENT FAILED: Cleared pending emails for Order #$orderId");
        }
    } else {
        error_log("⚠️ PAYMENT FAILED: Order #$orderId already marked as PAID - ignoring failure notification");
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment marked as failed - no confirmation emails sent'
    ]);
    
} catch (Exception $e) {
    error_log("❌ PAYMENT FAILED ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
