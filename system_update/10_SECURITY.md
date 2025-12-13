# Security Considerations

## Overview

This document details security measures for the customer account system, including rate limiting, session security, and data protection.

## Authentication Security

### 1. OTP Security

**Rate Limiting:**
```php
// Max 3 OTP requests per email per hour
function checkOTPRateLimit($email) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM customer_otp_codes 
        WHERE email = ? 
        AND created_at > datetime('now', '-1 hour')
    ");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() < 3;
}
```

**OTP Expiry:**
- 10-minute validity window
- Single-use tokens
- Max 5 verification attempts per code

**OTP Generation:**
```php
// Cryptographically secure 6-digit OTP
function generateSecureOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
```

### 2. Password Security

**Password Requirements:**
- Minimum 6 characters
- No maximum length
- Uses PHP's `password_hash()` with PASSWORD_DEFAULT (bcrypt)

**Password Storage:**
```php
// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verify password
$valid = password_verify($password, $storedHash);
```

**Login Rate Limiting:**
```php
// 5 attempts before 15-minute lockout
function isLoginRateLimited($email) {
    $key = 'login_attempts_customer_' . md5($email);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => null];
    
    // Reset after 15 minutes
    if ($attempts['first_attempt'] && time() - $attempts['first_attempt'] > 900) {
        unset($_SESSION[$key]);
        return false;
    }
    
    return $attempts['count'] >= 5;
}
```

### 3. Session Security

**Token Generation:**
```php
// 256-bit entropy token
$token = bin2hex(random_bytes(32)); // 64 hex characters
```

**Cookie Configuration:**
```php
setcookie('customer_token', $token, [
    'expires' => strtotime('+365 days'),
    'path' => '/',
    'secure' => true,      // HTTPS only
    'httponly' => true,    // No JavaScript access
    'samesite' => 'Lax'    // CSRF protection
]);
```

**Session Validation:**
```php
function validateCustomerSession() {
    // Check token exists
    // Verify not expired
    // Verify is_active = 1
    // Verify customer status = 'active'
    // Update last_activity_at
}
```

**Session Revocation:**
- On logout: Revoke current session
- On logout all: Revoke all sessions
- On password change: Revoke all other sessions
- On account suspension: Revoke all sessions

## API Security

### 1. CSRF Protection

All POST endpoints require session validation:

```php
// Check customer is authenticated
$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Authentication required']));
}
```

For form submissions, use CSRF tokens:

```php
// Generate token
$csrfToken = getCsrfToken();

// Validate on submit
if (!validateCsrfToken($_POST['csrf_token'])) {
    http_response_code(403);
    exit('CSRF validation failed');
}
```

### 2. Input Validation

All inputs sanitized:

```php
$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
$phone = preg_replace('/[^0-9+]/', '', $input['phone']);
$name = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
```

### 3. Rate Limiting by Endpoint

| Endpoint | Limit | Window | By |
|----------|-------|--------|-----|
| check-email | 10 | 1 minute | IP |
| request-otp | 3 | 1 hour | Email |
| verify-otp | 5 | Per OTP | Code |
| login | 5 | 15 minutes | Email |
| profile | 30 | 1 minute | Customer |
| orders | 60 | 1 minute | Customer |

**Implementation:**
```php
function checkAPIRateLimit($identifier, $limit, $windowSeconds) {
    $db = getDb();
    $key = 'rate_' . md5($identifier);
    
    // Clean old entries
    $db->prepare("DELETE FROM rate_limits WHERE expires_at < datetime('now')")->execute();
    
    // Check current count
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    
    if ($row && $row['count'] >= $limit) {
        return false;
    }
    
    // Increment or insert
    if ($row) {
        $db->prepare("UPDATE rate_limits SET count = count + 1 WHERE key = ?")->execute([$key]);
    } else {
        $expires = date('Y-m-d H:i:s', time() + $windowSeconds);
        $db->prepare("INSERT INTO rate_limits (key, count, expires_at) VALUES (?, 1, ?)")
           ->execute([$key, $expires]);
    }
    
    return true;
}
```

## Data Protection

### 1. Template Credentials Encryption

