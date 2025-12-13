# Checkout Flow Changes

## Overview

This document details the modifications to the checkout process to integrate customer accounts while maintaining a conversion-optimized experience.

## Current Checkout Flow (Before Update)

```
1. User has items in cart
2. Goes to cart-checkout.php
3. Fills form: Name, Email, WhatsApp
4. Selects payment method (Manual/Automatic)
5. Manual: Shows bank details, WhatsApp buttons
6. Automatic: Redirects to Paystack
7. On success: Shows confirmation page (temporary URL)
8. Order tied to session_id only
```

## New Checkout Flow (After Update)

```
1. User has items in cart
2. Goes to cart-checkout.php
3. Step 1: Enter Email
   └── Check if email exists in customers table
   
4a. IF EMAIL EXISTS (Returning Customer):
    └── Show password input
    └── Login with password
    └── Session created
    └── Skip to Step 3 (Payment Method)
    
4b. IF EMAIL NEW (New Customer):
    └── Send OTP (SMS via Termii + Email)
    └── Show OTP input (6 digits)
    └── Verify OTP
    └── Create customer account (without password)
    └── Session created
    └── Skip to Step 3 (Payment Method)

5. Step 2: Personal Info (CONDITIONAL)
   └── ONLY SHOWN if customer has no phone on file
   └── Phone number input (WhatsApp)
   └── Update customer profile

6. Step 3: Payment Method Selection
   └── Manual or Automatic (Paystack)
   └── Order created, linked to customer_id

7. Payment Processing
   └── Manual: Bank details, WhatsApp buttons
   └── Automatic: Paystack popup

8. Confirmation → Redirect to User Dashboard
   └── /user/orders.php?confirmed=ORDER_ID
   └── Persistent, always accessible
```

## UI Component: Email Verification Step

### HTML Structure

```html
<div id="checkout-auth-step" x-data="checkoutAuth()">
    <!-- Step 1: Email Input -->
    <div x-show="step === 'email'" class="auth-step">
        <h3 class="text-lg font-semibold mb-4">Enter Your Email</h3>
        <p class="text-gray-600 mb-4">We'll check if you have an existing account</p>
        
        <div class="mb-4">
            <input 
                type="email" 
                x-model="email"
                @keyup.enter="checkEmail()"
                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500"
                placeholder="your@email.com"
                required
            >
        </div>
        
        <button 
            @click="checkEmail()" 
            :disabled="loading || !email"
            class="w-full bg-primary-600 text-white py-3 rounded-lg font-semibold hover:bg-primary-700 disabled:opacity-50"
        >
            <span x-show="!loading">Continue</span>
            <span x-show="loading" class="flex items-center justify-center">
                <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">...</svg>
                Checking...
            </span>
        </button>
    </div>
    
    <!-- Step 2a: Password Login (Existing User) -->
    <div x-show="step === 'password'" class="auth-step">
        <h3 class="text-lg font-semibold mb-2">Welcome Back!</h3>
        <p class="text-gray-600 mb-4">
            Logging in as <span class="font-medium" x-text="email"></span>
            <button @click="step = 'email'" class="text-primary-600 underline ml-2">Change</button>
        </p>
        
        <div class="mb-4">
            <input 
                type="password" 
                x-model="password"
                @keyup.enter="login()"
                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500"
                placeholder="Enter your password"
                required
            >
        </div>
        
        <div class="flex items-center justify-between mb-4">
            <a href="/user/forgot-password.php" class="text-sm text-primary-600 hover:underline">
                Forgot password?
            </a>
        </div>
        
        <button 
            @click="login()" 
            :disabled="loading || !password"
            class="w-full bg-primary-600 text-white py-3 rounded-lg font-semibold"
        >
            <span x-show="!loading">Login & Continue</span>
            <span x-show="loading">Logging in...</span>
        </button>
        
        <p x-show="error" class="text-red-600 text-sm mt-2" x-text="error"></p>
    </div>
    
    <!-- Step 2b: OTP Verification (New User) -->
    <div x-show="step === 'otp'" class="auth-step">
        <h3 class="text-lg font-semibold mb-2">Verify Your Email</h3>
        <p class="text-gray-600 mb-4">
            We sent a 6-digit code to <span class="font-medium" x-text="email"></span>
            <button @click="step = 'email'" class="text-primary-600 underline ml-2">Change</button>
        </p>
        
        <!-- OTP Input (6 boxes) -->
        <div class="flex justify-center gap-2 mb-4">
            <template x-for="(digit, index) in 6">
                <input 
                    type="text" 
                    maxlength="1"
                    x-model="otpDigits[index]"
                    @input="handleOTPInput($event, index)"
                    @keydown.backspace="handleOTPBackspace($event, index)"
                    @paste="handleOTPPaste($event)"
                    class="w-12 h-14 text-center text-2xl font-bold border-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    :id="'otp-' + index"
                >
            </template>
        </div>
        
        <p class="text-sm text-gray-500 mb-4">
            Check your inbox and spam folder. 
            <span x-show="canResend">
                <button @click="resendOTP()" class="text-primary-600 underline">Resend code</button>
            </span>
            <span x-show="!canResend">
                Resend in <span x-text="resendTimer"></span>s
            </span>
        </p>
        
        <button 
            @click="verifyOTP()" 
            :disabled="loading || otpCode.length !== 6"
            class="w-full bg-primary-600 text-white py-3 rounded-lg font-semibold"
        >
            <span x-show="!loading">Verify & Continue</span>
            <span x-show="loading">Verifying...</span>
        </button>
        
        <p x-show="error" class="text-red-600 text-sm mt-2" x-text="error"></p>
    </div>
    
    <!-- Step 3: Authenticated - Show Checkout Form -->
    <div x-show="step === 'authenticated'" class="auth-step">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 flex items-center">
            <i class="bi bi-check-circle-fill text-green-600 text-xl mr-3"></i>
            <div>
                <p class="font-medium text-green-800">Logged in as <span x-text="customerName || email"></span></p>
                <button @click="logout()" class="text-sm text-green-600 underline">Use different account</button>
            </div>
        </div>
        
        <!-- Phone Input (if needed) -->
        <div x-show="!customerPhone" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
            <input 
                type="tel" 
                x-model="phone"
                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500"
                placeholder="+234..."
                required
            >
            <p class="text-sm text-gray-500 mt-1">For order updates and support</p>
        </div>
        
        <!-- Payment Method Selection -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
            <!-- Existing payment method UI -->
        </div>
    </div>
</div>
```

