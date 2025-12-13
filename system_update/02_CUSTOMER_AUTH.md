# Customer Authentication System

## Overview

This document details the customer authentication flow, including OTP verification, password login, and session management. The system is designed for minimal friction while maintaining security.

## Authentication Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    CHECKOUT / LOGIN FLOW                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐                                                 │
│  │ Enter Email │                                                 │
│  └──────┬──────┘                                                 │
│         │                                                         │
│         ▼                                                         │
│  ┌─────────────────┐                                             │
│  │ Check Database  │                                             │
│  └────────┬────────┘                                             │
│           │                                                       │
│     ┌─────┴─────┐                                                │
│     │           │                                                │
│     ▼           ▼                                                │
│  ┌──────┐   ┌──────────┐                                        │
│  │ NEW  │   │ EXISTING │                                        │
│  └───┬──┘   └────┬─────┘                                        │
│      │           │                                               │
│      ▼           ▼                                               │
│  ┌────────┐  ┌──────────────┐                                   │
│  │Send OTP│  │Show Password │                                   │
│  │SMS+Email│ │    Input     │                                   │
│  └────┬───┘  └──────┬───────┘                                   │
│       │             │                                            │
│       ▼             ▼                                            │
│  ┌─────────┐  ┌───────────┐                                     │
│  │Verify   │  │  Login    │                                     │
│  │OTP Code │  │  Verify   │                                     │
│  └────┬────┘  └─────┬─────┘                                     │
│       │             │                                            │
│       └──────┬──────┘                                            │
│              │                                                    │
│              ▼                                                    │
│  ┌───────────────────┐                                          │
│  │ Create Session    │                                          │
│  │ (12-month token)  │                                          │
│  └─────────┬─────────┘                                          │
│            │                                                     │
│            ▼                                                     │
│  ┌───────────────────┐                                          │
│  │ Continue Checkout │                                          │
│  └───────────────────┘                                          │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

## OTP System

### OTP Generation

```php
/**
 * Generate and send OTP to customer
 * 
 * @param string $email Customer email
 * @param string $phone Customer phone (optional, for SMS)
 * @param string $type OTP type: email_verify, phone_verify, login, password_reset
 * @return array Success status and message
 */
function generateCustomerOTP($email, $phone = null, $type = 'email_verify') {
    $db = getDb();
    
    // Rate limiting: max 3 OTPs per email per hour
    $rateLimitCheck = $db->prepare("
        SELECT COUNT(*) FROM customer_otp_codes 
        WHERE email = ? 
        AND created_at > datetime('now', '-1 hour')
    ");
    $rateLimitCheck->execute([$email]);
    if ($rateLimitCheck->fetchColumn() >= 3) {
        return ['success' => false, 'message' => 'Too many OTP requests. Please wait.'];
    }
    
    // Invalidate previous OTPs
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1 WHERE email = ? AND is_used = 0")
       ->execute([$email]);
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiry (10 minutes)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Insert OTP record
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes (email, phone, otp_code, otp_type, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$email, $phone, $otpCode, $type, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    // Send via SMS (Termii) if phone provided
    $smsSent = false;
    if ($phone) {
        $smsSent = sendTermiiOTP($phone, $otpCode, $otpId);
    }
    
    // Send via email
    $emailSent = sendOTPEmail($email, $otpCode);
    
    // Update delivery status
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ?, email_sent = ? WHERE id = ?")
       ->execute([$smsSent ? 1 : 0, $emailSent ? 1 : 0, $otpId]);
    
    return [
        'success' => $emailSent || $smsSent,
        'message' => 'OTP sent successfully',
        'delivery' => [
            'sms' => $smsSent,
            'email' => $emailSent
        ]
    ];
}
```

### OTP Verification

