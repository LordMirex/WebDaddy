<?php
/**
 * Customer OTP System
 * 
 * Handles OTP generation, sending, and verification for customer authentication
 * Supports SMS via Termii and email as fallback
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/termii.php';

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
    
    $sent = sendOTPEmail($email, $otpCode, $fullName);
    
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

function sendPhoneVerificationOTP($customerId, $phoneNumber) {
    $db = getDb();
    
    if (empty($phoneNumber) || strlen($phoneNumber) < 10) {
        return ['success' => false, 'error' => 'Please enter a valid phone number'];
    }
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    $rateLimitCheck = $db->prepare("
        SELECT COUNT(*) FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND otp_type = 'phone_verify'
        AND created_at > datetime('now', '-1 hour')
    ");
    $rateLimitCheck->execute([$customerId]);
    if ($rateLimitCheck->fetchColumn() >= OTP_RATE_LIMIT_HOUR) {
        return ['success' => false, 'error' => 'Too many OTP requests. Please wait.'];
    }
    
    $otpCode = generateOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, phone, otp_code, otp_type, delivery_method, expires_at)
        VALUES (?, ?, ?, 'phone_verify', 'sms', ?)
    ");
    $stmt->execute([$customerId, $cleanNumber, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    $smsSent = sendTermiiSMSOTP($cleanNumber, $otpCode, $otpId);
    
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ? WHERE id = ?")
       ->execute([$smsSent ? 1 : 0, $otpId]);
    
    if (!$smsSent) {
        return ['success' => false, 'error' => 'Failed to send SMS. Please try again.'];
    }
    
    return [
        'success' => true, 
        'message' => 'SMS OTP sent to your phone number',
        'phone_masked' => maskPhoneForDisplay($cleanNumber),
        'expires_in' => OTP_EXPIRY_MINUTES * 60
    ];
}

function verifyPhoneOTP($customerId, $phoneNumber, $otpCode) {
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
    
    return [
        'success' => true,
        'message' => 'Phone number verified successfully',
        'phone' => $cleanNumber
    ];
}

function sendOTPEmail($email, $otpCode, $name = null) {
    $greeting = $name ? "Hi {$name}," : "Hi,";
    
    $subject = "Your Verification Code - " . SITE_NAME;
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #4F46E5;'>{$greeting}</h2>
        <p>Your verification code is:</p>
        <div style='background: #F3F4F6; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
            <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1F2937;'>{$otpCode}</span>
        </div>
        <p>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
        <p style='color: #6B7280; font-size: 14px;'>If you didn't request this code, please ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>This is an automated email from " . SITE_NAME . "</p>
    </div>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send SMS OTP via Termii
 * This is the real implementation that connects to Termii API
 * 
 * @param string $phone Phone number
 * @param string $otpCode OTP code
 * @param int $otpId OTP record ID
 * @return bool Success status
 */
function sendTermiiSMSOTP($phone, $otpCode, $otpId) {
    // Check if Termii is configured
    if (empty(TERMII_API_KEY)) {
        error_log("Termii SMS not configured - TERMII_API_KEY is empty. SMS OTP disabled.");
        return false;
    }
    
    $result = sendTermiiOTPSMS($phone, $otpCode, $otpId);
    
    if (!$result['success']) {
        // Try voice call as fallback
        error_log("SMS OTP failed, trying voice fallback for phone: " . maskPhoneForDisplay($phone));
        $voiceResult = sendTermiiVoiceOTP($phone, $otpCode);
        return $voiceResult['success'];
    }
    
    return true;
}

function maskPhoneForDisplay($phone) {
    $length = strlen($phone);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($phone, -4);
}

function cleanupExpiredOTPs() {
    $db = getDb();
    $db->exec("
        DELETE FROM customer_otp_codes 
        WHERE expires_at < datetime('now', '-24 hours')
    ");
}
