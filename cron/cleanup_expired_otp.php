<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Clean up old customer OTP codes (24 hours)
$stmt = $db->prepare("
    DELETE FROM customer_otp_codes 
    WHERE created_at < datetime('now', '-24 hours')
");
$stmt->execute();

$deleted = $stmt->rowCount();
error_log("OTP Cleanup: Deleted {$deleted} expired codes");
echo "Cleaned up {$deleted} expired OTP codes\n";

// Clean up rate limits (24 hours)
$stmt = $db->prepare("
    DELETE FROM rate_limits 
    WHERE last_attempt < datetime('now', '-24 hours')
");
$stmt->execute();
$rateLimitsDeleted = $stmt->rowCount();
error_log("Rate Limits Cleanup: Deleted {$rateLimitsDeleted} expired entries");
echo "Cleaned up {$rateLimitsDeleted} expired rate limit entries\n";

// Auto-expire admin verification OTPs after 10 minutes - mark as used
$stmt = $db->prepare("
    UPDATE admin_verification_otps 
    SET is_used = 1, used_at = datetime('now')
    WHERE is_used = 0 AND expires_at < datetime('now')
");
$stmt->execute();
$adminOtpsExpired = $stmt->rowCount();
error_log("Admin OTP Cleanup: Expired {$adminOtpsExpired} verification codes");
echo "Auto-expired {$adminOtpsExpired} admin verification OTPs\n";

// Mark OTP notifications as read after they expire (10 minutes from creation)
$stmt = $db->prepare("
    UPDATE customer_notifications 
    SET is_read = 1 
    WHERE type = 'identity_verification' 
    AND is_read = 0 
    AND created_at < datetime('now', '-10 minutes')
");
$stmt->execute();
$notificationsRead = $stmt->rowCount();
error_log("Notification Cleanup: Marked {$notificationsRead} OTP notifications as read");
echo "Auto-marked {$notificationsRead} OTP notifications as read\n";

// Delete old admin verification OTPs (older than 24 hours)
$stmt = $db->prepare("
    DELETE FROM admin_verification_otps 
    WHERE created_at < datetime('now', '-24 hours')
");
$stmt->execute();
$adminOtpsDeleted = $stmt->rowCount();
error_log("Admin OTP Cleanup: Deleted {$adminOtpsDeleted} old verification codes");
echo "Deleted {$adminOtpsDeleted} old admin verification OTPs\n";
