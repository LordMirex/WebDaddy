<?php
/**
 * Customer Registration Page
 * 3-Step Registration Wizard:
 * Step 1: Email + OTP Verification
 * Step 2: Username + Password + Confirm Password + WhatsApp (MANDATORY)
 * Step 3: Success + Dashboard Guide
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/customer_session.php';
require_once __DIR__ . '/../includes/customer_otp.php';

startSecureSession();

$customer = validateCustomerSession();
if ($customer) {
    $isIncomplete = isset($_SESSION['incomplete_registration']) && $_SESSION['incomplete_registration'] === true;
    $registrationStep = (int)($customer['registration_step'] ?? 0);
    $accountComplete = (int)($customer['account_complete'] ?? 0);
    
    if (!$isIncomplete && $registrationStep === 0 && $accountComplete === 1) {
        header('Location: /user/');
        exit;
    }
    
    unset($_SESSION['incomplete_registration']);
}

$error = '';
$success = '';
$step = 'email';

if (isset($_SESSION['reg_customer_id']) && isset($_SESSION['reg_email_verified'])) {
    $step = 'password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - WebDaddy Empire</title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet"><script defer src="/assets/js/alpine.min.js"></script>
    <script defer src="/assets/js/alpine.min.js"></script>
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.css">
    <link rel="icon" href="/assets/images/favicon.png" type="image/png">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md" x-data="registrationFlow()">
        <div class="text-center mb-8">
            <a href="/">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-12 mx-auto mb-4">
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Create Your Account</h1>
            <p class="text-gray-600 mt-2">Join WebDaddy Empire today</p>
        </div>
        
        <!-- Step Indicator (3 steps) -->
        <div class="mb-6">
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center">
                    <div :class="step >= 1 ? 'bg-amber-600 text-white' : 'bg-gray-300 text-gray-600'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">1</div>
                    <span class="ml-1 text-xs hidden sm:inline" :class="step >= 1 ? 'text-amber-600 font-medium' : 'text-gray-500'">Email</span>
                </div>
                <div class="w-8 sm:w-12 h-px bg-gray-300"></div>
                <div class="flex items-center">
                    <div :class="step >= 2 ? 'bg-amber-600 text-white' : 'bg-gray-300 text-gray-600'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">2</div>
                    <span class="ml-1 text-xs hidden sm:inline" :class="step >= 2 ? 'text-amber-600 font-medium' : 'text-gray-500'">Account</span>
                </div>
                <div class="w-8 sm:w-12 h-px bg-gray-300"></div>
                <div class="flex items-center">
                    <div :class="step >= 3 ? 'bg-amber-600 text-white' : 'bg-gray-300 text-gray-600'" class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">3</div>
                    <span class="ml-1 text-xs hidden sm:inline" :class="step >= 3 ? 'text-amber-600 font-medium' : 'text-gray-500'">Done</span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><span x-text="error"></span>
            </div>
            
            <div x-show="success" class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">
                <i class="bi-check-circle mr-2"></i><span x-text="success"></span>
            </div>
            
            <!-- Step 1: Email Entry & OTP Verification -->
            <div x-show="step === 1">
                <!-- Email Input (before OTP sent) -->
                <div x-show="!otpSent">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi-envelope text-3xl text-amber-600"></i>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900">Verify Your Email</h2>
                        <p class="text-gray-600 text-sm mt-1">We'll send you a verification code</p>
                    </div>
                    
                    <form @submit.prevent="sendEmailOTP" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" x-model="email" required
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                   placeholder="your@email.com">
                        </div>
                        
                        <button type="submit" :disabled="loading" 
                                class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            <span x-show="!loading">Send Verification Code</span>
                            <span x-show="loading"><i class="bi-arrow-repeat animate-spin mr-2"></i>Sending...</span>
                        </button>
                    </form>
                </div>
                
                <!-- OTP Verification (after OTP sent) -->
                <div x-show="otpSent">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi-envelope-check text-3xl text-amber-600"></i>
                        </div>
                        <p class="text-gray-600">We've sent a 6-digit code to</p>
                        <p class="font-semibold text-gray-900" x-text="email"></p>
                    </div>
                    
                    <form @submit.prevent="verifyEmailOTP" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2 text-center">Enter Verification Code</label>
                            <input type="text" x-model="otpCode" required maxlength="6" pattern="[0-9]{6}"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-center text-2xl tracking-widest"
                                   placeholder="000000">
                        </div>
                        
                        <!-- Spam folder warning -->
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
                            <p class="text-amber-700 text-sm font-medium">
                                <i class="bi-exclamation-triangle mr-1"></i>
                                Can't find it? Check your <strong>Spam/Junk</strong> folder!
                            </p>
                        </div>
                        
                        <button type="submit" :disabled="loading || otpCode.length !== 6" 
                                class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            <span x-show="!loading">Verify Email</span>
                            <span x-show="loading"><i class="bi-arrow-repeat animate-spin mr-2"></i>Verifying...</span>
                        </button>
                        
                        <div class="text-center">
                            <button type="button" @click="resendOTP" :disabled="resendCooldown > 0" 
                                    class="text-amber-600 hover:underline text-sm disabled:text-gray-400">
                                <span x-show="resendCooldown <= 0">Resend code</span>
                                <span x-show="resendCooldown > 0">Resend in <span x-text="resendCooldown"></span>s</span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <button @click="otpSent = false; otpCode = ''; error = ''" class="text-gray-500 hover:text-gray-700 text-sm">
                            <i class="bi-arrow-left mr-1"></i> Change email
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Step 2: Username, Password, WhatsApp -->
            <div x-show="step === 2">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="bi-check-lg text-3xl text-green-600"></i>
                    </div>
                    <p class="text-gray-600">Email verified!</p>
                    <p class="font-semibold text-gray-900">Now set up your account</p>
                </div>
                
                <form @submit.prevent="saveCredentials" class="space-y-5">
                    <!-- Username (pre-filled with auto-generated, editable) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <input type="text" x-model="username" required minlength="3"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               placeholder="your_username">
                        <p class="text-xs text-gray-500 mt-1">Auto-generated from your email. You can change it.</p>
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" x-model="password" required minlength="6"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               placeholder="At least 6 characters">
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" x-model="confirmPassword" required minlength="6"
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               placeholder="Re-enter your password">
                    </div>
                    
                    <!-- WhatsApp Number - MANDATORY -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            WhatsApp Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" x-model="whatsappNumber" required
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               placeholder="+234 xxx xxx xxxx">
                        <p class="text-xs text-gray-500 mt-1">Required for order updates and support.</p>
                    </div>
                    
                    <button type="submit" :disabled="loading" 
                            class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                        <span x-show="!loading">Complete Registration</span>
                        <span x-show="loading"><i class="bi-arrow-repeat animate-spin mr-2"></i>Saving...</span>
                    </button>
                </form>
            </div>
            
            <!-- Step 3: Success -->
            <div x-show="step === 3">
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="bi-check-circle-fill text-4xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Account Created!</h2>
                    <p class="text-gray-600 mb-6">Welcome to WebDaddy Empire. Your account is ready.</p>
                    
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-left">
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Here you can track your order status, view delivery details, make payment via bank transfer or card, retry failed payments, and get support if needed.
                        </p>
                    </div>
                    
                    <a href="/user/" class="block w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition text-center">
                        Go to Dashboard
                    </a>
                    
                    <a href="/" class="block mt-4 text-amber-600 hover:underline">
                        Browse Products
                    </a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-6 space-y-4">
            <p class="text-gray-600 text-sm">
                Already have an account? <a href="/user/login.php" class="text-amber-600 hover:underline font-medium">Sign in</a>
            </p>
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm inline-block">
                <i class="bi-arrow-left mr-1"></i> Back to Store
            </a>
        </div>
    </div>
    
    <script>
    function registrationFlow() {
        return {
            step: 1,
            email: '',
            otpCode: '',
            otpSent: false,
            username: '',
            password: '',
            confirmPassword: '',
            whatsappNumber: '',
            customerId: null,
            loading: false,
            error: '',
            success: '',
            resendCooldown: 0,
            
            async sendEmailOTP() {
                if (!this.email || !this.email.includes('@')) {
                    this.error = 'Please enter a valid email address';
                    return;
                }
                
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
                        this.error = data.error || data.message || 'Failed to send verification code';
                    }
                } catch (e) {
                    this.error = 'Connection error. Please try again.';
                }
                
                this.loading = false;
            },
            
            async verifyEmailOTP() {
                if (this.otpCode.length !== 6) {
                    this.error = 'Please enter all 6 digits';
                    return;
                }
                
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
                        this.customerId = data.customer_id || data.customer?.id;
                        this.username = data.username || data.customer?.username || '';
                        this.step = 2;
                    } else {
                        this.error = data.message || data.error || 'Invalid verification code';
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
                
                if (!this.whatsappNumber || this.whatsappNumber.length < 10) {
                    this.error = 'Please enter a valid WhatsApp number';
                    return;
                }
                
                this.loading = true;
                this.error = '';
                
                try {
                    const response = await fetch('/api/customer/complete-registration.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            email: this.email,
                            username: this.username,
                            password: this.password,
                            confirm_password: this.confirmPassword,
                            whatsapp_number: this.whatsappNumber
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        this.step = 3;
                    } else {
                        this.error = data.error || data.message || 'Failed to complete registration';
                    }
                } catch (e) {
                    this.error = 'Connection error. Please try again.';
                }
                
                this.loading = false;
            },
            
            async resendOTP() {
                if (this.resendCooldown > 0) return;
                
                this.loading = true;
                this.error = '';
                
                try {
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
                        this.success = 'Verification code sent!';
                        this.startResendCooldown();
                        setTimeout(() => this.success = '', 3000);
                    } else {
                        this.error = data.error || 'Failed to resend code';
                    }
                } catch (e) {
                    this.error = 'Connection error. Please try again.';
                }
                
                this.loading = false;
            },
            
            startResendCooldown() {
                this.resendCooldown = 60;
                const timer = setInterval(() => {
                    this.resendCooldown--;
                    if (this.resendCooldown <= 0) {
                        clearInterval(timer);
                    }
                }, 1000);
            }
        }
    }
    </script>
</body>
</html>
