# Frontend Changes

## Overview

This document details all frontend UI/UX changes required for the customer account system.

## Index Page Changes

### 1. Navigation Bar Updates

Add customer account link to main navigation:

**Current navbar (index.php lines ~200-250):**
```html
<nav class="hidden md:flex items-center space-x-6">
    <a href="#products">Products</a>
    <a href="#templates">Templates</a>
    <a href="#tools">Tools</a>
    <a href="#contact">Contact</a>
</nav>
```

**Updated navbar:**
```html
<nav class="hidden md:flex items-center space-x-6">
    <a href="#products">Products</a>
    <a href="#templates">Templates</a>
    <a href="#tools">Tools</a>
    <a href="#contact">Contact</a>
    
    <!-- Customer Account Link -->
    <div x-data="{ customer: null }" x-init="checkCustomerSession()">
        <template x-if="customer">
            <a href="/user/" class="flex items-center text-primary-600 hover:text-primary-700">
                <i class="bi bi-person-circle mr-1"></i>
                <span x-text="customer.name || 'My Account'"></span>
            </a>
        </template>
        <template x-if="!customer">
            <a href="/user/login.php" class="text-gray-600 hover:text-primary-600">
                <i class="bi bi-box-arrow-in-right mr-1"></i>
                Track Order
            </a>
        </template>
    </div>
</nav>
```

### 2. Mobile Menu Updates

Add account link to mobile menu:

```html
<!-- Mobile Menu Items -->
<div class="md:hidden" x-show="mobileMenuOpen">
    <!-- Existing items -->
    <a href="#products">Products</a>
    <a href="#templates">Templates</a>
    <a href="#tools">Tools</a>
    <a href="#contact">Contact</a>
    
    <!-- Account Link -->
    <div class="border-t pt-4 mt-4">
        <div x-data="{ customer: null }" x-init="checkCustomerSession()">
            <a :href="customer ? '/user/' : '/user/login.php'" 
               class="flex items-center py-2 text-gray-700">
                <i class="bi bi-person-circle mr-2"></i>
                <span x-text="customer ? (customer.name || 'My Account') : 'Track Order'"></span>
            </a>
        </div>
    </div>
</div>
```

### 3. Customer Session Check Script

Add to main JS file or inline:

```javascript
// Check if customer is logged in
async function checkCustomerSession() {
    try {
        const response = await fetch('/api/customer/profile.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                return data.customer;
            }
        }
    } catch (e) {
        // Not logged in or error
    }
    return null;
}

// Alpine.js integration
document.addEventListener('alpine:init', () => {
    Alpine.data('customerNav', () => ({
        customer: null,
        async init() {
            this.customer = await checkCustomerSession();
        }
    }));
});
```

## Checkout Page Changes (cart-checkout.php)

### 1. Replace Personal Info Form

Replace the current customer info form with the new email verification flow.

**Current form structure (simplified):**
```html
<div class="customer-info">
    <input type="text" name="customer_name" placeholder="Full Name" required>
    <input type="email" name="customer_email" placeholder="Email" required>
    <input type="tel" name="customer_phone" placeholder="WhatsApp" required>
</div>
```

