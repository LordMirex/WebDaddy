<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/paystack.php';

header('Content-Type: application/json');

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = intval($input['order_id'] ?? 0);
$action = $input['action'] ?? 'pay_with_paystack';

if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

$db = getDb();

// Verify order belongs to customer
$stmt = $db->prepare("SELECT id, customer_id, final_amount, customer_email, status FROM pending_orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order['customer_id'] !== $customer['id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Check order status - must be pending or failed for retry
if ($order['status'] !== 'pending' && $order['status'] !== 'failed') {
    echo json_encode(['success' => false, 'error' => 'This order cannot be paid at this time']);
    exit;
}

// Initialize Paystack payment
try {
    $amount = (int)($order['final_amount'] * 100); // Convert to kobo
    $email = $order['customer_email'];
    
    // Create payment via Paystack API
    $paystackUrl = 'https://api.paystack.co/transaction/initialize';
    
    $paymentData = [
        'email' => $email,
        'amount' => $amount,
        'reference' => 'ORDER-' . $orderId . '-' . time(),
        'metadata' => [
            'order_id' => $orderId,
            'customer_id' => $customer['id'],
            'order_amount' => $order['final_amount']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $paystackUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && $responseData['status'] === true) {
        // Store payment reference in database for later webhook verification
        $paymentStmt = $db->prepare("
            INSERT INTO payments (pending_order_id, customer_id, amount, reference, status, payment_method, created_at)
            VALUES (?, ?, ?, ?, 'pending', 'paystack', datetime('now'))
        ");
        $paymentStmt->execute([$orderId, $customer['id'], $order['final_amount'], $paymentData['metadata']['reference']]);
        
        echo json_encode([
            'success' => true,
            'authorization_url' => $responseData['data']['authorization_url'],
            'access_code' => $responseData['data']['access_code'],
            'reference' => $responseData['data']['reference']
        ]);
    } else {
        error_log("Paystack API Error for Order #$orderId: " . json_encode($responseData));
        
        // For shared hosting where Paystack might be blocked
        // Provide fallback message
        if ($httpCode >= 500 || !$response) {
            echo json_encode([
                'success' => false,
                'error' => 'Payment service temporarily unavailable. Please try the bank transfer option or contact support via WhatsApp.',
                'fallback_available' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $responseData['message'] ?? 'Failed to initialize payment. Please try again.'
            ]);
        }
    }
} catch (Exception $e) {
    error_log("Payment initialization error for Order #$orderId: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your payment. Please try again or use bank transfer.'
    ]);
}
?>
