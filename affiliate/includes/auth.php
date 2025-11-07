<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/analytics.php';

function requireAffiliate()
{
    if (!isset($_SESSION['affiliate_id']) || !isset($_SESSION['affiliate_role']) || $_SESSION['affiliate_role'] !== 'affiliate') {
        header('Location: /affiliate/login.php');
        exit;
    }
}

function loginAffiliate($emailOrCode, $password)
{
    $db = getDb();
    
    try {
        // Convert affiliate code to uppercase for case-insensitive matching
        $loginInput = strtoupper($emailOrCode);
        
        $stmt = $db->prepare("
            SELECT u.*, a.id as affiliate_id, a.code as affiliate_code, a.status as affiliate_status 
            FROM users u
            INNER JOIN affiliates a ON u.id = a.user_id
            WHERE (u.email = ? OR a.code = ?) 
            AND u.role = 'affiliate' 
            AND u.status = 'active'
        ");
        $stmt->execute([$emailOrCode, $loginInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['affiliate_status'] !== 'active') {
                return false;
            }
            
            session_regenerate_id(true);
            $_SESSION['affiliate_id'] = $user['affiliate_id'];
            $_SESSION['affiliate_user_id'] = $user['id'];
            $_SESSION['affiliate_email'] = $user['email'];
            $_SESSION['affiliate_name'] = $user['name'];
            $_SESSION['affiliate_code'] = $user['affiliate_code'];
            $_SESSION['affiliate_role'] = $user['role'];
            
            logActivity('affiliate_login', 'Affiliate logged in: ' . $user['email'], $user['id']);
            trackAffiliateAction($user['affiliate_id'], 'login');
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Affiliate login error: ' . $e->getMessage());
        return false;
    }
}

function logoutAffiliate()
{
    if (isset($_SESSION['affiliate_user_id'])) {
        logActivity('affiliate_logout', 'Affiliate logged out', $_SESSION['affiliate_user_id']);
    }
    
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function getAffiliateName()
{
    return $_SESSION['affiliate_name'] ?? 'Affiliate';
}

function getAffiliateId()
{
    return $_SESSION['affiliate_id'] ?? null;
}

function getAffiliateInfo()
{
    $affiliateId = getAffiliateId();
    if (!$affiliateId) {
        return null;
    }
    
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, a.* 
            FROM affiliates a
            INNER JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$affiliateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching affiliate info: ' . $e->getMessage());
        return null;
    }
}
