<?php
/**
 * Send SMS OTP to phone number for verification
 * Uses Termii API for SMS delivery
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

// Include Termii if available
if (file_exists(__DIR__ . '/../../includes/termii.php')) {
    require_once __DIR__ . '/../../includes/termii.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limit: 5 SMS per 5 minutes per IP
$clientIP = getClientIP();
enforceRateLimit($clientIP, 'send_phone_otp', 5, 300, 'Too many SMS requests. Please wait a few minutes.');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

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

try {
    $db = getDb();
    
    // Find customer by email
    $stmt = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found. Please start registration again.']);
        exit;
    }
    
    // Clean phone number
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP in database
    $insertStmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, phone, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, ?, 'phone_verify', 'sms', ?)
    ");
    $insertStmt->execute([
        $customer['id'],
        $email,
        $cleanPhone,
        $otpCode,
        $expiresAt
    ]);
    $otpId = $db->lastInsertId();
    
    // Send SMS via Termii (or fallback)
    $smsSent = false;
    $smsMessage = "Your WebDaddy Empire verification code is: $otpCode. Valid for 10 minutes.";
    
    if (function_exists('sendTermiiSMS')) {
        $smsSent = sendTermiiSMS($cleanPhone, $smsMessage);
    } elseif (function_exists('sendTermiiOTPSMS')) {
        $smsSent = sendTermiiOTPSMS($cleanPhone, $otpCode, $otpId);
    } else {
        // Fallback: Log OTP for development
        error_log("PHONE OTP for $cleanPhone: $otpCode (Termii not configured)");
        $smsSent = true; // Simulate success for dev
    }
    
    // Update OTP record
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ? WHERE id = ?")
       ->execute([$smsSent ? 1 : 0, $otpId]);
    
    if (!$smsSent) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send SMS. Please try again.']);
        exit;
    }
    
    // Log activity
    if (function_exists('logCustomerActivity')) {
        logCustomerActivity($customer['id'], 'phone_otp_sent', "Phone OTP sent to $cleanPhone");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your phone',
        'expires_in' => 600 // 10 minutes
    ]);
    
} catch (PDOException $e) {
    error_log("Send Phone OTP DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Send Phone OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
