<?php
/**
 * User Security Settings Page
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'security';
$pageTitle = 'Security Settings';

$passwordSuccess = '';
$passwordError = '';
$sessionSuccess = '';

$hasPassword = !empty($customer['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($hasPassword && empty($currentPassword)) {
            $passwordError = 'Current password is required';
        } elseif (empty($newPassword)) {
            $passwordError = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $passwordError = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'Passwords do not match';
        } elseif ($hasPassword && !password_verify($currentPassword, $customer['password_hash'])) {
            $passwordError = 'Current password is incorrect';
        } else {
            $db = getDb();
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                UPDATE customers 
                SET password_hash = ?, password_changed_at = datetime('now'), updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$hash, $customer['id']]);
            
            logCustomerActivity($customer['id'], 'password_changed', 'Password was changed');
            
            $customer['password_hash'] = $hash;
            $hasPassword = true;
            $passwordSuccess = $hasPassword ? 'Password changed successfully!' : 'Password set successfully!';
        }
    }
    
    if ($action === 'revoke_session') {
        $sessionId = intval($_POST['session_id'] ?? 0);
        
        if ($sessionId > 0) {
            $db = getDb();
            $stmt = $db->prepare("
                UPDATE customer_sessions 
                SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'manual_revoke'
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$sessionId, $customer['id']]);
            
            logCustomerActivity($customer['id'], 'session_revoked', "Session ID $sessionId was revoked");
            $sessionSuccess = 'Session revoked successfully';
        }
    }
    
    if ($action === 'revoke_all') {
        $currentToken = $_COOKIE['customer_token'] ?? null;
        
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE customer_sessions 
            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'revoke_all_sessions'
            WHERE customer_id = ? AND is_active = 1 AND session_token != ?
        ");
        $stmt->execute([$customer['id'], $currentToken]);
        
        logCustomerActivity($customer['id'], 'all_sessions_revoked', 'All other sessions were revoked');
        $sessionSuccess = 'All other sessions have been revoked';
    }
}

$sessions = getCustomerActiveSessions($customer['id']);
$currentToken = $_COOKIE['customer_token'] ?? null;

function parseUserAgent($userAgent) {
    $device = 'Unknown Device';
    $browser = 'Unknown Browser';
    
    if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $device = 'iOS Device';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $device = 'Android Device';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $device = 'Windows PC';
    } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
        $device = 'Mac';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $device = 'Linux';
    }
    
    if (preg_match('/Chrome\/[\d.]+/i', $userAgent) && !preg_match('/Edg/i', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Safari\/[\d.]+/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Firefox\/[\d.]+/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Edg\/[\d.]+/i', $userAgent)) {
        $browser = 'Edge';
    }
    
    return ['device' => $device, 'browser' => $browser];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <!-- Password Section -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-6 border-b">
            <h2 class="text-lg font-bold text-gray-900">
                <?= $hasPassword ? 'Change Password' : 'Set Password' ?>
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                <?= $hasPassword ? 'Update your account password' : 'Create a password to secure your account' ?>
            </p>
        </div>
        
        <div class="p-6">
            <?php if ($passwordSuccess): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-6">
                <i class="bi-check-circle mr-2"></i><?= htmlspecialchars($passwordSuccess) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($passwordError): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 mb-6">
                <i class="bi-exclamation-circle mr-2"></i><?= htmlspecialchars($passwordError) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                
                <?php if ($hasPassword): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter current password">
                </div>
                <?php endif; ?>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Enter new password (min. 6 characters)">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                           placeholder="Confirm new password">
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="px-6 py-3 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition">
                        <i class="bi-lock mr-2"></i><?= $hasPassword ? 'Change Password' : 'Set Password' ?>
                    </button>
                </div>
            </form>
            
            <?php if ($hasPassword && !empty($customer['password_changed_at'])): ?>
            <p class="text-xs text-gray-500 mt-4">
                <i class="bi-clock mr-1"></i>
                Last changed: <?= date('F j, Y', strtotime($customer['password_changed_at'])) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Active Sessions -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Active Sessions</h2>
                <p class="text-sm text-gray-500 mt-1">Manage devices logged into your account</p>
            </div>
            
            <?php if (count($sessions) > 1): ?>
            <form method="POST" onsubmit="return confirm('Revoke all other sessions?')">
                <input type="hidden" name="action" value="revoke_all">
                <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">
                    <i class="bi-x-circle mr-1"></i>Revoke All Others
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="divide-y">
            <?php if ($sessionSuccess): ?>
            <div class="p-4 bg-green-50 border-b border-green-100">
                <i class="bi-check-circle text-green-600 mr-2"></i>
                <span class="text-green-700"><?= htmlspecialchars($sessionSuccess) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (empty($sessions)): ?>
            <div class="p-8 text-center text-gray-500">
                No active sessions found
            </div>
            <?php else: ?>
            <?php foreach ($sessions as $session): 
                $parsed = parseUserAgent($session['user_agent'] ?? '');
                $db = getDb();
                $stmt = $db->prepare("SELECT session_token FROM customer_sessions WHERE id = ?");
                $stmt->execute([$session['id']]);
                $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                $isCurrentSession = ($sessionData['session_token'] ?? '') === $currentToken;
            ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <?php if (strpos($parsed['device'], 'iPhone') !== false || strpos($parsed['device'], 'iPad') !== false || strpos($parsed['device'], 'iOS') !== false): ?>
                            <i class="bi-phone text-gray-600"></i>
                            <?php elseif (strpos($parsed['device'], 'Android') !== false): ?>
                            <i class="bi-phone text-gray-600"></i>
                            <?php else: ?>
                            <i class="bi-laptop text-gray-600"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">
                                <?= htmlspecialchars($session['device_name'] ?? $parsed['device']) ?>
                                <?php if ($isCurrentSession): ?>
                                <span class="ml-2 px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">Current</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?= htmlspecialchars($parsed['browser']) ?>
                                <?php if (!empty($session['ip_address'])): ?>
                                · <?= htmlspecialchars($session['ip_address']) ?>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                Last active: <?= date('M j, Y g:i A', strtotime($session['last_activity_at'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!$isCurrentSession): ?>
                    <form method="POST" onsubmit="return confirm('Revoke this session?')">
                        <input type="hidden" name="action" value="revoke_session">
                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700">
                            <i class="bi-x-circle"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Security Tips -->
    <div class="bg-blue-50 rounded-xl p-6 border border-blue-100">
        <h3 class="font-semibold text-blue-900 mb-3">
            <i class="bi-lightbulb mr-2"></i>Security Tips
        </h3>
        <ul class="space-y-2 text-sm text-blue-800">
            <li><i class="bi-check2 mr-2"></i>Use a strong, unique password for your account</li>
            <li><i class="bi-check2 mr-2"></i>Regularly review your active sessions</li>
            <li><i class="bi-check2 mr-2"></i>Revoke sessions from devices you don't recognize</li>
            <li><i class="bi-check2 mr-2"></i>Never share your password with anyone</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
