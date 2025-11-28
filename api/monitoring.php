<?php
/**
 * Error Monitoring & Health Check API
 * Provides real-time monitoring of application errors, performance, and system health
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/access_log.php';
require_once __DIR__ . '/../includes/security.php';

$startTime = microtime(true);
$action = $_GET['action'] ?? 'health';

try {
    $db = getDb();
    
    switch ($action) {
        case 'health':
            // System health check
            $errorLog = __DIR__ . '/../logs/error.log';
            $accessLog = __DIR__ . '/../logs/access.log';
            $cacheDir = __DIR__ . '/../cache';
            
            $recentErrors = 0;
            $recentRequests = 0;
            $systemHealth = 'HEALTHY';
            
            // Count errors in last hour (optimized for large files)
            if (file_exists($errorLog)) {
                $lastHour = time() - 3600;
                $handle = fopen($errorLog, 'r');
                if ($handle) {
                    fseek($handle, -8192, SEEK_END); // Start from last 8KB
                    while (!feof($handle) && $recentErrors < 1000) {
                        $line = fgets($handle);
                        if ($line && strtotime(substr($line, 0, 19)) > $lastHour) {
                            $recentErrors++;
                        }
                    }
                    fclose($handle);
                }
            }
            
            // Count requests in last hour (optimized for large files)
            if (file_exists($accessLog)) {
                $lastHour = time() - 3600;
                $handle = fopen($accessLog, 'r');
                if ($handle) {
                    fseek($handle, -8192, SEEK_END); // Start from last 8KB
                    while (!feof($handle) && $recentRequests < 1000) {
                        $line = fgets($handle);
                        if ($line && preg_match('/\[(.*?)\]/', $line, $matches)) {
                            if (strtotime($matches[1]) > $lastHour) {
                                $recentRequests++;
                            }
                        }
                    }
                    fclose($handle);
                }
            }
            
            // Determine health status
            if ($recentErrors > 50) {
                $systemHealth = 'CRITICAL';
            } elseif ($recentErrors > 20) {
                $systemHealth = 'WARNING';
            }
            
            // Database check
            $dbStatus = 'OK';
            try {
                $stmt = $db->query("SELECT 1");
                $stmt->fetch();
            } catch (Exception $e) {
                $dbStatus = 'ERROR';
                $systemHealth = 'CRITICAL';
            }
            
            echo json_encode([
                'success' => true,
                'status' => $systemHealth,
                'timestamp' => date('Y-m-d H:i:s'),
                'metrics' => [
                    'errors_last_hour' => $recentErrors,
                    'requests_last_hour' => $recentRequests,
                    'cache_files' => count(glob($cacheDir . '/*.cache')),
                    'database' => $dbStatus,
                    'uptime_check' => 'OK'
                ]
            ]);
            break;
            
        case 'errors':
            // Get recent errors (optimized)
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $errorLog = __DIR__ . '/../logs/error.log';
            
            $errors = [];
            if (file_exists($errorLog)) {
                $handle = fopen($errorLog, 'r');
                if ($handle) {
                    fseek($handle, -65536, SEEK_END); // Start from last 64KB
                    $lines = [];
                    while (!feof($handle)) {
                        $line = fgets($handle);
                        if (trim($line)) {
                            $lines[] = trim($line);
                        }
                    }
                    $errors = array_slice(array_reverse($lines), 0, $limit);
                    fclose($handle);
                }
            }
            
            echo json_encode([
                'success' => true,
                'error_count' => count($errors),
                'errors' => $errors,
                'limit' => $limit
            ]);
            break;
            
        case 'api_performance':
            // Get API performance metrics (optimized for large files)
            $accessLog = __DIR__ . '/../logs/access.log';
            
            $totalRequests = 0;
            $totalTime = 0;
            $slowestRequest = 0;
            $fastestRequest = PHP_FLOAT_MAX;
            $endpointStats = [];
            
            if (file_exists($accessLog)) {
                $lastHour = time() - 3600;
                $handle = fopen($accessLog, 'r');
                if ($handle) {
                    fseek($handle, -131072, SEEK_END); // Start from last 128KB
                    while (!feof($handle) && $totalRequests < 5000) {
                        $line = fgets($handle);
                        if (preg_match('/\[(.*?)\].*?GET\s(\/[^\s]*)\s.*?Status:\s(\d+).*?Time:\s([\d.]+)/', $line, $matches)) {
                            if (strtotime($matches[1]) > $lastHour) {
                                $endpoint = $matches[2];
                                $status = (int)$matches[3];
                                $time = (float)$matches[4];
                                
                                $totalRequests++;
                                $totalTime += $time;
                                $slowestRequest = max($slowestRequest, $time);
                                $fastestRequest = min($fastestRequest, $time);
                                
                                if (!isset($endpointStats[$endpoint])) {
                                    $endpointStats[$endpoint] = ['calls' => 0, 'total_time' => 0, 'min_time' => PHP_FLOAT_MAX, 'max_time' => 0, 'errors' => 0];
                                }
                                $endpointStats[$endpoint]['calls']++;
                                $endpointStats[$endpoint]['total_time'] += $time;
                                $endpointStats[$endpoint]['min_time'] = min($endpointStats[$endpoint]['min_time'], $time);
                                $endpointStats[$endpoint]['max_time'] = max($endpointStats[$endpoint]['max_time'], $time);
                                if ($status >= 400) $endpointStats[$endpoint]['errors']++;
                            }
                        }
                    }
                    fclose($handle);
                }
            }
            
            // Calculate averages for endpoints
            foreach ($endpointStats as &$stats) {
                $stats['avg_time'] = round($stats['calls'] > 0 ? $stats['total_time'] / $stats['calls'] : 0, 2);
                unset($stats['total_time']);
            }
            
            uasort($endpointStats, function($a, $b) { return $b['calls'] - $a['calls']; });
            
            echo json_encode([
                'success' => true,
                'period' => 'last_hour',
                'total_requests' => $totalRequests,
                'avg_response_time' => round($totalRequests > 0 ? $totalTime / $totalRequests : 0, 2) . 'ms',
                'fastest_response' => ($fastestRequest === PHP_FLOAT_MAX ? '0' : round($fastestRequest, 2)) . 'ms',
                'slowest_response' => round($slowestRequest, 2) . 'ms',
                'endpoints' => array_slice($endpointStats, 0, 20, true)
            ]);
            break;
            
        case 'cache_status':
            // Cache performance metrics
            $cacheDir = __DIR__ . '/../cache';
            $cacheFiles = glob($cacheDir . '/*.cache');
            
            $totalCached = 0;
            $totalSize = 0;
            
            foreach ($cacheFiles as $file) {
                $totalCached++;
                $totalSize += filesize($file);
            }
            
            echo json_encode([
                'success' => true,
                'cache_enabled' => true,
                'cache_files' => $totalCached,
                'total_cache_size' => round($totalSize / 1024, 2) . 'KB',
                'cache_directory' => $cacheDir,
                'writable' => is_writable($cacheDir)
            ]);
            break;
        
        case 'webhook_security':
            // Get webhook security statistics
            $stats = getWebhookSecurityStats();
            
            echo json_encode([
                'success' => true,
                'today_webhooks' => $stats['today_webhooks'],
                'blocked_today' => $stats['blocked_today'],
                'successful_payments' => $stats['successful_payments'],
                'failed_payments' => $stats['failed_payments'],
                'recent_events' => $stats['recent_events']
            ]);
            break;
        
        case 'system_logs':
            // Get all system logs from all sources
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $allLogs = [];
            
            // Activity logs
            $stmt = $db->query("SELECT 'activity' as type, 'Admin Activity' as category, action as description, details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT $limit");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allLogs[] = $row;
            }
            
            // Security logs
            $stmt = $db->query("SELECT 'security' as type, 'Security' as category, event_type as description, CONCAT('IP: ', ip_address, ' | ', details) as details, created_at FROM security_logs ORDER BY created_at DESC LIMIT $limit");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allLogs[] = $row;
            }
            
            // Payment logs
            $stmt = $db->query("SELECT 'payment' as type, 'Payments' as category, event_type as description, CONCAT('Amount: ', amount, ' | Status: ', status) as details, created_at FROM payment_logs ORDER BY created_at DESC LIMIT $limit");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allLogs[] = $row;
            }
            
            // Email events
            $stmt = $db->query("SELECT 'email' as type, 'Emails' as category, event_type as description, CONCAT('To: ', recipient_email) as details, created_at FROM email_events ORDER BY created_at DESC LIMIT $limit");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allLogs[] = $row;
            }
            
            // Commission logs
            $stmt = $db->query("SELECT 'commission' as type, 'Commission' as category, action as description, CONCAT('Amount: ', amount, ' | User: ', affiliate_id) as details, created_at FROM commission_log ORDER BY created_at DESC LIMIT $limit");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $allLogs[] = $row;
            }
            
            // Sort by created_at descending
            usort($allLogs, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Get log counts
            $activityCount = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
            $securityCount = $db->query("SELECT COUNT(*) FROM security_logs")->fetchColumn();
            $paymentCount = $db->query("SELECT COUNT(*) FROM payment_logs")->fetchColumn();
            $emailCount = $db->query("SELECT COUNT(*) FROM email_events")->fetchColumn();
            $commissionCount = $db->query("SELECT COUNT(*) FROM commission_log")->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'counts' => [
                    'activity' => (int)$activityCount,
                    'security' => (int)$securityCount,
                    'payment' => (int)$paymentCount,
                    'email' => (int)$emailCount,
                    'commission' => (int)$commissionCount
                ],
                'logs' => array_slice($allLogs, 0, $limit),
                'total_displayed' => count($allLogs)
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Available: health, errors, api_performance, cache_status, webhook_security, system_logs'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Monitoring API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Monitoring failed'
    ]);
}

$duration = (microtime(true) - $startTime) * 1000;
logApiAccess('/api/monitoring.php?action=' . $action, 'GET', 200, $duration);
rotateAccessLogs();
?>
