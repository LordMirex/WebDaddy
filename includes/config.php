<?php

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
// Set timezone to Nigeria (Africa/Lagos = GMT+1 / WAT)
date_default_timezone_set('Africa/Lagos');

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
// Update these with your actual SMTP credentials
define('SMTP_HOST', 'mail.webdaddy.online');           // SMTP server
define('SMTP_PORT', 465);                                  // SMTP port (465 for SSL, 587 for TLS)
define('SMTP_SECURE', 'ssl');                              // SSL or TLS
define('SMTP_USER', 'admin@webdaddy.online');        // SMTP username
define('SMTP_PASS', 'ItuZq%kF%5oE');                       // SMTP password
define('SMTP_FROM_EMAIL', 'admin@webdaddy.online');  // From email address
define('SMTP_FROM_NAME', 'WebDaddy Empire');               // From name

// Affiliate Settings
define('AFFILIATE_COOKIE_DAYS', 30);        // How long affiliate cookies last
define('AFFILIATE_COMMISSION_RATE', 0.30);  // 30% commission rate
define('CUSTOMER_DISCOUNT_RATE', 0.20);     // 20% discount for customers using affiliate links

// Site Settings
define('SITE_URL', 'https://webdaddy.online');  // Your site URL
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
define('DISPLAY_ERRORS', false);

// PHP Error Reporting
if (DISPLAY_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// SQLite Database Check - verify database file exists
$dbFile = __DIR__ . '/../database/webdaddy.db';
if (!file_exists($dbFile)) {
    die('Database file not found! Please ensure webdaddy.db exists in the database folder.');
}
