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
    
    // Verify payment with Paystack
    $verification = verifyPayment($input['reference']);
    
    if (!$verification['success']) {
        throw new Exception($verification['message']);
    }
    
    // Get payment record
    $payment = getPaymentByReference($input['reference']);
    if (!$payment) {
        throw new Exception('Payment record not found');
    }
    
    $db = getDb();
    
    // Mark order as paid
    $stmt = $db->prepare("
        UPDATE pending_orders 
        SET status = 'paid', 
            payment_verified_at = datetime('now'),
            payment_method = 'paystack'
        WHERE id = ?
    ");
    $stmt->execute([$payment['pending_order_id']]);
    
    // Create delivery records and send emails
    createDeliveryRecords($payment['pending_order_id']);
    
    // Clear cart
    clearCart();
    
    echo json_encode([
        'success' => true,
        'order_id' => $payment['pending_order_id'],
        'message' => 'Payment verified successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
