<?php

// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED
// Just edit the values below directly!
// ============================================

// Database Configuration - SQLite (Single File Database)
// Database file: webdaddy.db (located in root directory)
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
define('SITE_URL', 'http://webdaddy.online');  // Your site URL
define('SITE_NAME', 'WebDaddy Empire');       // Your site name

// Default Admin Credentials (stored as plain text - no hashing!)
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASSWORD', 'admin123');         // Plain text password - change this!
define('ADMIN_NAME', 'Admin User');
define('ADMIN_PHONE', '08012345678');

// Security Settings
define('SESSION_LIFETIME', 3600);

// Error Display (set to false in production)
define('DISPLAY_ERRORS', true);

// PHP Error Reporting
if (DISPLAY_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// SQLite Database Check - verify database file exists
$dbFile = __DIR__ . '/../webdaddy.db';
if (!file_exists($dbFile)) {
    die('Database file not found! Please ensure webdaddy.db exists in the root directory.');
}
