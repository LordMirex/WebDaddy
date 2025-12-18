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

$activeNav = 'blog';
$showCart = true;
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$showMobileCTA = true;

$pageTitle = 'Blog | ' . SITE_NAME;
$pageDescription = 'Expert insights on website design, SEO, e-commerce, and digital marketing for Nigerian businesses. Learn how to grow your online presence.';
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
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/blog/main.css">
    <link rel="stylesheet" href="/assets/css/blog/blocks.css">
    <link rel="stylesheet" href="/assets/css/blog/sticky-rail.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gold: { DEFAULT: '#D4AF37', 400: '#E8BB45' },
                        navy: { DEFAULT: '#0f172a', dark: '#0a1929', light: '#1e293b' }
                    }
                }
            }
        }
        }
        document.documentElement.classList.add('dark');
    </script>
    <style>
        .btn-gold-shine {
            background: linear-gradient(135deg, #F5D669 0%, #D4AF37 50%, #B8942E 100%);
            box-shadow: 0 4px 15px rgba(212,175,55,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .btn-gold-shine:hover {
            background: linear-gradient(135deg, #FADE7A 0%, #E8BB45 50%, #D4AF37 100%);
            box-shadow: 0 6px 25px rgba(212,175,55,0.5);
        }
    </style>
</head>
<body class="blog-page">
    <?php require_once __DIR__ . '/../includes/layout/header.php'; ?>

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
                            <?php if ($post['category_name']): ?>
                            <a href="<?= blogGetCategoryUrl(['slug' => $post['category_slug']], $affiliateCode) ?>" class="blog-card-category">
                                <?= htmlspecialchars($post['category_name']) ?>
                            </a>
                            <?php endif; ?>
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
                    <?php if ($page > 1): ?>
                    <a href="/blog/?page=<?= $page - 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn blog-pagination-prev">
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
                        <a href="/blog/?page=1<?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-num">1</a>
                        <?php if ($start > 2): ?>
                        <span class="blog-pagination-dots">...</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="/blog/?page=<?= $i ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" 
                           class="blog-pagination-num <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                        <span class="blog-pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="/blog/?page=<?= $totalPages ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-num"><?= $totalPages ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="/blog/?page=<?= $page + 1 ?><?= $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '' ?>" class="blog-pagination-btn blog-pagination-next">
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
                    
                    <?php if (!empty($popularPosts)): ?>
                    <div class="blog-sidebar-section">
                        <h4 class="blog-sidebar-title">Popular Posts</h4>
                        <div class="blog-sidebar-posts">
                            <?php foreach ($popularPosts as $popPost): ?>
                            <a href="<?= blogGetPostUrl($popPost, $affiliateCode) ?>" class="blog-sidebar-post">
                                <?php if ($popPost['featured_image']): ?>
                                <img src="<?= htmlspecialchars($popPost['featured_image']) ?>" 
                                     alt="" class="blog-sidebar-post-img" loading="lazy">
                                <?php endif; ?>
                                <div class="blog-sidebar-post-content">
                                    <span class="blog-sidebar-post-title"><?= htmlspecialchars($popPost['title']) ?></span>
                                    <span class="blog-sidebar-post-meta"><?= $popPost['reading_time_minutes'] ?> min read</span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
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

    <?php require_once __DIR__ . '/../includes/layout/footer.php'; ?>

    <script src="/assets/js/blog/interactions.js"></script>
    <script src="/assets/js/customer-auth.js"></script>
    <script src="/assets/js/nav-smartness.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('customerNav', () => ({
                customer: null,
                async init() {
                    this.customer = await checkCustomerSession();
                }
            }));
        });
        function toggleCartDrawer() {
            window.location.href = '/?view=templates#products';
        }
    </script>
</body>
</html>
