<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

$db = getDb();

try {
    // Check if admin exists
    $stmt = $db->prepare("SELECT id, email FROM admins WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Admin account not found']);
        exit;
    }
    
    // Rate limiting: max 3 OTP requests per hour
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM admin_login_otps 
        WHERE admin_email = ? 
        AND created_at > datetime('now', '-1 hour')
    ");
    $stmt->execute([$email]);
    $recentOtpCount = $stmt->fetchColumn();
    
    if ($recentOtpCount >= 3) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'error' => 'Too many OTP requests. Please try again in 1 hour.'
        ]);
        exit;
    }
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // OTP expires in 10 minutes
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Expire previous OTPs for this admin
    $stmt = $db->prepare("
        UPDATE admin_login_otps 
        SET is_used = 1
        WHERE admin_email = ? AND is_used = 0 AND expires_at > datetime('now')
    ");
    $stmt->execute([$email]);
    
    // Create new OTP record
    $stmt = $db->prepare("
        INSERT INTO admin_login_otps (admin_id, admin_email, otp_code, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$admin['id'], $email, $otpCode, $expiresAt]);
    
    // Send OTP email
    $emailSent = sendAdminLoginOTPEmail($email, $otpCode);
    
    if (!$emailSent) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send OTP email']);
        exit;
    }
    
    error_log("Admin OTP requested for {$email}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email',
        'expires_in' => 600
    ]);
    
} catch (PDOException $e) {
    error_log('Admin OTP request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