**New authentication step structure:**
```html
<div id="checkout-auth-section" x-data="checkoutAuth()">
    <!-- Email Input Step -->
    <div x-show="step === 'email'" class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            Email Address
        </label>
        <div class="flex gap-2">
            <input 
                type="email" 
                x-model="email"
                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                placeholder="your@email.com"
            >
            <button 
                @click="checkEmail()"
                :disabled="loading || !email"
                class="px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 disabled:opacity-50"
            >
                <span x-show="!loading">Continue</span>
                <span x-show="loading"><i class="bi bi-arrow-repeat animate-spin"></i></span>
            </button>
        </div>
    </div>
    
    <!-- Password Step (returning user) -->
    <div x-show="step === 'password'" class="mb-6">
        <div class="bg-blue-50 p-4 rounded-lg mb-4">
            <p class="text-blue-800">
                Welcome back! Login as <strong x-text="email"></strong>
                <button @click="step = 'email'" class="text-blue-600 underline ml-2 text-sm">change</button>
            </p>
        </div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
        <div class="flex gap-2">
            <input 
                type="password" 
                x-model="password"
                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg"
                placeholder="Enter your password"
            >
            <button 
                @click="login()"
                :disabled="loading"
                class="px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold"
            >
                Login
            </button>
        </div>
        <a href="/user/forgot-password.php" class="text-sm text-primary-600 mt-2 inline-block">
            Forgot password?
        </a>
    </div>
    
    <!-- OTP Step (new user) -->
    <div x-show="step === 'otp'" class="mb-6">
        <div class="bg-green-50 p-4 rounded-lg mb-4">
            <p class="text-green-800">
                <i class="bi bi-envelope-check mr-2"></i>
                Verification code sent to <strong x-text="email"></strong>
                <button @click="step = 'email'" class="text-green-600 underline ml-2 text-sm">change</button>
            </p>
        </div>
        
        <label class="block text-sm font-semibold text-gray-700 mb-2">Enter 6-digit code</label>
        
        <!-- OTP Input Boxes -->
        <div class="flex justify-center gap-3 mb-4">
            <template x-for="(_, i) in 6">
                <input 
                    type="text" 
                    maxlength="1"
                    x-model="otpDigits[i]"
                    @input="handleOTPInput($event, i)"
                    @keydown.backspace="handleOTPBackspace($event, i)"
                    @paste.prevent="handleOTPPaste($event)"
                    :id="'otp-' + i"
                    class="w-14 h-16 text-center text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                >
            </template>
        </div>
        
        <p class="text-sm text-gray-500 text-center">
            Check your inbox and spam folder.
            <span x-show="!canResend">Resend in <span x-text="resendTimer"></span>s</span>
            <button x-show="canResend" @click="resendOTP()" class="text-primary-600 underline">
                Resend code
            </button>
        </p>
        
        <p x-show="error" class="text-red-600 text-sm text-center mt-2" x-text="error"></p>
    </div>
    
    <!-- Authenticated State -->
    <div x-show="step === 'authenticated'" class="mb-6">
        <div class="bg-green-100 border border-green-300 p-4 rounded-lg flex items-center justify-between">
            <div class="flex items-center">
                <i class="bi bi-check-circle-fill text-green-600 text-xl mr-3"></i>
                <div>
                    <p class="font-semibold text-green-800" x-text="customerName || email"></p>
                    <p class="text-sm text-green-700" x-text="email"></p>
                </div>
            </div>
            <button @click="logout()" class="text-green-600 hover:text-green-800">
                <i class="bi bi-box-arrow-right"></i> Switch
            </button>
        </div>
        
        <!-- Phone input if needed -->
        <div x-show="!customerPhone" class="mt-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                WhatsApp Number <span class="text-red-500">*</span>
            </label>
            <input 
                type="tel" 
                x-model="phone"
                name="customer_phone"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg"
                placeholder="+234..."
                required
            >
            <p class="text-sm text-gray-500 mt-1">For order updates and support</p>
        </div>
        
        <!-- Hidden fields for form submission -->
        <input type="hidden" name="customer_id" :value="customerId">
        <input type="hidden" name="customer_email" :value="email">
        <input type="hidden" name="customer_name" :value="customerName || ''">
    </div>
</div>
```

### 2. Checkout Alpine.js Component

Add the full checkout auth component (see 03_CHECKOUT_FLOW.md for complete code).

### 3. Payment Form Conditional Display

Only show payment options after authentication:

```html
<div x-show="step === 'authenticated'" class="payment-section">
    <!-- Existing payment method selection -->
    <div class="payment-methods">
        <label class="payment-option">
            <input type="radio" name="payment_method" value="automatic" checked>
            <span>Pay with Card (Paystack)</span>
        </label>
        <label class="payment-option">
            <input type="radio" name="payment_method" value="manual">
            <span>Bank Transfer</span>
        </label>
    </div>
    
    <button type="submit" class="checkout-btn">
        Complete Order
    </button>
</div>
```

## Confirmation Page Changes

### Redirect to Dashboard

