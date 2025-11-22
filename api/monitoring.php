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
            
            // Count errors in last hour
            if (file_exists($errorLog)) {
                $lastHour = time() - 3600;
                $errors = file($errorLog);
                foreach ($errors as $error) {
                    if (strtotime(substr($error, 0, 19)) > $lastHour) {
                        $recentErrors++;
                    }
                }
            }
            
            // Count requests in last hour
            if (file_exists($accessLog)) {
                $lastHour = time() - 3600;
                $requests = file($accessLog);
                foreach ($requests as $request) {
                    if (preg_match('/\[(.*?)\]/', $request, $matches)) {
                        if (strtotime($matches[1]) > $lastHour) {
                            $recentRequests++;
                        }
                    }
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
            // Get recent errors
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $errorLog = __DIR__ . '/../logs/error.log';
            
            $errors = [];
            if (file_exists($errorLog)) {
                $allErrors = file($errorLog);
                $recentErrors = array_slice($allErrors, -$limit);
                foreach (array_reverse($recentErrors) as $line) {
                    if (trim($line)) {
                        $errors[] = trim($line);
                    }
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
            // Get API performance metrics
            $accessLog = __DIR__ . '/../logs/access.log';
            
            $apiMetrics = [];
            $totalRequests = 0;
            $totalTime = 0;
            $slowestRequest = 0;
            $fastestRequest = PHP_FLOAT_MAX;
            $endpointStats = [];
            
            if (file_exists($accessLog)) {
                $lastHour = time() - 3600;
                $requests = file($accessLog);
                
                foreach ($requests as $line) {
                    if (preg_match('/\[(.*?)\].*?GET\s(\/[^\s]*)\s.*?Status:\s(\d+).*?Time:\s([\d.]+)/', $line, $matches)) {
                        $timestamp = $matches[1];
                        $endpoint = $matches[2];
                        $status = $matches[3];
                        $time = (float)$matches[4];
                        
                        if (strtotime($timestamp) > $lastHour) {
                            $totalRequests++;
                            $totalTime += $time;
                            $slowestRequest = max($slowestRequest, $time);
                            $fastestRequest = min($fastestRequest, $time);
                            
                            if (!isset($endpointStats[$endpoint])) {
                                $endpointStats[$endpoint] = [
                                    'calls' => 0,
                                    'avg_time' => 0,
                                    'min_time' => PHP_FLOAT_MAX,
                                    'max_time' => 0,
                                    'errors' => 0
                                ];
                            }
                            
                            $endpointStats[$endpoint]['calls']++;
                            $endpointStats[$endpoint]['min_time'] = min($endpointStats[$endpoint]['min_time'], $time);
                            $endpointStats[$endpoint]['max_time'] = max($endpointStats[$endpoint]['max_time'], $time);
                            if ($status >= 400) {
                                $endpointStats[$endpoint]['errors']++;
                            }
                        }
                    }
                }
            }
            
            // Calculate averages
            foreach ($endpointStats as $endpoint => $stats) {
                $endpointStats[$endpoint]['avg_time'] = round($stats['calls'] > 0 ? array_sum(array_column([$stats], 'avg_time')) / $stats['calls'] : 0, 2);
            }
            
            echo json_encode([
                'success' => true,
                'period' => 'last_hour',
                'total_requests' => $totalRequests,
                'avg_response_time' => round($totalRequests > 0 ? $totalTime / $totalRequests : 0, 2) . 'ms',
                'fastest_response' => round($fastestRequest, 2) . 'ms',
                'slowest_response' => round($slowestRequest, 2) . 'ms',
                'endpoints' => array_slice($endpointStats, 0, 20)
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
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Available: health, errors, api_performance, cache_status'
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
