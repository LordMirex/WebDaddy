<?php
/**
 * Email Processor Trigger
 * Include this in key pages to ensure email queue is processed
 * Called automatically on cart/checkout/admin operations
 */

function triggerEmailProcessor() {
    // Only process if enough time has passed (avoid repeated processing)
    $lastProcessed = (int)($_SESSION['last_email_process'] ?? 0);
    $now = time();
    
    // Process every 60 seconds minimum
    if (($now - $lastProcessed) < 60) {
        return;
    }
    
    // Trigger background email processing
    require_once __DIR__ . '/email_queue.php';
    
    try {
        processEmailQueue();
        $_SESSION['last_email_process'] = $now;
    } catch (Exception $e) {
        error_log("Email processor error: " . $e->getMessage());
    }
}

// Auto-trigger on critical operations
function ensureEmailProcessing() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    triggerEmailProcessor();
}
