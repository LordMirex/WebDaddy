<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
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
    
    // Update order status to paid
    $stmt = $db->prepare("
        UPDATE pending_orders 
        SET status = 'paid', 
            payment_verified_at = datetime('now'),
            payment_method = 'paystack'
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$orderId]);
    
    // Only proceed if order was actually updated (not already processed)
    if ($stmt->rowCount() > 0) {
        // Create delivery records and send emails
        createDeliveryRecords($orderId);
        
        // Clear cart
        clearCart();
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment verified successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
