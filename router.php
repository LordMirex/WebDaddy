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
    http_response_code(403);
    echo '403 Forbidden';
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

// Tool detail page routing: /tool/slug-name → tool.php?slug=slug-name
if (preg_match('#^/tool/([a-z0-9\-_]+)/?$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/tool.php';
    require __DIR__ . '/tool.php';
    exit;
}

// Legacy tool query parameter: ?tool=name
if (!empty($_GET['tool'])) {
    $_GET['slug'] = $_GET['tool'];
    $_SERVER['SCRIPT_NAME'] = '/tool.php';
    require __DIR__ . '/tool.php';
    exit;
}

// Template slug routing: /slug-name → template.php?slug=slug-name
// Must not match admin, affiliate, assets, api, uploads, mailer directories
// Must match pattern: /lowercase-slug-with-hyphens
if (preg_match('#^/([a-z0-9\-_]+)/?$#i', $path, $matches)) {
    // Exclude specific directories/files
    $excluded = ['admin', 'affiliate', 'assets', 'api', 'uploads', 'mailer', 'index', 'template', 'sitemap', 'tool', 'robots'];
    $slug = $matches[1];
    
    if (!in_array(strtolower($slug), $excluded) && !file_exists(__DIR__ . '/' . $slug . '.php')) {
        // Route to template.php with slug parameter
        $_GET['slug'] = $slug;
        $_SERVER['SCRIPT_NAME'] = '/template.php';
        require __DIR__ . '/template.php';
        exit;
    }
}

// Fallback to index.php for root and unmatched routes
if ($path === '/' || !file_exists(__DIR__ . $path)) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    exit;
}

// For PHP files, serve them directly
if (preg_match('/\.php$/', $path)) {
    require __DIR__ . $path;
    exit;
}

// If we get here, return false to let PHP serve the file
return false;
