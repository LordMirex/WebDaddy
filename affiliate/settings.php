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
    if (isset($_POST['update_profile'])) {
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
    } elseif (isset($_POST['update_bank_details'])) {
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
    } elseif (isset($_POST['change_password'])) {
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

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-gear"></i> Account Settings
    </h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person"></i> Profile Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               value="<?php echo htmlspecialchars($userInfo['name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               value="<?php echo htmlspecialchars($userInfo['email'] ?? ''); ?>" 
                               disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($userInfo['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-bank"></i> Bank Account Details
                </h5>
            </div>
            <div class="card-body">
                <?php if ($savedBankDetails): ?>
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle"></i> <strong>Bank details saved!</strong><br>
                        <small>These will be used automatically for withdrawal requests.</small>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Bank Name:</strong> <?php echo htmlspecialchars($savedBankDetails['bank_name']); ?></p>
                        <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($savedBankDetails['account_number']); ?></p>
                        <p class="mb-1"><strong>Account Name:</strong> <?php echo htmlspecialchars($savedBankDetails['account_name']); ?></p>
                    </div>
                    <hr>
                    <p class="text-muted small mb-3">Update your bank details below if needed:</p>
                <?php else: ?>
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> <strong>Save your bank details</strong><br>
                        <small>This will make withdrawal requests faster - you'll only need to enter the amount!</small>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Bank Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="bank_name" 
                               name="bank_name" 
                               value="<?php echo htmlspecialchars($savedBankDetails['bank_name'] ?? ''); ?>" 
                               placeholder="e.g., First Bank, GTBank, etc."
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number</label>
                        <input type="text" 
                               class="form-control" 
                               id="account_number" 
                               name="account_number" 
                               value="<?php echo htmlspecialchars($savedBankDetails['account_number'] ?? ''); ?>" 
                               placeholder="Enter your account number"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_name" class="form-label">Account Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="account_name" 
                               name="account_name" 
                               value="<?php echo htmlspecialchars($savedBankDetails['account_name'] ?? ''); ?>" 
                               placeholder="Enter account holder's name"
                               required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="update_bank_details" class="btn btn-success">
                            <i class="bi bi-save"></i> <?php echo $savedBankDetails ? 'Update Bank Details' : 'Save Bank Details'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-shield-lock"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="current_password" 
                               name="current_password" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="new_password" 
                               name="new_password" 
                               minlength="6"
                               required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="confirm_password" 
                               name="confirm_password" 
                               minlength="6"
                               required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle"></i> Account Information
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Affiliate Code:</strong> <code><?php echo htmlspecialchars($affiliateInfo['code']); ?></code></p>
                <p class="mb-2"><strong>Account Status:</strong> 
                    <span class="badge <?php echo $affiliateInfo['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                        <?php echo ucfirst($affiliateInfo['status']); ?>
                    </span>
                </p>
                <p class="mb-2"><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($affiliateInfo['created_at'])); ?></p>
                <hr>
                <div class="mt-3">
                    <h6>Performance Summary</h6>
                    <p class="mb-1"><strong>Total Clicks:</strong> <?php echo number_format($affiliateInfo['total_clicks']); ?></p>
                    <p class="mb-1"><strong>Total Sales:</strong> <?php echo number_format($affiliateInfo['total_sales']); ?></p>
                    <p class="mb-1"><strong>Commission Earned:</strong> <?php echo formatCurrency($affiliateInfo['commission_earned']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
