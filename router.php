<?php
/**
 * Router for PHP Built-in Development Server
 * 
 * This file handles URL routing for the PHP development server
 * since it doesn't support .htaccess files.
 * 
 * Usage: php -S 0.0.0.0:5000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// Remove query string for routing decisions
$path = strtok($uri, '?');

// Serve static files directly
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false; // Serve the requested resource as-is
}

// Block access to sensitive directories
if (preg_match('#^/(includes|database|uploads/private)/#', $path)) {
    $_SERVER['SCRIPT_NAME'] = '/403.php';
    require __DIR__ . '/403.php';
    exit;
}

// Robots.txt routing
if ($path === '/robots.txt' || $path === '/robots') {
    $_SERVER['SCRIPT_NAME'] = '/robots.php';
    require __DIR__ . '/robots.php';
    exit;
}

// Sitemap routing
if ($path === '/sitemap.xml' || $path === '/sitemap') {
    $_SERVER['SCRIPT_NAME'] = '/sitemap.php';
    require __DIR__ . '/sitemap.php';
    exit;
}

// Tool detail page routing: /tool/slug-name → index.php?tool=slug-name
// Tools open as modals on the index page, not a separate page
if (preg_match('#^/tool/([a-zA-Z0-9\-_]+)/?$#i', $path, $matches)) {
    $_GET['tool'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    exit;
}

// NOTE: ?tool=name parameter is handled by index.php for modal preview
// Do NOT redirect it to tool.php - let it stay on index.php

// Template slug routing: /slug-name → template.php?slug=slug-name
// Must not match admin, affiliate, assets, api, uploads, mailer directories
// Must match pattern: /lowercase-slug-with-hyphens
if (preg_match('#^/([a-z0-9_-]+)/?$#i', $path, $matches)) {
    // Exclude specific directories/files
    $excluded = ['admin', 'affiliate', 'user', 'assets', 'api', 'uploads', 'mailer', 'index', 'template', 'sitemap', 'tool', 'robots'];
    $slug = $matches[1];
    
    if (!in_array(strtolower($slug), $excluded) && !file_exists(__DIR__ . '/' . $slug . '.php')) {
        // Route to template.php with slug parameter
        $_GET['slug'] = $slug;
        $_SERVER['SCRIPT_NAME'] = '/template.php';
        require __DIR__ . '/template.php';
        exit;
    }
}

// Fallback: 404 for unmatched routes, index for root
if ($path === '/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    exit;
} elseif (!file_exists(__DIR__ . $path)) {
    $_SERVER['SCRIPT_NAME'] = '/404.php';
    require __DIR__ . '/404.php';
    exit;
}

// For PHP files, serve them directly
if (preg_match('/\.php$/', $path)) {
    require __DIR__ . $path;
    exit;
}

// If we get here, return false to let PHP serve the file
return false;
