# WebDaddy Empire - Implementation Fix Guide

**Date:** December 15, 2025  
**Purpose:** Document all required changes to align checkout, registration, and user account flows with the correct specifications.

---

## Table of Contents
1. [Key Principles](#key-principles)
2. [Database Schema Changes](#database-schema-changes)
3. [Registration Page (user/register.php)](#registration-page)
4. [Checkout Page - New Users](#checkout-page---new-users)
5. [Checkout Page - Existing Users](#checkout-page---existing-users)
6. [Post-Payment Redirect](#post-payment-redirect)
7. [Account Completion Modal](#account-completion-modal)
8. [Manual Payment Flow](#manual-payment-flow)
9. [Automatic Payment Flow](#automatic-payment-flow)
10. [Admin Panel Updates](#admin-panel-updates)
11. [API Endpoints](#api-endpoints)
12. [File Changes Summary](#file-changes-summary)
13. [Testing Checklist](#testing-checklist)

---

## Key Principles

1. **NO `full_name` field** - Replace with `username` (auto-generated from email)
2. **Checkout = Email + OTP only** - New users only verify email to purchase
3. **Account completion happens AFTER purchase** - Via modal on order page
4. **WhatsApp is MANDATORY** - Not optional, but NOT verified
5. **Phone number is verified via SMS OTP** - Using Termii API
6. **All redirects go to `/user/orders/{order_id}`** - Not checkout confirmation page
7. **Admin shows username + email** - Instead of full_name

---

## Database Schema Changes

### 1. Customers Table Updates

```sql
-- These columns should exist or be added:
username VARCHAR(50) UNIQUE NOT NULL -- Auto-generated from email, editable
password_hash VARCHAR(255) -- NULL until account completed
whatsapp_number VARCHAR(20) NOT NULL -- MANDATORY, not verified
phone VARCHAR(20) -- Verified via SMS OTP
phone_verified INTEGER DEFAULT 0 -- 1 = verified
email_verified INTEGER DEFAULT 1 -- Always 1 after OTP
registration_step VARCHAR(20) DEFAULT 'email_verified' 
    -- Values: email_verified, credentials_set, phone_verified, completed
account_complete INTEGER DEFAULT 0 -- 0 = pending setup, 1 = complete
status VARCHAR(20) DEFAULT 'pending_setup' 
    -- Values: pending_setup, active, suspended

-- REMOVE or make optional:
full_name -- No longer used
```

### 2. Username Auto-Generation Function

```php
function generateUsernameFromEmail($email) {
    // Extract part before @
    $baseName = explode('@', strtolower($email))[0];
    
    // Remove special characters, keep alphanumeric and underscores
    $baseName = preg_replace('/[^a-z0-9_]/', '', $baseName);
    
    // Limit to 15 characters
    $baseName = substr($baseName, 0, 15);
    
    // Add random suffix for uniqueness
    $suffix = rand(100, 999);
    $username = $baseName . '_' . $suffix;
    
    // Check if exists, regenerate if needed
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM customers WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        // Recursively try again with different suffix
        return generateUsernameFromEmail($email);
    }
    
    return $username;
}

// Examples:
// john.doe@gmail.com → johndoe_427
// user123@yahoo.com → user123_891
```

### 3. Admin Queries Update

```sql
-- Replace full_name with username in all admin queries
SELECT 
    po.*,
    c.username,
    c.email,
    c.whatsapp_number,
    c.phone
FROM pending_orders po
LEFT JOIN customers c ON po.customer_id = c.id
```

---

## Registration Page

**Location:** `/user/register.php`

### Current Flow (WRONG)
```
Step 1: Email + Full Name → Send OTP
Step 2: OTP Verification
Step 3: Password + WhatsApp (optional)
Step 4: Success
```

### New Flow (CORRECT)
```
Step 1: Email ONLY → Send OTP → Verify OTP
Step 2: Username (editable) + Password + Confirm Password + WhatsApp (MANDATORY)
Step 3: Phone Number + SMS OTP Verification
Step 4: Success + Dashboard Guide
```

### Step 1: Email Entry & OTP Verification

```html
<!-- Step 1: Email Only - NO FULL NAME -->
<div x-show="step === 1">
    <h2>Step 1: Verify Your Email</h2>
    
    <form @submit.prevent="sendEmailOTP" x-show="!otpSent">
        <div>
            <label>Email Address</label>
            <input type="email" x-model="email" required
                   placeholder="your@email.com">
        </div>
        
        <!-- NO FULL NAME INPUT HERE -->
        
        <button type="submit" :disabled="loading">
            Send Verification Code
        </button>
    </form>
    
    <!-- OTP Input appears after email submitted -->
    <form @submit.prevent="verifyEmailOTP" x-show="otpSent">
        <p>Enter the 6-digit code sent to <span x-text="email"></span></p>
        
        <input type="text" x-model="otpCode" maxlength="6" 
               placeholder="000000">
        
        <button type="submit" :disabled="loading || otpCode.length !== 6">
            Verify & Continue
        </button>
        
        <button type="button" @click="resendOTP" :disabled="resendCooldown > 0">
            <span x-show="resendCooldown > 0">Resend in <span x-text="resendCooldown"></span>s</span>
            <span x-show="resendCooldown <= 0">Resend Code</span>
        </button>
    </form>
</div>
```

### Step 2: Credentials & WhatsApp

```html
<!-- Step 2: Username, Password, WhatsApp -->
<div x-show="step === 2">
    <h2>Step 2: Create Your Account</h2>
    
    <form @submit.prevent="saveCredentials">
        <!-- Username (pre-filled with auto-generated, editable) -->
        <div>
            <label>Username</label>
            <input type="text" x-model="username" required minlength="3"
                   placeholder="your_username">
            <p class="help-text">Auto-generated from your email. You can change it.</p>
        </div>
        
        <!-- Password -->
        <div>
            <label>Password</label>
            <input type="password" x-model="password" required minlength="6"
                   placeholder="At least 6 characters">
        </div>
        
        <!-- Confirm Password -->
        <div>
            <label>Confirm Password</label>
            <input type="password" x-model="confirmPassword" required
                   placeholder="Re-enter your password">
        </div>
        
        <!-- WhatsApp Number - MANDATORY -->
        <div>
            <label>WhatsApp Number <span class="required">*</span></label>
            <input type="tel" x-model="whatsappNumber" required
                   placeholder="+234 xxx xxx xxxx">
            <p class="help-text">Required for order updates and support.</p>
        </div>
        
        <button type="submit" :disabled="loading">
            Continue
        </button>
    </form>
</div>
```

### Step 3: Phone Number with SMS OTP

```html
<!-- Step 3: Phone Number Verification -->
<div x-show="step === 3">
    <h2>Step 3: Verify Your Phone</h2>
    
    <!-- Phone Input (before sending SMS) -->
    <div x-show="!phoneSent">
        <div>
            <label>Phone Number (SMS)</label>
            <input type="tel" x-model="phoneNumber" required
                   placeholder="+234 xxx xxx xxxx">
            <p class="help-text">
                <strong>Important:</strong> We will send a verification code to this number. 
                Make sure you can receive SMS on this number.
            </p>
        </div>
        
        <button @click="sendPhoneOTP" :disabled="loading || !phoneNumber">
            Send SMS Code
        </button>
    </div>
    
    <!-- Phone OTP Input (after sending SMS) -->
    <div x-show="phoneSent">
        <p>Enter the code sent to <span x-text="phoneNumber"></span></p>
        
        <input type="text" x-model="phoneOtpCode" maxlength="6"
               placeholder="000000">
        
        <button @click="verifyPhoneOTP" :disabled="loading || phoneOtpCode.length !== 6">
            Complete Registration
        </button>
        
        <button type="button" @click="phoneSent = false">
            Change Number
        </button>
    </div>
</div>
```

### Step 4: Success & Dashboard Guide

```html
<!-- Step 4: Success -->
<div x-show="step === 4">
    <div class="success-icon">✓</div>
    <h2>Account Created Successfully!</h2>
    
    <div class="guide-box">
        <h3>Quick Guide</h3>
        <ul>
            <li><strong>Browse Products:</strong> Check our templates and tools</li>
            <li><strong>Track Orders:</strong> See all your purchases and delivery status</li>
            <li><strong>View Credentials:</strong> Access website credentials after delivery</li>
            <li><strong>Download Files:</strong> Get your purchased files anytime</li>
        </ul>
    </div>
    
    <a href="/user/" class="btn-primary">Go to Dashboard</a>
    <a href="/" class="btn-secondary">Browse Products</a>
</div>
```

### Registration JavaScript

```javascript
function registrationFlow() {
    return {
        step: 1,
        email: '',
        otpCode: '',
        otpSent: false,
        username: '', // Pre-filled after OTP verification
        password: '',
        confirmPassword: '',
        whatsappNumber: '', // MANDATORY
        phoneNumber: '',
        phoneOtpCode: '',
        phoneSent: false,
        loading: false,
        error: '',
        resendCooldown: 0,
        
        async sendEmailOTP() {
            if (!this.email) return;
            this.loading = true;
            this.error = '';
            
            try {
                // First check if email already registered
                const checkResponse = await fetch('/api/customer/check-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.email })
                });
                const checkData = await checkResponse.json();
                
                if (checkData.exists && checkData.has_password) {
                    this.error = 'This email is already registered. Please login instead.';
                    this.loading = false;
                    return;
                }
                
                // Send OTP
                const response = await fetch('/api/customer/request-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        email: this.email,
                        type: 'email_verify'
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.otpSent = true;
                    this.startResendCooldown();
                } else {
                    this.error = data.message || 'Failed to send code';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        async verifyEmailOTP() {
            if (this.otpCode.length !== 6) return;
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/verify-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: this.email,
                        code: this.otpCode,
                        type: 'email_verify',
                        source: 'registration'
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    // Account created, username auto-generated
                    this.username = data.username; // Pre-fill with auto-generated
                    this.step = 2;
                } else {
                    this.error = data.message || 'Invalid code';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        async saveCredentials() {
            // Validate
            if (this.password !== this.confirmPassword) {
                this.error = 'Passwords do not match';
                return;
            }
            if (this.password.length < 6) {
                this.error = 'Password must be at least 6 characters';
                return;
            }
            if (!this.whatsappNumber) {
                this.error = 'WhatsApp number is required';
                return;
            }
            
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/set-credentials.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: this.email,
                        username: this.username,
                        password: this.password,
                        whatsapp_number: this.whatsappNumber
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.step = 3;
                } else {
                    this.error = data.message || 'Failed to save';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        async sendPhoneOTP() {
            if (!this.phoneNumber) return;
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/send-phone-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: this.email,
                        phone: this.phoneNumber
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.phoneSent = true;
                } else {
                    this.error = data.message || 'Failed to send SMS';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        async verifyPhoneOTP() {
            if (this.phoneOtpCode.length !== 6) return;
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/verify-phone-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: this.email,
                        phone: this.phoneNumber,
                        code: this.phoneOtpCode
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    this.step = 4; // Success!
                } else {
                    this.error = data.message || 'Invalid code';
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            }
            
            this.loading = false;
        },
        
        startResendCooldown() {
            this.resendCooldown = 60;
            const interval = setInterval(() => {
                this.resendCooldown--;
                if (this.resendCooldown <= 0) clearInterval(interval);
            }, 1000);
        },
        
        async resendOTP() {
            if (this.resendCooldown > 0) return;
            await this.sendEmailOTP();
        }
    };
}
```

---

## Checkout Page - New Users

**Goal:** Maximize conversion - users buy BEFORE completing registration

### Current Flow (WRONG)
```
Email → OTP → Full Name → Personal Info → Payment → Confirmation Page
```

### New Flow (CORRECT)
```
Email → OTP → Payment → /user/orders/{id} → Account Completion Modal
```

### Changes Required

#### 1. Remove Full Name Input
- Delete the full name input field from checkout form
- Remove `fullName` / `newName` Alpine.js variables
- Remove name validation logic

#### 2. Skip Personal Info Section for New Users
After OTP is verified:
- DO NOT show personal info form
- Show: "Logged in as {username} ({email})"
- Enable payment method selection immediately

#### 3. JavaScript Changes for Checkout

```javascript
// In checkoutAuth() or similar component
async verifyOTP() {
    // ... verification logic ...
    
    if (data.success) {
        this.customerId = data.customer_id;
        this.username = data.username;
        
        // SKIP personal info - go directly to payment selection
        this.step = 'authenticated';
        this.canProceed = true;
        
        // Hide personal info section
        this.showPersonalInfo = false;
    }
}
```

#### 4. Checkout Form After OTP

```html
<!-- After OTP verified, show this instead of personal info form -->
<div x-show="step === 'authenticated'" class="logged-in-badge">
    <i class="bi-check-circle-fill text-green-600"></i>
    <span>Logged in as <strong x-text="username"></strong> (<span x-text="email"></span>)</span>
    <button @click="logout()" class="text-sm">Use different account</button>
</div>

<!-- Payment method selection - NO personal info inputs -->
<div class="payment-methods">
    <label>
        <input type="radio" name="payment" value="automatic" x-model="paymentMethod">
        Pay with Card (Paystack)
    </label>
    <label>
        <input type="radio" name="payment" value="manual" x-model="paymentMethod">
        Bank Transfer
    </label>
</div>

<!-- Proceed button - ACTIVE after OTP verified -->
<button @click="placeOrder()" :disabled="!canProceed">
    Place Order
</button>
```

---

## Checkout Page - Existing Users

### Flow for Users with Password
```
Email → Password → Payment → /user/orders/{id}
```

### Steps

1. **Email Entry**
   - User enters email
   - System checks: email exists AND has password

2. **Password Input**
   - Show "Welcome back!" message
   - Password input field
   - Login button

3. **After Login**
   - Session created
   - Skip to payment selection
   - No account completion modal needed after purchase

```javascript
// When checking email
async checkEmail() {
    const response = await fetch('/api/customer/check-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: this.email })
    });
    const data = await response.json();
    
    if (data.exists && data.has_password) {
        // Show password login
        this.step = 'password';
    } else if (data.exists && !data.has_password) {
        // Account exists but incomplete - send OTP
        await this.sendOTP();
    } else {
        // New user - send OTP
        await this.sendOTP();
    }
}

async login() {
    const response = await fetch('/api/customer/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: this.email,
            password: this.password
        })
    });
    const data = await response.json();
    
    if (data.success) {
        this.step = 'authenticated';
        this.canProceed = true;
        this.username = data.customer.username;
        this.accountComplete = data.customer.account_complete;
    } else {
        this.error = data.message || 'Invalid password';
    }
}
```

---

## Post-Payment Redirect

### Current (WRONG)
```php
header("Location: /cart-checkout.php?confirmed=" . $orderId);
```

### New (CORRECT)
```php
// After successful payment (Paystack callback or manual order creation)
if ($customerId) {
    // Redirect to user's order page
    $redirectUrl = "/user/orders/" . $orderId;
    
    // Add flag for first-time user (triggers modal)
    if ($customer['account_complete'] == 0) {
        $redirectUrl .= "?setup=1";
    }
    
    header("Location: " . $redirectUrl);
} else {
    // Fallback (shouldn't happen with new flow)
    header("Location: /cart-checkout.php?confirmed=" . $orderId);
}
exit;
```

### In Paystack Callback

```php
// api/paystack-verify.php or callback handler
if ($paymentVerified) {
    // Update order status
    updateOrderStatus($orderId, 'paid');
    
    // Process delivery
    processDelivery($orderId);
    
    // Get customer to check account status
    $customer = getCustomerById($customerId);
    $needsSetup = ($customer['account_complete'] == 0);
    
    // Redirect to order page
    $url = "/user/orders/" . $orderId;
    if ($needsSetup) {
        $url .= "?setup=1";
    }
    
    header("Location: " . $url);
    exit;
}
```

---

## Account Completion Modal

### When to Show
- User lands on `/user/orders/{id}`
- AND `customer.account_complete = 0`
- AND URL has `?setup=1` OR this is first visit to completed order

### Modal Structure (2 Steps)

#### Step 1: Username & Password

```html
<div class="modal-step" x-show="modalStep === 1">
    <h3>Complete Your Account</h3>
    <p>Set up your login credentials</p>
    
    <div class="form-group">
        <label>Username</label>
        <input type="text" x-model="username">
        <p class="help">You can edit this anytime</p>
    </div>
    
    <div class="form-group">
        <label>Password</label>
        <input type="password" x-model="password" placeholder="At least 6 characters">
    </div>
    
    <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" x-model="confirmPassword">
    </div>
    
    <button @click="saveCredentialsAndContinue()">Continue</button>
</div>
```

#### Step 2: WhatsApp & Phone

```html
<div class="modal-step" x-show="modalStep === 2">
    <h3>Contact Information</h3>
    
    <div class="form-group">
        <label>WhatsApp Number <span class="required">*</span></label>
        <input type="tel" x-model="whatsappNumber" required>
        <p class="help">For order updates and support</p>
    </div>
    
    <div class="form-group">
        <label>Phone Number (SMS)</label>
        <input type="tel" x-model="phoneNumber" x-show="!phoneSent">
        <p class="help" x-show="!phoneSent">We'll send a verification code</p>
        
        <button @click="sendPhoneOTP()" x-show="!phoneSent" :disabled="!phoneNumber">
            Send Code
        </button>
        
        <!-- Phone OTP Input -->
        <div x-show="phoneSent">
            <p>Enter code sent to <span x-text="phoneNumber"></span></p>
            <input type="text" x-model="phoneOtp" maxlength="6">
        </div>
    </div>
    
    <button @click="completeAccount()" :disabled="!whatsappNumber || (phoneSent && phoneOtp.length !== 6)">
        Complete Setup
    </button>
</div>
```

### Modal JavaScript

```javascript
function accountSetupModal() {
    return {
        showModal: false,
        modalStep: 1,
        
        // Pre-filled from server
        username: '',
        email: '',
        
        // User inputs
        password: '',
        confirmPassword: '',
        whatsappNumber: '',
        phoneNumber: '',
        phoneOtp: '',
        phoneSent: false,
        
        loading: false,
        error: '',
        
        init() {
            // Check if modal should show
            const needsSetup = document.body.dataset.needsSetup === 'true';
            const urlParams = new URLSearchParams(window.location.search);
            const hasSetupFlag = urlParams.has('setup');
            
            if (needsSetup || hasSetupFlag) {
                this.showModal = true;
                this.username = document.body.dataset.username || '';
                this.email = document.body.dataset.email || '';
            }
        },
        
        async saveCredentialsAndContinue() {
            if (this.password !== this.confirmPassword) {
                this.error = 'Passwords do not match';
                return;
            }
            
            this.loading = true;
            
            const response = await fetch('/api/customer/update-credentials.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: this.username,
                    password: this.password
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.modalStep = 2;
            } else {
                this.error = data.message;
            }
            
            this.loading = false;
        },
        
        async sendPhoneOTP() {
            this.loading = true;
            
            const response = await fetch('/api/customer/send-phone-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    whatsapp_number: this.whatsappNumber,
                    phone: this.phoneNumber
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.phoneSent = true;
            } else {
                this.error = data.message;
            }
            
            this.loading = false;
        },
        
        async completeAccount() {
            this.loading = true;
            
            const response = await fetch('/api/customer/complete-setup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    whatsapp_number: this.whatsappNumber,
                    phone: this.phoneNumber,
                    phone_otp: this.phoneOtp
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showModal = false;
                // Remove setup param from URL
                window.history.replaceState({}, '', window.location.pathname);
            } else {
                this.error = data.message;
            }
            
            this.loading = false;
        }
    };
}
```

---

## Manual Payment Flow

### Current (WRONG)
- Order created → Stay on checkout page → Show bank details there

### New (CORRECT)
- Order created → Redirect to `/user/orders/{id}?manual=1` → Show bank details on order page

### Order Page for Manual Payment

```html
<!-- Show when order is pending and payment method is manual -->
<div x-show="order.status === 'pending' && order.payment_method === 'manual'" class="payment-panel">
    <h3>Complete Your Payment</h3>
    
    <div class="bank-details">
        <p class="label">Transfer to:</p>
        <p class="account-number">0123456789</p>
        <p class="bank-name">First Bank</p>
        <p class="account-name">WebDaddy Empire</p>
        
        <button @click="copyAccountNumber()" class="copy-btn">
            <i class="bi-clipboard"></i> Copy Account Number
        </button>
    </div>
    
    <div class="amount">
        <p>Amount to pay:</p>
        <p class="amount-value">₦<span x-text="order.total"></span></p>
    </div>
    
    <div class="actions">
        <button @click="confirmPayment()" class="btn-success">
            <i class="bi-check-circle"></i> I've Completed Payment
        </button>
        
        <a :href="getWhatsAppLink()" target="_blank" class="btn-whatsapp">
            <i class="bi-whatsapp"></i> Send Proof via WhatsApp
        </a>
    </div>
</div>

<!-- Order Items (visible but delivery blocked) -->
<div class="order-items">
    <h4>Your Order</h4>
    <!-- List of products -->
    <p class="pending-notice">Delivery will be available after payment confirmation.</p>
</div>
```

### WhatsApp Link Generation

```javascript
getWhatsAppLink() {
    const message = encodeURIComponent(
        `Hi, I've completed payment for Order #${this.order.id}\n` +
        `Amount: ₦${this.order.total}\n` +
        `Email: ${this.order.customer_email}`
    );
    return `https://wa.me/234xxxxxxxxx?text=${message}`;
}
```

---

## Automatic Payment Flow

### Flow After Paystack Success

1. Paystack callback verifies payment
2. Order status updated to `paid`
3. Delivery processed
4. Redirect to `/user/orders/{order_id}?setup=1` (if first-time user)
5. Order page loads with delivery info
6. Account completion modal appears (if needed)

### Paystack Callback Handler

```php
// After payment verified
if ($paymentSuccess) {
    // Update order
    $db->prepare("UPDATE pending_orders SET status = 'paid', paid_at = datetime('now') WHERE id = ?")
        ->execute([$orderId]);
    
    // Trigger delivery
    processDelivery($orderId);
    
    // Get customer status
    $customer = getCustomerById($customerId);
    
    // Build redirect URL
    $url = "/user/orders/" . $orderId;
    if ($customer['account_complete'] == 0) {
        $url .= "?setup=1";
    }
    
    header("Location: " . $url);
    exit;
}
```

---

## Admin Panel Updates

### Orders List (`admin/orders.php`)

**Replace:** "Full Name" column  
**With:** "Username" column

```php
// Table header
<th>Customer</th>

// Table row
<td>
    <?php echo htmlspecialchars($order['username']); ?>
    <br>
    <small><?php echo htmlspecialchars($order['email']); ?></small>
</td>
```

### Customers List (`admin/customers.php`)

```php
// Show username as primary identifier
<td>
    <strong><?php echo htmlspecialchars($customer['username']); ?></strong>
</td>
<td><?php echo htmlspecialchars($customer['email']); ?></td>
<td><?php echo htmlspecialchars($customer['whatsapp_number']); ?></td>
<td><?php echo htmlspecialchars($customer['phone']); ?></td>
<td>
    <?php if ($customer['account_complete']): ?>
        <span class="badge-success">Complete</span>
    <?php else: ?>
        <span class="badge-warning">Pending Setup</span>
    <?php endif; ?>
</td>
```

### Order Detail (`admin/order-detail.php`)

```php
<div class="customer-info">
    <h4>Customer Information</h4>
    <p><strong>Username:</strong> <?php echo $customer['username']; ?></p>
    <p><strong>Email:</strong> <?php echo $customer['email']; ?></p>
    <p><strong>WhatsApp:</strong> <?php echo $customer['whatsapp_number']; ?></p>
    <p><strong>Phone:</strong> <?php echo $customer['phone']; ?></p>
</div>
```

---

## API Endpoints

### Existing Endpoints (Modify)

```
POST /api/customer/check-email.php
  Input: { email }
  Output: { exists, has_password, username, account_complete }

POST /api/customer/verify-otp.php
  Input: { email, code, type, source }
  Output: { success, customer_id, username, needs_setup, can_purchase }
  
  CHANGES:
  - Return auto-generated username
  - Set account_complete = 0 for new accounts
  - NO full_name parameter needed
```

### New Endpoints (Create)

```
POST /api/customer/set-credentials.php
  Input: { email, username, password, whatsapp_number }
  Output: { success }
  
  - Save username (check uniqueness)
  - Hash and save password
  - Save whatsapp_number
  - Update registration_step = 'credentials_set'

POST /api/customer/send-phone-otp.php
  Input: { email, phone } OR { whatsapp_number, phone }
  Output: { success, message }
  
  - Send SMS via Termii API
  - Store OTP in customer_otp_codes table
  - Type: 'phone_verify'

POST /api/customer/verify-phone-otp.php
  Input: { email, phone, code }
  Output: { success }
  
  - Verify phone OTP
  - Set phone_verified = 1
  - Update registration_step = 'phone_verified'

POST /api/customer/complete-setup.php
  Input: { whatsapp_number, phone, phone_otp } (from modal)
  Output: { success }
  
  - Called from account completion modal
  - Verify phone OTP
  - Save whatsapp_number if not already saved
  - Set phone_verified = 1
  - Set account_complete = 1
  - Set status = 'active'
  - Set registration_step = 'completed'

POST /api/customer/update-credentials.php
  Input: { username, password }
  Output: { success }
  
  - Called from account completion modal step 1
  - Update username (check uniqueness)
  - Hash and update password
```

---

## File Changes Summary

| File | Action | Changes |
|------|--------|---------|
| `user/register.php` | **Major Rewrite** | Remove full_name, add 4-step wizard, add phone OTP |
| `cart-checkout.php` | **Modify** | Remove name input, skip personal info, change redirect |
| `includes/customer_auth.php` | **Modify** | Add generateUsernameFromEmail(), update createCustomerAccount() |
| `api/customer/verify-otp.php` | **Modify** | Return username, set account_complete=0 |
| `api/customer/set-credentials.php` | **Create** | New endpoint for step 2 of registration |
| `api/customer/send-phone-otp.php` | **Create** | New endpoint for SMS OTP |
| `api/customer/verify-phone-otp.php` | **Create** | New endpoint for phone verification |
| `api/customer/complete-setup.php` | **Create** | New endpoint for modal completion |
| `api/customer/update-credentials.php` | **Create** | New endpoint for modal step 1 |
| `user/orders.php` or `user/order-detail.php` | **Modify** | Add account completion modal, add manual payment display |
| `admin/orders.php` | **Modify** | Replace full_name with username |
| `admin/customers.php` | **Modify** | Replace full_name with username |
| `admin/order-detail.php` | **Modify** | Update customer info display |
| `assets/js/customer-auth.js` | **Modify** | Remove full name handling |

---

## Testing Checklist

### Registration Page
- [ ] Step 1: Can enter email only (no full name)
- [ ] Step 1: OTP sent and can be verified
- [ ] Step 2: Username pre-filled with auto-generated value
- [ ] Step 2: Username is editable
- [ ] Step 2: WhatsApp is required (cannot proceed without it)
- [ ] Step 2: Password validation works
- [ ] Step 3: Phone number accepts input
- [ ] Step 3: SMS OTP is sent via Termii
- [ ] Step 3: Phone OTP can be verified
- [ ] Step 4: Success page shows and can navigate to dashboard

### Checkout - New User
- [ ] Only email input shown (no full name)
- [ ] OTP verification works
- [ ] Personal info section is skipped
- [ ] Can proceed to payment after OTP
- [ ] Paystack payment works
- [ ] Redirects to /user/orders/{id} after payment
- [ ] Account completion modal appears

### Checkout - Existing User
- [ ] Email check shows password input
- [ ] Can login with password
- [ ] Proceeds to payment after login
- [ ] No modal needed after purchase

### Account Completion Modal
- [ ] Shows for first-time purchasers
- [ ] Step 1: Can edit username and set password
- [ ] Step 2: WhatsApp is required
- [ ] Step 2: Phone OTP works
- [ ] Modal closes after completion
- [ ] Account marked as complete

### Manual Payment
- [ ] Order created with pending status
- [ ] Redirects to /user/orders/{id}
- [ ] Bank details shown on order page
- [ ] "I've Paid" button works
- [ ] WhatsApp link generates correctly

### Admin Panel
- [ ] Orders list shows username instead of full_name
- [ ] Customer list shows username
- [ ] Order detail shows username

---

## Notes

1. **WhatsApp Number is MANDATORY** - Users cannot complete registration or account setup without it. It is NOT verified via OTP - just stored.

2. **Phone Number is VERIFIED** - SMS OTP is sent via Termii API and must be verified.

3. **Username is Auto-Generated** - From email local part + random suffix. Users can edit it.

4. **Full Name is DEPRECATED** - Should not be collected anywhere. Keep column for legacy data but don't use it.

5. **Session Persistence** - After OTP/login, sessions should last 30+ days with "remember me" cookie.

6. **Conversion Focus** - Users can purchase with JUST email OTP. Account completion is post-purchase.
