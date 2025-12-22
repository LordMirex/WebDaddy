<?php
/**
 * Paystack Integration - Shared Hosting Compatible
 * Uses Redirect + Verify Flow (no webhooks required)
 * Works on shared hosting with proper User-Agent headers
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Initialize a payment with Paystack
 * Returns authorization URL for redirect flow
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
        'callback_url' => $orderData['callback_url'] ?? (defined('SITE_URL') ? SITE_URL : '') . '/paystack-callback.php',
        'metadata' => [
            'order_id' => $orderData['order_id'],
            'customer_name' => $orderData['customer_name'] ?? '',
            'custom_fields' => []
        ]
    ];
    
    $response = paystackApiCall($url, $fields, 'POST');
    
    if ($response && isset($response['status']) && $response['status']) {
        // Store payment record
        $db = getDb();
        
        $checkStmt = $db->prepare("SELECT id FROM payments WHERE pending_order_id = ?");
        $checkStmt->execute([$orderData['order_id']]);
        $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingPayment) {
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
        
        logPaymentEvent('initialize', 'paystack', 'success', $orderData['order_id'], null, $fields, $response);
        
        return [
            'success' => true,
            'reference' => $fields['reference'],
            'access_code' => $response['data']['access_code'],
            'authorization_url' => $response['data']['authorization_url']
        ];
    }
    
    logPaymentEvent('initialize', 'paystack', 'failed', $orderData['order_id'], null, $fields, $response);
    
    return [
        'success' => false,
        'message' => isset($response['message']) ? $response['message'] : 'Payment initialization failed'
    ];
}

/**
 * Verify a payment with Paystack (via callback, not webhook)
 * This is called when user is redirected back from Paystack
 */
function verifyPayment($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
    
    $response = paystackApiCall($url, null, 'GET');
    
    if ($response && isset($response['status']) && $response['status'] && 
        isset($response['data']['status']) && $response['data']['status'] === 'success') {
        
        // Payment successful
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed',
                amount_paid = ?,
                paystack_response = ?,
                payment_verified_at = datetime('now')
            WHERE paystack_reference = ?
        ");
        $stmt->execute([
            $response['data']['amount'] / 100, // Convert from kobo
            json_encode($response['data']),
            $reference
        ]);
        
        logPaymentEvent('verify', 'paystack', 'success', null, $reference, [], $response);
        
        return [
            'success' => true,
            'amount' => $response['data']['amount'] / 100,
            'status' => $response['data']['status'],
            'reference' => $reference
        ];
    }
    
    logPaymentEvent('verify', 'paystack', 'failed', null, $reference, [], $response);
    
    return [
        'success' => false,
        'message' => isset($response['message']) ? $response['message'] : 'Payment verification failed'
    ];
}

/**
 * Make API call to Paystack
 * Includes User-Agent header for shared hosting compatibility
 */
function paystackApiCall($url, $fields = null, $method = 'POST') {
    $secretKey = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : getenv('PAYSTACK_SECRET_KEY');
    
    if (!$secretKey) {
        error_log('Paystack error: Secret key not configured');
        return null;
    }
    
    $ch = curl_init();
    
    $headers = [
        "Authorization: Bearer {$secretKey}",
        "Content-Type: application/json",
        "Cache-Control: no-cache",
        // CRITICAL: Add User-Agent header for shared hosting compatibility
        // Many shared hosts block CURL requests without a proper User-Agent
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    if ($method === 'POST' && $fields) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Paystack CURL Error: " . $curlError);
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("Paystack API Error (HTTP $httpCode): " . substr($response, 0, 200));
        return null;
    }
    
    $result = json_decode($response, true);
    return $result;
}

/**
 * Generate unique payment reference
 */
function generatePaymentReference() {
    return 'ORDER-' . time() . '-' . mt_rand(100000, 999999);
}

/**
 * Log payment events for debugging
 */
function logPaymentEvent($action, $method, $status, $orderId = null, $reference = null, $request = [], $response = []) {
    $db = getDb();
    
    $logData = [
        'action' => $action,
        'method' => $method,
        'status' => $status,
        'order_id' => $orderId,
        'reference' => $reference,
        'request' => json_encode($request),
        'response' => json_encode($response),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $stmt = $db->prepare("
        INSERT INTO payment_logs (
            action, payment_method, status, order_id, reference, request_data, response_data, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([
        $action, $method, $status, $orderId, $reference,
        $logData['request'], $logData['response']
    ]);
    
    // Also log to error.log for debugging
    error_log(json_encode($logData));
}
?>
