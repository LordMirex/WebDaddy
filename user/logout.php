<?php
/**
 * Customer Logout
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_session.php';

$sessionToken = $_COOKIE['customer_session'] ?? $_SESSION['customer_session_token'] ?? null;

if ($sessionToken) {
    revokeCustomerSession($sessionToken, 'User logout');
}

unset($_SESSION['customer_id']);
unset($_SESSION['customer_session_token']);

setcookie('customer_session', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

header('Location: /user/login.php?logged_out=1');
exit;
