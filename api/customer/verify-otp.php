<?php
/**
 * Verify OTP and create/login customer session
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_otp.php';
require_once __DIR__ . '/../../includes/customer_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$type = $input['type'] ?? 'email_verify';

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and code are required']);
    exit;
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid code format']);
    exit;
}

$verifyResult = verifyCheckoutEmailOTP($email, $code);

if (!$verifyResult['success']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $verifyResult['error']
    ]);
    exit;
}

$existingCustomer = getCustomerByEmail($email);

if ($existingCustomer) {
    $customerId = $existingCustomer['id'];
    $customer = $existingCustomer;
} else {
    $createResult = createCustomerAccount($email);
    if (!$createResult['success'] && !$createResult['customer_id']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create account']);
        exit;
    }
    $customerId = $createResult['customer_id'];
    $customer = getCustomerById($customerId);
}

$sessionResult = createCustomerSession($customerId);
if (!$sessionResult['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create session']);
    exit;
}

$_SESSION['customer_id'] = $customerId;
$_SESSION['customer_session_token'] = $sessionResult['token'];

setcookie('customer_session', $sessionResult['token'], [
    'expires' => strtotime('+1 year'),
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

echo json_encode([
    'success' => true,
    'customer' => [
        'id' => $customerId,
        'email' => $customer['email'],
        'full_name' => $customer['full_name'],
        'phone' => $customer['phone'] ?: $customer['whatsapp_number'],
        'username' => $customer['username'] ?? null
    ],
    'needs_setup' => $customer['status'] === 'pending_setup',
    'registration_step' => $customer['registration_step']
]);
