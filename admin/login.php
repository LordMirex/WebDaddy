<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

// Check if user is already logged in via token
if (isset($_COOKIE['admin_token'])) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM users WHERE admin_login_token = ? AND role = 'admin'");
    $stmt->execute([$_COOKIE['admin_token']]);
    if ($stmt->fetchColumn()) {
        header('Location: /admin/');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $user = verifyAdminPassword($email, $password);
    if ($user) {
        clearLoginAttempts($email, 'admin');
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        
        // Store token in database
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET admin_login_token = ? WHERE id = ?");
        $stmt->execute([$token, $user['id']]);
        
        // Set secure cookie with token
        setcookie('admin_token', $token, [
            'expires' => time() + (30 * 86400), // 30 days
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Also set session for backward compatibility
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_role'] = $user['role'];
        
        logActivity('admin_login', 'Admin logged in: ' . $user['email'], $user['id']);
        
        header('Location: /admin/', true, 302);
        exit;
    } else {
        trackLoginAttempt($email, 'admin');
        $error = 'Invalid email or password.';
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
</head>
<body class="bg-gradient-to-br from-primary-900 via-primary-800 to-navy min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="max-w-[120px] mx-auto mb-4">
                <h2 class="text-3xl font-bold text-primary-900 mt-3">Admin Login</h2>
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
            
            <form method="POST" action="">
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
                
                <button type="submit" class="w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold py-3 px-4 rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl">
                    <i class="bi bi-box-arrow-in-right mr-2"></i> Login
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="/" class="text-primary-600 hover:text-primary-700 font-medium transition-colors inline-flex items-center">
                    <i class="bi bi-arrow-left mr-2"></i> Back to Site
                </a>
            </div>
        </div>
    </div>
</body>
</html>
