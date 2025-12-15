<?php
/**
 * Customer Logout
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_session.php';

// Destroy the customer session (handles token revocation and cookie clearing)
destroyCustomerSession();

// Clear any session variables
unset($_SESSION['customer_id']);
unset($_SESSION['customer_session_token']);

header('Location: /user/login.php?logged_out=1');
exit;
