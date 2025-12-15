<?php
/**
 * Verify Phone OTP and complete registration
 * Final step of registration - marks account as complete
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limit: 10 verification attempts per 10 minutes per IP
$clientIP = getClientIP();
enforceRateLimit($clientIP, 'verify_phone_otp', 10, 600, 'Too many verification attempts. Please wait.');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$code = trim($input['code'] ?? '');

// Validate
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

if (empty($phone) || strlen($phone) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid phone number is required']);
    exit;
}

if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid verification code format']);
    exit;
}

try {
    $db = getDb();
    
    // Find customer by email
    $stmt = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    $customerId = $customer['id'];
    
    // Clean phone number
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Verify OTP
    $otpStmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND phone = ?
        AND otp_code = ?
        AND otp_type = 'phone_verify'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $otpStmt->execute([$customerId, $cleanPhone, $code]);
    $otp = $otpStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        // Increment attempt counter on the most recent OTP
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE customer_id = ? AND phone = ? AND otp_type = 'phone_verify' AND is_used = 0
        ")->execute([$customerId, $cleanPhone]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired verification code']);
        exit;
    }
    
    // Mark OTP as used
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    // Complete registration - update customer
    $updateStmt = $db->prepare("
        UPDATE customers 
        SET phone = ?, 
            phone_verified = 1,
            phone_verified_at = datetime('now'),
            registration_step = 0,
            status = 'active',
            account_complete = 1,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $updateStmt->execute([$cleanPhone, $customerId]);
    
    // Create session for the user
    startSecureSession();
    $sessionResult = createCustomerSession($customerId);
    
    if ($sessionResult['success']) {
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_session_token'] = $sessionResult['token'];
        
        setcookie('customer_session', $sessionResult['token'], [
            'expires' => strtotime('+1 year'),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    // Log activity
    if (function_exists('logCustomerActivity')) {
        logCustomerActivity($customerId, 'registration_complete', 'Phone verified, registration complete');
    }
    
    // Send welcome email if available
    if (function_exists('sendCustomerWelcomeEmail')) {
        $customerData = getCustomerById($customerId);
        if ($customerData) {
            sendCustomerWelcomeEmail($customerData['email'], $customerData['username']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully!',
        'account_complete' => true
    ]);
    
} catch (PDOException $e) {
    error_log("Verify Phone OTP DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Verify Phone OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
