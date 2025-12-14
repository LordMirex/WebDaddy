<?php
/**
 * Reset Password Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/customer_session.php';

$customer = validateCustomerSession();
if ($customer) {
    header('Location: /user/security.php');
    exit;
}

$token = $_GET['token'] ?? '';
$success = '';
$error = '';
$tokenValid = false;
$customerData = null;

if (empty($token)) {
    $error = 'Invalid or missing reset token';
} else {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT pr.*, c.id as customer_id, c.email, c.full_name, c.status
        FROM customer_password_resets pr
        JOIN customers c ON pr.customer_id = c.id
        WHERE pr.reset_token = ?
        AND pr.is_used = 0
        AND pr.expires_at > datetime('now')
        ORDER BY pr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resetData) {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    } elseif ($resetData['status'] === 'suspended') {
        $error = 'This account has been suspended. Please contact support.';
    } else {
        $tokenValid = true;
        $customerData = $resetData;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword)) {
        $error = 'Please enter a new password';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $db = getDb();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            UPDATE customers 
            SET password_hash = ?, password_changed_at = datetime('now'), updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$hash, $customerData['customer_id']]);
        
        $stmt = $db->prepare("
            UPDATE customer_password_resets 
            SET is_used = 1, used_at = datetime('now')
            WHERE reset_token = ?
        ");
        $stmt->execute([$token]);
        
        revokeAllCustomerSessions($customerData['customer_id'], 'password_reset');
        
        logCustomerActivity($customerData['customer_id'], 'password_reset_completed', 'Password was reset via email link');
        
        $success = 'Your password has been reset successfully. You can now log in with your new password.';
        $tokenValid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - WebDaddy Empire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="/assets/images/favicon.png" type="image/png">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="/">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-12 mx-auto mb-4">
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Reset Password</h1>
            <p class="text-gray-600 mt-2">Create a new password for your account</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">
                <i class="bi-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
            <div class="text-center">
                <a href="/user/login.php" class="inline-block w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition text-center">
                    <i class="bi-box-arrow-in-right mr-2"></i>Go to Login
                </a>
            </div>
            
            <?php elseif ($error && !$tokenValid): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <div class="text-center space-y-3">
                <a href="/user/forgot-password.php" class="inline-block w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition">
                    Request New Reset Link
                </a>
                <a href="/user/login.php" class="text-gray-600 hover:text-gray-800 text-sm">
                    <i class="bi-arrow-left mr-1"></i>Back to Login
                </a>
            </div>
            
            <?php elseif ($tokenValid): ?>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    Resetting password for: <strong><?= htmlspecialchars($customerData['email']) ?></strong>
                </p>
            </div>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter new password (min. 6 characters)">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Confirm new password">
                </div>
                
                <button type="submit" class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition">
                    <i class="bi-lock mr-2"></i>Reset Password
                </button>
            </form>
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
