<?php
/**
 * Customer password login
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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// Rate limit: 5 login attempts per 15 minutes per email
if (!checkLoginRateLimit($email)) {
    logSecurityEvent('login_rate_limited', ['email' => $email]);
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => 'Too many login attempts. Please try again in 15 minutes.'
    ]);
    exit;
}

$customer = getCustomerByEmail($email);

if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

if (empty($customer['password_hash'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'No password set. Please use OTP verification.',
        'needs_otp' => true
    ]);
    exit;
}

if (!password_verify($password, $customer['password_hash'])) {
    logCustomerActivity($customer['id'], 'login_failed', 'Invalid password attempt');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

if ($customer['status'] === 'suspended') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account suspended. Please contact support.']);
    exit;
}

$sessionResult = createCustomerSession($customer['id']);
if (!$sessionResult['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create session']);
    exit;
}

$db = getDb();
$db->prepare("UPDATE customers SET last_login_at = datetime('now') WHERE id = ?")->execute([$customer['id']]);

logCustomerActivity($customer['id'], 'login_success', 'Password login successful');

$_SESSION['customer_id'] = $customer['id'];
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
        'id' => $customer['id'],
        'email' => $customer['email'],
        'username' => $customer['username'],
        'whatsapp_number' => $customer['whatsapp_number'] ?? ''
    ]
]);
