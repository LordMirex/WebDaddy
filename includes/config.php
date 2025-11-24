<?php

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
date_default_timezone_set('Africa/Lagos');

// ============================================
// PHP UPLOAD CONFIGURATION
// ============================================
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED HERE
// EDIT VALUES DIRECTLY - NO ENVIRONMENT VARIABLES NEEDED
// ============================================

// SMTP/Email Configuration - EDIT THESE WITH YOUR DETAILS
define('SMTP_HOST', 'mail.webdaddy.online');      // Your mail server
define('SMTP_PORT', 465);                         // Email port
define('SMTP_SECURE', 'ssl');                     // ssl or tls
define('SMTP_USER', 'admin@webdaddy.online');     // Your email
define('SMTP_PASS', 'ItuZq%kF%5oE');              // Your email password
define('SMTP_FROM_EMAIL', 'admin@webdaddy.online');
define('SMTP_FROM_NAME', 'WebDaddy Empire');

// PAYSTACK CONFIGURATION - YOUR LIVE API KEYS
define('PAYSTACK_SECRET_KEY', 'sk_live_7a98b3f6c784370454b96340b08836d518405b55');
define('PAYSTACK_PUBLIC_KEY', 'pk_live_3d212bae617ffaedeaa3319351b283356498824e');
define('PAYSTACK_MODE', 'live');

// WhatsApp Configuration
$whatsappNumber = '+2349132672126'; // Your WhatsApp number
if (function_exists('getSetting')) {
    $dbWhatsApp = getSetting('whatsapp_number');
    if ($dbWhatsApp) {
        $whatsappNumber = $dbWhatsApp;
    }
}
define('WHATSAPP_NUMBER', $whatsappNumber);

// Affiliate Settings
define('AFFILIATE_COOKIE_DAYS', 30);
define('AFFILIATE_COMMISSION_RATE', 0.30);
define('CUSTOMER_DISCOUNT_RATE', 0.20);

// Site Settings
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $siteUrl = 'https://webdaddy.online';
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $siteUrl = $protocol . $host;
}
define('SITE_URL', $siteUrl);
define('SITE_NAME', 'WebDaddy Empire');

// Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');
define('MAX_IMAGE_SIZE', 20 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'webm', 'mov', 'avi']);
define('TEMP_FILE_LIFETIME', 86400);

// Security Settings
define('SESSION_LIFETIME', 3600);
define('DISPLAY_ERRORS', false);

// Payment Settings
define('PAYMENT_CURRENCY', 'NGN');
define('DOWNLOAD_LINK_EXPIRY_DAYS', 7);
define('MAX_DOWNLOAD_ATTEMPTS', 5);

// ============================================
// DATABASE & ERROR REPORTING
// ============================================
if (DISPLAY_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error_log.txt');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Verify database file exists
$dbFile = __DIR__ . '/../database/webdaddy.db';
if (!file_exists($dbFile)) {
    die('Database file not found! Please ensure webdaddy.db exists in the database folder.');
}
