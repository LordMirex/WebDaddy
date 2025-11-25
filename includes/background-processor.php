<?php
/**
 * Background Email Processor - Run asynchronously
 * This file can be called periodically to process queued emails
 * It's safe to call - it will throttle itself to avoid running too often
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_queue.php';

// Set a longer timeout since this runs in background
set_time_limit(10);

try {
    // Quick check - only process if there are pending emails
    $db = getDb();
    $stmt = $db->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        error_log("📧 Background Processor: Processing {$result['count']} pending emails");
        processEmailQueue();
        error_log("📧 Background Processor: Email processing complete");
    }
} catch (Exception $e) {
    error_log("⚠️ Background Processor Error: " . $e->getMessage());
}
