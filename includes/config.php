<?php

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
// Set timezone to Nigeria (Africa/Lagos = GMT+1 / WAT)
date_default_timezone_set('Africa/Lagos');

// ============================================
// PHP UPLOAD CONFIGURATION
// ============================================
// NOTE: Upload limits are now set in php.ini (loaded via `php -c php.ini`)
// The ini_set() calls below are for runtime settings that CAN be changed,
// but upload_max_filesize and post_max_size cannot be changed at runtime
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED
// Just edit the values below directly!
// ============================================

// Database Configuration - SQLite (Single File Database)
// Database file: webdaddy.db (located in database folder)
// No server, no credentials needed - just one portable file!

// WhatsApp Configuration - Now pulled from database
// This will be loaded after database connection
$whatsappNumber = '+2349132672126'; // Default fallback
if (function_exists('getSetting')) {
    $dbWhatsApp = getSetting('whatsapp_number');
    if ($dbWhatsApp) {
        $whatsappNumber = $dbWhatsApp;
    }
}
define('WHATSAPP_NUMBER', $whatsappNumber);
// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED
// Just edit the values below directly!
// ============================================

// SMTP/Email Configuration (for sending emails)
// SECURITY: All credentials loaded from environment variables
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mail.webdaddy.online');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'WebDaddy Empire');

// Validate SMTP credentials are set
if (empty(SMTP_USER) || empty(SMTP_PASS) || empty(SMTP_FROM_EMAIL)) {
    error_log('WARNING: SMTP credentials not configured. Email sending will fail. Set SMTP_USER, SMTP_PASS, and SMTP_FROM_EMAIL environment variables.');
}

// Affiliate Settings
define('AFFILIATE_COOKIE_DAYS', 30);        // How long affiliate cookies last
define('AFFILIATE_COMMISSION_RATE', 0.30);  // 30% commission rate
define('CUSTOMER_DISCOUNT_RATE', 0.20);     // 20% discount for customers using affiliate links

// Site Settings
// Automatically detect the site URL based on the current domain
// Fallback to environment variable or hardcoded value for CLI contexts (cron, email jobs, etc.)
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    // CLI context or no HTTP_HOST - use environment variable or fallback
    $siteUrl = getenv('SITE_URL') ?: 'https://webdaddy.online';
} else {
    // Web context - auto-detect from request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $siteUrl = $protocol . $host;
}
define('SITE_URL', $siteUrl);
define('SITE_NAME', 'WebDaddy Empire');       // Your site name

// Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');
define('MAX_IMAGE_SIZE', 20 * 1024 * 1024); // 20MB for images
define('MAX_VIDEO_SIZE', 100 * 1024 * 1024); // 100MB for videos
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'webm', 'mov', 'avi']);
define('TEMP_FILE_LIFETIME', 86400); // 24 hours in seconds

// Security Settings
define('SESSION_LIFETIME', 3600);

// Error Display (set to false in production)
define('DISPLAY_ERRORS', false); // Disabled for production launch

// PHP Error Reporting
if (DISPLAY_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error_log.txt');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// PAYSTACK CONFIGURATION
// ============================================
// SECURITY: Paystack keys MUST be set as environment variables
$paystackSecretKey = getenv('PAYSTACK_SECRET_KEY');
$paystackPublicKey = getenv('PAYSTACK_PUBLIC_KEY');

if (empty($paystackSecretKey) || empty($paystackPublicKey)) {
    error_log('WARNING: Paystack API keys not configured. Payment processing will fail. Set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY environment variables.');
}

define('PAYSTACK_SECRET_KEY', $paystackSecretKey ?: '');
define('PAYSTACK_PUBLIC_KEY', $paystackPublicKey ?: '');
define('PAYSTACK_MODE', getenv('PAYSTACK_MODE') ?: 'live');
define('PAYMENT_CURRENCY', 'NGN');
define('DOWNLOAD_LINK_EXPIRY_DAYS', 7);
define('MAX_DOWNLOAD_ATTEMPTS', 5);

// SQLite Database Check - verify database file exists
$dbFile = __DIR__ . '/../database/webdaddy.db';
if (!file_exists($dbFile)) {
    die('Database file not found! Please ensure webdaddy.db exists in the database folder.');
}
