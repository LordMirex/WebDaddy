<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

echo "Starting monthly cleanup...\n";

$stmt = $db->prepare("
    DELETE FROM customer_sessions 
    WHERE is_active = 0 
    AND last_activity_at < datetime('now', '-30 days')
");
$stmt->execute();
echo "Deleted {$stmt->rowCount()} inactive sessions\n";

$stmt = $db->prepare("
    UPDATE customer_sessions
    SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'expired_inactivity'
    WHERE is_active = 1
    AND last_activity_at < datetime('now', '-90 days')
");
$stmt->execute();
echo "Expired {$stmt->rowCount()} inactive sessions\n";

$stmt = $db->prepare("
    DELETE FROM customer_otp_codes
    WHERE created_at < datetime('now', '-7 days')
");
$stmt->execute();
echo "Deleted {$stmt->rowCount()} old OTP codes\n";

echo "Running VACUUM...\n";
$db->exec("VACUUM");

echo "Running ANALYZE...\n";
$db->exec("ANALYZE");

echo "Monthly cleanup complete!\n";
error_log("Monthly cleanup completed successfully");
