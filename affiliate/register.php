<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
handleAffiliateTracking();

// Redirect if already logged in
if (isset($_SESSION['affiliate_id'])) {
    header('Location: /affiliate/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $myAffiliateCode = strtoupper(sanitizeInput($_POST['my_affiliate_code'] ?? ''));
    
    // Auto-generate name from email
    $name = explode('@', $email)[0];
    $phone = '';
    
    // Validation
    if (empty($email) || empty($password) || empty($myAffiliateCode)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!preg_match('/^[A-Z0-9]{4,20}$/', $myAffiliateCode)) {
        $error = 'Affiliate code must be 4-20 characters (letters and numbers only).';
    } else {
        $db = getDb();
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered. <a href="/affiliate/login.php">Login here</a>.';
            } else {
                // Start transaction
                $db->beginTransaction();
                
                // Check if chosen affiliate code already exists
                $stmt = $db->prepare("SELECT id FROM affiliates WHERE code = ?");
                $stmt->execute([$myAffiliateCode]);
                if ($stmt->fetch()) {
                    $error = 'This affiliate code is already taken. Please choose another one.';
                    $db->rollBack();
                } else {
                    $affiliateCode = $myAffiliateCode;
                    
                    // Insert user
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, phone, password_hash, role, status)
                        VALUES (?, ?, ?, ?, 'affiliate', 'active')
                    ");
                    $stmt->execute([$name, $email, $phone, $passwordHash]);
                    $userId = $db->lastInsertId('users_id_seq') ?: $db->lastInsertId();
                    
                    // Insert affiliate record
                    $stmt = $db->prepare("
                        INSERT INTO affiliates (user_id, code, status)
                        VALUES (?, ?, 'active')
                    ");
                    $stmt->execute([$userId, $affiliateCode]);
                    
                    $db->commit();
                    
                    // Log activity
                    logActivity('affiliate_registration', "New affiliate registered: $email", $userId);
                    
                    // Auto-login the user
                    $stmt = $db->prepare("SELECT id FROM affiliates WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($affiliate) {
                        session_regenerate_id(true);
                        $_SESSION['affiliate_id'] = $affiliate['id'];
                        $_SESSION['affiliate_user_id'] = $userId;
                        $_SESSION['affiliate_email'] = $email;
                        $_SESSION['affiliate_name'] = $name;
                        $_SESSION['affiliate_code'] = $affiliateCode;
                        $_SESSION['affiliate_role'] = 'affiliate';
                        
                        header('Location: /affiliate/');
                        exit;
                    }
                }
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Registration error: ' . $e->getMessage());
            $error = 'An error occurred during registration. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Become an Affiliate - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card login-card shadow-lg">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="mb-3">
                                    <i class="bi bi-person-plus-fill" style="font-size: 3rem; color: var(--royal-blue);"></i>
                                </div>
                                <h2 class="mt-3" style="color: var(--royal-blue);">Become an Affiliate</h2>
                                <p class="text-muted">Join our affiliate program and start earning commissions</p>
                            </div>
                            
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="my_affiliate_code" class="form-label">Choose Your Affiliate Code <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="my_affiliate_code" 
                                               name="my_affiliate_code" 
                                               placeholder="Enter your unique code (e.g., JOHN2024)"
                                               value="<?php echo htmlspecialchars($_POST['my_affiliate_code'] ?? ''); ?>"
                                               pattern="[A-Za-z0-9]{4,20}"
                                               required autofocus>
                                    </div>
                                    <small class="text-muted">4-20 characters, letters and numbers only. This will be YOUR affiliate code.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               placeholder="Enter your email"
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Create a password (min 6 characters)"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-person-check"></i> Register as Affiliate
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center">
                                <p class="mb-2">Already have an account?</p>
                                <a href="/affiliate/login.php" class="btn btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right"></i> Login Here
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
