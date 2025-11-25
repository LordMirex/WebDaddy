<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

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
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please try again.';
    } else {
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
            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement');
            }
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered. <a href="/affiliate/login.php">Login here</a>.';
            } else {
                // Start transaction
                $db->beginTransaction();
                
                // Check if chosen affiliate code already exists
                $stmt = $db->prepare("SELECT id FROM affiliates WHERE code = ?");
                if ($stmt === false) {
                    throw new PDOException('Failed to prepare statement');
                }
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
                    if ($stmt === false) {
                        throw new PDOException('Failed to prepare statement');
                    }
                    $result = $stmt->execute([$name, $email, $phone, $passwordHash]);
                    if ($result === false) {
                        throw new PDOException('Failed to create user account');
                    }
                    $userId = $db->lastInsertId('users_id_seq') ?: $db->lastInsertId();
                    
                    // Insert affiliate record
                    $stmt = $db->prepare("
                        INSERT INTO affiliates (user_id, code, status)
                        VALUES (?, ?, 'active')
                    ");
                    if ($stmt === false) {
                        throw new PDOException('Failed to prepare statement');
                    }
                    $result = $stmt->execute([$userId, $affiliateCode]);
                    if ($result === false) {
                        throw new PDOException('Failed to create affiliate record');
                    }
                    
                    $affiliateId = $db->lastInsertId('affiliates_id_seq') ?: $db->lastInsertId();
                    
                    // Create welcome announcement about spam folder
                    $welcomeTitle = "📧 Important: Check Your Spam Folder!";
                    $welcomeMessage = "<p>Welcome to <strong>WebDaddy Empire</strong>! We're excited to have you as an affiliate partner.</p>
                        <p><strong style='color: #dc2626;'>⚠️ IMPORTANT ACTION REQUIRED:</strong></p>
                        <ul>
                            <li>Check your <strong>spam/junk folder</strong> for emails from us</li>
                            <li>Mark our emails as <strong>\"Not Spam\"</strong> or <strong>\"Safe\"</strong></li>
                            <li>Add <strong>admin@webdaddy.online</strong> to your contacts</li>
                        </ul>
                        <p><strong>Why is this important?</strong></p>
                        <p>We will send you important notifications via email about:</p>
                        <ul>
                            <li>✅ Successful purchases made with your affiliate code</li>
                            <li>💰 Payment confirmations and receipts</li>
                            <li>🎯 Withdrawal request approvals</li>
                            <li>📊 Monthly earning reports</li>
                        </ul>
                        <p>This announcement will disappear in 7 days. If you don't see our emails in your inbox, please check spam!</p>";
                    
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                    
                    // Insert welcome announcement (system-generated, so created_by is NULL for affiliate signup)
                    $stmt = $db->prepare("
                        INSERT INTO announcements (title, message, type, is_active, created_by, affiliate_id, expires_at)
                        VALUES (?, ?, 'warning', 1, NULL, ?, ?)
                    ");
                    $stmt->execute([$welcomeTitle, $welcomeMessage, $affiliateId, $expiresAt]);
                    
                    $db->commit();
                    
                    // Log activity
                    logActivity('affiliate_registration', "New affiliate registered: $email (with welcome announcement)", $userId);
                    
                    // Send welcome email to new affiliate
                    sendAffiliateWelcomeEmail($name, $email, $affiliateCode);
                    
                    // Process email queue immediately after queuing emails
                    require_once __DIR__ . '/../includes/email_processor.php';
                    ensureEmailProcessing();
                    
                    // Auto-login the user
                    $stmt = $db->prepare("SELECT id FROM affiliates WHERE user_id = ?");
                    if ($stmt === false) {
                        throw new PDOException('Failed to prepare statement');
                    }
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
                    } else {
                        throw new PDOException('Failed to retrieve affiliate record');
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
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Become an Affiliate - <?php echo SITE_NAME; ?></title>
    
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
    <div class="w-full max-w-lg">
        <!-- Registration Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden transform hover:scale-[1.01] transition-transform duration-300">
            <div class="p-8 sm:p-10">
                <!-- Header -->
                <div class="text-center mb-8">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?php echo SITE_NAME; ?>" class="max-w-[120px] mx-auto mb-4">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Become an Affiliate</h2>
                    <p class="text-gray-600 font-medium">Join our affiliate program and start earning 30% commissions</p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="bi bi-exclamation-triangle text-red-500 text-xl mr-3 mt-0.5"></i>
                        <p class="text-red-700 text-sm font-medium"><?php echo $error; ?></p>
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
                
                <!-- Registration Form -->
                <form method="POST" action="" x-data="{ loading: false }" @submit="loading = true">
                    <?php echo csrfTokenField(); ?>
                    
                    <!-- Affiliate Code Input -->
                    <div class="mb-6">
                        <label for="my_affiliate_code" class="block text-sm font-semibold text-gray-700 mb-2">
                            Choose Your Affiliate Code <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-person-badge text-gray-400 text-lg"></i>
                            </div>
                            <input type="text" 
                                   id="my_affiliate_code" 
                                   name="my_affiliate_code" 
                                   placeholder="e.g., JOHN2024"
                                   value="<?php echo htmlspecialchars($_POST['my_affiliate_code'] ?? ''); ?>"
                                   pattern="[A-Za-z0-9]{4,20}"
                                   minlength="4"
                                   maxlength="20"
                                   required 
                                   autofocus
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-gray-900 placeholder-gray-400 uppercase">
                        </div>
                        <p class="mt-2 text-xs text-gray-500 flex items-start">
                            <i class="bi bi-info-circle mr-1 mt-0.5"></i>
                            <span>4-20 characters, letters and numbers only. This will be YOUR unique affiliate code.</span>
                        </p>
                    </div>
                    
                    <!-- Email Input -->
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-envelope text-gray-400 text-lg"></i>
                            </div>
                            <input type="email" 
                                   id="email" 
                                   name="email"
                                   autocomplete="email" 
                                   placeholder="Enter your email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                    
                    <!-- Password Input -->
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="bi bi-lock text-gray-400 text-lg"></i>
                            </div>
                            <input type="password" 
                                   id="password" 
                                   name="password"
                                   autocomplete="new-password" 
                                   placeholder="Create a password (min 6 characters)"
                                   minlength="6"
                                   required
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            :disabled="loading"
                            class="w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold py-3.5 px-6 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center space-x-2 mb-4">
                        <i class="bi bi-person-check text-lg" x-show="!loading"></i>
                        <i class="bi bi-arrow-repeat animate-spin" x-show="loading" style="display: none;"></i>
                        <span x-text="loading ? 'Creating account...' : 'Register as Affiliate'">Register as Affiliate</span>
                    </button>

                    <!-- Benefits Badge -->
                    <div class="bg-gradient-to-r from-gold/10 to-primary-100 border border-gold/30 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-br from-gold to-yellow-600 rounded-full flex items-center justify-center shadow">
                                    <i class="bi bi-star-fill text-white text-lg"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 text-sm mb-1">Affiliate Benefits</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5">
                                    <li class="flex items-center"><i class="bi bi-check2 text-green-600 mr-1"></i> 30% commission on all sales</li>
                                    <li class="flex items-center"><i class="bi bi-check2 text-green-600 mr-1"></i> 30-day cookie tracking</li>
                                    <li class="flex items-center"><i class="bi bi-check2 text-green-600 mr-1"></i> Marketing materials provided</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Divider -->
                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 font-medium">Already registered?</span>
                    </div>
                </div>
                
                <!-- Login Link -->
                <a href="/affiliate/login.php" class="w-full flex items-center justify-center space-x-2 px-6 py-3 border-2 border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 group">
                    <i class="bi bi-box-arrow-in-right text-lg group-hover:scale-110 transition-transform"></i>
                    <span>Login Here</span>
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
            <i class="bi bi-shield-check"></i> Secure registration with instant approval
        </p>
    </div>
</body>
</html>
