<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$phone = trim($input['phone'] ?? '');
$fullName = trim($input['full_name'] ?? '');

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

$db = getDb();

$customer = getCustomerByEmail($email);

if (!$customer) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please verify your email first']);
    exit;
}

if (!empty($customer['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Account already has a password. Please login instead.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$cleanPhone = !empty($phone) ? preg_replace('/[^0-9+]/', '', $phone) : null;

$stmt = $db->prepare("
    UPDATE customers 
    SET password_hash = ?,
        full_name = COALESCE(?, full_name),
        whatsapp_number = COALESCE(?, whatsapp_number),
        phone = COALESCE(?, phone),
        status = 'active',
        registration_step = 0,
        password_changed_at = datetime('now'),
        updated_at = datetime('now')
    WHERE id = ?
");
$stmt->execute([$passwordHash, $fullName ?: null, $cleanPhone, $cleanPhone, $customer['id']]);

$sessionResult = createCustomerSession($customer['id']);
if ($sessionResult['success']) {
    startSecureSession();
    $_SESSION['customer_id'] = $customer['id'];
    $_SESSION['customer_session_token'] = $sessionResult['token'];
    
    setcookie('customer_session', $sessionResult['token'], [
        'expires' => strtotime('+1 year'),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (!empty($fullName)) {
    sendCustomerWelcomeEmail($email, $fullName);
}

logCustomerActivity($customer['id'], 'registration_complete', 'Account registration completed with password');

echo json_encode([
    'success' => true,
    'message' => 'Account created successfully',
    'customer' => [
        'id' => $customer['id'],
        'email' => $customer['email'],
        'full_name' => $fullName ?: $customer['full_name']
    ]
]);
