<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$customerId = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

if (!$customerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Customer ID is required']);
    exit;
}

$db = getDb();

try {
    // Verify customer exists
    $stmt = $db->prepare("SELECT id, email, username FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    // Rate limiting: max 5 OTPs per customer per hour
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM admin_verification_otps 
        WHERE customer_id = ? 
        AND created_at > datetime('now', '-1 hour')
    ");
    $stmt->execute([$customerId]);
    $recentOtpCount = $stmt->fetchColumn();
    
    if ($recentOtpCount >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'error' => 'Rate limit exceeded. Maximum 5 OTPs per customer per hour.'
        ]);
        exit;
    }
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // OTP expires in 10 minutes
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $db->beginTransaction();
    
    // Insert OTP record
    $stmt = $db->prepare("
        INSERT INTO admin_verification_otps (customer_id, admin_id, otp_code, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$customerId, getAdminId(), $otpCode, $expiresAt]);
    
    // Create notification for customer
    $notificationTitle = 'Identity Verification Code';
    $notificationMessage = "Your identity verification code is: $otpCode. This code expires in 10 minutes. Only share this code with our support team when requested.";
    $notificationData = json_encode([
        'type' => 'identity_verification_otp',
        'otp_code' => $otpCode,
        'expires_at' => $expiresAt
    ]);
    
    $stmt = $db->prepare("
        INSERT INTO customer_notifications (customer_id, type, title, message, data, priority)
        VALUES (?, 'identity_verification', ?, ?, ?, 'high')
    ");
    $stmt->execute([$customerId, $notificationTitle, $notificationMessage, $notificationData]);
    
    $db->commit();
    
    logActivity('admin_otp_generated', "Generated OTP for customer #$customerId", getAdminId());
    
    echo json_encode([
        'success' => true,
        'otp_code' => $otpCode,
        'expires_at' => $expiresAt,
        'customer_email' => $customer['email'],
        'message' => 'OTP generated successfully and notification sent to customer dashboard.'
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('OTP generation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