### Alpine.js Component

```javascript
function checkoutAuth() {
    return {
        step: 'email', // email, password, otp, authenticated
        email: '',
        password: '',
        otpDigits: ['', '', '', '', '', ''],
        phone: '',
        
        loading: false,
        error: '',
        
        customerId: null,
        customerName: '',
        customerPhone: '',
        
        canResend: false,
        resendTimer: 60,
        
        get otpCode() {
            return this.otpDigits.join('');
        },
        
        async checkEmail() {
            if (!this.email) return;
            
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/check-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.email })
                });
                
                const data = await response.json();
                
                if (data.exists && data.has_password) {
                    // Existing user with password - show login
                    this.customerName = data.full_name;
                    this.step = 'password';
                } else {
                    // New user or no password - send OTP
                    await this.sendOTP();
                }
            } catch (err) {
                this.error = 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        
        async sendOTP() {
            this.loading = true;
            this.error = '';
            
            try {
                const response = await fetch('/api/customer/request-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        email: this.email,
                        phone: this.phone || null,
                        type: 'email_verify'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.step = 'otp';
                    this.startResendTimer();
                } else {
                    this.error = data.message || 'Failed to send OTP';
                }
            } catch (err) {
                this.error = 'Failed to send verification code';
            } finally {
                this.loading = false;
            }
        },
        
        async verifyOTP() {
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
                        type: 'email_verify'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.customerId = data.customer_id;
                    this.customerName = data.full_name;
                    this.customerPhone = data.phone;
                    this.step = 'authenticated';
                } else {
                    this.error = data.message || 'Invalid code';
                    this.otpDigits = ['', '', '', '', '', ''];
                    document.getElementById('otp-0')?.focus();
                }
            } catch (err) {
                this.error = 'Verification failed';
            } finally {
                this.loading = false;
            }
        },
        
        async login() {
            if (!this.password) return;
            
            this.loading = true;
            this.error = '';
            
            try {
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
                    this.customerId = data.customer.id;
                    this.customerName = data.customer.full_name;
                    this.customerPhone = data.customer.phone;
                    this.step = 'authenticated';
                } else {
                    this.error = data.message || 'Invalid credentials';
                }
            } catch (err) {
                this.error = 'Login failed';
            } finally {
                this.loading = false;
            }
        },
        
        // OTP input handling
        handleOTPInput(event, index) {
            const value = event.target.value;
            if (value && index < 5) {
                document.getElementById('otp-' + (index + 1))?.focus();
            }
            if (this.otpCode.length === 6) {
                this.verifyOTP();
            }
        },
        
        handleOTPBackspace(event, index) {
            if (!this.otpDigits[index] && index > 0) {
                document.getElementById('otp-' + (index - 1))?.focus();
            }
        },
        
        handleOTPPaste(event) {
            const paste = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            this.otpDigits = paste.split('').concat(['', '', '', '', '', '']).slice(0, 6);
            if (paste.length === 6) {
                this.verifyOTP();
            }
        },
        
        startResendTimer() {
            this.canResend = false;
            this.resendTimer = 60;
            const interval = setInterval(() => {
                this.resendTimer--;
                if (this.resendTimer <= 0) {
                    clearInterval(interval);
                    this.canResend = true;
                }
            }, 1000);
        },
        
        async resendOTP() {
            if (!this.canResend) return;
            await this.sendOTP();
        },
        
        logout() {
            this.step = 'email';
            this.email = '';
            this.password = '';
            this.otpDigits = ['', '', '', '', '', ''];
            this.customerId = null;
            this.customerName = '';
            this.customerPhone = '';
        }
    };
}
```

