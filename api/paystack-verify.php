<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['reference']) || empty($input['order_id'])) {
        throw new Exception('Missing required parameters');
    }
    
    $orderId = (int)$input['order_id'];
    $reference = $input['reference'];
    
    // Verify payment with Paystack
    $verification = verifyPayment($reference);
    
    $db = getDb();
    
    // Get order details for notifications
    $stmt = $db->prepare("SELECT id, customer_name, customer_phone, affiliate_code, original_price, final_amount FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Get order items
    $stmt = $db->prepare("
        SELECT 
            COALESCE(t.name, to.name) as name, 
            oi.product_type,
            COUNT(*) as qty
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools to ON oi.product_type = 'tool' AND oi.product_id = to.id
        WHERE oi.order_id = ?
        GROUP BY oi.product_id, oi.product_type
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $productNames = implode(', ', array_map(function($item) {
        return $item['name'] . ($item['qty'] > 1 ? ' (x' . $item['qty'] . ')' : '');
    }, $items));
    
    // Determine order type
    $hasTemplates = false;
    $hasTools = false;
    foreach ($items as $item) {
        if ($item['product_type'] === 'template') $hasTemplates = true;
        if ($item['product_type'] === 'tool') $hasTools = true;
    }
    
    $orderType = 'template';
    if ($hasTemplates && $hasTools) {
        $orderType = 'mixed';
    } elseif ($hasTemplates) {
        $orderType = 'template';
    } else {
        $orderType = 'tool';
    }
    
    // SECURITY: Use transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // Check current status
        $stmt = $db->prepare("SELECT status FROM pending_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($currentOrder && $currentOrder['status'] === 'paid') {
            // Already processed
            $db->rollBack();
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Payment already verified'
            ]);
            exit;
        }
        
        if ($verification['success']) {
            // PAYMENT SUCCEEDED: Mark order as PAID and send admin notification
            $stmt = $db->prepare("
                UPDATE pending_orders 
                SET status = 'paid', 
                    payment_verified_at = datetime('now'),
                    payment_method = 'paystack'
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$orderId]);
            
            $db->commit();
            
            // Send admin notification about successful payment (outside transaction)
            sendPaymentSuccessNotificationToAdmin(
                $orderId,
                $order['customer_name'],
                $order['customer_phone'],
                $productNames,
                formatCurrency($order['final_amount']),
                $order['affiliate_code'],
                $orderType
            );
            
            // Create delivery records
            createDeliveryRecords($orderId);
            
            clearCart();
            
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Payment verified successfully'
            ]);
        } else {
            // PAYMENT FAILED: Mark order as FAILED and send admin notification
            $failureReason = $verification['message'] ?? 'Payment verification failed';
            
            $stmt = $db->prepare("
                UPDATE pending_orders 
                SET status = 'failed',
                    payment_verified_at = datetime('now'),
                    payment_method = 'paystack'
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $db->commit();
            
            // Send admin notification about failed payment (outside transaction)
            sendPaymentFailureNotificationToAdmin(
                $orderId,
                $order['customer_name'],
                $order['customer_phone'],
                $productNames,
                formatCurrency($order['final_amount']),
                $failureReason,
                $order['affiliate_code'],
                $orderType
            );
            
            throw new Exception('Payment verification failed: ' . $failureReason);
        }
        
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
