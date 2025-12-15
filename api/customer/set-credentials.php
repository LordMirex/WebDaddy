<?php
/**
 * Set customer credentials (Step 2 of registration)
 * Sets username, password, and WhatsApp number
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limit: 10 requests per minute per IP
$clientIP = getClientIP();
enforceRateLimit($clientIP, 'set_credentials', 10, 60, 'Too many requests. Please wait a moment.');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$whatsappNumber = trim($input['whatsapp_number'] ?? '');

// Validate required fields
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

if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid WhatsApp number is required']);
    exit;
}

try {
    $db = getDb();
    
    // Find customer by email
    $stmt = $db->prepare("SELECT id, status FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found. Please verify your email first.']);
        exit;
    }
    
    // Clean username (alphanumeric and underscore only)
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    $username = strtolower($username);
    
    // Check if username is already taken (by another customer)
    $checkStmt = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
    $checkStmt->execute([$username, $customer['id']]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'This username is already taken. Please choose another.']);
        exit;
    }
    
    // Clean WhatsApp number (keep only digits and +)
    $cleanWhatsApp = preg_replace('/[^0-9+]/', '', $whatsappNumber);
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update customer
    $updateStmt = $db->prepare("
        UPDATE customers 
        SET username = ?,
            password_hash = ?,
            whatsapp_number = ?,
            registration_step = 2,
            password_changed_at = datetime('now'),
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $updateStmt->execute([$username, $passwordHash, $cleanWhatsApp, $customer['id']]);
    
    // Log activity
    if (function_exists('logCustomerActivity')) {
        logCustomerActivity($customer['id'], 'credentials_set', 'Username, password, and WhatsApp set');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Credentials saved successfully',
        'next_step' => 3
    ]);
    
} catch (PDOException $e) {
    error_log("Set Credentials DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Set Credentials Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
