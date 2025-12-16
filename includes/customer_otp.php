<?php
/**
 * Customer OTP System
 * 
 * Handles OTP generation, sending, and verification for customer authentication
 * Email-only verification (SMS removed)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// Use config constants if defined, otherwise use defaults
if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', defined('CUSTOMER_OTP_EXPIRY_MINUTES') ? CUSTOMER_OTP_EXPIRY_MINUTES : 10);
}
if (!defined('OTP_MAX_ATTEMPTS')) {
    define('OTP_MAX_ATTEMPTS', defined('CUSTOMER_OTP_MAX_ATTEMPTS') ? CUSTOMER_OTP_MAX_ATTEMPTS : 5);
}
if (!defined('OTP_RATE_LIMIT_HOUR')) {
    define('OTP_RATE_LIMIT_HOUR', defined('CUSTOMER_OTP_RATE_LIMIT_HOUR') ? CUSTOMER_OTP_RATE_LIMIT_HOUR : 3);
}

function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendCheckoutEmailOTP($email, $fullName = null) {
    $db = getDb();
    
    $rateLimitCheck = $db->prepare("
        SELECT COUNT(*) FROM customer_otp_codes 
        WHERE email = ? 
        AND otp_type = 'email_verify'
        AND created_at > datetime('now', '-1 hour')
    ");
    $rateLimitCheck->execute([$email]);
    if ($rateLimitCheck->fetchColumn() >= OTP_RATE_LIMIT_HOUR) {
        return ['success' => false, 'error' => 'Too many OTP requests. Please wait before trying again.'];
    }
    
    $otpCode = generateOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    $existingCustomer = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
    $existingCustomer->execute([$email]);
    $customerId = $existingCustomer->fetchColumn() ?: null;
    
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, 'email_verify', 'email', ?)
    ");
    $stmt->execute([$customerId, $email, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    $sent = sendOTPEmail($email, $otpCode);
    
    $db->prepare("UPDATE customer_otp_codes SET email_sent = ? WHERE id = ?")
       ->execute([$sent ? 1 : 0, $otpId]);
    
    if (!$sent) {
        return ['success' => false, 'error' => 'Failed to send OTP email. Please try again.'];
    }
    
    return [
        'success' => true,
        'message' => 'OTP sent to your email',
        'otp_id' => $otpId,
        'expires_in' => OTP_EXPIRY_MINUTES * 60
    ];
}

function verifyCheckoutEmailOTP($email, $otpCode) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE email = ?
        AND otp_code = ?
        AND otp_type = 'email_verify'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE email = ? AND otp_type = 'email_verify' AND is_used = 0
            ORDER BY created_at DESC LIMIT 1
        ")->execute([$email]);
        
        return ['success' => false, 'error' => 'Invalid or expired OTP code'];
    }
    
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    return [
        'success' => true,
        'message' => 'Email verified successfully',
        'email' => $email
    ];
}

function cleanupExpiredOTPs() {
    $db = getDb();
    $db->exec("
        DELETE FROM customer_otp_codes 
        WHERE expires_at < datetime('now', '-24 hours')
    ");
}
