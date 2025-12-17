<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/blog/schema.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

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

$allCategories = $blogCategory->getWithPostCount(true);

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
if ($affiliateCode && !isset($_SESSION['affiliate_code'])) {
    $_SESSION['affiliate_code'] = $affiliateCode;
}

$pageTitle = $category['meta_title'] ?: $category['name'] . ' | WebDaddy Blog';
$pageDescription = $category['meta_description'] ?: ($category['description'] ?: 'Browse our ' . $category['name'] . ' articles for expert insights and tips.');

$breadcrumbSchema = blogGenerateBreadcrumbSchema(['title' => $category['name']], $category);
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
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/blog/main.css">
</head>
<body class="blog-page">
    <header class="blog-header">
        <div class="blog-header-container">
            <a href="/" class="blog-logo">
                <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" width="180">
            </a>
            <nav class="blog-nav">
                <a href="/">Templates</a>
                <a href="/blog/" class="active">Blog</a>
                <a href="/?view=tools">Tools</a>
            </nav>
            <a href="/#templates" class="btn-premium btn-premium-gold btn-premium-sm">Get Started</a>
        </div>
    </header>

    <main class="blog-main">
        <section class="blog-hero blog-hero-category">
            <div class="blog-hero-content">
                <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                    <a href="/blog/">Blog</a>
                    <span class="blog-breadcrumb-sep">/</span>
                    <span><?= htmlspecialchars($category['name']) ?></span>
                </nav>
                <h1><?= htmlspecialchars($category['name']) ?></h1>
                <?php if ($category['description']): ?>
                <p class="blog-hero-subtitle"><?= htmlspecialchars($category['description']) ?></p>
                <?php endif; ?>
                <p class="blog-hero-count"><?= $totalPosts ?> article<?= $totalPosts !== 1 ? 's' : '' ?></p>
            </div>
        </section>

        <?php if (!empty($allCategories)): ?>
        <section class="blog-categories-bar">
            <div class="blog-container">
                <div class="blog-categories-scroll">
                    <a href="/blog/<?= $affiliateCode ? '?aff=' . urlencode($affiliateCode) : '' ?>" class="blog-category-pill">All Posts</a>
                    <?php foreach ($allCategories as $cat): ?>
                    <a href="<?= blogGetCategoryUrl($cat, $affiliateCode) ?>" 
                       class="blog-category-pill <?= $cat['id'] === $category['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                        <?php if ($cat['post_count'] > 0): ?>
                        <span class="blog-category-count"><?= $cat['post_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="blog-container blog-content-grid">
            <section class="blog-posts-section">
                <?php if (empty($posts)): ?>
                <div class="blog-empty-state">
                    <div class="blog-empty-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h10"/>
                        </svg>
                    </div>
                    <h2>No Posts in This Category</h2>
                    <p>We're working on content for this category. Check back soon!</p>
                    <a href="/blog/" class="btn-premium btn-premium-gold">View All Posts</a>
                </div>
                <?php else: ?>
                <div class="blog-posts-grid">
                    <?php foreach ($posts as $index => $post): ?>
                    <article class="blog-card">
                        <a href="<?= blogGetPostUrl($post, $affiliateCode) ?>" class="blog-card-image-link">
                            <?php if ($post['featured_image']): ?>
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>" 
                                 alt="<?= htmlspecialchars(blogGetFeaturedImageAlt($post)) ?>"
                                 class="blog-card-image"
                                 loading="<?= $index < 4 ? 'eager' : 'lazy' ?>">
                            <?php else: ?>
                            <div class="blog-card-image-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            </div>
                            <?php endif; ?>
                        </a>
                        <div class="blog-card-content">
                            <h2 class="blog-card-title">
                                <a href="<?= blogGetPostUrl($post, $affiliateCode) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h2>
                            <?php if ($post['excerpt']): ?>
                            <p class="blog-card-excerpt"><?= htmlspecialchars(blogTruncate($post['excerpt'], 120)) ?></p>
                            <?php endif; ?>
                            <div class="blog-card-meta">
                                <span class="blog-card-date"><?= blogFormatDate($post['publish_date']) ?></span>
                                <span class="blog-card-divider">·</span>
                                <span class="blog-card-reading-time"><?= $post['reading_time_minutes'] ?> min read</span>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="blog-pagination" aria-label="Blog pagination">
                    <?php $baseUrl = '/blog/category/' . $category['slug'] . '/'; ?>
                    
                    <?php if ($page > 1): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $page - 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn blog-pagination-prev">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <div class="blog-pagination-numbers">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1): ?>
                        <a href="<?= $baseUrl ?>?page=1<?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-num">1</a>
                        <?php if ($start > 2): ?>
                        <span class="blog-pagination-dots">...</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?= $baseUrl ?>?page=<?= $i ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" 
                           class="blog-pagination-num <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                        <span class="blog-pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="<?= $baseUrl ?>?page=<?= $totalPages ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-num"><?= $totalPages ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $page + 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn blog-pagination-next">
                        Next
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </section>

            <aside class="blog-sidebar">
                <div class="blog-sidebar-sticky">
                    <div class="blog-cta-card">
                        <h3>Get a Professional Website</h3>
                        <p>Browse our premium templates and launch your business online in 24 hours.</p>
                        <a href="/#templates" class="btn-premium btn-premium-gold">View Templates</a>
                    </div>
                    
                    <div class="blog-sidebar-section">
                        <h4 class="blog-sidebar-title">Need Help?</h4>
                        <a href="https://wa.me/<?= str_replace('+', '', WHATSAPP_NUMBER) ?>?text=Hi%20WebDaddy%2C%20I%20have%20a%20question" 
                           class="blog-whatsapp-btn" target="_blank" rel="noopener">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Chat on WhatsApp
                        </a>
                    </div>
                    
                    <?php if ($affiliateCode): ?>
                    <div class="blog-affiliate-notice">
                        <span class="blog-affiliate-badge">Referred by Partner</span>
                        <p>You're shopping with a partner code! Special offers may apply.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <footer class="blog-footer">
        <div class="blog-container">
            <div class="blog-footer-content">
                <div class="blog-footer-brand">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" width="140">
                    <p>Premium website templates and tools for Nigerian businesses.</p>
                </div>
                <div class="blog-footer-links">
                    <div class="blog-footer-column">
                        <h5>Quick Links</h5>
                        <a href="/">Templates</a>
                        <a href="/?view=tools">Tools</a>
                        <a href="/blog/">Blog</a>
                    </div>
                    <div class="blog-footer-column">
                        <h5>Support</h5>
                        <a href="https://wa.me/<?= str_replace('+', '', WHATSAPP_NUMBER) ?>" target="_blank" rel="noopener">WhatsApp</a>
                        <a href="mailto:<?= SUPPORT_EMAIL ?>">Email Us</a>
                    </div>
                </div>
            </div>
            <div class="blog-footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div class="blog-mobile-cta">
        <a href="https://wa.me/<?= str_replace('+', '', WHATSAPP_NUMBER) ?>" class="blog-mobile-cta-btn blog-mobile-cta-whatsapp" target="_blank" rel="noopener">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
        <a href="/#templates" class="blog-mobile-cta-btn blog-mobile-cta-templates">
            View Templates
        </a>
    </div>
</body>
</html>
