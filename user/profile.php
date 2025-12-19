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
                // Update email in database immediately
                $db = getDb();
                $stmt = $db->prepare("
                    UPDATE customers 
                    SET email = ?, email_verified = 1, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$newEmail, $customer['id']]);
                
                // Update session with new email immediately
                if (isset($_SESSION['customer_email'])) {
                    $_SESSION['customer_email'] = $newEmail;
                }
                
                logCustomerActivity($customer['id'], 'email_changed', 'Email address changed to ' . $newEmail);
                
                // Update local variable for immediate display
                $customer['email'] = $newEmail;
                $customer['email_verified'] = 1;
                unset($_SESSION['email_change_pending']);
                
                $success = 'Email address updated successfully! Your email is now ' . htmlspecialchars($newEmail);
            } else {
                $error = $result['error'] ?? 'Invalid OTP code';
            }
        }
    }
}

// Handle email change cancellation
if (isset($_GET['reset']) && $_GET['reset'] === 'email') {
    unset($_SESSION['email_change_pending']);
    header('Location: ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    exit;
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
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6 flex items-center gap-3 animate-in fade-in duration-300">
                <i class="bi-check-circle-fill text-lg flex-shrink-0"></i>
                <div>
                    <p class="font-semibold"><?= strpos($success, 'Email address updated') !== false ? 'Email Updated Successfully!' : htmlspecialchars($success) ?></p>
                    <?php if (strpos($success, 'Your email is now') !== false): ?>
                        <p class="text-sm mt-1 font-mono text-green-800"><?= htmlspecialchars($customer['email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6 flex items-center gap-3">
                <i class="bi-exclamation-circle-fill text-lg flex-shrink-0"></i>
                <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Email Change Modal/Section -->
            <?php if (isset($_SESSION['email_change_pending'])): ?>
            <div class="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl shadow-sm border border-amber-200 overflow-hidden" id="emailVerificationSection">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-amber-100">
                                <i class="bi-shield-check text-amber-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-1">Verify Your New Email</h3>
                            <p class="text-sm text-gray-600 mb-4">We sent a 6-digit code to <strong><?= htmlspecialchars($_SESSION['email_change_pending']['new_email']) ?></strong></p>
                            
                            <form id="emailOtpForm" class="space-y-4">
                                <div>
                                    <input type="hidden" name="action" value="verify_email_otp">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Verification Code</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="otp_code" placeholder="000000" maxlength="6"
                                               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-2xl tracking-widest text-center font-mono"
                                               pattern="[0-9]{6}" required autocomplete="off" id="otpInput">
                                        <button type="submit" id="verifyBtn" class="px-6 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition whitespace-nowrap flex items-center gap-2">
                                            <i class="bi-check-lg"></i><span>Verify</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="mt-4 pt-4 border-t border-amber-200 flex gap-3">
                                <button type="button" onclick="window.location.href='?reset=email'" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">
                                    Cancel & Start Over
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            document.getElementById('emailOtpForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const otpInput = document.getElementById('otpInput');
                const verifyBtn = document.getElementById('verifyBtn');
                const form = e.target;
                const otp = otpInput.value.trim();
                
                if (!/^\d{6}$/.test(otp)) {
                    showEmailError('Please enter a valid 6-digit verification code');
                    return;
                }
                
                verifyBtn.disabled = true;
                otpInput.disabled = true;
                const originalText = verifyBtn.innerHTML;
                verifyBtn.innerHTML = '<i class="bi-hourglass-split"></i><span>Verifying...</span>';
                
                try {
                    const formData = new FormData(form);
                    const response = await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const text = await response.text();
                    
                    // Check if response contains success message in HTML
                    if (response.ok && text.includes('Email address updated successfully')) {
                        verifyBtn.innerHTML = '<i class="bi-check-circle"></i><span>Verified!</span>';
                        verifyBtn.className = 'px-6 py-3 bg-green-600 text-white rounded-lg font-semibold whitespace-nowrap flex items-center gap-2';
                        setTimeout(() => window.location.reload(), 2000);
                    } else if (text.includes('Invalid or expired OTP')) {
                        throw new Error('This code is invalid or has expired. Please request a new one.');
                    } else if (text.includes('No email change request found') || text.includes('Session expired')) {
                        throw new Error('Your session expired. Please start the email change process again.');
                    } else if (text.includes('Please enter the OTP')) {
                        throw new Error('Please enter the 6-digit code sent to your email.');
                    } else {
                        throw new Error('Verification failed. Please check the code and try again.');
                    }
                } catch (err) {
                    verifyBtn.disabled = false;
                    otpInput.disabled = false;
                    verifyBtn.innerHTML = originalText;
                    showEmailError(err.message || 'Verification failed. Please try again.');
                    otpInput.focus();
                    otpInput.select();
                }
            });
            
            // Auto-focus next field after 6 digits
            document.getElementById('otpInput').addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    document.getElementById('verifyBtn').focus();
                }
                // Clear error on new input
                const errorDiv = document.getElementById('emailErrorMsg');
                if (errorDiv) errorDiv.remove();
            });
            
            function showEmailError(message) {
                let errorDiv = document.getElementById('emailErrorMsg');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.id = 'emailErrorMsg';
                    document.getElementById('emailVerificationSection').insertAdjacentElement('beforeend', errorDiv);
                }
                errorDiv.className = 'mt-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 flex items-start gap-3 animate-in fade-in duration-300';
                errorDiv.innerHTML = '<i class="bi-exclamation-circle-fill text-lg flex-shrink-0 mt-0.5"></i><div><p class="font-semibold">Verification Error</p><p class="text-sm mt-1">' + message + '</p></div>';
            }
            </script>
            <?php endif; ?>

            <!-- Main Profile Form -->
            <form method="POST" class="space-y-6" id="profileForm" <?= isset($_SESSION['email_change_pending']) ? 'style="display:none;"' : '' ?>>
                <!-- Email Change Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="flex gap-2 items-center">
                        <input type="email" value="<?= htmlspecialchars($customer['email']) ?>" disabled
                               class="flex-1 px-4 py-3 border rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                        <button type="button" onclick="document.getElementById('emailChangeForm').classList.toggle('hidden'); this.blur();"
                                class="px-4 py-3 text-amber-600 hover:bg-amber-50 border border-amber-200 rounded-lg font-semibold transition flex items-center gap-2 whitespace-nowrap">
                            <i class="bi-pencil"></i><span>Change</span>
                        </button>
                    </div>
                    
                    <?php if (!isset($_SESSION['email_change_pending'])): ?>
                        <!-- Email Change Input Form (Hidden by default) -->
                        <div id="emailChangeForm" class="hidden mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Enter New Email Address</label>
                            <p class="text-xs text-gray-600 mb-4">We'll send a 6-digit verification code to confirm the change.</p>
                            <div class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="email" id="newEmailInput" placeholder="your.new.email@example.com"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm"
                                           autocomplete="off" required>
                                    <button type="button" id="sendEmailOtpBtn" onclick="sendEmailOTP()" class="px-4 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition whitespace-nowrap flex items-center gap-2 disabled:opacity-50">
                                        <i class="bi-send"></i><span>Send Code</span>
                                    </button>
                                </div>
                                <button type="button" onclick="document.getElementById('emailChangeForm').classList.add('hidden'); document.getElementById('emailChangeError')?.remove();"
                                        class="text-xs text-gray-500 hover:text-gray-700 font-medium">✕ Cancel</button>
                            </div>
                            <script>
                            function sendEmailOTP() {
                                const emailInput = document.getElementById('newEmailInput');
                                const email = emailInput.value.trim();
                                const btn = document.getElementById('sendEmailOtpBtn');
                                
                                // Validate email format
                                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                if (!email || !emailRegex.test(email)) {
                                    showEmailChangeError('Please enter a valid email address (e.g., name@example.com)');
                                    emailInput.focus();
                                    return;
                                }
                                
                                btn.disabled = true;
                                const originalText = btn.innerHTML;
                                btn.innerHTML = '<i class="bi-hourglass-split"></i><span>Sending...</span>';
                                emailInput.disabled = true;
                                
                                const formData = new FormData();
                                formData.append('action', 'request_email_otp');
                                formData.append('new_email', email);
                                
                                fetch('?', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(r => r.text())
                                .then(text => {
                                    if (text.includes('OTP has been sent')) {
                                        btn.innerHTML = '<i class="bi-check-circle"></i><span>Code Sent!</span>';
                                        btn.className = 'px-4 py-3 bg-green-600 text-white rounded-lg font-semibold whitespace-nowrap flex items-center gap-2';
                                        document.getElementById('emailChangeForm').classList.add('hidden');
                                        setTimeout(() => window.location.reload(), 2000);
                                    } else if (text.includes('Too many OTP requests')) {
                                        throw new Error('Too many requests. Please wait a few minutes before trying again.');
                                    } else if (text.includes('Please enter a different email')) {
                                        throw new Error('This email is already your current email address.');
                                    } else if (text.includes('Please enter a valid email address')) {
                                        throw new Error('The email address format is invalid.');
                                    } else if (text.includes('Failed to send OTP')) {
                                        throw new Error('Unable to send verification code. Please check your email address.');
                                    } else {
                                        throw new Error('Failed to send code. Please try again.');
                                    }
                                })
                                .catch(err => {
                                    showEmailChangeError(err.message || 'Error sending code. Please try again.');
                                    btn.disabled = false;
                                    btn.innerHTML = originalText;
                                    emailInput.disabled = false;
                                });
                            }
                            
                            function showEmailChangeError(message) {
                                let errorDiv = document.getElementById('emailChangeError');
                                if (!errorDiv) {
                                    errorDiv = document.createElement('div');
                                    errorDiv.id = 'emailChangeError';
                                    document.getElementById('emailChangeForm').insertAdjacentElement('beforeend', errorDiv);
                                }
                                errorDiv.className = 'mt-3 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 flex items-start gap-2 text-sm animate-in fade-in duration-300';
                                errorDiv.innerHTML = '<i class="bi-exclamation-circle-fill flex-shrink-0 mt-0.5"></i><div>' + message + '</div>';
                            }
                            
                            // Clear error when user starts typing
                            document.getElementById('newEmailInput').addEventListener('input', function() {
                                const errorDiv = document.getElementById('emailChangeError');
                                if (errorDiv) errorDiv.remove();
                            });
                            </script>
                        </div>
                    <?php endif; ?>
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
