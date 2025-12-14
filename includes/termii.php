<?php
/**
 * Termii SMS API Integration
 * 
 * Termii is a Nigerian communications platform for SMS and voice OTP delivery.
 * Documentation: https://developer.termii.com/
 * 
 * Configuration is hardcoded in includes/config.php per user preference.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!defined('TERMII_API_KEY')) {
    define('TERMII_API_KEY', ''); // Set your Termii API key in config.php
}
if (!defined('TERMII_SENDER_ID')) {
    define('TERMII_SENDER_ID', 'WebDaddy');
}
if (!defined('TERMII_BASE_URL')) {
    define('TERMII_BASE_URL', 'https://api.ng.termii.com/api');
}

/**
 * Send SMS via Termii
 * 
 * @param string $phone Phone number with country code (e.g., +2348012345678)
 * @param string $message Message content
 * @return array Response with success status
 */
function sendTermiiSMS($phone, $message) {
    if (empty(TERMII_API_KEY)) {
        error_log("Termii API key not configured");
        return ['success' => false, 'error' => 'SMS service not configured'];
    }
    
    $url = TERMII_BASE_URL . '/sms/send';
    
    // Clean and format phone number
    $phone = formatPhoneForTermii($phone);
    
    $data = [
        'api_key' => TERMII_API_KEY,
        'to' => $phone,
        'from' => TERMII_SENDER_ID,
        'sms' => $message,
        'type' => 'plain',
        'channel' => 'generic'
    ];
    
    return makeTermiiRequest($url, $data);
}

/**
 * Send OTP via Termii SMS
 * 
 * @param string $phone Phone number
 * @param string $otp 6-digit OTP code
 * @param int|null $otpId Internal OTP record ID for tracking
 * @return array Response with message_id
 */
function sendTermiiOTPSMS($phone, $otp, $otpId = null) {
    if (empty(TERMII_API_KEY)) {
        error_log("Termii API key not configured");
        return ['success' => false, 'error' => 'SMS service not configured'];
    }
    
    $url = TERMII_BASE_URL . '/sms/otp/send';
    
    // Clean and format phone number
    $phone = formatPhoneForTermii($phone);
    
    $data = [
        'api_key' => TERMII_API_KEY,
        'message_type' => 'NUMERIC',
        'to' => $phone,
        'from' => TERMII_SENDER_ID,
        'channel' => 'generic',
        'pin_attempts' => 5,
        'pin_time_to_live' => 10, // 10 minutes
        'pin_length' => 6,
        'pin_placeholder' => '{pin}',
        'message_text' => "Your WebDaddy verification code is {pin}. Valid for 10 minutes. Do not share this code.",
        'pin' => $otp
    ];
    
    $response = makeTermiiRequest($url, $data);
    
    // Update OTP record with Termii message ID
    if ($response['success'] && !empty($response['data']['pinId']) && $otpId) {
        try {
            $db = getDb();
            $db->prepare("UPDATE customer_otp_codes SET termii_message_id = ?, sms_sent = 1 WHERE id = ?")
               ->execute([$response['data']['pinId'], $otpId]);
        } catch (Exception $e) {
            error_log("Failed to update OTP record with Termii message ID: " . $e->getMessage());
        }
    }
    
    // Log (without sensitive data)
    $maskedPhone = maskPhoneNumber($phone);
    error_log("Termii OTP sent to {$maskedPhone}: " . ($response['success'] ? 'Success' : 'Failed - ' . ($response['error'] ?? 'Unknown error')));
    
    return $response;
}

/**
 * Send OTP via Voice Call (fallback)
 * 
 * @param string $phone Phone number
 * @param string $otp 6-digit OTP code
 * @return array Response
 */
function sendTermiiVoiceOTP($phone, $otp) {
    if (empty(TERMII_API_KEY)) {
        return ['success' => false, 'error' => 'SMS service not configured'];
    }
    
    $url = TERMII_BASE_URL . '/sms/otp/send/voice';
    
    $phone = formatPhoneForTermii($phone);
    
    $data = [
        'api_key' => TERMII_API_KEY,
        'phone_number' => $phone,
        'pin' => $otp,
        'pin_attempts' => 3,
        'pin_time_to_live' => 10,
        'pin_length' => 6
    ];
    
    return makeTermiiRequest($url, $data);
}

