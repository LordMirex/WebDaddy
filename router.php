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
$path = parse_url($uri, PHP_URL_PATH);

// Serve static files directly
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false;
}

// Admin routing: /admin → admin/index.php
if ($path === '/admin' || $path === '/admin/') {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    require __DIR__ . '/admin/index.php';
    exit;
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

// Blog routing: /blog/ → blog/index.php
if ($path === '/blog' || $path === '/blog/') {
    $_SERVER['SCRIPT_NAME'] = '/blog/index.php';
    require __DIR__ . '/blog/index.php';
    exit;
}

// Blog category routing: /blog/category/slug/ → blog/category.php?category_slug=slug
if (preg_match('#^/blog/category/([a-zA-Z0-9\-_]+)/?$#i', $path, $matches)) {
    $_GET['category_slug'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/blog/category.php';
    require __DIR__ . '/blog/category.php';
    exit;
}

// Blog post routing: /blog/slug/ → blog/post.php?slug=slug
if (preg_match('#^/blog/([a-zA-Z0-9\-_]+)/?$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/blog/post.php';
    require __DIR__ . '/blog/post.php';
    exit;
}

// Tool detail page routing: /tool/slug-name → index.php?tool=slug-name
if (preg_match('#^/tool/([a-zA-Z0-9\-_]+)/?$#i', $path, $matches)) {
    $_GET['tool'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    exit;
}

// Template slug routing: /slug-name → template.php?slug=slug-name
// Must not match admin, affiliate, assets, api, uploads, mailer, user directories
if (preg_match('#^/([a-z0-9_-]+)/?$#i', $path, $matches)) {
    $excluded = ['admin', 'affiliate', 'user', 'assets', 'api', 'uploads', 'mailer', 'index', 'template', 'sitemap', 'tool', 'robots'];
    $slug = $matches[1];
    
    if (!in_array(strtolower($slug), $excluded) && !file_exists(__DIR__ . '/' . $slug . '.php')) {
        $_GET['slug'] = $slug;
        $_SERVER['SCRIPT_NAME'] = '/template.php';
        require __DIR__ . '/template.php';
        exit;
    }
}

// Fallback: serve files or 404
if ($path === '/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    exit;
}

// Try to serve the file directly if it ends with .php
if (preg_match('/\.php$/', $path) && file_exists(__DIR__ . $path)) {
    $_SERVER['SCRIPT_NAME'] = $path;
    require __DIR__ . $path;
    exit;
}

// 404 for everything else
if (!file_exists(__DIR__ . $path)) {
    $_SERVER['SCRIPT_NAME'] = '/404.php';
    require __DIR__ . '/404.php';
    exit;
}

return false;
