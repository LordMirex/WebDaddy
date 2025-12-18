<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/helpers.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

$blogPost = new BlogPost($db);
$blogCategory = new BlogCategory($db);
$blog = new Blog($db);

$posts = $blogPost->getPublished($page, $perPage);
$totalPosts = $blogPost->getPublishedCount();
$totalPages = ceil($totalPosts / $perPage);

$categories = $blogCategory->getWithPostCount(true);
$popularPosts = $blog->getPopularPosts(5);

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
if ($affiliateCode && !isset($_SESSION['affiliate_code'])) {
    $_SESSION['affiliate_code'] = $affiliateCode;
}

$pageTitle = 'Blog | ' . SITE_NAME;
$pageDescription = 'Expert insights on website design, SEO, e-commerce, and digital marketing for Nigerian businesses. Learn how to grow your online presence.';
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
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
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/blog/main.css">
    <link rel="stylesheet" href="/assets/css/blog/blocks.css">
    <link rel="stylesheet" href="/assets/css/blog/sticky-rail.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-navy-dark">
    <!-- Main Navigation -->
    <nav id="mainNav" class="bg-navy border-b border-navy-light/50 sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="/" class="flex-shrink-0">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" class="h-10 w-auto">
                </a>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/?view=templates" class="text-gold hover:text-gold-light text-sm font-medium transition-colors">Templates</a>
                    <a href="/blog/" class="text-gold hover:text-gold-light text-sm font-medium transition-colors">Blog</a>
                    <a href="/?view=tools" class="text-gray-300 hover:text-gold text-sm font-medium transition-colors">Tools</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="/affiliate/register.php" class="btn-gold-shine inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg text-navy transition-all">Affiliate</a>
                </div>
                
                <button @click="open = !open" class="md:hidden p-2 rounded-md text-gold hover:bg-navy-light">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <div x-show="open" class="md:hidden bg-navy border-t border-navy-light/50">
            <a href="/?view=templates" class="block px-4 py-3 text-gold hover:bg-navy-light font-medium">Templates</a>
            <a href="/blog/" class="block px-4 py-3 text-gold bg-gold/10 border-l-3 border-gold font-medium">Blog</a>
            <a href="/?view=tools" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-navy-light font-medium">Tools</a>
            <a href="/affiliate/register.php" class="block px-4 py-3 text-navy bg-gold font-medium text-center mt-2">Become Affiliate</a>
        </div>
    </nav>

    <main class="blog-main">
        <section class="blog-hero">
            <div class="blog-hero-content">
                <h1>WebDaddy Blog</h1>
                <p class="blog-hero-subtitle">Expert insights on building successful websites and growing your online business in Nigeria</p>
            </div>
        </section>

        <?php if (!empty($categories)): ?>
        <section class="blog-categories-bar">
            <div class="blog-container">
                <div class="blog-categories-scroll">
                    <a href="/blog/" class="blog-category-pill active">All Posts</a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="<?= blogGetCategoryUrl($cat, $affiliateCode) ?>" class="blog-category-pill">
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
                    <h2>No Posts Yet</h2>
                    <p>We're working on great content. Check back soon!</p>
                    <a href="/" class="btn-premium btn-premium-gold">Browse Templates</a>
                </div>
                <?php else: ?>
                <div class="blog-posts-grid">
                    <?php foreach ($posts as $index => $post): ?>
                    <article class="blog-card <?= $index === 0 && $page === 1 ? 'blog-card-featured' : '' ?>">
                        <a href="<?= blogGetPostUrl($post, $affiliateCode) ?>" class="blog-card-image-link">
                            <?php if ($post['featured_image']): ?>
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['featured_image_alt'] ?? $post['title']) ?>" class="blog-card-image" loading="lazy">
                            <?php else: ?>
                            <div class="blog-card-image-placeholder">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <path d="M21 15l-5-5L5 21"></path>
                                </svg>
                            </div>
                            <?php endif; ?>
                        </a>
                        
                        <div class="blog-card-content">
                            <?php if ($post['category_id']): ?>
                            <a href="<?= blogGetCategoryUrl($post, $affiliateCode) ?>" class="blog-card-category">
                                <?= htmlspecialchars($post['category_name'] ?? 'Uncategorized') ?>
                            </a>
                            <?php endif; ?>
                            
                            <h3 class="blog-card-title">
                                <a href="<?= blogGetPostUrl($post, $affiliateCode) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h3>
                            
                            <p class="blog-card-excerpt">
                                <?= htmlspecialchars($post['excerpt'] ?: blogTruncate(strip_tags($post['title']), 120)) ?>
                            </p>
                            
                            <div class="blog-card-meta">
                                <span><?= date('M d, Y', strtotime($post['publish_date'] ?? $post['created_at'])) ?></span>
                                <span class="blog-card-divider">•</span>
                                <span><?= $post['reading_time_minutes'] ?? 5 ?> min read</span>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($totalPages > 1): ?>
                <div class="blog-pagination">
                    <?php if ($page > 1): ?>
                    <a href="/blog/?page=<?= $page - 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn">
                        <span>← Previous</span>
                    </a>
                    <?php endif; ?>
                    
                    <div class="blog-pagination-numbers">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === 1 || $i === $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                <?php if ($i > 1 && $i !== 2 && $i !== $page - 1): ?>
                                <span class="blog-pagination-dots">...</span>
                                <?php endif; ?>
                                <a href="/blog/?page=<?= $i ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-num <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="/blog/?page=<?= $page + 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn">
                        <span>Next →</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </section>
            
            <aside class="blog-sidebar">
                <div class="blog-sidebar-sticky">
                    <div class="blog-cta-card">
                        <h3>Get a Professional Website</h3>
                        <p>Browse our premium templates and get started today</p>
                        <a href="/#templates" class="btn-premium btn-premium-gold btn-premium-full">View Templates</a>
                    </div>
                    
                    <?php if (!empty($popularPosts)): ?>
                    <div class="blog-sidebar-section">
                        <h4 class="blog-sidebar-title">Popular Posts</h4>
                        <div class="blog-sidebar-posts">
                            <?php foreach ($popularPosts as $popular): ?>
                            <a href="<?= blogGetPostUrl($popular, $affiliateCode) ?>" class="blog-sidebar-post">
                                <?php if ($popular['featured_image']): ?>
                                <img src="<?= htmlspecialchars($popular['featured_image']) ?>" alt="" class="blog-sidebar-post-img">
                                <?php endif; ?>
                                <div class="blog-sidebar-post-content">
                                    <div class="blog-sidebar-post-title"><?= htmlspecialchars($popular['title']) ?></div>
                                    <div class="blog-sidebar-post-meta"><?= $popular['reading_time_minutes'] ?? 5 ?> min read</div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <footer class="bg-navy-dark border-t border-navy-light/50 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; 2025 <?= SITE_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Mobile nav alpine integration
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Alpine) {
                Alpine.start();
            }
        });
    </script>
</body>
</html>
