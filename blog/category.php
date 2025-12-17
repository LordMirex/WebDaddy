<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/helpers.php';

$db = getDb();
$categorySlug = $_GET['category_slug'] ?? '';

if (empty($categorySlug)) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogCategory = new BlogCategory($db);
$category = $blogCategory->getBySlug($categorySlug);

if (!$category || !$category['is_active']) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

$blogPost = new BlogPost($db);
$posts = $blogPost->getByCategory($category['id'], $page, $perPage);
$totalPosts = $blogPost->getCategoryPostCount($category['id']);
$totalPages = ceil($totalPosts / $perPage);

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;

$pageTitle = $category['meta_title'] ?: $category['name'] . ' | WebDaddy Blog';
$pageDescription = $category['meta_description'] ?: $category['description'] ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= SITE_URL ?>/blog/category/<?= htmlspecialchars($category['slug']) ?>/">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>/blog/category/<?= htmlspecialchars($category['slug']) ?>/">
</head>
<body>
    <main>
        <h1><?= htmlspecialchars($category['name']) ?></h1>
        <p>Category archive page - Phase 2 will implement full rendering</p>
    </main>
</body>
</html>
