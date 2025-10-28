<?php

// ============================================
// SIMPLE CONFIGURATION - ALL HARDCODED
// Just edit the values below directly!
// ============================================

// Database Configuration - PostgreSQL
define('DB_HOST', 'db');             // Database server ('db' for Docker, 'localhost' for local install)
define('DB_NAME', 'template_store'); // Database name
define('DB_USER', 'postgres');       // Database username
define('DB_PASS', 'postgres');       // Database password
define('DB_PORT', 5432);             // Database port
define('DB_SSLMODE', 'prefer');      // SSL mode (prefer, require, disable)

// WhatsApp Configuration
define('WHATSAPP_NUMBER', '+2348012345678'); // Your WhatsApp number with country code

// SMTP/Email Configuration (for sending emails)
define('SMTP_HOST', 'smtp.example.com');           // SMTP server
define('SMTP_PORT', 587);                          // SMTP port
define('SMTP_USER', 'noreply@example.com');        // SMTP username
define('SMTP_PASS', 'your_smtp_password');         // SMTP password
define('SMTP_FROM_EMAIL', 'noreply@example.com');  // From email address
define('SMTP_FROM_NAME', 'Template Marketplace');  // From name

// Affiliate Settings
define('AFFILIATE_COOKIE_DAYS', 30);        // How long affiliate cookies last
define('AFFILIATE_COMMISSION_RATE', 0.30);  // 30% commission rate

// Site Settings
define('SITE_URL', 'http://localhost:8080');  // Your site URL (change to your domain)
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

// Early sanity check
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_PORT')) {
    die('Database configuration is missing or incomplete. Please check includes/config.php.');
}
