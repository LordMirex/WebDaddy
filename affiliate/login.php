<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isset($_SESSION['affiliate_id'])) {
    header('Location: /affiliate/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $emailOrCode = sanitizeInput($_POST['email_or_code'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($emailOrCode) || empty($password)) {
            $error = 'Please enter your email/code and password.';
        } elseif (isRateLimited($emailOrCode, 'affiliate')) {
            $error = getRateLimitMessage($emailOrCode, 'affiliate');
        } else {
            if (loginAffiliate($emailOrCode, $password)) {
                clearLoginAttempts($emailOrCode, 'affiliate');
                header('Location: /affiliate/');
                exit;
            } else {
                trackLoginAttempt($emailOrCode, 'affiliate');
                $error = 'Invalid credentials or inactive account. Please check your email/affiliate code and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Affiliate Login - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: '#d4af37',
                        navy: '#0f172a'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-primary-900 via-primary-800 to-primary-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden transform hover:scale-[1.02] transition-transform duration-300">
            <div class="p-8 sm:p-10">
                <!-- Logo & Header -->
                <div class="text-center mb-8">
                    <img src="<?php echo BASE_PATH; ?>assets/images/webdaddy-logo.png" alt="<?php echo SITE_NAME; ?>" class="max-w-[120px] mx-auto mb-4">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Affiliate Login</h2>
                    <p class="text-gray-500 font-medium"><?php echo SITE_NAME; ?></p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="bi bi-exclamation-triangle text-red-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-red-700 text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Success Message -->
                <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="bi bi-check-circle text-green-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-green-700 text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" x-data="{ loading: false }" @submit="loading = true">
                    <?php echo csrfTokenField(); ?>
                    
                    <!-- Email/Code Input -->
                    <div class="mb-6">
                        <label for="email_or_code" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email or Affiliate Code
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-person text-gray-400 text-lg"></i>
                            </div>
                            <input type="text" 
                                   id="email_or_code" 
                                   name="email_or_code" 
                                   placeholder="Enter email or affiliate code"
                                   value="<?php echo htmlspecialchars($_POST['email_or_code'] ?? ''); ?>"
                                   required 
                                   autofocus
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                    
                    <!-- Password Input -->
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-lock text-gray-400 text-lg"></i>
                            </div>
                            <input type="password" 
                                   id="password" 
                                   name="password"
                                   autocomplete="current-password" 
                                   placeholder="Enter password"
                                   required 
                                   minlength="6"
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            :disabled="loading"
                            class="w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold py-3.5 px-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center space-x-2">
                        <i class="bi bi-box-arrow-in-right" x-show="!loading"></i>
                        <i class="bi bi-arrow-repeat animate-spin" x-show="loading" style="display: none;"></i>
                        <span x-text="loading ? 'Logging in...' : 'Login'">Login</span>
                    </button>
                </form>
                
                <!-- Divider -->
                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 font-medium">New to our program?</span>
                    </div>
                </div>
                
                <!-- Register Link -->
                <a href="/affiliate/register.php" class="w-full flex items-center justify-center space-x-2 px-6 py-3 border-2 border-primary-600 text-primary-700 font-bold rounded-lg hover:bg-primary-50 transition-all duration-200 group">
                    <i class="bi bi-person-plus text-lg group-hover:scale-110 transition-transform"></i>
                    <span>Become an Affiliate</span>
                </a>
                
                <!-- Back to Home -->
                <div class="mt-6 text-center">
                    <a href="/" class="inline-flex items-center space-x-2 text-gray-600 hover:text-primary-700 font-medium transition-colors group">
                        <i class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        <span>Back to Home</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer Text -->
        <p class="text-center text-white text-sm mt-6 opacity-80">
            Join our affiliate program and start earning today
        </p>
    </div>
</body>
</html>
