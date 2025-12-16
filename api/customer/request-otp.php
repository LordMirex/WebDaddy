<?php
/**
 * Request OTP for email verification at checkout
 * Sends EMAIL OTP via Resend API
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_otp.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$type = $input['type'] ?? 'email_verify';

// Rate limit: 3 OTP requests per hour per email (applied after email validation)
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if (!checkOTPRateLimit($email)) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many OTP requests. Please wait before trying again.'
        ]);
        exit;
    }
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

if ($type !== 'email_verify') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP type for checkout']);
    exit;
}

// CHECK: Email must exist as a registered customer in database
$db = getDb();
$checkCustomer = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
$checkCustomer->execute([$email]);
$existingCustomerId = $checkCustomer->fetchColumn();

if (!$existingCustomerId) {
    // Email not found in database - reject OTP request
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'No account found with this email address. Please create an account first or contact support.'
    ]);
    exit;
}

$result = sendCheckoutEmailOTP($email);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email',
        'expires_in' => $result['expires_in']
    ]);
} else {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $result['error']
    ]);
}
