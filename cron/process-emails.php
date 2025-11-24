<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_queue.php';

// Process pending emails
processEmailQueue();

echo "Email queue processed at " . date('Y-m-d H:i:s') . "\n";
