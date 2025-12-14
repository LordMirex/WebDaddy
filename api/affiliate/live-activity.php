<?php
/**
 * API Endpoint: Get Live Affiliate Activity
 * Returns recent click and order events for the affiliate dashboard
 */

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/affiliate_stats.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check if affiliate is authenticated
if (!isset($_SESSION['affiliate_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

$affiliateId = $_SESSION['affiliate_id'];

try {
    // Get limit from query params (default 20, max 50)
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
    
    // Get recent activity events
    $events = getAffiliateRecentActivity($affiliateId, $limit);
    
    // Get real-time stats
    $stats = getAffiliateRealTimeStats($affiliateId);
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log('Error in live-activity API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load activity data'
    ]);
}
