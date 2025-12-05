<?php
/**
 * Security Helper Functions
 * IP Whitelisting, Rate Limiting, and Webhook Security
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Paystack Webhook IP Ranges (Official Paystack IPs)
// These IPs are from Paystack's documentation
define('PAYSTACK_WEBHOOK_IPS', [
    '52.31.139.75',
    '52.49.173.169', 
    '52.214.14.220',
    '52.31.139.75',
    '52.49.173.169',
    '52.214.14.220'
]);

// Rate limiting configuration
define('WEBHOOK_RATE_LIMIT', 100);  // Max requests per window
define('WEBHOOK_RATE_WINDOW', 60);  // Window in seconds (1 minute)
define('FAILED_WEBHOOK_ALERT_THRESHOLD', 5); // Alert after this many failures

/**
 * Check if IP is in Paystack's allowed IP list
 * Returns true if IP whitelisting is disabled or IP is allowed
 */
function isPaystackIP($ip) {
    // If IP whitelisting is disabled in config, allow all
    if (defined('DISABLE_IP_WHITELIST') && DISABLE_IP_WHITELIST === true) {
        return true;
    }
    
    // Get the real client IP
    $clientIP = getClientIP();
    
    // Check if the IP is in the allowed list
    if (in_array($clientIP, PAYSTACK_WEBHOOK_IPS)) {
        return true;
    }
    
    // For development/testing, allow localhost and private IPs
    if (isLocalOrDevIP($clientIP)) {
        return true;
    }
    
    // Log the blocked IP
    error_log("🚫 SECURITY: Blocked webhook request from unauthorized IP: " . $clientIP);
    logSecurityEvent('ip_blocked', $clientIP, 'Webhook request from non-Paystack IP');
    
    return false;
}

/**
 * Get the real client IP address
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Check for proxy headers (in case behind load balancer)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    return $ip;
}

/**
 * Check if IP is local/development IP
 */
function isLocalOrDevIP($ip) {
    // Localhost
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }
    
    // Private IP ranges
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    
    return false;
}

/**
 * Check rate limit for webhook requests
 * Returns true if within limit, false if rate exceeded
 */
