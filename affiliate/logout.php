<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
logoutAffiliate();

header('Location: /affiliate/login.php');
exit;
