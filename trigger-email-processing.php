<?php
/**
 * Email Processing Trigger (Non-Blocking)
 * Enhanced: Supports aggressive processing mode for bulk emails
 * Call this endpoint periodically to process queued emails
 * Safe to call - returns immediately without blocking
 * 
 * Usage:
 *   GET /trigger-email-processing.php         - Normal processing (25 emails/batch)
 *   GET /trigger-email-processing.php?aggressive=1 - Aggressive processing (50 emails/batch)
 *   GET /trigger-email-processing.php?stats=1      - Get queue statistics
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check for stats request (returns immediately with queue status)
if (isset($_GET['stats'])) {
    require_once __DIR__ . '/includes/email_queue.php';
    echo json_encode([
        'status' => 'ok',
        'queue' => getEmailQueueStats()
    ]);
    exit;
}

// Check for aggressive mode (for bulk sending)
$aggressive = isset($_GET['aggressive']) && $_GET['aggressive'];

// Run background processor in a non-blocking way
if (function_exists('fastcgi_finish_request')) {
    // FastCGI: Send response immediately, then process in background
    echo json_encode([
        'status' => 'queued',
        'mode' => $aggressive ? 'aggressive' : 'normal'
    ]);
    fastcgi_finish_request();
} else {
    // Fallback: Just send response
    echo json_encode([
        'status' => 'processing',
        'mode' => $aggressive ? 'aggressive' : 'normal'
    ]);
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// Now process emails in background (won't block user)
try {
    require_once __DIR__ . '/includes/email_queue.php';
    
    // Process emails with appropriate mode
    $batchSize = $aggressive ? 50 : 25;
    processEmailQueue($batchSize, $aggressive);
    
    // Also process any high-priority emails immediately
    processHighPriorityEmails();
    
} catch (Exception $e) {
    error_log("Email processor error: " . $e->getMessage());
}

exit;
