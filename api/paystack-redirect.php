<?php
/**
 * Paystack Redirect Payment Handler (CSP-proof Alternative)
 * This endpoint redirects to Paystack's hosted checkout - completely bypasses CSP issues
 * Usage: Server-side redirect, no JavaScript SDK needed
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paystack.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? 0;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

// Get order details
$db = getDb();
$stmt = $db->prepare("SELECT id, customer_email, final_amount FROM pending_orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Initialize payment with Paystack
$paymentData = [
    'order_id' => $orderId,
    'email' => $order['customer_email'],
    'amount' => $order['final_amount'],
    'callback_url' => SITE_URL . '/api/paystack-verify.php'
];

$result = initializePayment($paymentData);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Payment initialization failed']);
    exit;
}

// Return the redirect URL - client will use window.location to redirect
// This is a SERVER-SIDE redirect, so CSP doesn't block it
echo json_encode([
    'success' => true,
    'redirect_url' => $result['authorization_url'],
    'order_id' => $orderId
]);