function checkWebhookRateLimit() {
    $db = getDb();
    $clientIP = getClientIP();
    $windowStart = time() - WEBHOOK_RATE_WINDOW;
    
    try {
        // Count requests from this IP in the current window
        $stmt = $db->prepare("
            SELECT COUNT(*) as request_count 
            FROM webhook_rate_limits 
            WHERE ip_address = ? AND request_time > ?
        ");
        $stmt->execute([$clientIP, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $requestCount = $result['request_count'] ?? 0;
        
        // Check if over limit
        if ($requestCount >= WEBHOOK_RATE_LIMIT) {
            error_log("🚫 SECURITY: Rate limit exceeded for IP: " . $clientIP . " (Count: $requestCount)");
            logSecurityEvent('rate_limit_exceeded', $clientIP, "Request count: $requestCount in " . WEBHOOK_RATE_WINDOW . " seconds");
            return false;
        }
        
        // Record this request
        $stmt = $db->prepare("
            INSERT INTO webhook_rate_limits (ip_address, request_time) 
            VALUES (?, ?)
        ");
        $stmt->execute([$clientIP, time()]);
        
        // Cleanup old entries periodically (1% chance each request)
        if (rand(1, 100) === 1) {
            cleanupRateLimitTable();
        }
        
        return true;
        
    } catch (Exception $e) {
        // If rate limiting fails, allow request but log error
        error_log("⚠️ SECURITY: Rate limit check failed: " . $e->getMessage());
        return true;
    }
}

/**
 * Cleanup old rate limit entries
 */
function cleanupRateLimitTable() {
    try {
        $db = getDb();
        $cutoff = time() - (WEBHOOK_RATE_WINDOW * 2);
        $stmt = $db->prepare("DELETE FROM webhook_rate_limits WHERE request_time < ?");
        $stmt->execute([$cutoff]);
    } catch (Exception $e) {
        error_log("⚠️ SECURITY: Rate limit cleanup failed: " . $e->getMessage());
    }
}

/**
 * Log security events
 */
function logSecurityEvent($eventType, $ip, $details = null) {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO security_logs (event_type, ip_address, details, user_agent, created_at)
            VALUES (?, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $stmt->execute([
            $eventType,
            $ip,
            $details,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("⚠️ SECURITY: Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Send alert email for failed webhook attempts
 */
function sendWebhookFailureAlert($reason, $data = null) {
    require_once __DIR__ . '/mailer.php';
    
    $db = getDb();
    $clientIP = getClientIP();
    
    try {
        // Count recent failures from this IP
        $stmt = $db->prepare("
            SELECT COUNT(*) as failure_count 
            FROM security_logs 
            WHERE ip_address = ? 
            AND event_type IN ('webhook_failed', 'ip_blocked', 'signature_invalid', 'rate_limit_exceeded')
            AND created_at > datetime('now', '-1 hour', '+1 hour')
        ");
        $stmt->execute([$clientIP]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $failureCount = $result['failure_count'] ?? 0;
        
        // Only send alert if threshold exceeded
        if ($failureCount >= FAILED_WEBHOOK_ALERT_THRESHOLD) {
            // Check if we already sent an alert in the last hour
            $stmt = $db->prepare("
                SELECT COUNT(*) as alert_count 
                FROM security_logs 
                WHERE event_type = 'alert_sent' 
                AND ip_address = ?
                AND created_at > datetime('now', '-1 hour', '+1 hour')
            ");
            $stmt->execute([$clientIP]);
            $alertResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (($alertResult['alert_count'] ?? 0) == 0) {
                // Send alert email
                $subject = '⚠️ WebDaddy Security Alert: Multiple Webhook Failures';
                $content = '<h2 style="color:#dc2626;">Security Alert</h2>' .
                    '<p>Multiple failed webhook attempts detected from IP: <strong>' . htmlspecialchars($clientIP) . '</strong></p>' .
                    '<p><strong>Failure Count:</strong> ' . $failureCount . ' in the last hour</p>' .
                    '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>' .
                    '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>' .
                    ($data ? '<p><strong>Data:</strong> <pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre></p>' : '') .
                    '<p>Please review the security logs in your admin panel.</p>';
                
                $emailHtml = createEmailTemplate($subject, $content, 'Admin');
                $sent = sendEmail(SMTP_USER, $subject, $emailHtml);
                
                if ($sent) {
                    logSecurityEvent('alert_sent', $clientIP, 'Security alert email sent');
                    error_log("✅ SECURITY: Alert email sent for IP: " . $clientIP);
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("⚠️ SECURITY: Failed to send alert: " . $e->getMessage());
    }
}

/**
 * Validate webhook signature
 * Returns true if valid, false if invalid
 */
function validateWebhookSignature($input, $signature) {
    $expectedSignature = hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY);
    
    if ($signature !== $expectedSignature) {
        $clientIP = getClientIP();
        error_log("🚫 SECURITY: Invalid webhook signature from IP: " . $clientIP);
        logSecurityEvent('signature_invalid', $clientIP, 'HMAC signature mismatch');
        sendWebhookFailureAlert('Invalid webhook signature', ['ip' => $clientIP]);
        return false;
    }
    
    return true;
}

/**
 * Full webhook security check
 * Runs all security checks and returns result
 */
function performWebhookSecurityCheck($input, $signature) {
    $result = [
        'passed' => true,
        'reason' => null
    ];
    
    // 1. Check IP whitelist
    if (!isPaystackIP(getClientIP())) {
        $result['passed'] = false;
        $result['reason'] = 'IP not in whitelist';
        return $result;
    }
    
    // 2. Check rate limit
    if (!checkWebhookRateLimit()) {
        $result['passed'] = false;
        $result['reason'] = 'Rate limit exceeded';
        return $result;
    }
    
    // 3. Validate signature
    if (!validateWebhookSignature($input, $signature)) {
        $result['passed'] = false;
        $result['reason'] = 'Invalid signature';
        return $result;
    }
    
    return $result;
}

/**
 * Get webhook security stats for dashboard
 * FIXED: Use consistent timezone handling - compare timestamps directly
 * Records are stored with datetime('now', '+1 hour') for Nigeria time
 */
function getWebhookSecurityStats() {
    $db = getDb();
    
    try {
        // Get start of today in Nigeria time (UTC+1)
        // This ensures we capture all records from today regardless of server timezone
        $todayStart = "datetime('now', '+1 hour', 'start of day')";
        
        // Total webhook requests today
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM payment_logs 
            WHERE event_type = 'webhook_received' 
            AND created_at >= $todayStart
        ");
        $todayWebhooks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Blocked requests today
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM security_logs 
            WHERE event_type IN ('ip_blocked', 'rate_limit_exceeded', 'signature_invalid')
            AND created_at >= $todayStart
        ");
        $blockedToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Successful payments today
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM payment_logs 
            WHERE event_type = 'payment_completed' 
            AND created_at >= $todayStart
        ");
        $successfulPayments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Failed payments today
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM payment_logs 
            WHERE event_type = 'payment_failed' 
            AND created_at >= $todayStart
        ");
        $failedPayments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Recent security events
        $stmt = $db->query("
            SELECT event_type, ip_address, details, created_at 
            FROM security_logs 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'today_webhooks' => $todayWebhooks,
            'blocked_today' => $blockedToday,
            'successful_payments' => $successfulPayments,
            'failed_payments' => $failedPayments,
            'recent_events' => $recentEvents
        ];
        
    } catch (Exception $e) {
        error_log("⚠️ SECURITY: Failed to get stats: " . $e->getMessage());
        return [
            'today_webhooks' => 0,
            'blocked_today' => 0,
            'successful_payments' => 0,
            'failed_payments' => 0,
            'recent_events' => []
        ];
    }
}
