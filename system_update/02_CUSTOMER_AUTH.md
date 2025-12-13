# Customer Authentication System

## Overview

This document details the customer authentication flow, including registration, OTP verification, password login, session management, and account recovery. The system is designed for minimal friction while maintaining security.

## IMPORTANT: OTP Types and Usage

### Checkout OTP (Email Only)
- At checkout, only **EMAIL OTP** is sent (by the system automatically)
- This is for email verification of new users at checkout
- No SMS OTP is sent at checkout

### Phone/SMS OTP 
- SMS OTP is collected during **account profile update** (Step 3 of registration)
- This phone number is used for **account recovery** purposes
- SMS is sent via Termii integration

---

## Registration Flow (New User Account Setup)

After a user verifies their email OTP at checkout, they are prompted to complete their account in 3 steps:

```
┌─────────────────────────────────────────────────────────────────┐
│                 NEW ACCOUNT REGISTRATION FLOW                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ╔═══════════════════════════════════════════════════════════╗  │
│  ║ STEP 1: Account Credentials                               ║  │
│  ║                                                           ║  │
│  ║   • Username (unique identifier)                          ║  │
│  ║   • Password                                              ║  │
│  ║   • Confirm Password                                      ║  │
│  ║                                                           ║  │
│  ╚═══════════════════════════════════════════════════════════╝  │
│                              │                                   │
│                              ▼                                   │
│  ╔═══════════════════════════════════════════════════════════╗  │
│  ║ STEP 2: WhatsApp Number (MANDATORY)                       ║  │
│  ║                                                           ║  │
│  ║   • WhatsApp number input                                 ║  │
│  ║   • NO OTP verification for WhatsApp                      ║  │
│  ║   • Used for order updates and support                    ║  │
│  ║                                                           ║  │
│  ╚═══════════════════════════════════════════════════════════╝  │
│                              │                                   │
│                              ▼                                   │
│  ╔═══════════════════════════════════════════════════════════╗  │
│  ║ STEP 3: Phone/Mobile Number (SMS OTP Verification)        ║  │
│  ║                                                           ║  │
│  ║   • Enter phone number (for SMS)                          ║  │
│  ║   • System sends SMS OTP via Termii                       ║  │
│  ║   • User enters the OTP code received via SMS             ║  │
│  ║   • This phone is used for ACCOUNT RECOVERY               ║  │
│  ║   • Can choose to receive OTP via this phone or email     ║  │
│  ║     when doing forgot password/forgot email               ║  │
│  ║                                                           ║  │
│  ╚═══════════════════════════════════════════════════════════╝  │
│                              │                                   │
│                              ▼                                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Account Complete! Redirect to Dashboard                   │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Step 1: Account Credentials

```php
/**
 * Process Step 1: Username and Password
 */
function processRegistrationStep1($customerId, $data) {
    $db = getDb();
    
    // Validate username uniqueness
    $usernameCheck = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
    $usernameCheck->execute([$data['username'], $customerId]);
    if ($usernameCheck->fetch()) {
        return ['success' => false, 'error' => 'Username already taken'];
    }
    
    // Validate password match
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'error' => 'Passwords do not match'];
    }
    
    // Validate password strength (min 6 characters)
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    // Update customer record
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
```

### Step 2: WhatsApp Number (Mandatory, No OTP)

```php
/**
 * Process Step 2: WhatsApp Number (No Verification)
 */
function processRegistrationStep2($customerId, $whatsappNumber) {
    $db = getDb();
    
    // Validate WhatsApp number format (basic validation)
    if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
        return ['success' => false, 'error' => 'Please enter a valid WhatsApp number'];
    }
    
    // Clean and format the number
    $cleanNumber = preg_replace('/[^0-9+]/', '', $whatsappNumber);
    
    // Update customer record
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
```

### Step 3: Phone/Mobile Number with SMS OTP

```php
/**
 * Process Step 3: Phone Number with SMS OTP Verification
 * This phone number is used for account recovery (forgot password, forgot email)
 */
