<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/helpers.php';

$db = getDb();
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

$blogPost = new BlogPost($db);
$blogCategory = new BlogCategory($db);

$posts = $blogPost->getPublished($page, $perPage);
$totalPosts = $blogPost->getPublishedCount();
$totalPages = ceil($totalPosts / $perPage);

$categories = $blogCategory->getAll(true);

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;

$pageTitle = 'Blog | WebDaddy Empire';
$pageDescription = 'Expert insights on website design, SEO, e-commerce, and digital marketing for Nigerian businesses.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= SITE_URL ?>/blog/">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>/blog/">
</head>
<body>
    <main>
        <h1>WebDaddy Blog</h1>
        <p>Blog listing page - Phase 2 will implement full rendering</p>
    </main>
</body>
</html>
