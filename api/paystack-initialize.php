<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/paystack.php';

header('Content-Type: application/json');

startSecureSession();

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['name']) || empty($input['email']) || empty($input['phone'])) {
        throw new Exception('Missing required fields');
    }
    
    // Get cart
    $cart = getCart();
    if (empty($cart)) {
        throw new Exception('Cart is empty');
    }
    
    $affiliateCode = $input['affiliate_code'] ?? null;
    $totals = getCartTotal(null, $affiliateCode);
    
    // Create pending order
    $orderId = createPendingOrder([
        'customer_name' => sanitizeInput($input['name']),
        'customer_email' => sanitizeInput($input['email']),
        'customer_phone' => sanitizeInput($input['phone']),
        'business_name' => sanitizeInput($input['business_name'] ?? ''),
        'payment_method' => 'paystack',
        'affiliate_code' => $affiliateCode,
        'total_amount' => $totals['final']
    ], $cart);
    
    if (!$orderId) {
        throw new Exception('Failed to create order');
    }
    
    // Initialize Paystack payment
    $paymentData = initializePayment([
        'order_id' => $orderId,
        'customer_name' => $input['name'],
        'email' => $input['email'],
        'amount' => $totals['final'],
        'currency' => 'NGN',
        'callback_url' => SITE_URL . '/cart-checkout.php?confirmed=' . $orderId
    ]);
    
    if (!$paymentData['success']) {
        throw new Exception($paymentData['message']);
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'reference' => $paymentData['reference'],
        'access_code' => $paymentData['access_code'],
        'authorization_url' => $paymentData['authorization_url'],
        'amount' => $totals['final'],
        'public_key' => PAYSTACK_PUBLIC_KEY
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
