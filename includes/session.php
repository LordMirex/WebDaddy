<?php

function startSecureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        // On shared hosting, it's safer to use the default session path 
        // unless we have a specific private directory. /tmp/php_sessions 
        // might not be writable or shared across users.
        $sessionPath = __DIR__ . '/../cache/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0755, true);
        }
        
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }
        
        // Detect if running in HTTPS (for iframe/proxy compatibility)
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $isSecure = $isSecure || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        // Use SameSite=None for iframe compatibility (requires Secure)
        // Falls back to Lax on HTTP for local development
        ini_set('session.cookie_samesite', $isSecure ? 'None' : 'Lax');
        ini_set('session.cookie_secure', $isSecure ? 1 : 0);
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME ?: 86400);
        
        session_start();
    }
}

function handleAffiliateTracking()
{
    if (isset($_GET['aff']) && !empty($_GET['aff'])) {
        $affiliateCode = function_exists('sanitizeInput') ? sanitizeInput($_GET['aff']) : trim($_GET['aff']);
        
        $_SESSION['affiliate_code'] = $affiliateCode;
        
        setcookie(
            'affiliate_code',
            $affiliateCode,
            time() + (defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        
        if (function_exists('incrementAffiliateClick')) {
            incrementAffiliateClick($affiliateCode);
        }
    }
    
    if (empty($_SESSION['affiliate_code']) && isset($_COOKIE['affiliate_code'])) {
        $_SESSION['affiliate_code'] = $_COOKIE['affiliate_code'];
    }
}

function getAffiliateCode()
{
    return $_SESSION['affiliate_code'] ?? $_COOKIE['affiliate_code'] ?? null;
}

function handleUserReferralTracking()
{
    if (isset($_GET['ref']) && !empty($_GET['ref'])) {
        $referralCode = function_exists('sanitizeInput') ? sanitizeInput($_GET['ref']) : trim($_GET['ref']);
        
        $_SESSION['referral_code'] = $referralCode;
        
        setcookie(
            'referral_code',
            $referralCode,
            time() + (defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        
        if (function_exists('incrementUserReferralClick')) {
            incrementUserReferralClick($referralCode);
        }
    }
    
    if (empty($_SESSION['referral_code']) && isset($_COOKIE['referral_code'])) {
        $_SESSION['referral_code'] = $_COOKIE['referral_code'];
    }
}

function getUserReferralCode()
{
    return $_SESSION['referral_code'] ?? $_COOKIE['referral_code'] ?? null;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin($redirectUrl = '/admin/login.php')
{
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

function requireRole($role)
{
    if (!isLoggedIn() || $_SESSION['user_role'] !== $role) {
        header('Location: /');
        exit;
    }
}

function loginUser($userId, $userName, $userEmail, $userRole)
{
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;
    $_SESSION['user_role'] = $userRole;
    $_SESSION['login_time'] = time();
}

function logoutUser()
{
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole()
{
    return $_SESSION['user_role'] ?? null;
}

function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken()
{
    return $_SESSION['csrf_token'] ?? generateCsrfToken();
}

function validateCsrfToken($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfTokenField()
{
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function requireCsrfToken()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

function trackLoginAttempt($identifier, $type = 'admin')
{
    $key = 'login_attempts_' . $type . '_' . md5($identifier);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];
    
    $attempts['count']++;
    $attempts['last_attempt'] = time();
    
    if (!isset($attempts['first_attempt'])) {
        $attempts['first_attempt'] = time();
    }
    
    $_SESSION[$key] = $attempts;
    
    return $attempts;
}

function isRateLimited($identifier, $type = 'admin', $maxAttempts = 5, $lockoutTime = 900)
{
    $key = 'login_attempts_' . $type . '_' . md5($identifier);
    $attempts = $_SESSION[$key] ?? null;
    
    if (!$attempts) {
        return false;
    }
    
    $timeSinceFirst = time() - $attempts['first_attempt'];
    
    if ($timeSinceFirst > $lockoutTime) {
        unset($_SESSION[$key]);
        return false;
    }
    
    return $attempts['count'] >= $maxAttempts;
}

function clearLoginAttempts($identifier, $type = 'admin')
{
    $key = 'login_attempts_' . $type . '_' . md5($identifier);
    unset($_SESSION[$key]);
}

function getRateLimitMessage($identifier, $type = 'admin', $lockoutTime = 900)
{
    $key = 'login_attempts_' . $type . '_' . md5($identifier);
    $attempts = $_SESSION[$key] ?? null;
    
    if (!$attempts) {
        return '';
    }
    
    $timeRemaining = $lockoutTime - (time() - $attempts['first_attempt']);
    $minutes = ceil($timeRemaining / 60);
    
    return "Too many failed login attempts. Please try again in {$minutes} minute(s).";
}

function optimizeSessionWrite()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

register_shutdown_function(function() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
});
