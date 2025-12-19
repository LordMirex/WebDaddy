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
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'update_profile') {
        // Update username and WhatsApp
        $username = trim($_POST['username'] ?? '');
        $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
        $cleanWhatsapp = preg_replace('/[^0-9+]/', '', $whatsappNumber);
        
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
                    SET username = ?, whatsapp_number = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$username, $cleanWhatsapp, $customer['id']]);
                
                logCustomerActivity($customer['id'], 'profile_updated', 'Profile information updated');
                
                $customer['username'] = $username;
                $customer['whatsapp_number'] = $cleanWhatsapp;
                
                $success = 'Profile updated successfully!';
            }
        }
    } elseif ($action === 'request_email_otp') {
        // Request OTP for email change
        $newEmail = trim($_POST['new_email'] ?? '');
        
        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (strtolower($newEmail) === strtolower($customer['email'])) {
            $error = 'Please enter a different email address';
        } else {
            require_once __DIR__ . '/../includes/customer_otp.php';
            $result = sendCheckoutEmailOTP($newEmail);
            
            if ($result['success']) {
                $_SESSION['email_change_pending'] = [
                    'new_email' => $newEmail,
                    'customer_id' => $customer['id'],
                    'timestamp' => time()
                ];
                $success = 'OTP has been sent to ' . htmlspecialchars($newEmail) . '. Please check your inbox.';
            } else {
                $error = $result['error'] ?? 'Failed to send OTP';
            }
        }
    } elseif ($action === 'verify_email_otp') {
        // Verify OTP and change email
        $otpCode = trim($_POST['otp_code'] ?? '');
        
        if (!isset($_SESSION['email_change_pending'])) {
            $error = 'No email change request found. Please start over.';
        } elseif (empty($otpCode)) {
            $error = 'Please enter the OTP code';
        } else {
            require_once __DIR__ . '/../includes/customer_otp.php';
            $newEmail = $_SESSION['email_change_pending']['new_email'];
            $result = verifyCheckoutEmailOTP($newEmail, $otpCode);
            
            if ($result['success']) {
                // Update email in database
                $db = getDb();
                $stmt = $db->prepare("
                    UPDATE customers 
                    SET email = ?, email_verified = 1, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$newEmail, $customer['id']]);
                
                logCustomerActivity($customer['id'], 'email_changed', 'Email address changed to ' . $newEmail);
                
                $customer['email'] = $newEmail;
                $customer['email_verified'] = 1;
                unset($_SESSION['email_change_pending']);
                
                $success = 'Email address updated successfully!';
            } else {
                $error = $result['error'] ?? 'Invalid OTP code';
            }
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
            
            <form method="POST" class="space-y-6" id="profileForm">
                <!-- Email Change Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($customer['email']) ?>" disabled
                           class="w-full px-4 py-3 border rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                    
                    <?php if (!isset($_SESSION['email_change_pending'])): ?>
                        <button type="button" onclick="document.getElementById('emailChangeForm').style.display='block'; this.style.display='none'"
                                class="text-xs text-amber-600 hover:text-amber-700 mt-2 font-semibold">
                            <i class="bi-pencil mr-1"></i>Change Email
                        </button>
                    <?php endif; ?>
                    
                    <!-- Email Change Form -->
                    <div id="emailChangeForm" style="display:<?= isset($_SESSION['email_change_pending']) ? 'block' : 'none' ?>;" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <?php if (isset($_SESSION['email_change_pending'])): ?>
                            <!-- OTP Verification Step -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Verify OTP</label>
                                <p class="text-xs text-gray-600 mb-3">An OTP has been sent to <strong><?= htmlspecialchars($_SESSION['email_change_pending']['new_email']) ?></strong></p>
                                <input type="hidden" name="action" value="verify_email_otp">
                                <div class="flex gap-2">
                                    <input type="text" name="otp_code" placeholder="Enter 6-digit OTP" maxlength="6"
                                           class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                           pattern="[0-9]{6}" required>
                                    <button type="submit" class="px-4 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition whitespace-nowrap">
                                        Verify
                                    </button>
                                </div>
                                <button type="button" onclick="document.getElementById('emailChangeForm').style.display='none'; location.reload()"
                                        class="text-xs text-gray-500 hover:text-gray-700 mt-2">Cancel</button>
                            </div>
                        <?php else: ?>
                            <!-- New Email Entry Step -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Email Address</label>
                                <input type="hidden" name="action" value="request_email_otp">
                                <div class="flex gap-2">
                                    <input type="email" name="new_email" placeholder="Enter new email address"
                                           class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                           required>
                                    <button type="submit" class="px-4 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition whitespace-nowrap">
                                        Send OTP
                                    </button>
                                </div>
                                <button type="button" onclick="document.getElementById('emailChangeForm').style.display='none'"
                                        class="text-xs text-gray-500 hover:text-gray-700 mt-2">Cancel</button>
                            </div>
                        <?php endif; ?>
                    </div>
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
                    <input type="tel" name="whatsapp_number"
                           value="<?= htmlspecialchars($customer['whatsapp_number'] ?? '') ?>"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter your WhatsApp number">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Account Created</label>
                    <input type="text" value="<?= date('F j, Y', strtotime($customer['created_at'])) ?>" disabled
                           class="w-full px-4 py-3 border rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                </div>
                
                <div class="pt-4 border-t">
                    <?php if (!isset($_SESSION['email_change_pending'])): ?>
                        <input type="hidden" name="action" value="update_profile">
                        <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition">
                            <i class="bi-check-lg mr-2"></i>Save Changes
                        </button>
                    <?php endif; ?>
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