```php
/**
 * Verify OTP code
 * 
 * @param string $email Customer email
 * @param string $code 6-digit OTP code
 * @param string $type Expected OTP type
 * @return array Verification result
 */
function verifyCustomerOTP($email, $code, $type = 'email_verify') {
    $db = getDb();
    
    // Find valid OTP
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE email = ? 
        AND otp_code = ?
        AND otp_type = ?
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $code, $type]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        // Increment attempts on latest OTP
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE email = ? AND is_used = 0
            ORDER BY created_at DESC LIMIT 1
        ")->execute([$email]);
        
        return ['success' => false, 'message' => 'Invalid or expired OTP code'];
    }
    
    // Mark as used
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    return [
        'success' => true,
        'message' => 'OTP verified successfully',
        'otp_id' => $otp['id']
    ];
}
```

## Session Management

### Session Token Generation

```php
/**
 * Create long-lasting customer session
 * 
 * @param int $customerId Customer ID
 * @param int $daysValid Days until expiry (default 365)
 * @return string Session token
 */
function createCustomerSession($customerId, $daysValid = 365) {
    $db = getDb();
    
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    
    // Collect device info
    $deviceFingerprint = generateDeviceFingerprint();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = getClientIP();
    $deviceName = parseDeviceName($userAgent);
    
    // Calculate expiry
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));
    
    // Insert session
    $stmt = $db->prepare("
        INSERT INTO customer_sessions 
        (customer_id, session_token, device_fingerprint, user_agent, ip_address, device_name, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$customerId, $token, $deviceFingerprint, $userAgent, $ipAddress, $deviceName, $expiresAt]);
    
    // Set cookie (HTTP-only, secure)
    setcookie('customer_token', $token, [
        'expires' => strtotime($expiresAt),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Also store in PHP session for immediate use
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_token'] = $token;
    
    // Log activity
    logCustomerActivity($customerId, 'session_created', 'New session created');
    
    return $token;
}
```

### Session Validation

```php
/**
 * Validate customer session from token
 * 
 * @return array|null Customer data or null if invalid
 */
function validateCustomerSession() {
    $db = getDb();
    
    // Check PHP session first
    if (isset($_SESSION['customer_id']) && isset($_SESSION['customer_token'])) {
        $token = $_SESSION['customer_token'];
    }
    // Check cookie
    elseif (isset($_COOKIE['customer_token'])) {
        $token = $_COOKIE['customer_token'];
    }
    else {
        return null;
    }
    
    // Validate token
    $stmt = $db->prepare("
        SELECT cs.*, c.*
        FROM customer_sessions cs
        JOIN customers c ON cs.customer_id = c.id
        WHERE cs.session_token = ?
        AND cs.is_active = 1
        AND cs.expires_at > datetime('now')
        AND c.status = 'active'
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        // Clear invalid session
        unset($_SESSION['customer_id'], $_SESSION['customer_token']);
        setcookie('customer_token', '', time() - 3600, '/');
        return null;
    }
    
    // Update last activity (rolling session)
    $db->prepare("UPDATE customer_sessions SET last_activity_at = datetime('now') WHERE session_token = ?")
       ->execute([$token]);
    
    // Refresh PHP session
    $_SESSION['customer_id'] = $session['customer_id'];
    $_SESSION['customer_token'] = $token;
    
    return [
        'id' => $session['customer_id'],
        'email' => $session['email'],
        'full_name' => $session['full_name'],
        'phone' => $session['phone'],
        'username' => $session['username']
    ];
}
```

### Session Logout

```php
/**
 * Logout customer - revoke session
 * 
 * @param bool $allDevices Revoke all sessions for this customer
 */
function logoutCustomer($allDevices = false) {
    $db = getDb();
    $customerId = $_SESSION['customer_id'] ?? null;
    $token = $_SESSION['customer_token'] ?? $_COOKIE['customer_token'] ?? null;
    
    if ($allDevices && $customerId) {
        // Revoke all sessions
        $db->prepare("
            UPDATE customer_sessions 
            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'logout_all'
            WHERE customer_id = ?
        ")->execute([$customerId]);
    }
    elseif ($token) {
        // Revoke current session only
        $db->prepare("
            UPDATE customer_sessions 
            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'logout'
            WHERE session_token = ?
        ")->execute([$token]);
    }
    
    // Clear PHP session
    unset($_SESSION['customer_id'], $_SESSION['customer_token']);
    
    // Clear cookie
    setcookie('customer_token', '', time() - 3600, '/');
    
    if ($customerId) {
        logCustomerActivity($customerId, 'logout', $allDevices ? 'Logged out all devices' : 'Logged out');
    }
}
```

