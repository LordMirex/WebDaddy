<?php
/**
 * Customer Login Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/customer_session.php';

$customer = validateCustomerSession();
if ($customer) {
    header('Location: /user/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $customer = getCustomerByEmail($email);
        
        if (!$customer) {
            $error = 'Invalid email or password';
        } elseif (empty($customer['password_hash'])) {
            $error = 'No password set. Please use the checkout page to verify your email.';
        } elseif (!password_verify($password, $customer['password_hash'])) {
            $error = 'Invalid email or password';
        } elseif ($customer['status'] === 'suspended') {
            $error = 'Account suspended. Please contact support.';
        } else {
            $sessionResult = createCustomerSession($customer['id']);
            if ($sessionResult['success']) {
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_session_token'] = $sessionResult['token'];
                $_SESSION['customer_name'] = $customer['name'] ?? $customer['email'];
                
                setcookie('customer_session', $sessionResult['token'], [
                    'expires' => strtotime('+1 year'),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                $db = getDb();
                $db->prepare("UPDATE customers SET last_login_at = datetime('now') WHERE id = ?")->execute([$customer['id']]);
                
                $redirect = $_SESSION['redirect_after_login'] ?? '/user/';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Login failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WebDaddy Empire</title>
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
            <h1 class="text-2xl font-bold text-gray-900">Customer Login</h1>
            <p class="text-gray-600 mt-2">Sign in to access your account</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
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
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter your password">
                </div>
                
                <div class="flex items-center justify-between text-sm">
                    <a href="/user/forgot-password.php" class="text-amber-600 hover:underline">Forgot password?</a>
                </div>
                
                <button type="submit" class="w-full bg-amber-600 text-white py-3 rounded-lg font-semibold hover:bg-amber-700 transition">
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-600">
                <p>Don't have an account? <a href="/user/register.php" class="text-amber-600 hover:underline font-medium">Create one for free</a></p>
            </div>
        </div>
        
        <div class="text-center mt-6">
            <a href="/" class="text-gray-500 hover:text-gray-700">
                <i class="bi-arrow-left mr-1"></i> Back to Store
            </a>
        </div>
    </div>
</body>
</html>
