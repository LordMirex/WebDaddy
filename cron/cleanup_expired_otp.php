<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

$stmt = $db->prepare("
    DELETE FROM customer_otp_codes 
    WHERE created_at < datetime('now', '-24 hours')
");
$stmt->execute();

$deleted = $stmt->rowCount();
error_log("OTP Cleanup: Deleted {$deleted} expired codes");
echo "Cleaned up {$deleted} expired OTP codes\n";

$stmt = $db->prepare("
    DELETE FROM rate_limits 
    WHERE last_attempt < datetime('now', '-24 hours')
");
$stmt->execute();
$rateLimitsDeleted = $stmt->rowCount();
error_log("Rate Limits Cleanup: Deleted {$rateLimitsDeleted} expired entries");
echo "Cleaned up {$rateLimitsDeleted} expired rate limit entries\n";
