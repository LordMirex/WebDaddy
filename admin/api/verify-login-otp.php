<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otpCode = trim($_POST['otp_code'] ?? '');

if (!$email || !$otpCode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and OTP code required']);
    exit;
}

$db = getDb();

try {
    // Verify OTP
    $stmt = $db->prepare("
        SELECT * FROM admin_login_otps 
        WHERE admin_email = ?
        AND otp_code = ?
        AND is_used = 0
        AND expires_at > datetime('now')
        AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $otpCode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        // Increment attempts
        $stmt = $db->prepare("
            UPDATE admin_login_otps 
            SET attempts = attempts + 1 
            WHERE admin_email = ? AND is_used = 0 AND expires_at > datetime('now')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP code']);
        exit;
    }
    
    // Mark OTP as used
    $stmt = $db->prepare("
        UPDATE admin_login_otps 
        SET is_used = 1, used_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$otp['id']]);
    
    // Get admin info
    $stmt = $db->prepare("SELECT id, email, username FROM admins WHERE id = ? AND is_active = 1");
    $stmt->execute([$otp['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Admin account not found']);
        exit;
    }
    
    // Create admin session
    startSecureSession();
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['session_verified'] = true;
    
    error_log("✅ Admin OTP verified for {$email} (ID: {$admin['id']})");
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'admin_id' => $admin['id']
    ]);
    
} catch (PDOException $e) {
    error_log('Admin OTP verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
