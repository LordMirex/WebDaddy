<?php
/**
 * Cleanup API Endpoint
 * Allows admin to manually trigger file cleanup
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cleanup.php';

// Set JSON response header
header('Content-Type: application/json');

// Start session
startSecureSession();

// Check if user is logged in as admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Run cleanup
    $cleanup = new FileCleanup();
    $result = $cleanup->runCleanup();
    
    // Log activity
    logActivity(
        'file_cleanup',
        "File cleanup completed: {$result['deleted_count']} files deleted, {$result['freed_space_formatted']} freed",
        getAdminId()
    );
    
    // Return result
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
