<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

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
    $stmt = $db->prepare("SELECT id, status, payment_method, customer_email, customer_name, customer_phone, affiliate_code, final_amount FROM pending_orders WHERE id = ?");
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
        
        // Get order items for admin notification
        $itemStmt = $db->prepare("
            SELECT 
                COALESCE(t.name, tl.name) as name, 
                oi.product_type,
                COUNT(*) as qty
            FROM order_items oi
            LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
            LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
            WHERE oi.pending_order_id = ?
            GROUP BY oi.product_id, oi.product_type
        ");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $productNames = implode(', ', array_map(function($item) {
            return $item['name'] . ($item['qty'] > 1 ? ' (x' . $item['qty'] . ')' : '');
        }, $items));
        
        // Determine order type for admin notification
        $hasTemplates = false;
        $hasTools = false;
        foreach ($items as $item) {
            if ($item['product_type'] === 'template') $hasTemplates = true;
            if ($item['product_type'] === 'tool') $hasTools = true;
        }
        $orderType = ($hasTemplates && $hasTools) ? 'mixed' : ($hasTemplates ? 'template' : 'tool');
        
        // SEND ADMIN NOTIFICATION about failed payment
        error_log("⚠️ PAYMENT FAILED: Sending admin failure notification for Order #$orderId");
        sendPaymentFailureNotificationToAdmin(
            $orderId,
            $order['customer_name'],
            $order['customer_phone'],
            $productNames,
            formatCurrency($order['final_amount']),
            $reason,
            $order['affiliate_code'],
            $orderType
        );
        error_log("⚠️ PAYMENT FAILED: Admin notification sent");
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