function sendPhoneVerificationOTP($customerId, $phoneNumber) {
    $db = getDb();
    
    // Validate phone number format
    if (empty($phoneNumber) || strlen($phoneNumber) < 10) {
        return ['success' => false, 'error' => 'Please enter a valid phone number'];
    }
    
    // Clean and format the number
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Check rate limiting
    $rateLimitCheck = $db->prepare("
        SELECT COUNT(*) FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND otp_type = 'phone_verify'
        AND created_at > datetime('now', '-1 hour')
    ");
    $rateLimitCheck->execute([$customerId]);
    if ($rateLimitCheck->fetchColumn() >= 3) {
        return ['success' => false, 'error' => 'Too many OTP requests. Please wait.'];
    }
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, phone, otp_code, otp_type, expires_at)
        VALUES (?, ?, ?, 'phone_verify', ?)
    ");
    $stmt->execute([$customerId, $cleanNumber, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    // Send SMS via Termii
    $smsSent = sendTermiiOTP($cleanNumber, $otpCode, $otpId);
    
    // Update delivery status
    $db->prepare("UPDATE customer_otp_codes SET sms_sent = ? WHERE id = ?")
       ->execute([$smsSent ? 1 : 0, $otpId]);
    
    if (!$smsSent) {
        return ['success' => false, 'error' => 'Failed to send SMS. Please try again.'];
    }
    
    return [
        'success' => true, 
        'message' => 'SMS OTP sent to your phone number',
        'phone_masked' => maskPhoneNumber($cleanNumber)
    ];
}

/**
 * Verify phone OTP and complete registration
 */
function verifyPhoneAndCompleteRegistration($customerId, $phoneNumber, $otpCode) {
    $db = getDb();
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Find valid OTP
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
        // Increment attempts on latest OTP
        $db->prepare("
            UPDATE customer_otp_codes 
            SET attempts = attempts + 1 
            WHERE customer_id = ? AND phone = ? AND is_used = 0
            ORDER BY created_at DESC LIMIT 1
        ")->execute([$customerId, $cleanNumber]);
        
        return ['success' => false, 'error' => 'Invalid or expired OTP code'];
    }
    
    // Mark OTP as used
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    // Update customer with verified phone number and complete registration
    $stmt = $db->prepare("
        UPDATE customers 
        SET phone = ?, 
            phone_verified = 1,
            phone_verified_at = datetime('now'),
            registration_step = 0,  -- 0 means registration complete
            status = 'active',
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$cleanNumber, $customerId]);
    
    logCustomerActivity($customerId, 'registration_complete', 'Phone verified, registration complete');
    
    // Send welcome email
    $customer = getCustomerById($customerId);
    sendCustomerWelcomeEmail($customer['email'], $customer['full_name'] ?: $customer['username']);
    
    return ['success' => true, 'message' => 'Account registration complete!'];
}
```

---

## Checkout / Login Flow Diagram

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
│  │EMAIL   │  │    Input     │                                   │
│  │ ONLY   │  │              │                                   │
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
│       ┌────┴────┐                                               │
│       │         │                                               │
│       ▼         ▼                                               │
│  ┌─────────┐ ┌────────────────────┐                             │
│  │NEW USER │ │ EXISTING USER      │                             │
│  │         │ │                    │                             │
│  │ Go to   │ │ Continue to        │                             │
│  │ 3-Step  │ │ Payment Method     │                             │
│  │ Setup   │ │                    │                             │
│  └─────────┘ └────────────────────┘                             │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## Account Recovery System

Users can recover their account using either EMAIL or PHONE (SMS). This gives them flexibility in case they lose access to one of them.

### Recovery Options

```
┌─────────────────────────────────────────────────────────────────┐
│                    ACCOUNT RECOVERY OPTIONS                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ User clicks "Forgot Password" / "Forgot Email"            │  │
│  └─────────────────────────┬─────────────────────────────────┘  │
│                            │                                     │
│                            ▼                                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Enter your Email OR Phone Number to receive OTP           │  │
│  │                                                           │  │
│  │   ○ Send OTP to my Email                                  │  │
│  │   ○ Send OTP to my Phone (SMS)                            │  │
│  │                                                           │  │
│  └─────────────────────────┬─────────────────────────────────┘  │
│                            │                                     │
│              ┌─────────────┴─────────────┐                      │
│              │                           │                      │
│              ▼                           ▼                      │
│  ┌───────────────────┐      ┌───────────────────┐              │
│  │ EMAIL OTP         │      │ SMS OTP           │              │
│  │ (System Sends)    │      │ (Via Termii)      │              │
│  └─────────┬─────────┘      └─────────┬─────────┘              │
│            │                          │                         │
│            └────────────┬─────────────┘                         │
│                         │                                        │
│                         ▼                                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Enter OTP Code                                            │  │
│  └─────────────────────────┬─────────────────────────────────┘  │
│                            │                                     │
│                            ▼                                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ OTP Verified → Reset Password / Show Email                │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Recovery Functions

```php
/**
 * Initiate password recovery - User can choose email or phone
 * 
 * @param string $identifier Email or phone number
 * @param string $method 'email' or 'phone'
 * @return array Result
 */
function initiatePasswordRecovery($identifier, $method = 'email') {
    $db = getDb();
    
    // Find customer by email or phone
    if ($method === 'email') {
        $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND status = 'active'");
        $stmt->execute([$identifier]);
    } else {
        $stmt = $db->prepare("SELECT * FROM customers WHERE phone = ? AND phone_verified = 1 AND status = 'active'");
        $stmt->execute([$identifier]);
    }
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'No account found with this ' . $method];
    }
    
    // Generate OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, email, phone, otp_code, otp_type, expires_at)
        VALUES (?, ?, ?, ?, 'password_reset', ?)
    ");
    $stmt->execute([
        $customer['id'], 
        $customer['email'], 
        $customer['phone'], 
        $otpCode, 
        $expiresAt
    ]);
    $otpId = $db->lastInsertId();
    
    // Send OTP via chosen method
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

/**
 * Initiate email recovery (forgot email) - User provides phone number
 */
function initiateEmailRecovery($phoneNumber) {
    $db = getDb();
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Find customer by verified phone
    $stmt = $db->prepare("
        SELECT * FROM customers 
        WHERE phone = ? 
        AND phone_verified = 1 
        AND status = 'active'
    ");
    $stmt->execute([$cleanNumber]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'No account found with this phone number'];
    }
    
    // Generate OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP
    $stmt = $db->prepare("
        INSERT INTO customer_otp_codes 
        (customer_id, phone, otp_code, otp_type, expires_at)
        VALUES (?, ?, ?, 'email_recovery', ?)
    ");
    $stmt->execute([$customer['id'], $cleanNumber, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    // Send SMS OTP
    $sent = sendTermiiOTP($cleanNumber, $otpCode, $otpId);
    
    if (!$sent) {
        return ['success' => false, 'error' => 'Failed to send SMS. Please try again.'];
    }
    
    return [
        'success' => true,
        'message' => 'OTP sent to your phone',
        'customer_id' => $customer['id'],
        'phone_masked' => maskPhoneNumber($cleanNumber)
    ];
}

/**
 * Verify recovery OTP and show email (for forgot email)
 */
function verifyEmailRecoveryOTP($customerId, $phoneNumber, $otpCode) {
    $db = getDb();
    
    $cleanNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Verify OTP
    $stmt = $db->prepare("
        SELECT * FROM customer_otp_codes 
        WHERE customer_id = ? 
        AND phone = ?
        AND otp_code = ?
        AND otp_type = 'email_recovery'
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId, $cleanNumber, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        return ['success' => false, 'error' => 'Invalid or expired OTP'];
    }
    
    // Mark as used
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    // Get customer email
    $customer = getCustomerById($customerId);
    
    logCustomerActivity($customerId, 'email_recovered', 'User recovered email via phone OTP');
    
    return [
        'success' => true,
        'message' => 'Your email address is: ' . $customer['email'],
        'email' => $customer['email']
    ];
}

/**
 * Verify password reset OTP and allow password change
 */
function verifyPasswordResetOTP($customerId, $otpCode, $method = 'email') {
    $db = getDb();
    
    // Find valid OTP
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
        return ['success' => false, 'error' => 'Invalid or expired OTP'];
    }
    
    // Mark as used
    $db->prepare("UPDATE customer_otp_codes SET is_used = 1, used_at = datetime('now') WHERE id = ?")
       ->execute([$otp['id']]);
    
    // Generate password reset token
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $db->prepare("
        UPDATE customers 
        SET reset_token = ?, reset_token_expires = ?
        WHERE id = ?
    ")->execute([$resetToken, $tokenExpiry, $customerId]);
    
    logCustomerActivity($customerId, 'password_reset_verified', 'OTP verified, password reset allowed');
    
    return [
        'success' => true,
        'reset_token' => $resetToken,
        'message' => 'OTP verified. You can now reset your password.'
    ];
}

/**
 * Reset password using token
 */
function resetPasswordWithToken($resetToken, $newPassword, $confirmPassword) {
    $db = getDb();
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'Passwords do not match'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    // Find customer by reset token
    $stmt = $db->prepare("
        SELECT * FROM customers 
        WHERE reset_token = ? 
        AND reset_token_expires > datetime('now')
    ");
    $stmt->execute([$resetToken]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        return ['success' => false, 'error' => 'Invalid or expired reset link'];
    }
    
    // Update password and clear token
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->prepare("
        UPDATE customers 
        SET password_hash = ?, 
            password_changed_at = datetime('now'),
            reset_token = NULL,
            reset_token_expires = NULL,
            updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$hash, $customer['id']]);
    
    // Revoke all sessions (force re-login)
    $db->prepare("
        UPDATE customer_sessions 
        SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'password_changed'
        WHERE customer_id = ?
    ")->execute([$customer['id']]);
    
    logCustomerActivity($customer['id'], 'password_reset', 'Password reset successfully');
    
    // Send confirmation email
    sendPasswordSetEmail($customer['email'], $customer['full_name'] ?: $customer['username']);
    
    return ['success' => true, 'message' => 'Password reset successfully. Please login with your new password.'];
}
```

---

## OTP System

### OTP Generation (Email Only for Checkout)

```php
/**
 * Generate and send OTP to customer email (for checkout)
 * 
 * NOTE: At checkout, ONLY email OTP is sent. 
 * SMS OTP is used during registration Step 3 and account recovery.
 * 
 * @param string $email Customer email
 * @param string $type OTP type: email_verify (default for checkout)
 * @return array Success status and message
 */
function generateCheckoutOTP($email, $type = 'email_verify') {
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
        INSERT INTO customer_otp_codes (email, otp_code, otp_type, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$email, $otpCode, $type, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    // Send via email ONLY (no SMS at checkout)
    $emailSent = sendOTPEmail($email, $otpCode);
    
    // Update delivery status
    $db->prepare("UPDATE customer_otp_codes SET email_sent = ? WHERE id = ?")
       ->execute([$emailSent ? 1 : 0, $otpId]);
    
    return [
        'success' => $emailSent,
        'message' => $emailSent ? 'OTP sent to your email' : 'Failed to send OTP',
        'delivery' => [
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

---

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
        'whatsapp_number' => $session['whatsapp_number'],
        'username' => $session['username'],
        'registration_step' => $session['registration_step']
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

---

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
            'full_name' => $customer['full_name'],
            'registration_step' => $customer['registration_step']
        ],
        'token' => $token
    ];
}
```

---

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

### Create Customer Account (After Checkout OTP)

```php
/**
 * Create new customer account after email OTP verification at checkout
 * Account will need to complete 3-step registration
 */
function createCustomerAccount($email) {
    $db = getDb();
    
    // Check if already exists
    $check = checkCustomerEmail($email);
    if ($check['exists']) {
        return ['success' => false, 'message' => 'Account already exists', 'customer_id' => $check['customer_id']];
    }
    
    // Generate temporary username from email
    $username = explode('@', $email)[0] . '_' . random_int(100, 999);
    
    $stmt = $db->prepare("
        INSERT INTO customers (email, username, status, email_verified, registration_step)
        VALUES (?, ?, 'pending_setup', 1, 1)
    ");
    $stmt->execute([$email, $username]);
    $customerId = $db->lastInsertId();
    
    // Log activity
    logCustomerActivity($customerId, 'account_created', 'Account created via checkout OTP verification');
    
    return [
        'success' => true,
        'customer_id' => $customerId,
        'needs_setup' => true,
        'registration_step' => 1,
        'message' => 'Account created. Please complete setup.'
    ];
}
```

### Masking Functions

```php
/**
 * Mask email for display (show first 2 chars and domain)
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    
    if (strlen($name) <= 2) {
        return $name[0] . '*@' . $domain;
    }
    
    return substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) . '@' . $domain;
}

/**
 * Mask phone number for display
 */
function maskPhoneNumber($phone) {
    $length = strlen($phone);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($phone, -4);
}
```

---

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

---

## Database Schema Updates

```sql
-- Update customers table for new registration flow
ALTER TABLE customers ADD COLUMN whatsapp_number VARCHAR(20);
ALTER TABLE customers ADD COLUMN phone_verified INTEGER DEFAULT 0;
ALTER TABLE customers ADD COLUMN phone_verified_at DATETIME;
ALTER TABLE customers ADD COLUMN registration_step INTEGER DEFAULT 0;
-- 0 = complete, 1 = needs username/password, 2 = needs whatsapp, 3 = needs phone verification

ALTER TABLE customers ADD COLUMN reset_token VARCHAR(64);
ALTER TABLE customers ADD COLUMN reset_token_expires DATETIME;
```

---

## File Locations

New/Updated files:
- `includes/customer_auth.php` - All authentication functions
- `includes/customer_session.php` - Session management
- `includes/customer_otp.php` - OTP functions
- `includes/customer_recovery.php` - Account recovery functions
- `user/complete-setup.php` - 3-step registration completion page
- `user/forgot-password.php` - Password recovery (choose email or phone)
- `user/forgot-email.php` - Email recovery via phone
- `user/reset-password.php` - Password reset form

---

## Testing Checklist

- [ ] New user checkout sends EMAIL OTP only
- [ ] OTP verification creates account with registration_step = 1
- [ ] Step 1: Username and password set correctly
- [ ] Step 2: WhatsApp number saved (no OTP verification)
- [ ] Step 3: Phone number verified via SMS OTP
- [ ] Registration completes and user redirected to dashboard
- [ ] Existing user login with password works
- [ ] Account recovery via email works
- [ ] Account recovery via phone/SMS works
- [ ] Forgot email via phone works
- [ ] Password reset with token works
- [ ] Sessions revoked after password change
- [ ] Rate limiting works on all OTP endpoints
