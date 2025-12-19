<?php
/**
 * API Rate Limiter
 * 
 * Provides rate limiting for API endpoints to prevent abuse
 * Uses database-backed storage for persistent rate limiting across requests
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Check if a rate limit has been exceeded
 * 
 * @param string $identifier Unique identifier (email, IP, user_id, etc.)
 * @param string $action Action being rate limited (e.g., 'check_email', 'login')
 * @param int $limit Maximum allowed requests
 * @param int $windowSeconds Time window in seconds
 * @return bool True if allowed, false if rate limited
 */
function checkRateLimit($identifier, $action, $limit, $windowSeconds) {
    $db = getDb();
    $key = 'rate_' . $action . '_' . md5($identifier);
    
    // Clean up expired entries periodically (1% chance per request)
    if (rand(1, 100) === 1) {
        cleanupExpiredRateLimits();
    }
    
    // Check current count
    $stmt = $db->prepare("SELECT count, expires_at FROM rate_limits WHERE rate_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $now = time();
    
    if ($row) {
        $expiresAt = strtotime($row['expires_at']);
        
        // If expired, reset
        if ($expiresAt <= $now) {
            $newExpires = date('Y-m-d H:i:s', $now + $windowSeconds);
            $db->prepare("UPDATE rate_limits SET count = 1, expires_at = ? WHERE rate_key = ?")
               ->execute([$newExpires, $key]);
            return true;
        }
        
        // Check if limit exceeded
        if ($row['count'] >= $limit) {
            return false;
        }
        
        // Increment count
        $db->prepare("UPDATE rate_limits SET count = count + 1 WHERE rate_key = ?")
           ->execute([$key]);
        return true;
    }
    
    // Create new rate limit entry
    $expires = date('Y-m-d H:i:s', $now + $windowSeconds);
    $stmt = $db->prepare("INSERT INTO rate_limits (rate_key, count, expires_at) VALUES (?, 1, ?)");
    try {
        $stmt->execute([$key, $expires]);
    } catch (PDOException $e) {
        // Handle race condition - key might have been created by another request
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            return checkRateLimit($identifier, $action, $limit, $windowSeconds);
        }
        throw $e;
    }
    
    return true;
}

/**
 * Get remaining requests before rate limit
 * 
 * @param string $identifier Unique identifier
 * @param string $action Action being checked
 * @param int $limit Maximum allowed requests
 * @return int Remaining requests
 */
function getRateLimitRemaining($identifier, $action, $limit) {
    $db = getDb();
    $key = 'rate_' . $action . '_' . md5($identifier);
    
    $stmt = $db->prepare("SELECT count, expires_at FROM rate_limits WHERE rate_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return $limit;
    }
    
    // Check if expired
    if (strtotime($row['expires_at']) <= time()) {
        return $limit;
    }
    
    return max(0, $limit - $row['count']);
}

/**
 * Reset rate limit for a specific identifier and action
 * 
 * @param string $identifier Unique identifier
 * @param string $action Action to reset
 */
function resetRateLimit($identifier, $action) {
    $db = getDb();
    $key = 'rate_' . $action . '_' . md5($identifier);
    $db->prepare("DELETE FROM rate_limits WHERE rate_key = ?")->execute([$key]);
}

/**
 * Clean up expired rate limit entries
 */
function cleanupExpiredRateLimits() {
    $db = getDb();
    $db->exec("DELETE FROM rate_limits WHERE expires_at < datetime('now')");
}

/**
 * Check rate limit and return JSON error if exceeded
 * 
 * @param string $identifier Unique identifier
 * @param string $action Action being rate limited
 * @param int $limit Maximum allowed requests
 * @param int $windowSeconds Time window in seconds
 * @param string $message Custom error message
 * @return bool True if allowed, exits with error if rate limited
 */
function enforceRateLimit($identifier, $action, $limit, $windowSeconds, $message = null) {
    if (!checkRateLimit($identifier, $action, $limit, $windowSeconds)) {
        header('Content-Type: application/json');
        http_response_code(429);
        
        $errorMessage = $message ?: 'Too many requests. Please try again later.';
        
        echo json_encode([
            'success' => false,
            'error' => $errorMessage,
            'retry_after' => $windowSeconds
        ]);
        exit;
    }
    return true;
}

/**
 * Get the client's IP address
 * 
 * @return string Client IP address
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Check for proxy headers (be careful in production)
    $proxyHeaders = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP'
    ];
    
    foreach ($proxyHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            break;
        }
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
}

/**
 * Login rate limiting - 5 attempts per 15 minutes per email
 * 
 * @param string $email Email address
 * @return bool True if allowed
 */
function checkLoginRateLimit($email) {
    $limit = defined('API_RATE_LIMIT_LOGIN') ? API_RATE_LIMIT_LOGIN : 5;
    return checkRateLimit($email, 'login', $limit, 900); // 15 minutes
}

/**
 * OTP request rate limiting - 1 request per minute per email (strict limit for instant delivery)
 * 
 * @param string $email Email address
 * @return bool True if allowed
 */
function checkOTPRateLimit($email) {
    $limit = defined('CUSTOMER_OTP_RATE_LIMIT_MINUTE') ? CUSTOMER_OTP_RATE_LIMIT_MINUTE : 1;
    $window = defined('API_RATE_LIMIT_WINDOW_MINUTE') ? API_RATE_LIMIT_WINDOW_MINUTE : 60;
    return checkRateLimit($email, 'otp_request', $limit, $window);
}

/**
 * Check email rate limiting - 10 requests per minute per IP
 * 
 * @param string $ip IP address
 * @return bool True if allowed
 */
function checkEmailCheckRateLimit($ip) {
    $limit = defined('API_RATE_LIMIT_CHECK_EMAIL') ? API_RATE_LIMIT_CHECK_EMAIL : 10;
    $window = defined('API_RATE_LIMIT_WINDOW_MINUTE') ? API_RATE_LIMIT_WINDOW_MINUTE : 60;
    return checkRateLimit($ip, 'check_email', $limit, $window);
}

/**
 * Record failed login attempt for rate limiting
 * 
 * @param string $email Email address
 */
function recordFailedLogin($email) {
    logSecurityEvent('failed_login', [
        'email' => $email,
        'ip' => getClientIP()
    ]);
}

/**
 * Log security-relevant events
 * 
 * @param string $event Event type
 * @param array $data Event data
 */
function logSecurityEvent($event, $data = []) {
    $logData = [
        'event' => $event,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    $logData = array_merge($logData, $data);
    
    // Remove sensitive data before logging
    unset($logData['password']);
    unset($logData['otp_code']);
    
    error_log("SECURITY: " . json_encode($logData));
}
