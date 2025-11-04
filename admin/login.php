<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (isRateLimited($email, 'admin')) {
            $error = getRateLimitMessage($email, 'admin');
        } elseif (loginAdmin($email, $password)) {
            clearLoginAttempts($email, 'admin');
            header('Location: /admin/');
            exit;
        } else {
            trackLoginAttempt($email, 'admin');
            $error = 'Invalid email or password.';
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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/forms.js" defer></script>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card login-card shadow-lg">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" style="max-width: 120px; margin-bottom: 1rem;">
                                <h2 class="mt-3" style="color: var(--royal-blue);">Admin Login</h2>
                                <p class="text-muted"><?php echo SITE_NAME; ?></p>
                            </div>
                            
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" data-validate data-loading>
                                <?php echo csrfTokenField(); ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Logging in...">
                                        <i class="bi bi-box-arrow-in-right"></i> Login
                                    </button>
                                </div>
                            </form>
                            
                            <div class="mt-4 text-center">
                                <a href="/" class="text-decoration-none">
                                    <i class="bi bi-arrow-left"></i> Back to Site
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
