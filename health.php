<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => []
];

try {
    $start = microtime(true);
    $db = getDb();
    $db->query("SELECT 1");
    $health['checks']['database'] = [
        'status' => 'ok',
        'response_time_ms' => round((microtime(true) - $start) * 1000, 2)
    ];
} catch (Exception $e) {
    $health['status'] = 'critical';
    $health['checks']['database'] = ['status' => 'error', 'message' => 'Connection failed'];
}

$tables = ['customers', 'customer_sessions', 'customer_otp_codes', 'pending_orders', 'deliveries'];
foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $health['checks']['table_' . $table] = ['status' => 'ok', 'count' => (int)$count];
    } catch (Exception $e) {
        $health['status'] = 'warning';
        $health['checks']['table_' . $table] = ['status' => 'error'];
    }
}

try {
    $recentErrors = $db->query("
        SELECT COUNT(*) FROM customer_activity_log 
        WHERE action LIKE '%error%' 
        AND created_at > datetime('now', '-1 hour')
    ")->fetchColumn();

    if ($recentErrors > 10) {
        $health['status'] = 'warning';
        $health['checks']['recent_errors'] = ['status' => 'elevated', 'count' => (int)$recentErrors];
    } else {
        $health['checks']['recent_errors'] = ['status' => 'ok', 'count' => (int)$recentErrors];
    }
} catch (Exception $e) {
    $health['checks']['recent_errors'] = ['status' => 'unknown'];
}

// Resend Email API check (replaces SMS - all notifications via email now)
if (defined('RESEND_API_KEY') && !empty(RESEND_API_KEY)) {
    $health['checks']['resend_email'] = ['status' => 'configured'];
} else {
    $health['checks']['resend_email'] = ['status' => 'not_configured'];
}

http_response_code($health['status'] === 'ok' ? 200 : ($health['status'] === 'warning' ? 200 : 503));
echo json_encode($health, JSON_PRETTY_PRINT);
