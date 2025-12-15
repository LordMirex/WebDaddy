<?php
/**
 * Complete account setup from modal (post-purchase)
 * Used when user completes checkout without full registration
 * Handles: Username, Password, WhatsApp, Phone verification
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Rate limit
$clientIP = getClientIP();
enforceRateLimit($clientIP, 'complete_setup', 10, 60, 'Too many requests. Please wait.');

$input = json_decode(file_get_contents('php://input'), true);

// Determine which step we're processing
$action = $input['action'] ?? 'complete';

try {
    $db = getDb();
    
    // Get current customer
    $customer = getCustomerById($customerId);
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    if ($action === 'credentials') {
        // Step 1: Save username and password
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        
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
        
        // Clean and validate username
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($username));
        
        // Check uniqueness
        $checkStmt = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $customerId]);
        if ($checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username already taken']);
            exit;
        }
        
        // Update
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("
            UPDATE customers 
            SET username = ?, password_hash = ?, password_changed_at = datetime('now'), updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$username, $hash, $customerId]);
        
        echo json_encode(['success' => true, 'message' => 'Credentials saved', 'next' => 'contact']);
        
    } elseif ($action === 'send_phone_otp') {
        // Send phone OTP
        $whatsappNumber = trim($input['whatsapp_number'] ?? '');
        $phone = trim($input['phone'] ?? '');
        
        if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid WhatsApp number is required']);
            exit;
        }
        
        if (empty($phone) || strlen($phone) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid phone number is required']);
            exit;
        }
        
        // Clean numbers
        $cleanWhatsApp = preg_replace('/[^0-9+]/', '', $whatsappNumber);
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Save WhatsApp number
        $db->prepare("UPDATE customers SET whatsapp_number = ?, updated_at = datetime('now') WHERE id = ?")
           ->execute([$cleanWhatsApp, $customerId]);
        
        // Generate and store OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $insertStmt = $db->prepare("
            INSERT INTO customer_otp_codes 
            (customer_id, email, phone, otp_code, otp_type, delivery_method, expires_at)
            VALUES (?, ?, ?, ?, 'phone_verify', 'sms', ?)
        ");
        $insertStmt->execute([$customerId, $customer['email'], $cleanPhone, $otpCode, $expiresAt]);
        
        // Send SMS (log for dev)
        error_log("PHONE OTP for $cleanPhone: $otpCode");
        
        echo json_encode(['success' => true, 'message' => 'Verification code sent']);
        
    } elseif ($action === 'verify_phone') {
        // Verify phone OTP and complete
        $phoneOtp = trim($input['phone_otp'] ?? '');
        $phone = trim($input['phone'] ?? '');
        
        if (strlen($phoneOtp) !== 6 || !ctype_digit($phoneOtp)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid code format']);
            exit;
        }
        
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
            LIMIT 1
        ");
        $otpStmt->execute([$customerId, $cleanPhone, $phoneOtp]);
        $otp = $otpStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otp) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired code']);
            exit;
        }
        
        // Mark OTP used
        $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
           ->execute([$otp['id']]);
        
        // Complete account
        $db->prepare("
            UPDATE customers 
            SET phone = ?, 
                phone_verified = 1, 
                phone_verified_at = datetime('now'),
                status = 'active',
                account_complete = 1,
                registration_step = 0,
                updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$cleanPhone, $customerId]);
        
        if (function_exists('logCustomerActivity')) {
            logCustomerActivity($customerId, 'account_setup_complete', 'Account setup completed via modal');
        }
        
        echo json_encode(['success' => true, 'message' => 'Account setup complete!', 'account_complete' => true]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Complete Setup DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Complete Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
