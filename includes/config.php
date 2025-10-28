<?php

// Database Configuration - Uses environment variables for Replit
// Falls back to local config if not running on Replit
if (getenv('DATABASE_URL')) {
    // Replit environment - PostgreSQL
    define('DB_HOST', getenv('PGHOST'));
    define('DB_NAME', getenv('PGDATABASE'));
    define('DB_USER', getenv('PGUSER'));
    define('DB_PASS', getenv('PGPASSWORD'));
} else {
    // Local environment - MySQL/PostgreSQL
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'template_store');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// WhatsApp Configuration
define('WHATSAPP_NUMBER', '+2348012345678');

// SMTP/Email Configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@example.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM_EMAIL', 'noreply@example.com');
define('SMTP_FROM_NAME', 'Template Marketplace');

// Affiliate Settings
define('AFFILIATE_COOKIE_DAYS', 30);
define('AFFILIATE_COMMISSION_RATE', 0.30);

// Site Settings
$replitDomain = getenv('REPLIT_DEV_DOMAIN');
if ($replitDomain) {
    define('SITE_URL', 'https://' . $replitDomain);
} else {
    define('SITE_URL', 'http://localhost:5000');
}
define('SITE_NAME', 'WebDaddy Empire');

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
