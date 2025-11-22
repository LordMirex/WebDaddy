<?php
/**
 * Access Logging for API Requests
 * Logs all API access for monitoring and debugging
 */

function logApiAccess($endpoint, $method, $status, $responseTime = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $accessLogFile = $logDir . '/access.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $responseTimeStr = $responseTime ? sprintf('%.3fms', $responseTime) : 'N/A';
    
    $logEntry = sprintf(
        "[%s] %s %s - Status: %d - IP: %s - Time: %s - UA: %s\n",
        $timestamp,
        $method,
        $endpoint,
        $status,
        $ip,
        $responseTimeStr,
        substr($userAgent, 0, 100)
    );
    
    error_log($logEntry, 3, $accessLogFile);
}

function rotateAccessLogs() {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        return false;
    }
    
    $accessLogFile = $logDir . '/access.log';
    if (!file_exists($accessLogFile)) {
        return false;
    }
    
    $fileSize = filesize($accessLogFile);
    $maxSize = 10485760; // 10MB
    
    if ($fileSize > $maxSize) {
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedFile = $logDir . '/access_' . $timestamp . '.log';
        rename($accessLogFile, $rotatedFile);
        
        // Clean up old logs older than 7 days
        $sevenDaysAgo = time() - (7 * 24 * 60 * 60);
        $files = glob($logDir . '/access_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $sevenDaysAgo) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    return false;
}
?>
