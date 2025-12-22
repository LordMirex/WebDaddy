<?php

function isAdmin()
{
    // Check token-based auth (primary)
    if (isset($_COOKIE['admin_token'])) {
        $db = getDb();
        $stmt = $db->prepare("SELECT id, role FROM users WHERE admin_login_token = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$_COOKIE['admin_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_role'] = 'admin';
            return true;
        }
    }
    
    // Fallback to session
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function verifyAdminPassword($email, $password)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check both hashed and plain text passwords for compatibility
            $passwordMatch = false;
            
            // Try plain text comparison first
            if ($user['password_hash'] === $password) {
                $passwordMatch = true;
            }
            // Fallback to hash verification if it's a hashed password
            elseif (password_verify($password, $user['password_hash'])) {
                $passwordMatch = true;
            }
            
            if ($passwordMatch) {
                return $user;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

function loginAdmin($email, $password)
{
    // This now requires token verification - see admin/login.php
    // This function kept for backwards compatibility
    $user = verifyAdminPassword($email, $password);
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_role'] = $user['role'];
        
        logActivity('admin_login', 'Admin logged in: ' . $user['email'], $user['id']);
        
        return true;
    }
    return false;
}

function logoutAdmin()
{
    if (isset($_SESSION['admin_id'])) {
        logActivity('admin_logout', 'Admin logged out', $_SESSION['admin_id']);
    }
    
    // Clear token from database
    if (isset($_COOKIE['admin_token'])) {
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET admin_login_token = NULL WHERE admin_login_token = ?");
        $stmt->execute([$_COOKIE['admin_token']]);
    }
    
    // Clear cookie
    setcookie('admin_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function getAdminName()
{
    return $_SESSION['admin_name'] ?? 'Admin';
}

function getAdminId()
{
    return $_SESSION['admin_id'] ?? null;
}
