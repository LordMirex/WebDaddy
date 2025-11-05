<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAffiliate();

$affiliateInfo = getAffiliateInfo();
if (!$affiliateInfo) {
    logoutAffiliate();
    header('Location: /affiliate/login.php');
    exit;
}

$db = getDb();
$userId = $_SESSION['affiliate_user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile']) || isset($_POST['name'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        
        if (empty($name)) {
            $error = 'Name is required.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                if ($stmt->execute([$name, $phone, $userId])) {
                    $success = 'Profile updated successfully!';
                    $_SESSION['affiliate_name'] = $name;
                } else {
                    $error = 'Failed to update profile.';
                }
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    } elseif (isset($_POST['update_bank_details']) || isset($_POST['bank_name'])) {
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
                if ($stmt->execute([$bankDetails, $userId])) {
                    $success = 'Bank details saved successfully! You can now request withdrawals easily.';
                } else {
                    $error = 'Failed to save bank details.';
                }
            } catch (PDOException $e) {
                error_log('Bank details update error: ' . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    } elseif (isset($_POST['change_password']) || isset($_POST['current_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            try {
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($currentPassword, $user['password_hash'])) {
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    
                    if ($stmt->execute([$newPasswordHash, $userId])) {
                        $success = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password.';
                    }
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (PDOException $e) {
                error_log('Password change error: ' . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}

try {
    $stmt = $db->prepare("SELECT name, email, phone, bank_details FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $savedBankDetails = null;
    if (!empty($userInfo['bank_details'])) {
        $savedBankDetails = json_decode($userInfo['bank_details'], true);
    }
} catch (PDOException $e) {
    error_log('Error fetching user info: ' . $e->getMessage());
    $userInfo = [];
    $savedBankDetails = null;
}

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-8 pb-4 border-b border-gray-200">
    <div class="flex items-center space-x-3">
        <div class="w-12 h-12 bg-gradient-to-br from-primary-600 to-primary-800 rounded-lg flex items-center justify-center shadow-lg">
            <i class="bi bi-gear text-2xl text-gold"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">Account Settings</h1>
    </div>
</div>

<?php if ($error): ?>
<div x-data="{ show: true }" x-show="show" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-6 relative">
    <button @click="show = false" class="absolute top-4 right-4 text-red-500 hover:text-red-700">
        <i class="bi bi-x-lg"></i>
    </button>
    <div class="flex items-center pr-8">
        <i class="bi bi-exclamation-triangle text-red-600 text-xl mr-3"></i>
        <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div x-data="{ show: true }" x-show="show" class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-6 relative">
    <button @click="show = false" class="absolute top-4 right-4 text-green-500 hover:text-green-700">
        <i class="bi bi-x-lg"></i>
    </button>
    <div class="flex items-center pr-8">
        <i class="bi bi-check-circle text-green-600 text-xl mr-3"></i>
        <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Settings Cards Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Profile Information -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-4 text-white">
            <h5 class="text-xl font-bold flex items-center">
                <i class="bi bi-person mr-2"></i> Profile Information
            </h5>
        </div>
        <div class="p-6">
            <form method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($userInfo['name'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed" 
                           id="email" 
                           value="<?php echo htmlspecialchars($userInfo['email'] ?? ''); ?>" 
                           disabled>
                    <p class="text-sm text-gray-500 mt-1">Email cannot be changed</p>
                </div>
                
                <div class="mb-6">
                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                           id="phone" 
                           name="phone" 
                           value="<?php echo htmlspecialchars($userInfo['phone'] ?? ''); ?>">
                </div>
                
                <button type="submit" 
                        name="update_profile" 
                        :disabled="submitting"
                        class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-800 hover:from-primary-700 hover:to-primary-900 text-white font-bold rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center space-x-2">
                    <i class="bi bi-save text-lg" x-show="!submitting"></i>
                    <i class="bi bi-arrow-repeat animate-spin text-lg" x-show="submitting" style="display: none;"></i>
                    <span x-text="submitting ? 'Updating...' : 'Update Profile'">Update Profile</span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Bank Account Details -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4 text-white">
            <h5 class="text-xl font-bold flex items-center">
                <i class="bi bi-bank mr-2"></i> Bank Account Details
            </h5>
        </div>
        <div class="p-6">
            <?php if ($savedBankDetails): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-4">
                    <div class="flex items-start">
                        <i class="bi bi-check-circle text-green-600 text-xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="text-green-700 font-bold">Bank details saved!</p>
                            <p class="text-sm text-green-600 mt-1">These will be used automatically for withdrawal requests.</p>
                        </div>
                    </div>
                </div>
                <div class="mb-4 bg-gray-50 rounded-lg p-4">
                    <p class="mb-2"><strong class="text-gray-900">Bank Name:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($savedBankDetails['bank_name']); ?></span></p>
                    <p class="mb-2"><strong class="text-gray-900">Account Number:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($savedBankDetails['account_number']); ?></span></p>
                    <p class="mb-0"><strong class="text-gray-900">Account Name:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($savedBankDetails['account_name']); ?></span></p>
                </div>
                <div class="border-t border-gray-200 pt-4 mb-4"></div>
                <p class="text-sm text-gray-600 mb-4">Update your bank details below if needed:</p>
            <?php else: ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-4">
                    <div class="flex items-start">
                        <i class="bi bi-info-circle text-blue-600 text-xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="text-blue-700 font-bold">Save your bank details</p>
                            <p class="text-sm text-blue-600 mt-1">This will make withdrawal requests faster - you'll only need to enter the amount!</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                <div class="mb-4">
                    <label for="bank_name" class="block text-sm font-semibold text-gray-700 mb-2">Bank Name</label>
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors" 
                           id="bank_name" 
                           name="bank_name" 
                           value="<?php echo htmlspecialchars($savedBankDetails['bank_name'] ?? ''); ?>" 
                           placeholder="e.g., First Bank, GTBank, etc."
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2">Account Number</label>
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors" 
                           id="account_number" 
                           name="account_number" 
                           value="<?php echo htmlspecialchars($savedBankDetails['account_number'] ?? ''); ?>" 
                           placeholder="Enter your account number"
                           required>
                </div>
                
                <div class="mb-6">
                    <label for="account_name" class="block text-sm font-semibold text-gray-700 mb-2">Account Name</label>
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors" 
                           id="account_name" 
                           name="account_name" 
                           value="<?php echo htmlspecialchars($savedBankDetails['account_name'] ?? ''); ?>" 
                           placeholder="Enter account holder's name"
                           required>
                </div>
                
                <button type="submit" 
                        name="update_bank_details" 
                        :disabled="submitting"
                        class="w-full px-6 py-3 bg-gradient-to-r from-green-600 to-green-800 hover:from-green-700 hover:to-green-900 text-white font-bold rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center space-x-2">
                    <i class="bi bi-save text-lg" x-show="!submitting"></i>
                    <i class="bi bi-arrow-repeat animate-spin text-lg" x-show="submitting" style="display: none;"></i>
                    <span x-text="submitting ? 'Saving...' : '<?php echo $savedBankDetails ? "Update Bank Details" : "Save Bank Details"; ?>'">
                        <?php echo $savedBankDetails ? 'Update Bank Details' : 'Save Bank Details'; ?>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Bottom Grid: Password & Account Info -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Change Password -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-700 px-6 py-4 text-white">
            <h5 class="text-xl font-bold flex items-center">
                <i class="bi bi-shield-lock mr-2"></i> Change Password
            </h5>
        </div>
        <div class="p-6">
            <form method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                <div class="mb-4">
                    <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                    <input type="password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" 
                           id="current_password" 
                           name="current_password" 
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                    <input type="password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" 
                           id="new_password" 
                           name="new_password" 
                           minlength="6"
                           required>
                    <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" 
                           id="confirm_password" 
                           name="confirm_password" 
                           minlength="6"
                           required>
                </div>
                
                <button type="submit" 
                        name="change_password" 
                        :disabled="submitting"
                        class="w-full px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-700 hover:from-yellow-600 hover:to-yellow-800 text-white font-bold rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center space-x-2">
                    <i class="bi bi-key text-lg" x-show="!submitting"></i>
                    <i class="bi bi-arrow-repeat animate-spin text-lg" x-show="submitting" style="display: none;"></i>
                    <span x-text="submitting ? 'Changing...' : 'Change Password'">Change Password</span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Account Information -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
            <h5 class="text-xl font-bold flex items-center">
                <i class="bi bi-info-circle mr-2"></i> Account Information
            </h5>
        </div>
        <div class="p-6">
            <div class="space-y-3 mb-6">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-semibold text-gray-700">Affiliate Code:</span>
                    <code class="px-3 py-1 bg-gray-100 text-primary-700 rounded font-mono font-bold"><?php echo htmlspecialchars($affiliateInfo['code']); ?></code>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-semibold text-gray-700">Account Status:</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $affiliateInfo['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        <?php echo ucfirst($affiliateInfo['status']); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-semibold text-gray-700">Member Since:</span>
                    <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($affiliateInfo['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h6 class="font-bold text-gray-900 mb-4 flex items-center">
                    <i class="bi bi-graph-up mr-2 text-primary-600"></i> Performance Summary
                </h6>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total Clicks:</span>
                        <span class="text-sm font-bold text-gray-900"><?php echo number_format($affiliateInfo['total_clicks']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total Sales:</span>
                        <span class="text-sm font-bold text-gray-900"><?php echo number_format($affiliateInfo['total_sales']); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                        <span class="text-sm font-semibold text-gray-700">Commission Earned:</span>
                        <span class="text-base font-bold text-green-600"><?php echo formatCurrency($affiliateInfo['commission_earned']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
