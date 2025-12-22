<?php
/**
 * Paystack Webhook Verification Handler
 * Verifies payment status and updates order accordingly
 * 
 * NOTE: On shared hosting, this endpoint may not receive webhooks from Paystack
 * In such cases, payments should be verified manually or via manual payment confirmation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/delivery.php';

// Set JSON response type
header('Content-Type: application/json');

// CRITICAL: Log all webhook attempts for debugging
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
];
error_log('PAYSTACK WEBHOOK ATTEMPT: ' . json_encode($logEntry));

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify Paystack signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');

$hash = hash_hmac('sha512', $body, PAYSTACK_SECRET_KEY);

if ($hash !== $signature) {
    error_log('PAYSTACK: Invalid signature');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid signature']);
    exit;
}

$event = json_decode($body, true);

if ($event['event'] !== 'charge.success') {
    echo json_encode(['success' => true, 'message' => 'Event acknowledged but not processed']);
    exit;
}

// Payment successful - update order
$reference = $event['data']['reference'] ?? '';
$orderId = null;

// Extract order ID from reference (format: ORDER-{orderId}-{timestamp})
if (preg_match('/ORDER-(\d+)-/', $reference, $matches)) {
    $orderId = (int)$matches[1];
}

if (!$orderId) {
    error_log('PAYSTACK: Could not extract order ID from reference: ' . $reference);
    echo json_encode(['success' => true, 'message' => 'Payment recorded but order not found']);
    exit;
}

$db = getDb();

try {
    // Get order
    $stmt = $db->prepare("SELECT id, customer_id, customer_email, status FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        error_log("PAYSTACK: Order #$orderId not found");
        echo json_encode(['success' => true, 'message' => 'Order not found']);
        exit;
    }
    
    // Only update if order is still pending
    if ($order['status'] !== 'pending') {
        error_log("PAYSTACK: Order #$orderId already has status: {$order['status']}");
        echo json_encode(['success' => true, 'message' => 'Order already processed']);
        exit;
    }
    
    // Update order status to paid
    $updateStmt = $db->prepare("UPDATE pending_orders SET status = 'paid', payment_verified_at = datetime('now') WHERE id = ?");
    $updateStmt->execute([$orderId]);
    
    // Record payment
    $paymentStmt = $db->prepare("
        INSERT INTO payments (pending_order_id, customer_id, amount, reference, status, payment_method, verified_at)
        SELECT id, customer_id, final_amount, ?, 'completed', 'paystack', datetime('now')
        FROM pending_orders WHERE id = ?
    ");
    $paymentStmt->execute([$reference, $orderId]);
    
    // Send payment confirmation email to customer
    if ($order['customer_email']) {
        require_once __DIR__ . '/includes/email_queue.php';
        require_once __DIR__ . '/includes/functions.php';
        
        // Queue the payment confirmation email
        addEmailToQueue([
            'to' => $order['customer_email'],
            'subject' => 'Payment Confirmed - Order #' . $orderId,
            'template' => 'payment_confirmed',
            'data' => [
                'order_id' => $orderId,
                'customer_name' => $order['customer_id'],
                'amount' => $event['data']['amount'] / 100
            ]
        ]);
        
        processEmailQueue();
    }
    
    error_log("✅ PAYSTACK: Payment verified for Order #$orderId");
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    error_log('PAYSTACK: Verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
