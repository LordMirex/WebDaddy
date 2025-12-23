<?php
/**
 * Customer Session Management
 * 
 * Handles customer session tokens with long-lasting "remember me" functionality
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

define('CUSTOMER_SESSION_DURATION', 365 * 24 * 60 * 60);
define('CUSTOMER_SESSION_COOKIE', 'customer_token');

function createCustomerSession($customerId, $rememberMe = true) {
    $db = getDb();
    
    $token = bin2hex(random_bytes(32));
    
    $duration = $rememberMe ? CUSTOMER_SESSION_DURATION : 24 * 60 * 60;
    $expiresAt = date('Y-m-d H:i:s', time() + $duration);
    
    $deviceFingerprint = md5(
        ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
        ($_SERVER['REMOTE_ADDR'] ?? '') . 
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    );
    
    $stmt = $db->prepare("
        INSERT INTO customer_sessions 
        (customer_id, session_token, device_fingerprint, user_agent, ip_address, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $customerId,
        $token,
        $deviceFingerprint,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $expiresAt
    ]);
    
    // CRITICAL: For iframe environments (Replit proxy), use SameSite=None with Secure
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $secure = $secure || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $sameSite = $secure ? 'None' : 'Lax'; // SameSite=None requires Secure
    
    setcookie(
        CUSTOMER_SESSION_COOKIE,
        $token,
        [
            'expires' => time() + $duration,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite
        ]
    );
    
    return ['success' => true, 'token' => $token];
}

function getCustomerFromSession() {
    $token = $_COOKIE[CUSTOMER_SESSION_COOKIE] ?? null;
    
    if (!$token) {
        return null;
    }
    
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT cs.*, c.* 
        FROM customer_sessions cs
        JOIN customers c ON cs.customer_id = c.id
        WHERE cs.session_token = ?
        AND cs.is_active = 1
        AND cs.expires_at > datetime('now')
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        destroyCustomerSession();
        return null;
    }
    
    if ($session['status'] === 'suspended') {
        destroyCustomerSession();
        return null;
    }
    
    $db->prepare("UPDATE customer_sessions SET last_activity_at = datetime('now') WHERE session_token = ?")
       ->execute([$token]);
    
    return $session;
}

function isCustomerLoggedIn() {
    return getCustomerFromSession() !== null;
}

function requireCustomerLogin($redirectUrl = '/user/login.php') {
    if (!isCustomerLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// getCurrentCustomer() is defined in includes/functions.php - no need to redefine here

function getCurrentCustomerId() {
    $customer = getCurrentCustomer();
    return $customer ? $customer['id'] : null;
}

function destroyCustomerSession() {
    $token = $_COOKIE[CUSTOMER_SESSION_COOKIE] ?? null;
    
    if ($token) {
        $db = getDb();
        $db->prepare("
            UPDATE customer_sessions 
            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'logout'
            WHERE session_token = ?
        ")->execute([$token]);
    }
    
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $sameSite = $secure ? 'None' : 'Lax';
    
    setcookie(CUSTOMER_SESSION_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
}

function revokeAllCustomerSessions($customerId, $reason = 'manual_revoke') {
    $db = getDb();
    $db->prepare("
        UPDATE customer_sessions 
        SET is_active = 0, revoked_at = datetime('now'), revoke_reason = ?
        WHERE customer_id = ? AND is_active = 1
    ")->execute([$reason, $customerId]);
}

function getCustomerActiveSessions($customerId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT id, device_name, user_agent, ip_address, created_at, last_activity_at
        FROM customer_sessions
        WHERE customer_id = ? AND is_active = 1 AND expires_at > datetime('now')
        ORDER BY last_activity_at DESC
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cleanupExpiredSessions() {
    $db = getDb();
    $db->exec("
        UPDATE customer_sessions 
        SET is_active = 0, revoke_reason = 'expired'
        WHERE is_active = 1 AND expires_at < datetime('now')
    ");
}

function logCustomerActivity($customerId, $action, $details = null) {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO customer_activity_log (customer_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $customerId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function getCustomerActivityLog($customerId, $limit = 50) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM customer_activity_log
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$customerId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateCustomerSession() {
    return getCustomerFromSession();
}
