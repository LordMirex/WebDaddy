<?php
/**
 * Email Processing Trigger (Non-Blocking)
 * Call this endpoint periodically to process queued emails
 * Safe to call - returns immediately without blocking
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Run background processor in a non-blocking way
if (function_exists('fastcgi_finish_request')) {
    // FastCGI: Send response immediately, then process in background
    echo json_encode(['status' => 'queued']);
    fastcgi_finish_request();
} else {
    // Fallback: Just send response
    echo json_encode(['status' => 'processing']);
    flush();
    ob_flush();
}

// Now process emails in background (won't block user)
try {
    require_once __DIR__ . '/includes/background-processor.php';
} catch (Exception $e) {
    error_log("Background processor error: " . $e->getMessage());
}

exit;