## Password Management

### Password Login

```php
/**
 * Login customer with email and password
 */
function loginCustomerWithPassword($email, $password) {
    $db = getDb();
    
    // Check rate limiting
    if (isRateLimited($email, 'customer')) {
        return ['success' => false, 'message' => 'Too many attempts. Please wait.'];
    }
    
    // Find customer
    $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer || empty($customer['password_hash'])) {
        trackLoginAttempt($email, 'customer');
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Verify password
    if (!password_verify($password, $customer['password_hash'])) {
        trackLoginAttempt($email, 'customer');
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Clear rate limit
    clearLoginAttempts($email, 'customer');
    
    // Update last login
    $db->prepare("UPDATE customers SET last_login_at = datetime('now') WHERE id = ?")
       ->execute([$customer['id']]);
    
    // Create session
    $token = createCustomerSession($customer['id']);
    
    return [
        'success' => true,
        'customer' => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'full_name' => $customer['full_name']
        ],
        'token' => $token
    ];
}
```

### Set Password (First Time)

```php
/**
 * Set password for customer who verified via OTP
 */
function setCustomerPassword($customerId, $password) {
    $db = getDb();
    
    // Validate password strength
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET password_hash = ?, password_changed_at = datetime('now'), updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$hash, $customerId]);
    
    logCustomerActivity($customerId, 'password_set', 'Password set for first time');
    
    return ['success' => true, 'message' => 'Password set successfully'];
}
```

## Helper Functions

### Check if Email Exists

```php
/**
 * Check if customer email exists in database
 */
function checkCustomerEmail($email) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, email, password_hash, full_name FROM customers WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['exists' => false];
    }
    
    return [
        'exists' => true,
        'has_password' => !empty($customer['password_hash']),
        'customer_id' => $customer['id'],
        'full_name' => $customer['full_name']
    ];
}
```

### Create Customer Account

```php
/**
 * Create new customer account after OTP verification
 */
function createCustomerAccount($email, $phone = null, $fullName = null) {
    $db = getDb();
    
    // Check if already exists
    $check = checkCustomerEmail($email);
    if ($check['exists']) {
        return ['success' => false, 'message' => 'Account already exists', 'customer_id' => $check['customer_id']];
    }
    
    // Generate username from email
    $username = explode('@', $email)[0];
    
    $stmt = $db->prepare("
        INSERT INTO customers (email, phone, full_name, username, status, email_verified)
        VALUES (?, ?, ?, ?, 'active', 1)
    ");
    $stmt->execute([$email, $phone, $fullName, $username]);
    $customerId = $db->lastInsertId();
    
    // Log activity
    logCustomerActivity($customerId, 'account_created', 'Account created via OTP verification');
    
    // Send welcome email
    sendCustomerWelcomeEmail($email, $fullName ?: $username);
    
    return [
        'success' => true,
        'customer_id' => $customerId,
        'message' => 'Account created successfully'
    ];
}
```

## Security Considerations

### Rate Limiting
- 3 OTP requests per email per hour
- 5 OTP verification attempts per code
- 5 password attempts before lockout (15 min)

### Token Security
- 64-character hex tokens (256-bit entropy)
- HTTP-only cookies prevent XSS
- SameSite=Lax prevents CSRF
- Secure flag in production

### Session Hygiene
- Regenerate session ID on login
- Rolling expiry on activity
- Revocation on logout
- Device fingerprinting for anomaly detection

## File Locations

New files to create:
- `includes/customer_auth.php` - All functions above
- `includes/customer_session.php` - Session management
- `includes/customer_otp.php` - OTP functions
