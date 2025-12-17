<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogTag.php';
require_once __DIR__ . '/../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/blog/schema.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$tagSlug = $_GET['tag_slug'] ?? '';

if (empty($tagSlug)) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogTag = new BlogTag($db);
$tag = $blogTag->getBySlug($tagSlug);

if (!$tag) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

$blogPost = new BlogPost($db);
$posts = $blogPost->getByTag($tag['id'], $page, $perPage);
$totalPosts = $blogPost->getTagPostCount($tag['id']);
$totalPages = ceil($totalPosts / $perPage);

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
if ($affiliateCode && !isset($_SESSION['affiliate_code'])) {
    $_SESSION['affiliate_code'] = $affiliateCode;
}

$pageTitle = $tag['meta_title'] ?: $tag['name'] . ' | WebDaddy Blog';
$pageDescription = $tag['meta_description'] ?: 'Posts tagged with ' . htmlspecialchars($tag['name']);

$breadcrumbSchema = blogGenerateBreadcrumbSchema(['title' => $tag['name']], null, $tag);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <script type="application/ld+json">
    <?php echo json_encode($breadcrumbSchema); ?>
    </script>
</head>
<body>
    <div class="blog-container">
        <h1><?php echo htmlspecialchars($tag['name']); ?></h1>
        
        <?php if (empty($posts)): ?>
            <p>No posts found with this tag.</p>
        <?php else: ?>
            <div class="blog-posts-grid">
                <?php foreach ($posts as $post): ?>
                    <article class="blog-card">
                        <h2><a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>/"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                        <p><?php echo htmlspecialchars(substr($post['excerpt'], 0, 150)); ?>...</p>
                        <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>/">Read More</a>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?tag_slug=<?php echo htmlspecialchars($tagSlug); ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
