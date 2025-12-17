<?php
/**
 * Customer Authentication System
 * 
 * Handles customer login, registration, password management
 * Email-only verification (SMS removed)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/customer_session.php';
require_once __DIR__ . '/mailer.php';

function checkCustomerEmail($email) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, email, password_hash, username, status, registration_step, account_complete FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['exists' => false];
    }
    
    return [
        'exists' => true,
        'has_password' => !empty($customer['password_hash']),
        'customer_id' => $customer['id'],
        'username' => $customer['username'],
        'status' => $customer['status'],
        'registration_step' => $customer['registration_step'],
        'account_complete' => (int)($customer['account_complete'] ?? 0)
    ];
}

function getCustomerById($customerId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerByEmail($email) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate unique username from email
 * Format: email-local-part + random suffix
 */
function generateUsernameFromEmail($email) {
    $db = getDb();
    
    $baseName = explode('@', strtolower($email))[0];
    $baseName = preg_replace('/[^a-z0-9_]/', '', $baseName);
    $baseName = substr($baseName, 0, 15);
    
    if (empty($baseName)) {
        $baseName = 'user';
    }
    
    $suffix = random_int(100, 999);
    $username = $baseName . '_' . $suffix;
    
    $attempts = 0;
    while ($attempts < 5) {
        $stmt = $db->prepare("SELECT id FROM customers WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
        $suffix = random_int(100, 999);
        $username = $baseName . '_' . $suffix;
        $attempts++;
    }
    
    return $baseName . '_' . substr(time(), -4);
}

function createCustomerAccount($email, $fullName = null, $whatsappNumber = null) {
    $db = getDb();
    
    $check = checkCustomerEmail($email);
    if ($check['exists']) {
        return ['success' => false, 'message' => 'Account already exists', 'customer_id' => $check['customer_id']];
    }
    
    $username = generateUsernameFromEmail($email);
    
    // Clean WhatsApp number if provided
    $cleanWhatsapp = $whatsappNumber ? preg_replace('/[^0-9+]/', '', $whatsappNumber) : null;
    
    $stmt = $db->prepare("
        INSERT INTO customers (email, username, full_name, whatsapp_number, status, email_verified, registration_step, account_complete)
        VALUES (?, ?, ?, ?, 'pending_setup', 1, 1, 0)
    ");
    $stmt->execute([$email, $username, $fullName, $cleanWhatsapp]);
    $customerId = $db->lastInsertId();
    
    logCustomerActivity($customerId, 'account_created', 'Account created via checkout OTP verification');
    
    return [
        'success' => true,
        'customer_id' => $customerId,
        'username' => $username,
        'needs_setup' => true,
        'registration_step' => 1,
        'account_complete' => false,
        'message' => 'Account created. Please complete setup.'
    ];
}

function processRegistrationStep1($customerId, $data) {
    $db = getDb();
    
    $usernameCheck = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
    $usernameCheck->execute([$data['username'], $customerId]);
    if ($usernameCheck->fetch()) {
        return ['success' => false, 'error' => 'Username already taken'];
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'error' => 'Passwords do not match'];
    }
    
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        UPDATE customers 
        SET username = ?, 
            password_hash = ?, 
            password_changed_at = datetime('now'),
            registration_step = 2,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$data['username'], $hash, $customerId]);
    
    logCustomerActivity($customerId, 'registration_step1', 'Username and password set');
    
    return ['success' => true, 'next_step' => 2];
}

/**
 * Complete registration with WhatsApp number
 * This is now the final step (no SMS verification)
 */
function completeRegistrationWithWhatsApp($customerId, $username, $password, $whatsappNumber) {
    $db = getDb();
    
    // Validate username uniqueness
    $usernameCheck = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
    $usernameCheck->execute([$username, $customerId]);
    if ($usernameCheck->fetch()) {
        return ['success' => false, 'error' => 'Username already taken'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
        return ['success' => false, 'error' => 'Please enter a valid WhatsApp number'];
    }
    
    $cleanWhatsapp = preg_replace('/[^0-9+]/', '', $whatsappNumber);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET username = ?,
            password_hash = ?, 
            password_changed_at = datetime('now'),
            whatsapp_number = ?,
            registration_step = 0,
            status = 'active',
            account_complete = 1,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$username, $hash, $cleanWhatsapp, $customerId]);
    
    logCustomerActivity($customerId, 'registration_complete', 'Registration completed with WhatsApp number');
    
    return ['success' => true, 'message' => 'Account registration complete!'];
}

