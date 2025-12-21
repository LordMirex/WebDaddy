<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

// ⏸️ TESTING MODE: Always reset to password step (OTP disabled)
unset($_SESSION['login_step'], $_SESSION['pending_admin_email'], $_SESSION['pending_admin_id'], $_SESSION['otp_expires_at']);

$error = '';
$step = 'password'; // Always password step when OTP is disabled
$pending_email = $_SESSION['pending_admin_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please try again.';
    } elseif ($step === 'password') {
        // STEP 1: Verify email and password
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (isRateLimited($email, 'admin')) {
            $error = getRateLimitMessage($email, 'admin');
        } else {
            $user = verifyAdminPassword($email, $password);
            if ($user) {
                clearLoginAttempts($email, 'admin');
                
                // ⏸️ TESTING MODE: Skip OTP and login directly (for speed)
                // Uncomment the OTP section below when returning to production
                
                // Direct login without OTP verification
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_role'] = $user['role'];
                
                logActivity('admin_login', 'Admin logged in: ' . $user['email'], $user['id']);
                error_log("✅ Admin logged in WITHOUT OTP (TESTING MODE): {$email}");
                
                header('Location: /admin/');
                exit;
                
                /*
                // ⏸️ PAUSED OTP CODE - Uncomment to enable
                // Password verified - now request OTP
                $db = getDb();
                
                // Check rate limit for OTP requests
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM admin_login_otps 
                    WHERE admin_email = ? 
                    AND created_at > datetime('now', '-1 hour')
                ");
                $stmt->execute([$email]);
                $recentOtpCount = $stmt->fetchColumn();
                
                if ($recentOtpCount >= 3) {
                    $error = 'Too many OTP requests. Please try again in 1 hour.';
                } else {
                    // Generate OTP
                    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Expire previous OTPs
                    $stmt = $db->prepare("
                        UPDATE admin_login_otps 
                        SET is_used = 1
                        WHERE admin_email = ? AND is_used = 0 AND expires_at > datetime('now')
                    ");
                    $stmt->execute([$email]);
                    
                    // Create new OTP
                    $stmt = $db->prepare("
                        INSERT INTO admin_login_otps (admin_id, admin_email, otp_code, expires_at)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $email, $otpCode, $expiresAt]);
                    
                    // Send OTP email
                    $emailSent = sendAdminLoginOTPEmail($email, $otpCode);
                    
                    if ($emailSent) {
                        // Move to OTP verification step
                        $_SESSION['login_step'] = 'otp';
                        $_SESSION['pending_admin_email'] = $email;
                        $_SESSION['pending_admin_id'] = $user['id'];
                        $_SESSION['otp_expires_at'] = $expiresAt;
                        $step = 'otp';
                        error_log("Admin login OTP sent to {$email}");
                    } else {
                        $error = 'Failed to send OTP email. Please try again.';
                    }
                }
                */
            } else {
                trackLoginAttempt($email, 'admin');
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($step === 'otp') {
        // STEP 2: Verify OTP
        $otpCode = trim($_POST['otp_code'] ?? '');
        $email = $pending_email;
        
        if (!$email) {
            $error = 'Session expired. Please login again.';
            unset($_SESSION['login_step'], $_SESSION['pending_admin_email'], $_SESSION['pending_admin_id']);
            $step = 'password';
        } elseif (empty($otpCode)) {
            $error = 'Please enter the verification code.';
        } else {
            $db = getDb();
            
            // Verify OTP
            $stmt = $db->prepare("
                SELECT * FROM admin_login_otps 
                WHERE admin_email = ?
                AND otp_code = ?
                AND is_used = 0
                AND expires_at > datetime('now')
                AND attempts < max_attempts
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email, $otpCode]);
            $otp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$otp) {
                // Increment attempts
                $stmt = $db->prepare("
                    UPDATE admin_login_otps 
                    SET attempts = attempts + 1 
                    WHERE admin_email = ? AND is_used = 0 AND expires_at > datetime('now')
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$email]);
                
                $error = 'Invalid or expired verification code.';
            } else {
                // Mark OTP as used
                $stmt = $db->prepare("
                    UPDATE admin_login_otps 
                    SET is_used = 1, used_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$otp['id']]);
                
                // Get admin info
                $stmt = $db->prepare("SELECT id, email, name, role FROM users WHERE id = ? AND role = 'admin' AND status = 'active'");
                $stmt->execute([$otp['admin_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // OTP verified - create full session
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    // Clear login attempt tracking
                    clearLoginAttempts($email, 'admin');
                    
                    // Log activity
                    logActivity('admin_login', 'Admin logged in: ' . $user['email'], $user['id']);
                    error_log("✅ Admin OTP verified for {$email}");
                    
                    header('Location: /admin/');
                    exit;
                } else {
                    $error = 'Admin account not found.';
                }
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
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
    <script src="/assets/js/forms.js" defer></script>
</head>
<body class="bg-gradient-to-br from-primary-900 via-primary-800 to-navy min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="max-w-[120px] mx-auto mb-4">
                <h2 class="text-3xl font-bold text-primary-900 mt-3">
                    <?php echo $step === 'otp' ? 'Verify Code' : 'Admin Login'; ?>
                </h2>
                <p class="text-gray-600 mt-2"><?php echo SITE_NAME; ?></p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="bi bi-exclamation-triangle text-xl mr-3"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" data-validate data-loading>
                <?php echo csrfTokenField(); ?>
                
                <?php if ($step === 'password'): ?>
                    <!-- PASSWORD STEP -->
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="bi bi-envelope text-gray-400"></i>
                            </div>
                            <input type="email" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" id="email" name="email" autocomplete="email" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="bi bi-lock text-gray-400"></i>
                            </div>
                            <input type="password" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" id="password" name="password" autocomplete="current-password" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold py-3 px-4 rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl" data-loading-text="Logging in...">
                        <i class="bi bi-box-arrow-in-right mr-2"></i> Login
                    </button>
                
                <?php elseif ($step === 'otp'): ?>
                    <!-- OTP STEP - DISABLED FOR TESTING -->
                    <!-- This section is hidden because OTP is paused -->
                <?php endif; ?>
            </form>
            
            <?php if ($step === 'password'): ?>
            <div class="mt-6 text-center">
                <a href="/" class="text-primary-600 hover:text-primary-700 font-medium transition-colors inline-flex items-center">
                    <i class="bi bi-arrow-left mr-2"></i> Back to Site
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
