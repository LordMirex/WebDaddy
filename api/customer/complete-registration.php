<?php
/**
 * Complete Registration API
 * Final step: Username + Password + WhatsApp Number
 * No SMS verification - email-only
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';
$whatsappNumber = trim($input['whatsapp_number'] ?? '');

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

if (empty($username) || strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
    exit;
}

if (empty($password) || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
    exit;
}

if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid WhatsApp number is required']);
    exit;
}

try {
    $db = getDb();
    
    // Find customer by email
    $customer = getCustomerByEmail($email);
    
    if (!$customer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please verify your email first']);
        exit;
    }
    
    // Check if already has password (already registered)
    if (!empty($customer['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Account already has a password. Please login instead.']);
        exit;
    }
    
    // Check username uniqueness
    $usernameCheck = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
    $usernameCheck->execute([$username, $customer['id']]);
    if ($usernameCheck->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already taken. Please choose another.']);
        exit;
    }
    
    // Complete registration
    $result = completeRegistrationWithWhatsApp($customer['id'], $username, $password, $whatsappNumber);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }
    
    // Create session for the user
    $sessionResult = createCustomerSession($customer['id']);
    
    if ($sessionResult['success']) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_session_token'] = $sessionResult['token'];
        
        setcookie('customer_session', $sessionResult['token'], [
            'expires' => strtotime('+1 year'),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    // Send welcome email
    sendCustomerWelcomeEmail($email, $username);
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully!',
        'customer' => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'username' => $username
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Complete Registration DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Complete Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
