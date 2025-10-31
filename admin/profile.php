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

<div class="page-header">
    <h1><i class="bi bi-person-circle"></i> Admin Profile</h1>
    <p class="text-muted">Manage your account settings</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($admin['name']); ?>" 
                            required
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($admin['email']); ?>" 
                            required
                        >
                        <small class="text-muted">This email is used for login and notifications</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($admin['phone']); ?>"
                        >
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <p class="mb-2"><strong>Role:</strong></p>
                        <p class="text-muted"><span class="badge bg-danger">Administrator</span></p>
                    </div>
                    <div class="col-6">
                        <p class="mb-2"><strong>Status:</strong></p>
                        <p class="text-muted"><span class="badge bg-success">Active</span></p>
                    </div>
                    <div class="col-6">
                        <p class="mb-2"><strong>User ID:</strong></p>
                        <p class="text-muted">#<?php echo $admin['id']; ?></p>
                    </div>
                    <div class="col-6">
                        <p class="mb-2"><strong>Created:</strong></p>
                        <p class="text-muted"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <?php
                // Check if password is hashed or plain text
                $isHashed = password_get_info($admin['password_hash'])['algo'] !== null;
                ?>
                
                <?php if (!$isHashed): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Security Warning:</strong> Your password is currently stored as plain text. 
                    Please change it to enable secure password hashing.
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input 
                            type="password" 
                            name="current_password" 
                            class="form-control" 
                            required
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input 
                            type="password" 
                            name="new_password" 
                            class="form-control" 
                            minlength="6" 
                            required
                        >
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            class="form-control" 
                            minlength="6" 
                            required
                        >
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-check"></i> Password Security Tips</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Use at least 8 characters</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Add numbers and special characters</li>
                    <li>Don't use common words or patterns</li>
                    <li>Change your password regularly</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
