<?php
/**
 * Create Payment Record Before Paystack Popup
 * This endpoint creates a payment record with the unique reference BEFORE
 * the Paystack popup opens, so that webhooks can find the payment record.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');
startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order_id']) || empty($input['reference']) || empty($input['amount'])) {
        throw new Exception('Missing required parameters');
    }
    
    $orderId = (int)$input['order_id'];
    $reference = $input['reference'];
    $amount = (int)$input['amount'];
    $email = $input['email'] ?? '';
    
    $db = getDb();
    
    // Verify the order exists and is pending
    $stmt = $db->prepare("SELECT id, status, final_amount, customer_email FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if ($order['status'] === 'paid') {
        echo json_encode([
            'success' => true,
            'message' => 'Order already paid',
            'already_paid' => true
        ]);
        exit;
    }
    
    // Check if payment record already exists for this reference
    $stmt = $db->prepare("SELECT id FROM payments WHERE paystack_reference = ?");
    $stmt->execute([$reference]);
    $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingPayment) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment record already exists',
            'payment_id' => $existingPayment['id']
        ]);
        exit;
    }
    
    // Check if payment record exists for this order (update it)
    $stmt = $db->prepare("SELECT id FROM payments WHERE pending_order_id = ?");
    $stmt->execute([$orderId]);
    $existingOrderPayment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingOrderPayment) {
        // Update existing payment with new reference
        $stmt = $db->prepare("
            UPDATE payments 
            SET paystack_reference = ?,
                amount_requested = ?,
                status = 'pending',
                updated_at = datetime('now', '+1 hour')
            WHERE id = ?
        ");
        $stmt->execute([$reference, $amount / 100, $existingOrderPayment['id']]);
        
        error_log("📝 PAYMENT RECORD: Updated existing payment #{$existingOrderPayment['id']} for Order #$orderId with reference: $reference");
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment record updated',
            'payment_id' => $existingOrderPayment['id']
        ]);
    } else {
        // Create new payment record
        $stmt = $db->prepare("
            INSERT INTO payments (
                pending_order_id, payment_method, amount_requested, currency,
                paystack_reference, status, created_at
            ) VALUES (?, 'paystack', ?, 'NGN', ?, 'pending', datetime('now', '+1 hour'))
        ");
        $stmt->execute([$orderId, $amount / 100, $reference]);
        $paymentId = $db->lastInsertId();
        
        error_log("📝 PAYMENT RECORD: Created new payment #$paymentId for Order #$orderId with reference: $reference");
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment record created',
            'payment_id' => $paymentId
        ]);
    }
    
} catch (Exception $e) {
    error_log("❌ CREATE PAYMENT RECORD ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
