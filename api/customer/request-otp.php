<?php
/**
 * Request OTP for email verification at checkout
 * Only sends EMAIL OTP (not SMS) at checkout
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_otp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$type = $input['type'] ?? 'email_verify';

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
