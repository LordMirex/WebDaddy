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
    $emailOrCode = sanitizeInput($_POST['email_or_code'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($emailOrCode) || empty($password)) {
        $error = 'Please enter your email/code and password.';
    } else {
        if (loginAffiliate($emailOrCode, $password)) {
            header('Location: /affiliate/');
            exit;
        } else {
            $error = 'Invalid credentials or inactive account. Please check your email/affiliate code and password.';
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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card login-card shadow-lg">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="mb-3">
                                    <i class="bi bi-cash-stack" style="font-size: 3rem; color: var(--royal-blue);"></i>
                                </div>
                                <h2 class="mt-3" style="color: var(--royal-blue);">Affiliate Login</h2>
                                <p class="text-muted"><?php echo SITE_NAME; ?></p>
                            </div>
                            
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email_or_code" class="form-label">Email or Affiliate Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="email_or_code" 
                                               name="email_or_code" 
                                               placeholder="Enter email or affiliate code"
                                               value="<?php echo htmlspecialchars($_POST['email_or_code'] ?? ''); ?>"
                                               required autofocus>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Enter password"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-box-arrow-in-right"></i> Login
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p class="mb-2">Don't have an account?</p>
                                <a href="/affiliate/register.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person-plus"></i> Become an Affiliate
                                </a>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <a href="/" class="text-decoration-none text-muted">
                                    <i class="bi bi-arrow-left"></i> Back to Home
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
