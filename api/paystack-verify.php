<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // SECURITY: CSRF protection for payment verification
    if (empty($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        throw new Exception('Security validation failed');
    }
    
    if (empty($input['reference'])) {
        throw new Exception('Missing payment reference');
    }
    
    // Verify payment with Paystack (this updates payment record automatically)
    $verification = verifyPayment($input['reference']);
    
    if (!$verification['success']) {
        throw new Exception($verification['message']);
    }
    
    // Get order ID from verification response
    $orderId = $verification['order_id'];
    if (!$orderId) {
        throw new Exception('Order ID not found in verification response');
    }
    
    $db = getDb();
    
    // SECURITY: Use transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // IDEMPOTENCY: Check if order is already processed
        $stmt = $db->prepare("SELECT status, session_id FROM pending_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // SECURITY: Verify this session created the order
        if ($order['session_id'] !== session_id()) {
            throw new Exception('Unauthorized access to order');
        }
        
        // IDEMPOTENCY: Only process if status is pending
        if ($order['status'] === 'paid') {
            // Already processed - return success without duplicate delivery
            $db->rollBack();
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Payment already verified'
            ]);
            exit;
        }
        
        // Update order status to paid
        $stmt = $db->prepare("
            UPDATE pending_orders 
            SET status = 'paid', 
                payment_verified_at = datetime('now'),
                payment_method = 'paystack'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$orderId]);
        
        // Commit transaction before delivery (delivery is separate process)
        $db->commit();
        
        // Create delivery records and send emails (outside transaction)
        createDeliveryRecords($orderId);
        
        // Clear cart (safe to do after commit)
        clearCart();
        
        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'message' => 'Payment verified successfully'
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