function customerLogin($email, $password) {
    $db = getDb();
    
    if (isCustomerRateLimited($email)) {
        return [
            'success' => false,
            'error' => 'Too many login attempts. Please try again later.',
            'rate_limited' => true
        ];
    }
    
    $stmt = $db->prepare("SELECT id, email, password_hash, full_name, status, registration_step FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        trackCustomerLoginAttempt($email);
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    if ($customer['status'] === 'suspended') {
        return ['success' => false, 'error' => 'Your account has been suspended. Please contact support.'];
    }
    
    if (empty($customer['password_hash'])) {
        return ['success' => false, 'error' => 'No password set. Please verify via OTP.', 'needs_otp' => true];
    }
    
    if (!password_verify($password, $customer['password_hash'])) {
        trackCustomerLoginAttempt($email);
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    clearCustomerLoginAttempts($email);
    
    $sessionResult = createCustomerSession($customer['id']);
    $token = $sessionResult['token'] ?? null;
    
    $db->prepare("UPDATE customers SET last_login_at = datetime('now') WHERE id = ?")
       ->execute([$customer['id']]);
    
    logCustomerActivity($customer['id'], 'login', 'Password login successful');
    
    return [
        'success' => true,
        'customer' => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'full_name' => $customer['full_name'],
            'registration_step' => $customer['registration_step']
        ],
        'token' => $token
    ];
}

function initiatePasswordRecovery($identifier) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM customers WHERE LOWER(email) = LOWER(?) AND status != 'suspended'");
    $stmt->execute([$identifier]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'No account found with this email'];
    }
    
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, 'password_reset', 'email', ?)
    ");
    $stmt->execute([
        $customer['id'], 
        $customer['email'], 
        $otpCode,
        $expiresAt
    ]);
    $otpId = $db->lastInsertId();
    
    $sent = sendRecoveryOTPEmail($customer['email'], $otpCode);
    $db->prepare("UPDATE customer_otp_codes SET email_sent = ? WHERE id = ?")
       ->execute([$sent ? 1 : 0, $otpId]);
    
    if (!$sent) {
        return ['success' => false, 'error' => 'Failed to send OTP. Please try again.'];
    }
    
    return [
        'success' => true,
        'message' => 'OTP sent to your email',
        'customer_id' => $customer['id'],
        'masked_contact' => maskEmail($customer['email'])
    ];
}

function verifyRecoveryOTP($customerId, $otpCode) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND otp_code = ?
        AND otp_type = 'password_reset'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE customer_id = ? AND otp_type = 'password_reset' AND is_used = 0
        ")->execute([$customerId]);
        
        return ['success' => false, 'error' => 'Invalid or expired OTP code'];
    }
    
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    $db->prepare("UPDATE customers SET reset_token = ?, reset_token_expires = ? WHERE id = ?")
       ->execute([$resetToken, $tokenExpires, $customerId]);
    
    logCustomerActivity($customerId, 'recovery_otp_verified', 'Recovery OTP verified');
    
    return [
        'success' => true,
        'reset_token' => $resetToken,
        'message' => 'OTP verified. You can now reset your password.'
    ];
}

function resetCustomerPassword($resetToken, $newPassword, $confirmPassword) {
    $db = getDb();
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'Passwords do not match'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    $stmt = $db->prepare("
        SELECT id FROM customers 
        WHERE reset_token = ? 
        AND reset_token_expires > datetime('now')
    ");
    $stmt->execute([$resetToken]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'Invalid or expired reset token'];
    }
    
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $db->prepare("
        UPDATE customers 
        SET password_hash = ?, 
            reset_token = NULL, 
            reset_token_expires = NULL,
            password_changed_at = datetime('now'),
            updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$hash, $customer['id']]);
    
    $db->prepare("
        UPDATE customer_sessions 
        SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'password_change'
        WHERE customer_id = ?
    ")->execute([$customer['id']]);
    
    logCustomerActivity($customer['id'], 'password_reset', 'Password reset successful');
    
    return ['success' => true, 'message' => 'Password reset successful. Please login with your new password.'];
}

function maskEmail($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    
    if (strlen($name) <= 2) {
        return $name[0] . '*@' . $domain;
    }
    
    return substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) . '@' . $domain;
}

if (!function_exists('maskPhoneNumber')) {
    function maskPhoneNumber($phone) {
        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($phone, -4);
    }
}

function isCustomerRateLimited($email) {
    return isRateLimited($email, 'customer', 5, 900);
}

function trackCustomerLoginAttempt($email) {
    return trackLoginAttempt($email, 'customer');
}

function clearCustomerLoginAttempts($email) {
    return clearLoginAttempts($email, 'customer');
}
