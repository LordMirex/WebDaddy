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

// Global error handler to prevent 500 errors with no response
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("OTP Verify Error [$errno]: $errstr in $errfile:$errline");
    return false;
});

set_exception_handler(function($e) {
    error_log("OTP Verify Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});

try {
    // CRITICAL: Start session before setting any session variables
    startSecureSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No input received']);
        exit;
    }

    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

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
    
    // CRITICAL: Migrate cart items from session to customer
    // This ensures cart persists even if session ID changes
    $sessionId = session_id();
    if ($sessionId && $customerId) {
        $db = getDb();
        $stmt = $db->prepare("UPDATE cart_items SET customer_id = ? WHERE session_id = ? AND customer_id IS NULL");
        $stmt->execute([$customerId, $sessionId]);
    }

    // Note: createCustomerSession already sets the customer_token cookie with proper settings

    // Determine if this is a new user (account_complete = 0 or no password)
    $isNewUser = empty($customer['password_hash']) || ($customer['account_complete'] ?? 0) == 0;
    $accountComplete = (int)($customer['account_complete'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $customerId,
            'email' => $customer['email'],
            'username' => $customer['username'] ?? null,
            'whatsapp_number' => $customer['whatsapp_number'] ?? '',
            'account_complete' => $accountComplete
        ],
        'customer_id' => $customerId,
        'username' => $customer['username'] ?? null,
        'isNewUser' => $isNewUser,
        'needs_setup' => $customer['status'] === 'pending_setup' || !$accountComplete,
        'account_complete' => $accountComplete,
        'registration_step' => $customer['registration_step']
    ]);

} catch (PDOException $e) {
    error_log("OTP Verify DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("OTP Verify Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