```php
// Encryption (when admin saves credentials)
function encryptPassword($password) {
    $key = getEncryptionKey(); // From secure config
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
    return [
        'encrypted' => base64_encode($encrypted),
        'iv' => base64_encode($iv)
    ];
}

// Decryption (when customer views)
function decryptPassword($encrypted, $iv) {
    $key = getEncryptionKey();
    return openssl_decrypt(
        base64_decode($encrypted),
        'AES-256-CBC',
        $key,
        0,
        base64_decode($iv)
    );
}
```

### 2. Sensitive Data in Logs

Never log:
- Passwords
- OTP codes
- Session tokens
- Decrypted credentials

```php
// Safe logging
error_log("Customer #{$customerId} logged in from {$ip}");

// NEVER do this
error_log("Password: {$password}"); // WRONG
```

### 3. Download Token Security

```php
// Token validation
function validateDownloadToken($token) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM download_tokens
        WHERE token = ?
        AND expires_at > datetime('now')
        AND download_count < max_downloads
    ");
    $stmt->execute([$token]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
```

## Device Fingerprinting

Track devices for anomaly detection:

```php
function generateDeviceFingerprint() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    return hash('sha256', $ua . $accept . $lang . $encoding);
}

function parseDeviceName($userAgent) {
    // Extract browser and OS
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';
    
    // Browser detection
    if (preg_match('/Chrome\/[\d.]+/', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Safari\/[\d.]+/', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/Edge\/[\d.]+/', $userAgent)) $browser = 'Edge';
    
    // OS detection
    if (strpos($userAgent, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($userAgent, 'Mac') !== false) $os = 'Mac';
    elseif (strpos($userAgent, 'Linux') !== false) $os = 'Linux';
    elseif (strpos($userAgent, 'iPhone') !== false) $os = 'iPhone';
    elseif (strpos($userAgent, 'Android') !== false) $os = 'Android';
    
    return "$browser on $os";
}
```

## Activity Logging

Log security-relevant actions:

```php
function logCustomerActivity($customerId, $action, $details = null) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO customer_activity_log 
        (customer_id, action, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, datetime('now'))
    ");
    
    $stmt->execute([
        $customerId,
        $action,
        $details,
        getClientIP(),
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Actions to log:
// - session_created
// - session_revoked
// - password_set
// - password_changed
// - profile_updated
// - credential_view
// - file_download
// - logout
// - login_failed
```

## Termii API Security

```php
// Store API key securely
define('TERMII_API_KEY', getenv('TERMII_API_KEY')); // From environment

// Never log API key
function sendTermiiOTP($phone, $otp, $otpId) {
    $data = [
        'api_key' => TERMII_API_KEY, // Masked in logs
        'message_type' => 'NUMERIC',
        'to' => $phone,
        'from' => 'WebDaddy',
        'pin_type' => 'NUMERIC',
        'pin_attempts' => 5,
        'pin_time_to_live' => 10,
        'pin_length' => 6,
        'pin_placeholder' => '{otp}',
        'message_text' => "Your WebDaddy code is {otp}. Valid for 10 minutes.",
        'pin' => $otp
    ];
    
    // Make API call
    $response = curlPost('https://api.ng.termii.com/api/sms/otp/send', $data);
    
    // Log without sensitive data
    error_log("Termii OTP sent to " . substr($phone, 0, -4) . '****');
    
    return $response;
}
```

## Security Headers

Add to all customer pages:

```php
// In user/includes/header.php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net");
```

## Security Checklist

- [ ] OTP rate limiting implemented
- [ ] Password hashing uses bcrypt
- [ ] Login rate limiting implemented
- [ ] Session tokens are 256-bit
- [ ] Cookies are HTTP-only and Secure
- [ ] CSRF protection on all forms
- [ ] Input validation on all endpoints
- [ ] API rate limiting per endpoint
- [ ] Credentials encrypted at rest
- [ ] Sensitive data not logged
- [ ] Download tokens validated
- [ ] Device fingerprinting enabled
- [ ] Activity logging comprehensive
- [ ] Termii API key secured
- [ ] Security headers set
