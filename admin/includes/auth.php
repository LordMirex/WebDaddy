<?php

function requireAdmin()
{
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        header('Location: /admin/login.php');
        exit;
    }
}

function loginAdmin($email, $password)
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
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_role'] = $user['role'];
                
                logActivity('admin_login', 'Admin logged in: ' . $user['email'], $user['id']);
                
                return true;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

function logoutAdmin()
{
    if (isset($_SESSION['admin_id'])) {
        logActivity('admin_logout', 'Admin logged out', $_SESSION['admin_id']);
    }
    
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
