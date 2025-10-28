<?php

function startSecureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        $sessionPath = '/tmp/php_sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }
        
        session_save_path($sessionPath);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 86400);
        
        session_start();
    }
}

function handleAffiliateTracking()
{
    if (isset($_GET['aff']) && !empty($_GET['aff'])) {
        $affiliateCode = sanitizeInput($_GET['aff']);
        
        $_SESSION['affiliate_code'] = $affiliateCode;
        
        setcookie(
            'affiliate_code',
            $affiliateCode,
            time() + (AFFILIATE_COOKIE_DAYS * 24 * 60 * 60),
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        
        incrementAffiliateClick($affiliateCode);
    }
    
    if (empty($_SESSION['affiliate_code']) && isset($_COOKIE['affiliate_code'])) {
        $_SESSION['affiliate_code'] = $_COOKIE['affiliate_code'];
    }
}

function getAffiliateCode()
{
    return $_SESSION['affiliate_code'] ?? $_COOKIE['affiliate_code'] ?? null;
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