## Backend Changes to cart-checkout.php

### Order Creation with Customer ID

```php
// In the order creation section, add customer_id

// Get authenticated customer
$customer = validateCustomerSession();
$customerId = $customer ? $customer['id'] : null;

// Create order with customer_id
$orderData = [
    'customer_id' => $customerId,  // NEW
    'customer_name' => $customer ? $customer['full_name'] : $customerName,
    'customer_email' => $customer ? $customer['email'] : $customerEmail,
    'customer_phone' => $customer ? ($customer['phone'] ?: $_POST['phone']) : $customerPhone,
    'affiliate_code' => $totals['affiliate_code'],
    // ... rest of order data
];
```

### Confirmation Redirect

```php
// After successful order, redirect to user dashboard instead of session-based confirm page

if ($customerId) {
    // Authenticated user - redirect to dashboard
    header("Location: /user/orders.php?confirmed=" . $orderId);
} else {
    // Guest checkout (should be rare now) - use old confirm flow
    header("Location: /cart-checkout.php?confirmed=" . $orderId);
}
exit;
```

## Failed Payment Handling

When automatic payment fails, the customer is already logged in:

```php
// On payment failure
if ($paymentFailed) {
    // Customer account already exists
    // Order already created (status: pending)
    
    // Options presented to user:
    // 1. "Retry Payment" - Try Paystack again
    // 2. "Switch to Manual" - Show bank details
    // 3. "Complete Later" - Go to dashboard, order stays pending
}
```

User can later access unpaid orders from `/user/orders.php` and complete payment.

## Phone Number Update Flow

If customer logs in but has no phone number saved:

```php
// Before proceeding to payment
if ($customer && empty($customer['phone']) && !empty($_POST['phone'])) {
    // Update customer profile with phone
    $db->prepare("UPDATE customers SET phone = ?, updated_at = datetime('now') WHERE id = ?")
       ->execute([$_POST['phone'], $customer['id']]);
}
```

## Session Persistence

Cart persists across sessions for logged-in users:

```php
// When customer logs in, merge session cart with any saved cart
function mergeCustomerCart($customerId, $sessionId) {
    $db = getDb();
    
    // Get session cart items
    $sessionItems = $db->prepare("SELECT * FROM cart_items WHERE session_id = ? AND customer_id IS NULL")
        ->execute([$sessionId])->fetchAll();
    
    foreach ($sessionItems as $item) {
        // Check if already in customer's cart
        $existing = $db->prepare("SELECT id FROM cart_items WHERE customer_id = ? AND product_id = ? AND product_type = ?")
            ->execute([$customerId, $item['product_id'], $item['product_type']])->fetch();
        
        if ($existing) {
            // Update quantity
            $db->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?")
                ->execute([$item['quantity'], $existing['id']]);
        } else {
            // Add to customer's cart
            $db->prepare("UPDATE cart_items SET customer_id = ? WHERE id = ?")
                ->execute([$customerId, $item['id']]);
        }
    }
}
```

## Testing Checklist

- [ ] New user can checkout with OTP verification
- [ ] Returning user can login with password
- [ ] Password-less user gets OTP
- [ ] Failed OTP shows error, allows retry
- [ ] Rate limiting works (3 OTPs per hour)
- [ ] Order linked to customer_id
- [ ] Confirmation redirects to dashboard
- [ ] Phone number collected if missing
- [ ] Cart persists after login
- [ ] Failed payment allows retry/switch
