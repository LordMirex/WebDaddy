<?php
$pageTitle = 'Admin Profile';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$success = '';
$error = '';

// Get admin details
$adminId = getAdminId();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get saved bank details
$savedBankDetails = null;
if (!empty($admin['bank_details'])) {
    $savedBankDetails = json_decode($admin['bank_details'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                // Check if email is taken by another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $adminId]);
                
                if ($stmt->fetch()) {
                    $error = 'This email is already in use by another user.';
                } else {
                    // Update profile
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $adminId]);
                    
                    $success = 'Profile updated successfully!';
                    logActivity('profile_updated', 'Admin updated their profile', $adminId);
                    
                    // Refresh admin data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update session
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $name;
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_bank_details') {
        $bankName = sanitizeInput($_POST['bank_name'] ?? '');
        $accountNumber = sanitizeInput($_POST['account_number'] ?? '');
        $accountName = sanitizeInput($_POST['account_name'] ?? '');
        
        if (empty($bankName) || empty($accountNumber) || empty($accountName)) {
            $error = 'All bank details fields are required.';
        } else {
            $bankDetails = json_encode([
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_name' => $accountName
            ]);
            
            try {
                $stmt = $db->prepare("UPDATE users SET bank_details = ? WHERE id = ?");
                if ($stmt->execute([$bankDetails, $adminId])) {
                    $success = 'Bank details saved successfully!';
                    logActivity('bank_details_updated', 'Admin updated their bank details', $adminId);
                    
                    // Refresh admin data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to save bank details.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            // Verify current password (handle both plain text and hashed)
            $passwordMatch = false;
            
            if (password_verify($currentPassword, $admin['password_hash'])) {
                // Hashed password verification
                $passwordMatch = true;
            } elseif ($admin['password_hash'] === $currentPassword) {
                // Plain text comparison (legacy)
                $passwordMatch = true;
            }
            
            if (!$passwordMatch) {
                $error = 'Current password is incorrect.';
            } else {
                try {
                    // Hash the new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $adminId]);
                    
                    $success = 'Password changed successfully! Your password is now securely hashed.';
                    logActivity('password_changed', 'Admin changed their password', $adminId);
                    
                    // Refresh admin data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-person-circle text-primary-600"></i> Admin Profile
    </h1>
    <p class="text-gray-600 mt-2">Manage your account settings</p>
</div>

<?php if ($success): ?>
<div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-check-circle text-xl"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
    </div>
    <button @click="show = false" class="text-green-700 hover:text-green-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-exclamation-circle text-xl"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <button @click="show = false" class="text-red-700 hover:text-red-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-person text-primary-600"></i> Profile Information
                </h5>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name <span class="text-red-600">*</span></label>
                        <input 
                            type="text" 
                            name="name" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            value="<?php echo htmlspecialchars($admin['name']); ?>" 
                            required
                        >
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span class="text-red-600">*</span></label>
                        <input 
                            type="email" 
                            name="email" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            value="<?php echo htmlspecialchars($admin['email']); ?>" 
                            required
                        >
                        <small class="text-gray-500 text-sm">This email is used for login and notifications</small>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            value="<?php echo htmlspecialchars($admin['phone']); ?>"
                        >
                    </div>
                    
                    <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] shadow-lg">
                        <i class="bi bi-save mr-2"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-info-circle text-primary-600"></i> Account Information
                </h5>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Role:</p>
                        <p><span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">Administrator</span></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Status:</p>
                        <p><span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">Active</span></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">User ID:</p>
                        <p class="text-gray-900">#<?php echo $admin['id']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Created:</p>
                        <p class="text-gray-900"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-shield-lock text-primary-600"></i> Change Password
                </h5>
            </div>
            <div class="p-6">
                <?php
                // Check if password is hashed or plain text
                $isHashed = password_get_info($admin['password_hash'])['algo'] !== null;
                ?>
                
                <?php if (!$isHashed): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-lg mb-6 flex items-start gap-3">
                    <i class="bi bi-exclamation-triangle text-xl mt-0.5"></i>
                    <div>
                        <strong class="font-semibold">Security Warning:</strong> Your password is currently stored as plain text. 
                        Please change it to enable secure password hashing.
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password <span class="text-red-600">*</span></label>
                        <input 
                            type="password" 
                            name="current_password" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            required
                        >
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">New Password <span class="text-red-600">*</span></label>
                        <input 
                            type="password" 
                            name="new_password" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            minlength="6" 
                            required
                        >
                        <small class="text-gray-500 text-sm">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password <span class="text-red-600">*</span></label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                            minlength="6" 
                            required
                        >
                    </div>
                    
                    <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] shadow-lg">
                        <i class="bi bi-key mr-2"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h6 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-shield-check text-green-600"></i> Password Security Tips
                </h6>
            </div>
            <div class="p-6">
                <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Use at least 8 characters</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Include uppercase and lowercase letters</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Add numbers and special characters</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Don't use common words or patterns</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Change your password regularly</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
