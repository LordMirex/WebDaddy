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
// ERROR LOGGING CONFIGURATION
// ============================================
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
$errorLogPath = $logDir . '/error.log';
ini_set('error_log', $errorLogPath);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Ensure all error_log() calls write to the error.log file
if (!function_exists('logError')) {
    function logError($message, $context = null) {
        $errorLogPath = __DIR__ . '/../logs/error.log';
        $timestamp = date('[d-M-Y H:i:s ' . date_default_timezone_get() . ']');
        $contextStr = $context ? ' [' . json_encode($context) . ']' : '';
        error_log($timestamp . ' ' . $message . $contextStr);
    }
}

// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED HERE
// EDIT VALUES DIRECTLY - NO ENVIRONMENT VARIABLES NEEDED
// ============================================

// SMTP/Email Configuration - FOR ADMIN EMAILS ONLY
// Admin emails stay on SMTP (admin@webdaddy.online)
define('SMTP_HOST', 'mail.webdaddy.online');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'admin@webdaddy.online');
define('SMTP_PASS', 'ItuZq%kF%5oE');
define('SMTP_FROM_EMAIL', 'admin@webdaddy.online');
define('SMTP_FROM_NAME', 'WebDaddy Empire');

// PAYSTACK CONFIGURATION - LIVE MODE
// HARDCODED LIVE KEYS - USER REQUESTED
define('PAYSTACK_PUBLIC_KEY', 'pk_live_3d212bae617ffaedeaa3319351b283356498824e');
define('PAYSTACK_SECRET_KEY', 'sk_live_7a98b3f6c784370454b96340b08836d518405b55');
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
define('AFFILIATE_COMMISSION_RATE', 0.30); // 30% commission for affiliates (of final paid amount)
define('CUSTOMER_DISCOUNT_RATE', 0.20);     // 20% discount for customers using referral/affiliate codes

// User Referral Settings (customers referring other customers)
define('USER_REFERRAL_COMMISSION_RATE', 0.30); // 30% commission for user referrers (of final paid amount)
define('USER_REFERRAL_DISCOUNT_RATE', 0.20);   // 20% discount for referred users

// Site Settings - webdaddy.online at root level in public_html
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $siteUrl = 'https://webdaddy.online';
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $siteUrl = $protocol . $host;
}

define('SITE_URL', $siteUrl);
define('SITE_NAME', 'WebDaddy Empire');
define('SUPPORT_EMAIL', 'support@webdaddy.online');

// Upload Settings - All at root: /uploads, /assets, /blog
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');
define('ASSETS_URL', SITE_URL . '/assets');
define('BLOG_URL', SITE_URL . '/blog');
define('MAX_IMAGE_SIZE', 20 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024); // 500MB for video uploads
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'webm', 'mov', 'avi']);
define('TEMP_FILE_LIFETIME', 86400);

// Security Settings
define('SESSION_LIFETIME', 3600);
define('DISPLAY_ERRORS', false);

// Webhook Security Settings
define('DISABLE_IP_WHITELIST', true);  // Set to false in production to enable IP whitelisting
define('WEBHOOK_RATE_LIMIT', 100);     // Max webhook requests per minute per IP
define('FAILED_WEBHOOK_ALERT_THRESHOLD', 5); // Send alert after this many failures

// Payment Settings
define('PAYMENT_CURRENCY', 'NGN');
define('DOWNLOAD_LINK_EXPIRY_DAYS', 30);
define('MAX_DOWNLOAD_ATTEMPTS', 10);

// Delivery Settings (Phase 3)
define('DELIVERY_RETRY_MAX_ATTEMPTS', 3);
define('DELIVERY_RETRY_BASE_DELAY_SECONDS', 60);
define('TEMPLATE_DELIVERY_REMINDER_HOURS', 24);

// ============================================
// MAILTRAP EMAIL CONFIGURATION (For User Emails)
// ============================================
// Mailtrap API for fast, reliable email delivery to users
// All user-facing emails (OTP, notifications, deliveries) go through Mailtrap
// Using hello@ domain-verified sender for better inbox delivery
define('MAILTRAP_API_KEY', '7c7fe934790facba06a11568cfdead8a');
define('MAILTRAP_FROM_EMAIL', 'hello@webdaddy.online');
define('MAILTRAP_FROM_NAME', 'WebDaddy Empire');
define('MAILTRAP_API_HOST', 'send.api.mailtrap.io');
// Webhook URL: https://webdaddy.online/api/mailtrap-webhook.php

// ============================================
// GMAIL SMTP CONFIGURATION (For User OTP Emails)
// ============================================
// Gmail SMTP for instant OTP delivery to users
// OTP emails only - all other user emails go through Mailtrap API



define('GMAIL_OTP_USER', 'webdaddyempire@gmail.com');
define('GMAIL_OTP_APP_PASSWORD', 'dmkx gqts vwao bqth');

// Customer Session Settings
define('CUSTOMER_SESSION_LIFETIME_DAYS', 365);  // 12-month sessions
define('CUSTOMER_OTP_EXPIRY_MINUTES', 10);
define('CUSTOMER_OTP_MAX_ATTEMPTS', 5);
define('CUSTOMER_OTP_RATE_LIMIT_HOUR', 3);

// API Rate Limiting Settings
define('API_RATE_LIMIT_CHECK_EMAIL', 10);        // 10 requests per minute per IP
define('API_RATE_LIMIT_REQUEST_OTP', 3);         // 3 OTP requests per hour per email
define('API_RATE_LIMIT_LOGIN', 5);               // 5 login attempts per 15 min per email
define('API_RATE_LIMIT_PROFILE', 30);            // 30 profile requests per minute
define('API_RATE_LIMIT_WINDOW_MINUTE', 60);      // 1 minute window
define('API_RATE_LIMIT_WINDOW_HOUR', 3600);      // 1 hour window

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
