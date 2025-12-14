<?php
/**
 * Customer Authentication System
 * 
 * Handles customer login, registration, password management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/customer_session.php';
require_once __DIR__ . '/mailer.php';

function checkCustomerEmail($email) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, email, password_hash, full_name, status, registration_step FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['exists' => false];
    }
    
    return [
        'exists' => true,
        'has_password' => !empty($customer['password_hash']),
        'customer_id' => $customer['id'],
        'full_name' => $customer['full_name'],
        'status' => $customer['status'],
        'registration_step' => $customer['registration_step']
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

function createCustomerAccount($email, $fullName = null, $phone = null) {
    $db = getDb();
    
    $check = checkCustomerEmail($email);
    if ($check['exists']) {
        return ['success' => false, 'message' => 'Account already exists', 'customer_id' => $check['customer_id']];
    }
    
    $username = explode('@', $email)[0] . '_' . random_int(100, 999);
    
    $stmt = $db->prepare("
        INSERT INTO customers (email, username, full_name, phone, status, email_verified, registration_step)
        VALUES (?, ?, ?, ?, 'pending_setup', 1, 1)
    ");
    $stmt->execute([$email, $username, $fullName, $phone]);
    $customerId = $db->lastInsertId();
    
    logCustomerActivity($customerId, 'account_created', 'Account created via checkout OTP verification');
    
    return [
        'success' => true,
        'customer_id' => $customerId,
        'needs_setup' => true,
        'registration_step' => 1,
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

function processRegistrationStep2($customerId, $whatsappNumber) {
    $db = getDb();
    
    if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
        return ['success' => false, 'error' => 'Please enter a valid WhatsApp number'];
    }
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $whatsappNumber);
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET whatsapp_number = ?, 
            registration_step = 3,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$cleanNumber, $customerId]);
    
    logCustomerActivity($customerId, 'registration_step2', 'WhatsApp number set');
    
    return ['success' => true, 'next_step' => 3];
}

function verifyPhoneAndCompleteRegistration($customerId, $phoneNumber, $otpCode) {
    $db = getDb();
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND phone = ?
        AND otp_code = ?
        AND otp_type = 'phone_verify'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId, $cleanNumber, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE customer_id = ? AND phone = ? AND is_used = 0
        ")->execute([$customerId, $cleanNumber]);
        
        return ['success' => false, 'error' => 'Invalid or expired OTP code'];
    }
    
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET phone = ?, 
            phone_verified = 1,
            phone_verified_at = datetime('now'),
            registration_step = 0,
            status = 'active',
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$cleanNumber, $customerId]);
    
    logCustomerActivity($customerId, 'registration_complete', 'Phone verified, registration complete');
    
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
    
    $token = createCustomerSession($customer['id']);
    
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

function initiatePasswordRecovery($identifier, $method = 'email') {
    $db = getDb();
    
    if ($method === 'email') {
        $stmt = $db->prepare("SELECT * FROM customers WHERE LOWER(email) = LOWER(?) AND status != 'suspended'");
        $stmt->execute([$identifier]);
    } else {
        $stmt = $db->prepare("SELECT * FROM customers WHERE phone = ? AND phone_verified = 1 AND status != 'suspended'");
        $stmt->execute([$identifier]);
    }
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'No account found with this ' . $method];
    }
    
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, phone, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, ?, 'password_reset', ?, ?)
    ");
    $stmt->execute([
        $customer['id'], 
        $customer['email'], 
        $customer['phone'], 
        $otpCode,
        $method,
        $expiresAt
    ]);
    $otpId = $db->lastInsertId();
    
    if ($method === 'email') {
        $sent = sendRecoveryOTPEmail($customer['email'], $otpCode);
        $db->prepare("UPDATE customer_otp_codes SET email_sent = ? WHERE id = ?")
           ->execute([$sent ? 1 : 0, $otpId]);
    } else {
        $sent = sendTermiiOTP($customer['phone'], $otpCode, $otpId);
        $db->prepare("UPDATE customer_otp_codes SET sms_sent = ? WHERE id = ?")
           ->execute([$sent ? 1 : 0, $otpId]);
    }
    
    if (!$sent) {
        return ['success' => false, 'error' => 'Failed to send OTP. Please try again.'];
    }
    
    return [
        'success' => true,
        'message' => 'OTP sent to your ' . $method,
        'customer_id' => $customer['id'],
        'masked_contact' => $method === 'email' 
            ? maskEmail($customer['email']) 
            : maskPhoneNumber($customer['phone'])
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

function initiateEmailRecovery($phoneNumber) {
    $db = getDb();
    
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    $stmt = $db->prepare("SELECT * FROM customers WHERE phone = ? AND phone_verified = 1 AND status != 'suspended'");
    $stmt->execute([$cleanPhone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'No account found with this phone number'];
    }
    
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, phone, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, ?, 'login', 'sms', ?)
    ");
    $stmt->execute([$customer['id'], $customer['email'], $cleanPhone, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    $sent = sendTermiiOTP($cleanPhone, $otpCode, $otpId);
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ? WHERE id = ?")
       ->execute([$sent ? 1 : 0, $otpId]);
    
    if (!$sent) {
        return ['success' => false, 'error' => 'Failed to send SMS. Please try again.'];
    }
    
    return [
        'success' => true,
        'customer_id' => $customer['id'],
        'message' => 'SMS OTP sent to your phone',
        'masked_phone' => maskPhoneNumber($cleanPhone)
    ];
}

function verifyEmailRecoveryOTP($customerId, $otpCode) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND otp_code = ?
        AND otp_type = 'login'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        return ['success' => false, 'error' => 'Invalid or expired OTP code'];
    }
    
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    $customer = getCustomerById($customerId);
    
    logCustomerActivity($customerId, 'email_recovery', 'Email recovered via phone OTP');
    
    return [
        'success' => true,
        'email' => $customer['email'],
        'masked_email' => maskEmail($customer['email']),
        'message' => 'Your email is: ' . maskEmail($customer['email'])
    ];
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

function maskPhoneNumber($phone) {
    $length = strlen($phone);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($phone, -4);
}

function sendRecoveryOTPEmail($email, $otpCode) {
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mailer.php';
    }
    
    $subject = 'Password Recovery OTP - ' . SITE_NAME;
    $body = "
        <h2>Password Recovery</h2>
        <p>Your OTP code for password recovery is:</p>
        <h1 style='font-size: 32px; letter-spacing: 5px; color: #4F46E5;'>{$otpCode}</h1>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this, please ignore this email.</p>
    ";
    
    return sendEmail($email, $subject, $body);
}

function sendTermiiOTP($phone, $otpCode, $otpId) {
    return true;
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
