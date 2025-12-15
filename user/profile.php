<?php
/**
 * User Profile Settings Page
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'profile';
$pageTitle = 'Profile Settings';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($username)) {
        $error = 'Username is required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } else {
        $db = getDb();
        
        // Check if username is unique (excluding current customer)
        $checkStmt = $db->prepare("SELECT id FROM customers WHERE LOWER(username) = LOWER(?) AND id != ?");
        $checkStmt->execute([$username, $customer['id']]);
        if ($checkStmt->fetch()) {
            $error = 'This username is already taken. Please choose another.';
        } else {
            $stmt = $db->prepare("
                UPDATE customers 
                SET username = ?, phone = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$username, $phone, $customer['id']]);
            
            logCustomerActivity($customer['id'], 'profile_updated', 'Profile information updated');
            
            $customer['username'] = $username;
            $customer['phone'] = $phone;
            
            $success = 'Profile updated successfully!';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-6 border-b">
            <h2 class="text-lg font-bold text-gray-900">Profile Information</h2>
            <p class="text-sm text-gray-500 mt-1">Update your account details</p>
        </div>
        
        <div class="p-6">
            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">
                <i class="bi-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($customer['email']) ?>" disabled
                           class="w-full px-4 py-3 border rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                    <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required minlength="3"
                           value="<?= htmlspecialchars($customer['username'] ?? '') ?>"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter your username" pattern="[a-zA-Z0-9_]+">
                    <p class="text-xs text-gray-500 mt-1">Letters, numbers, and underscores only</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone"
                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter your phone number">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Account Created</label>
                    <input type="text" value="<?= date('F j, Y', strtotime($customer['created_at'])) ?>" disabled
                           class="w-full px-4 py-3 border rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                </div>
                
                <div class="pt-4 border-t">
                    <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition">
                        <i class="bi-check-lg mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="mt-6 bg-white rounded-xl shadow-sm border">
        <div class="p-6 border-b">
            <h2 class="text-lg font-bold text-gray-900">Account Status</h2>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="bi-shield-check text-green-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Account Status</p>
                        <p class="text-sm text-gray-500">Your account is active and in good standing</p>
                    </div>
                </div>
                <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-700">
                    <?= ucfirst($customer['status'] ?? 'active') ?>
                </span>
            </div>
            
            <?php if (!empty($customer['email_verified'])): ?>
            <div class="mt-4 pt-4 border-t flex items-center space-x-3">
                <i class="bi-envelope-check text-green-600"></i>
                <span class="text-sm text-gray-600">Email verified</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