/**
 * Check SMS Delivery Status
 * 
 * @param string $messageId Termii message/pin ID
 * @return array Delivery status
 */
function checkTermiiDeliveryStatus($messageId) {
    if (empty(TERMII_API_KEY)) {
        return ['success' => false, 'error' => 'SMS service not configured'];
    }
    
    $url = TERMII_BASE_URL . '/sms/inbox?api_key=' . TERMII_API_KEY . '&message_id=' . $messageId;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => true,
        'status' => $data['status'] ?? 'unknown',
        'data' => $data
    ];
}

/**
 * Get Termii Account Balance
 * 
 * @return array Balance info
 */
function getTermiiBalance() {
    if (empty(TERMII_API_KEY)) {
        return ['success' => false, 'error' => 'SMS service not configured'];
    }
    
    $url = TERMII_BASE_URL . '/get-balance?api_key=' . TERMII_API_KEY;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => true,
        'balance' => $data['balance'] ?? 0,
        'currency' => $data['currency'] ?? 'NGN',
        'data' => $data
    ];
}

/**
 * Format phone number for Termii API
 * 
 * @param string $phone Raw phone number
 * @return string Formatted phone number with country code
 */
function formatPhoneForTermii($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // If starts with +, it's already formatted
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    
    // If starts with 234 (Nigeria), add +
    if (strpos($phone, '234') === 0) {
        return '+' . $phone;
    }
    
    // If starts with 0 (local Nigerian format), replace with +234
    if (strpos($phone, '0') === 0) {
        return '+234' . substr($phone, 1);
    }
    
    // Default: assume Nigerian number without prefix
    return '+234' . $phone;
}

/**
 * Mask phone number for display/logging
 * 
 * @param string $phone Phone number
 * @return string Masked phone number
 */
function maskPhoneNumber($phone) {
    $length = strlen($phone);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return substr($phone, 0, 4) . str_repeat('*', $length - 8) . substr($phone, -4);
}

/**
 * Make HTTP request to Termii API
 * 
 * @param string $url API endpoint
 * @param array $data Request payload
 * @return array Response
 */
function makeTermiiRequest($url, $data) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Termii API Network Error: {$error}");
        return [
            'success' => false,
            'error' => 'Network error: ' . $error
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $responseData
        ];
    }
    
    // Log error without sensitive data
    $safeData = $data;
    unset($safeData['api_key']); // Remove API key from logs
    error_log("Termii API Error (HTTP {$httpCode}): " . json_encode($responseData));
    
    return [
        'success' => false,
        'error' => $responseData['message'] ?? 'API error (HTTP ' . $httpCode . ')',
        'http_code' => $httpCode
    ];
}

/**
 * Send OTP with fallback strategy
 * Email is always sent as backup, SMS is primary if phone is provided
 * 
 * @param string $email Email address
 * @param string|null $phone Phone number (optional)
 * @param string $otp OTP code
 * @param int|null $otpId OTP record ID
 * @return array Result with delivery methods used
 */
function sendOTPWithFallback($email, $phone, $otp, $otpId = null) {
    $methods = [];
    $smsSent = false;
    $emailSent = false;
    
    // Try SMS first if phone provided and Termii is configured
    if ($phone && !empty(TERMII_API_KEY)) {
        $smsResult = sendTermiiOTPSMS($phone, $otp, $otpId);
        $smsSent = $smsResult['success'];
        
        if ($smsSent) {
            $methods[] = 'sms';
        } else {
            // Try voice as fallback for SMS failure
            $voiceResult = sendTermiiVoiceOTP($phone, $otp);
            if ($voiceResult['success']) {
                $methods[] = 'voice';
                $smsSent = true;
            }
        }
    }
    
    // Always send email as backup
    if (function_exists('sendOTPEmail')) {
        $emailSent = sendOTPEmail($email, $otp);
        if ($emailSent) {
            $methods[] = 'email';
        }
    }
    
    return [
        'success' => $smsSent || $emailSent,
        'methods' => $methods,
        'sms_sent' => $smsSent,
        'email_sent' => $emailSent
    ];
}
