<?php
/**
 * Paystack Integration
 * Handles payment initialization, verification, and webhook processing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Initialize a payment with Paystack
 */
function initializePayment($orderData) {
    $secretKey = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : getenv('PAYSTACK_SECRET_KEY');
    if (!$secretKey) {
        return [
            'success' => false,
            'message' => 'Paystack secret key not configured'
        ];
    }
    
    $url = "https://api.paystack.co/transaction/initialize";
    
    $fields = [
        'email' => $orderData['email'],
        'amount' => $orderData['amount'] * 100, // Convert to kobo
        'currency' => $orderData['currency'] ?? 'NGN',
        'reference' => generatePaymentReference(),
        'callback_url' => $orderData['callback_url'] ?? (defined('SITE_URL') ? SITE_URL : '') . '/cart-checkout.php',
        'metadata' => [
            'order_id' => $orderData['order_id'],
            'customer_name' => $orderData['customer_name'] ?? '',
            'custom_fields' => []
        ]
    ];
    
    $response = paystackApiCall($url, $fields);
    
    if ($response && isset($response['status']) && $response['status']) {
        // Store payment record - check if exists first to avoid constraint violation
        $db = getDb();
        
        // Check if payment already exists for this order
        $checkStmt = $db->prepare("SELECT id FROM payments WHERE pending_order_id = ?");
        $checkStmt->execute([$orderData['order_id']]);
        $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingPayment) {
            // Update existing payment record
            $stmt = $db->prepare("
                UPDATE payments 
                SET amount_requested = ?, currency = ?,
                    paystack_reference = ?, paystack_access_code = ?, 
                    paystack_authorization_url = ?, status = 'pending',
                    updated_at = CURRENT_TIMESTAMP
                WHERE pending_order_id = ?
            ");
            $stmt->execute([
                $orderData['amount'],
                $fields['currency'],
                $fields['reference'],
                $response['data']['access_code'],
                $response['data']['authorization_url'],
                $orderData['order_id']
            ]);
        } else {
            // Insert new payment record
            $stmt = $db->prepare("
                INSERT INTO payments (
                    pending_order_id, payment_method, amount_requested, currency,
                    paystack_reference, paystack_access_code, paystack_authorization_url
                ) VALUES (?, 'paystack', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderData['order_id'],
                $orderData['amount'],
                $fields['currency'],
                $fields['reference'],
                $response['data']['access_code'],
                $response['data']['authorization_url']
            ]);
        }
        
        // Log event
        logPaymentEvent('initialize', 'paystack', 'success', $orderData['order_id'], null, $fields, $response);
        
        return [
            'success' => true,
            'reference' => $fields['reference'],
            'access_code' => $response['data']['access_code'],
            'authorization_url' => $response['data']['authorization_url']
        ];
    }
    
    // Log failure
    logPaymentEvent('initialize', 'paystack', 'failed', $orderData['order_id'], null, $fields, $response);
    
    return [
        'success' => false,
        'message' => isset($response['message']) ? $response['message'] : 'Payment initialization failed'
    ];
}

/**
 * Verify a payment with Paystack
 */
function verifyPayment($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . $reference;
    
    $response = paystackApiCall($url, null, 'GET');
    
    if ($response && isset($response['status']) && $response['status'] && isset($response['data']['status']) && $response['data']['status'] === 'success') {
        // Update payment record
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed',
                amount_paid = ?,
                paystack_response = ?,
                payment_verified_at = datetime('now', '+1 hour')
            WHERE paystack_reference = ?
        ");
        $stmt->execute([
            $response['data']['amount'] / 100, // Convert from kobo
            json_encode($response['data']),
            $reference
        ]);
        
        // Get payment details
        $payment = getPaymentByReference($reference);
        
        // Log event
        logPaymentEvent('verify', 'paystack', 'success', $payment['pending_order_id'], $payment['id'], null, $response);
        
        return [
            'success' => true,
            'order_id' => $payment['pending_order_id'],
            'amount' => $response['data']['amount'] / 100
        ];
    }
    
    // Log failure
    logPaymentEvent('verify', 'paystack', 'failed', null, null, ['reference' => $reference], $response);
    
    return [
        'success' => false,
        'message' => isset($response['message']) ? $response['message'] : 'Payment verification failed'
    ];
}

/**
 * Make API call to Paystack
 */
function paystackApiCall($url, $fields = null, $method = 'POST') {
    $secretKey = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : getenv('PAYSTACK_SECRET_KEY');
    if (!$secretKey) {
        return ['status' => false, 'message' => 'Paystack secret key not configured'];
    }
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST' && $fields) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => false, 'message' => 'cURL Error: ' . $error];
    }
    
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Generate unique payment reference
 */
function generatePaymentReference() {
    return 'WDE_' . time() . '_' . bin2hex(random_bytes(8));
}

/**
 * Get payment by reference
 */
function getPaymentByReference($reference) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM payments WHERE paystack_reference = ?");
    $stmt->execute([$reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get payment by order ID
 */
function getPaymentByOrderId($orderId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM payments WHERE pending_order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Log payment event
 */
function logPaymentEvent($eventType, $provider, $status, $orderId = null, $paymentId = null, $request = null, $response = null) {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO payment_logs (
            pending_order_id, payment_id, event_type, provider, status,
            request_data, response_data, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $paymentId,
        $eventType,
        $provider,
        $status,
        $request ? json_encode($request) : null,
        $response ? json_encode($response) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
