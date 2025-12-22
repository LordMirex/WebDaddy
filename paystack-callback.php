<?php
/**
 * Paystack Callback Handler
 * Processes payment verification after user returns from Paystack
 * No webhooks needed - this works on all shared hosting environments
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paystack.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/customer_session.php';

$reference = $_GET['reference'] ?? '';

if (!$reference) {
    http_response_code(400);
    die('No payment reference provided');
}

try {
    // Verify payment with Paystack
    $verification = verifyPayment($reference);
    
    if (!$verification['success']) {
        error_log("❌ Payment verification failed for reference: $reference");
        
        // Get order from reference to redirect back
        $db = getDb();
        $stmt = $db->prepare("SELECT id FROM pending_orders WHERE id = (SELECT pending_order_id FROM payments WHERE paystack_reference = ?)");
        $stmt->execute([$reference]);
        $orderId = $stmt->fetchColumn();
        
        if ($orderId) {
            // Mark order as failed
            $db->prepare("UPDATE pending_orders SET status = 'failed' WHERE id = ?")->execute([$orderId]);
            header('Location: /user/order-detail.php?id=' . $orderId . '&payment=failed');
        } else {
            header('Location: /?payment=failed');
        }
        exit;
    }
    
    // Payment verified successfully
    $db = getDb();
    
    // Get payment record with order info
    $stmt = $db->prepare("
        SELECT po.id, po.customer_id, p.id as payment_id 
        FROM payments p
        JOIN pending_orders po ON p.pending_order_id = po.id
        WHERE p.paystack_reference = ?
        LIMIT 1
    ");
    $stmt->execute([$reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        error_log("⚠️  Payment verified but order not found for reference: $reference");
        header('Location: /?payment=success');
        exit;
    }
    
    $orderId = $payment['id'];
    
    // Update order status to paid
    $updateStmt = $db->prepare("UPDATE pending_orders SET status = 'paid', payment_verified_at = datetime('now') WHERE id = ?");
    $updateStmt->execute([$orderId]);
    
    // Send payment confirmation email if customer has email
    $customerStmt = $db->prepare("SELECT email FROM customers WHERE id = ?");
    $customerStmt->execute([$payment['customer_id']]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer && !empty($customer['email'])) {
        require_once __DIR__ . '/includes/email_queue.php';
        
        addEmailToQueue([
            'to' => $customer['email'],
            'subject' => 'Payment Confirmed - Order #' . $orderId,
            'template' => 'payment_confirmed',
            'data' => [
                'order_id' => $orderId,
                'amount' => $verification['amount']
            ]
        ]);
        
        processEmailQueue();
    }
    
    error_log("✅ Payment verified for Order #$orderId - Reference: $reference");
    
    // Redirect to order details page with success message
    header('Location: /user/order-detail.php?id=' . $orderId . '&payment=success');
    exit;
    
} catch (Exception $e) {
    error_log("Paystack callback error: " . $e->getMessage());
    http_response_code(500);
    die('Payment verification error occurred');
}
?>
