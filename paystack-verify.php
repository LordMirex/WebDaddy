<?php
/**
 * Paystack Webhook Verification Handler
 * Verifies payment status and updates order accordingly
 * 
 * SHARED HOSTING CONFIGURATION:
 * 1. Upload this file to your server root
 * 2. Set webhook URL in Paystack Dashboard: https://yourdomain.com/paystack-verify.php
 * 3. Ensure HTTPS is enabled (Paystack requires SSL)
 * 4. Test with a live transaction
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/delivery.php';

// Set JSON response type
header('Content-Type: application/json');

// Read request body once (php://input can only be read once)
$body = file_get_contents('php://input');

// Paystack webhook IPs (optional extra security - uncomment to enable)
// Note: For X-Forwarded-For, we extract only the first IP (client IP) from comma-separated list
// $allowedIps = ['52.31.139.75', '52.49.173.169', '52.214.14.220'];
// $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
// $clientIp = $forwardedFor ? trim(explode(',', $forwardedFor)[0]) : ($_SERVER['REMOTE_ADDR'] ?? '');
// if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
//     error_log('PAYSTACK: Blocked request from unauthorized IP: ' . $clientIp);
//     http_response_code(403);
//     exit;
// }

// CRITICAL: Log all webhook attempts for debugging
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'headers' => getallheaders(),
    'body' => $body,
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
        
        $amount = number_format($event['data']['amount'] / 100, 2);
        $subject = 'Payment Confirmed - Order #' . $orderId;
        $body = "Your payment of ₦{$amount} for Order #{$orderId} has been confirmed. Your digital products are now available for download in your account.";
        $htmlBody = "<h2>Payment Confirmed!</h2><p>Your payment of <strong>₦{$amount}</strong> for Order #{$orderId} has been confirmed.</p><p>Your digital products are now available for download in your account.</p><p>Thank you for your purchase!</p>";
        
        queueEmail($order['customer_email'], 'payment_confirmed', $subject, $body, $htmlBody, $orderId);
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
