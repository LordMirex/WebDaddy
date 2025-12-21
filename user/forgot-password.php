<?php
/**
 * Forgot Password Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/customer_session.php';
require_once __DIR__ . '/../includes/mailer.php';

$customer = validateCustomerSession();
if ($customer) {
    header('Location: /user/security.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $db = getDb();
        
        $stmt = $db->prepare("SELECT id, email, username, status FROM customers WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // SECURITY: Only proceed if customer exists and is active
        if ($customerData && !empty($customerData['id']) && !empty($customerData['email'])) {
            if ($customerData['status'] === 'suspended') {
                $error = 'This account has been suspended. Please contact support.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $db->prepare("
                    INSERT INTO customer_password_resets 
                    (customer_id, reset_token, expires_at, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customerData['id'],
                    $token,
                    $expiresAt,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                
                $resetLink = SITE_URL . '/user/reset-password.php?token=' . $token;
                $customerName = $customerData['username'] ?: explode('@', $customerData['email'])[0];
                
                $emailContent = "
                <h2>Password Reset Request</h2>
                <p>Hello {$customerName},</p>
                <p>We received a request to reset your password for your WebDaddy Empire account.</p>
                <p>Click the button below to reset your password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='display: inline-block; background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #f3f4f6; padding: 12px; border-radius: 6px; word-break: break-all; font-size: 14px;'>{$resetLink}</p>
                <p><strong>This link will expire in 1 hour.</strong></p>
                <p>If you didn't request this password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                ";
                
                $emailHtml = createEmailTemplate('Password Reset Request', $emailContent, $customerName);
                
                // SECURITY: Verify email before sending - prevent accidental sends to invalid addresses
                if (filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
                    $emailSent = sendUserEmail($customerData['email'], 'Reset Your Password - WebDaddy Empire', $emailHtml, 'password_reset');
                    
                    if (!$emailSent) {
                        error_log("Password reset email failed for customer ID: {$customerData['id']}, email: {$customerData['email']}");
                    }
                } else {
                    error_log("Invalid email format for customer ID: {$customerData['id']}, email: {$customerData['email']}");
                }
                
                logCustomerActivity($customerData['id'], 'password_reset_requested', 'Password reset email sent');
                
                $success = 'Password reset link has been sent to your email. Check your inbox and follow the instructions to reset your password.';
            }
        } else {
            // No customer found - show clear error message (just like invalid email)
            $error = 'No account exists with this email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - WebDaddy Empire</title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet"><script defer src="/assets/js/alpine.min.js"></script>
    <link rel="stylesheet" href="/assets/css/bootstrap-icons.css">
    <link rel="icon" href="/assets/images/favicon.png" type="image/png">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="/">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-12 mx-auto mb-4">
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Forgot Password</h1>
            <p class="text-gray-600 mt-2">Enter your email to reset your password</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">
                <i class="bi-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
            <div class="text-center">
                <a href="/user/login.php" class="text-amber-600 hover:underline font-medium">
                    <i class="bi-arrow-left mr-1"></i>Back to Login
                </a>
            </div>
            <?php else: ?>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="your@email.com">
                </div>
                
                <button type="submit" class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition">
                    <i class="bi-envelope mr-2"></i>Send Reset Link
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-600">
                <p>Remember your password? <a href="/user/login.php" class="text-amber-600 hover:underline">Sign in</a></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-6">
            <a href="/" class="text-gray-500 hover:text-gray-700">
                <i class="bi-arrow-left mr-1"></i> Back to Store
            </a>
        </div>
    </div>
</body>
</html>
