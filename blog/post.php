<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogBlock.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/blog/schema.php';

$db = getDb();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogPost = new BlogPost($db);
$post = $blogPost->getBySlug($slug);

if (!$post || $post['status'] !== 'published') {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogBlock = new BlogBlock($db);
$blocks = $blogBlock->getByPost($post['id']);

$blogCategory = new BlogCategory($db);
$category = $post['category_id'] ? $blogCategory->getById($post['category_id']) : null;

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;

$pageTitle = $post['meta_title'] ?: $post['title'] . ' | WebDaddy Blog';
$pageDescription = $post['meta_description'] ?: ($post['excerpt'] ?: '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= $post['canonical_url'] ?: (SITE_URL . '/blog/' . $post['slug'] . '/') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($post['og_title'] ?: $post['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($post['og_description'] ?: $pageDescription) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= SITE_URL ?>/blog/<?= htmlspecialchars($post['slug']) ?>/">
    <?php if ($post['og_image'] || $post['featured_image']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($post['og_image'] ?: $post['featured_image']) ?>">
    <?php endif; ?>
</head>
<body>
    <main>
        <article>
            <h1><?= htmlspecialchars($post['title']) ?></h1>
            <p>Single post page - Phase 2 will implement full block rendering</p>
        </article>
    </main>
</body>
</html>
