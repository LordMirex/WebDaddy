# Termii Integration

## Overview

Termii is a Nigerian communications platform specializing in SMS and voice OTP delivery. It's used by major companies like Paystack and offers excellent delivery rates in African markets.

## Account Setup

### Step 1: Create Termii Account

1. Visit https://termii.com
2. Sign up for a business account
3. Verify your business details
4. Fund your account (prepaid SMS credits)

### Step 2: Get API Credentials

1. Go to Dashboard → Settings → API Keys
2. Copy your API Key
3. Note your Sender ID (default or custom)

### Step 3: Configure Sender ID

1. Go to Dashboard → Sender ID
2. Request a custom sender ID (e.g., "WebDaddy")
3. Wait for approval (usually 24-48 hours)
4. Use default "Termii" until approved

## API Configuration

### Environment Variables

Add to `includes/config.php` or use environment variables:

```php
// Termii Configuration
define('TERMII_API_KEY', getenv('TERMII_API_KEY') ?: 'your_api_key_here');
define('TERMII_SENDER_ID', 'WebDaddy'); // Or your approved sender ID
define('TERMII_BASE_URL', 'https://api.ng.termii.com/api');
```

### Store API Key Securely

For Replit, add to Secrets:
- Key: `TERMII_API_KEY`
- Value: Your Termii API key

## PHP Implementation

### includes/termii.php

```php
<?php
/**
 * Termii SMS API Integration
 * 
 * Documentation: https://developer.termii.com/
 */

require_once __DIR__ . '/config.php';

/**
 * Send SMS via Termii
 * 
 * @param string $phone Phone number with country code (e.g., +2348012345678)
 * @param string $message Message content
 * @return array Response with success status
 */
function sendTermiiSMS($phone, $message) {
    $url = TERMII_BASE_URL . '/sms/send';
    
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Ensure country code
    if (strpos($phone, '+') !== 0) {
        $phone = '+234' . ltrim($phone, '0'); // Default to Nigeria
    }
    
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
 * Send OTP via Termii
 * 
 * @param string $phone Phone number
 * @param string $otp 6-digit OTP code
 * @param int $otpId Internal OTP record ID for tracking
 * @return array Response with message_id
 */
function sendTermiiOTP($phone, $otp, $otpId = null) {
    $url = TERMII_BASE_URL . '/sms/otp/send';
    
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '+') !== 0) {
        $phone = '+234' . ltrim($phone, '0');
    }
    
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
        'pin' => $otp // Pre-generated OTP
    ];
    
    $response = makeTermiiRequest($url, $data);
    
    // Update OTP record with Termii message ID
    if ($response['success'] && !empty($response['data']['pinId']) && $otpId) {
        $db = getDb();
        $db->prepare("UPDATE customer_otp_codes SET termii_message_id = ?, sms_sent = 1 WHERE id = ?")
           ->execute([$response['data']['pinId'], $otpId]);
    }
    
    // Log (without sensitive data)
    $maskedPhone = substr($phone, 0, -4) . '****';
    error_log("Termii OTP sent to {$maskedPhone}: " . ($response['success'] ? 'Success' : 'Failed'));
    
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
    $url = TERMII_BASE_URL . '/sms/otp/send/voice';
    
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '+') !== 0) {
        $phone = '+234' . ltrim($phone, '0');
    }
    
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
 * Get Account Balance
 * 
 * @return array Balance info
 */
function getTermiiBalance() {
    $url = TERMII_BASE_URL . '/get-balance?api_key=' . TERMII_API_KEY;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
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
        error_log("Termii API Error: {$error}");
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
    
    error_log("Termii API Error (HTTP {$httpCode}): " . $response);
    
    return [
        'success' => false,
        'error' => $responseData['message'] ?? 'API error',
        'http_code' => $httpCode
    ];
}
```

## Usage in OTP Flow

### In includes/customer_otp.php