After successful order, redirect to user dashboard:

```php
// In cart-checkout.php after order creation
if ($customer) {
    header("Location: /user/order-detail.php?id={$orderId}&confirmed=1");
} else {
    // Fallback for edge cases
    header("Location: /cart-checkout.php?confirmed={$orderId}");
}
exit;
```

### Dashboard Order Confirmation View

In `/user/order-detail.php`, show confirmation banner for new orders:

```html
<?php if (isset($_GET['confirmed'])): ?>
<div class="bg-green-100 border border-green-300 rounded-xl p-6 mb-6 text-center">
    <i class="bi bi-check-circle-fill text-green-600 text-4xl mb-3"></i>
    <h2 class="text-2xl font-bold text-green-800 mb-2">Order Confirmed!</h2>
    <p class="text-green-700">Thank you for your purchase. You can track your order status below.</p>
</div>
<?php endif; ?>
```

## New CSS Additions

Add to `assets/css/style.css`:

```css
/* OTP Input Styling */
.otp-input {
    width: 3.5rem;
    height: 4rem;
    text-align: center;
    font-size: 1.5rem;
    font-weight: bold;
    border: 2px solid #d1d5db;
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.otp-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    outline: none;
}

.otp-input.filled {
    border-color: #10b981;
    background-color: #ecfdf5;
}

/* Auth Step Transitions */
.auth-step {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Customer Badge */
.customer-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #1e40af;
}

/* Account Dropdown (for navbar) */
.account-dropdown {
    position: relative;
}

.account-dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 0.5rem;
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    min-width: 200px;
    z-index: 50;
}
```

## JavaScript Files

### New: assets/js/customer-auth.js

```javascript
/**
 * Customer Authentication Module
 */

const CustomerAuth = {
    /**
     * Check if customer is logged in
     */
    async checkSession() {
        try {
            const response = await fetch('/api/customer/profile.php');
            if (response.ok) {
                const data = await response.json();
                return data.success ? data.customer : null;
            }
        } catch (e) {
            console.error('Session check failed:', e);
        }
        return null;
    },
    
    /**
     * Check email existence
     */
    async checkEmail(email) {
        const response = await fetch('/api/customer/check-email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        return response.json();
    },
    
    /**
     * Request OTP
     */
    async requestOTP(email, phone = null) {
        const response = await fetch('/api/customer/request-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, phone, type: 'email_verify' })
        });
        return response.json();
    },
    
    /**
     * Verify OTP
     */
    async verifyOTP(email, code) {
        const response = await fetch('/api/customer/verify-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, code, type: 'email_verify' })
        });
        return response.json();
    },
    
    /**
     * Login with password
     */
    async login(email, password) {
        const response = await fetch('/api/customer/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        return response.json();
    },
    
    /**
     * Logout
     */
    async logout() {
        const response = await fetch('/api/customer/logout.php', {
            method: 'POST'
        });
        return response.json();
    }
};

// Export for module usage
if (typeof module !== 'undefined') {
    module.exports = CustomerAuth;
}
```

## Page Load Scripts

Update page load in index.php and cart-checkout.php:

```html
<!-- Add before closing </body> -->
<script src="/assets/js/customer-auth.js"></script>
<script>
    // Initialize customer state for Alpine components
    document.addEventListener('alpine:init', () => {
        Alpine.store('customer', {
            data: null,
            async init() {
                this.data = await CustomerAuth.checkSession();
            }
        });
    });
</script>
```

## Testing Checklist

- [ ] Account link shows in navbar (desktop)
- [ ] Account link shows in mobile menu
- [ ] Shows "Track Order" when not logged in
- [ ] Shows customer name when logged in
- [ ] Email input step works correctly
- [ ] Password step shows for existing users
- [ ] OTP step shows for new users
- [ ] OTP boxes auto-advance on input
- [ ] OTP paste handling works
- [ ] Resend timer works correctly
- [ ] Authenticated state shows correctly
- [ ] Phone input shows when needed
- [ ] Form submits with customer data
- [ ] Confirmation redirects to dashboard
- [ ] CSS animations smooth
- [ ] Mobile responsive