```php
require_once __DIR__ . '/termii.php';

function generateCustomerOTP($email, $phone = null, $type = 'email_verify') {
    // ... rate limiting and OTP generation code ...
    
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Insert OTP record
    // ... database insert ...
    $otpId = $db->lastInsertId();
    
    $smsSent = false;
    $emailSent = false;
    
    // Try SMS first if phone provided
    if ($phone) {
        $smsResult = sendTermiiOTP($phone, $otpCode, $otpId);
        $smsSent = $smsResult['success'];
        
        // If SMS fails, try voice as fallback
        if (!$smsSent) {
            $voiceResult = sendTermiiVoiceOTP($phone, $otpCode);
            $smsSent = $voiceResult['success'];
        }
    }
    
    // Always send email as backup
    $emailSent = sendOTPEmail($email, $otpCode);
    
    // Update record
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ?, email_sent = ? WHERE id = ?")
       ->execute([$smsSent ? 1 : 0, $emailSent ? 1 : 0, $otpId]);
    
    return [
        'success' => $smsSent || $emailSent,
        'message' => 'Verification code sent',
        'delivery' => [
            'sms' => $smsSent,
            'email' => $emailSent
        ]
    ];
}
```

## SMS Templates

### OTP Message

```
Your WebDaddy verification code is {PIN}. Valid for 10 minutes. Do not share this code.
```

### Order Confirmation (Optional)

```
Order #{ORDER_ID} confirmed! Amount: N{AMOUNT}. Track: {SHORT_LINK}
```

### Delivery Notification (Optional)

```
Your WebDaddy order #{ORDER_ID} has been delivered! Check your email for details.
```

## Pricing

Termii SMS pricing (as of 2025):

| Route | Cost per SMS |
|-------|--------------|
| Nigeria (local) | ~N4.00 |
| International | Varies by country |

Budget recommendation: Start with N5,000 credit (~1,250 SMS).

## Error Handling

### Common Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| 400 | Bad request | Check phone format |
| 401 | Invalid API key | Verify API key |
| 402 | Insufficient balance | Top up account |
| 429 | Rate limited | Wait and retry |
| 500 | Server error | Retry with backoff |

### Fallback Strategy

```php
function sendOTPWithFallback($email, $phone, $otp, $otpId) {
    $methods = [];
    
    // Try SMS first
    if ($phone) {
        $smsResult = sendTermiiOTP($phone, $otp, $otpId);
        if ($smsResult['success']) {
            $methods[] = 'sms';
        } else {
            // Try voice
            $voiceResult = sendTermiiVoiceOTP($phone, $otp);
            if ($voiceResult['success']) {
                $methods[] = 'voice';
            }
        }
    }
    
    // Always send email as backup
    if (sendOTPEmail($email, $otp)) {
        $methods[] = 'email';
    }
    
    return [
        'success' => count($methods) > 0,
        'methods' => $methods
    ];
}
```

## Testing

### Test Mode

Termii provides a sandbox environment:

```php
// Use sandbox for testing
define('TERMII_BASE_URL', 'https://api.ng.termii.com/api'); // Same URL
// Use test API key from Termii dashboard
```

### Test Phone Numbers

Use your own phone number for testing. Termii charges for all SMS including tests.

### Verify Integration

```php
// Quick test
require_once 'includes/termii.php';

// Check balance
$balance = getTermiiBalance();
echo "Balance: " . json_encode($balance);

// Send test SMS (costs money!)
$result = sendTermiiSMS('+234YOUR_NUMBER', 'Test from WebDaddy');
echo "SMS Result: " . json_encode($result);
```

## Monitoring

### Track Delivery Rates

```sql
-- SMS delivery success rate
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_otp,
    SUM(sms_sent) as sms_success,
    SUM(email_sent) as email_success,
    ROUND(SUM(sms_sent) * 100.0 / COUNT(*), 2) as sms_rate
FROM customer_otp_codes
WHERE created_at > datetime('now', '-7 days')
GROUP BY DATE(created_at);
```

### Low Balance Alert

```php
// Add to cron job
function checkTermiiBalance() {
    $balance = getTermiiBalance();
    $amount = $balance['balance'] ?? 0;
    
    if ($amount < 1000) { // N1000 threshold
        sendEmail(
            SUPPORT_EMAIL,
            'Low Termii Balance Alert',
            "Termii balance is low: N{$amount}. Please top up soon."
        );
    }
}
```

## Security Best Practices

1. **Never log API key**
2. **Store API key in environment variables**
3. **Validate phone numbers before sending**
4. **Rate limit OTP requests**
5. **Monitor for abuse patterns**
6. **Use HTTPS only**
