<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/Blog.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogBlock.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/blog/BlogTag.php';
require_once __DIR__ . '/../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/blog/schema.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogPost = new BlogPost($db);
$post = $blogPost->getBySlug($slug);

if (!$post || ($post['status'] !== 'published' && !isset($_GET['preview']))) {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

$blogBlock = new BlogBlock($db);
$blocks = $blogBlock->getByPost($post['id']);

$blogCategory = new BlogCategory($db);
$category = $post['category_id'] ? $blogCategory->getById($post['category_id']) : null;

$blogTag = new BlogTag($db);
$tags = $blogTag->getByPost($post['id']);

$blog = new Blog($db);
$relatedPosts = $category ? $blog->getRelatedPosts($post['id'], $category['id'], 3) : [];

$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
if ($affiliateCode && !isset($_SESSION['affiliate_code'])) {
    $_SESSION['affiliate_code'] = $affiliateCode;
}

blogTrackView($db, $post['id']);

$pageTitle = $post['meta_title'] ?: $post['title'] . ' | WebDaddy Blog';
$pageDescription = $post['meta_description'] ?: ($post['excerpt'] ?: blogTruncate(strip_tags($post['title']), 160));
$canonicalUrl = $post['canonical_url'] ?: (SITE_URL . '/blog/' . $post['slug'] . '/');

$articleSchema = blogGenerateArticleSchema($post, $blocks);
$breadcrumbSchema = blogGenerateBreadcrumbSchema($post, $category);

$toc = blogExtractTableOfContents($blocks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <?php if ($post['focus_keyword']): ?>
    <meta name="keywords" content="<?= htmlspecialchars($post['focus_keyword']) ?>">
    <?php endif; ?>
    
    <meta property="og:title" content="<?= htmlspecialchars($post['og_title'] ?: $post['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($post['og_description'] ?: $pageDescription) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <?php if ($post['og_image'] || $post['featured_image']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($post['og_image'] ?: $post['featured_image']) ?>">
    <?php endif; ?>
    <meta property="article:published_time" content="<?= date('c', strtotime($post['publish_date'])) ?>">
    <meta property="article:modified_time" content="<?= date('c', strtotime($post['updated_at'])) ?>">
    <?php if ($category): ?>
    <meta property="article:section" content="<?= htmlspecialchars($category['name']) ?>">
    <?php endif; ?>
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($post['twitter_title'] ?: $post['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($post['twitter_description'] ?: $pageDescription) ?>">
    <?php if ($post['twitter_image'] || $post['featured_image']): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($post['twitter_image'] ?: $post['featured_image']) ?>">
    <?php endif; ?>
    
    <script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES) ?></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <link rel="stylesheet" href="/assets/css/blog/main.css">
    <link rel="stylesheet" href="/assets/css/blog/blocks.css">
    <link rel="stylesheet" href="/assets/css/blog/sticky-rail.css">
    <link rel="stylesheet" href="/assets/css/blog/affiliate.css">
</head>
<body class="blog-page blog-post-page" data-post-id="<?= $post['id'] ?>" <?= $affiliateCode ? 'data-affiliate-code="' . htmlspecialchars($affiliateCode) . '"' : '' ?>>
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
        <article class="blog-article">
            <header class="blog-article-header">
                <div class="blog-container blog-container-narrow">
                    <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                        <a href="/blog/">Blog</a>
                        <?php if ($category): ?>
                        <span class="blog-breadcrumb-sep">/</span>
                        <a href="<?= blogGetCategoryUrl($category, $affiliateCode) ?>"><?= htmlspecialchars($category['name']) ?></a>
                        <?php endif; ?>
                        <span class="blog-breadcrumb-sep">/</span>
                        <span><?= htmlspecialchars(blogTruncate($post['title'], 40)) ?></span>
                    </nav>
                    
                    <?php if ($category): ?>
                    <a href="<?= blogGetCategoryUrl($category, $affiliateCode) ?>" class="blog-article-category">
                        <?= htmlspecialchars($category['name']) ?>
                    </a>
                    <?php endif; ?>
                    
                    <h1 class="blog-article-title"><?= htmlspecialchars($post['title']) ?></h1>
                    
                    <div class="blog-article-meta">
                        <div class="blog-article-author">
                            <?php if ($post['author_avatar']): ?>
                            <img src="<?= htmlspecialchars($post['author_avatar']) ?>" alt="<?= htmlspecialchars($post['author_name']) ?>" class="blog-author-avatar">
                            <?php else: ?>
                            <div class="blog-author-avatar-placeholder">
                                <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <span class="blog-author-name"><?= htmlspecialchars($post['author_name']) ?></span>
                        </div>
                        <span class="blog-meta-divider">·</span>
                        <time datetime="<?= date('Y-m-d', strtotime($post['publish_date'])) ?>" class="blog-article-date">
                            <?= blogFormatDate($post['publish_date']) ?>
                        </time>
                        <span class="blog-meta-divider">·</span>
                        <span class="blog-article-reading-time"><?= $post['reading_time_minutes'] ?> min read</span>
                    </div>
                    
                    <div class="blog-share-buttons">
                        <span class="blog-share-label">Share:</span>
                        <a href="https://wa.me/?text=<?= urlencode($post['title'] . ' ' . $canonicalUrl) ?>" 
                           class="blog-share-btn blog-share-whatsapp" target="_blank" rel="noopener" title="Share on WhatsApp">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($canonicalUrl) ?>" 
                           class="blog-share-btn blog-share-twitter" target="_blank" rel="noopener" title="Share on X/Twitter">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>" 
                           class="blog-share-btn blog-share-facebook" target="_blank" rel="noopener" title="Share on Facebook">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode($canonicalUrl) ?>&title=<?= urlencode($post['title']) ?>" 
                           class="blog-share-btn blog-share-linkedin" target="_blank" rel="noopener" title="Share on LinkedIn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                        <button class="blog-share-btn blog-share-copy" onclick="copyToClipboard('<?= htmlspecialchars($canonicalUrl) ?>')" title="Copy link">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <?php if ($post['featured_image']): ?>
                <div class="blog-article-featured-image">
                    <img src="<?= htmlspecialchars($post['featured_image']) ?>" 
                         alt="<?= htmlspecialchars(blogGetFeaturedImageAlt($post)) ?>"
                         loading="eager">
                </div>
                <?php endif; ?>
            </header>

            <div class="blog-article-content-wrapper blog-container">
                <div class="blog-article-body">
                    <?php if (!empty($toc) && count($toc) >= 3): ?>
                    <nav class="blog-toc" aria-label="Table of contents">
                        <h2 class="blog-toc-title">In This Article</h2>
                        <ul class="blog-toc-list">
                            <?php foreach ($toc as $item): ?>
                            <li class="blog-toc-item blog-toc-level-<?= $item['level'] ?>">
                                <a href="#<?= htmlspecialchars($item['id']) ?>"><?= htmlspecialchars($item['text']) ?></a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <div class="blog-blocks">
                        <?php if (empty($blocks)): ?>
                        <div class="blog-block blog-block-rich-text">
                            <p><?= nl2br(htmlspecialchars($post['excerpt'] ?: 'Content coming soon...')) ?></p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($blocks as $block): 
                            $data = $block['data_payload'];
                            $layout = $block['layout_variant'] ?? 'default';
                            $blockClass = 'blog-block blog-block-' . $block['block_type'] . ' blog-block-layout-' . $layout;
                        ?>
                        <div class="<?= $blockClass ?>" data-block-type="<?= $block['block_type'] ?>">
                            <?php
                            switch ($block['block_type']) {
                                case 'rich_text':
                                    $content = $data['content'] ?? '';
                                    $content = blogAddHeadingAnchors($content);
                                    echo '<div class="blog-rich-text">' . $content . '</div>';
                                    break;
                                    
                                case 'section_divider':
                                    $dividerType = $data['divider_type'] ?? 'line';
                                    $label = $data['label_text'] ?? '';
                                    if ($dividerType === 'labeled' && $label) {
                                        echo '<div class="blog-divider blog-divider-labeled"><span>' . htmlspecialchars($label) . '</span></div>';
                                    } elseif ($dividerType === 'gradient') {
                                        echo '<div class="blog-divider blog-divider-gradient"></div>';
                                    } elseif ($dividerType === 'space') {
                                        echo '<div class="blog-divider blog-divider-space"></div>';
                                    } else {
                                        echo '<hr class="blog-divider blog-divider-line">';
                                    }
                                    break;
                                    
                                case 'visual_explanation':
                                    $heading = $data['heading'] ?? '';
                                    $headingLevel = $data['heading_level'] ?? 'h2';
                                    $content = $data['content'] ?? '';
                                    $image = $data['image'] ?? [];
                                    $imagePos = $data['image_position'] ?? 'right';
                                    
                                    echo '<div class="blog-visual-explanation blog-visual-' . $imagePos . '">';
                                    echo '<div class="blog-visual-content">';
                                    if ($heading) {
                                        $anchorId = blogGenerateAnchorId($heading);
                                        echo '<' . $headingLevel . ' id="' . $anchorId . '">' . htmlspecialchars($heading) . '</' . $headingLevel . '>';
                                    }
                                    echo '<div class="blog-visual-text">' . $content . '</div>';
                                    echo '</div>';
                                    if (!empty($image['url'])) {
                                        echo '<figure class="blog-visual-figure">';
                                        echo '<img src="' . htmlspecialchars($image['url']) . '" alt="' . htmlspecialchars($image['alt'] ?? $heading) . '" loading="lazy">';
                                        if (!empty($image['caption'])) {
                                            echo '<figcaption>' . htmlspecialchars($image['caption']) . '</figcaption>';
                                        }
                                        echo '</figure>';
                                    }
                                    echo '</div>';
                                    break;
                                    
                                case 'inline_conversion':
                                    $headline = $data['headline'] ?? 'Ready to get started?';
                                    $subheadline = $data['subheadline'] ?? '';
                                    $ctaPrimary = $data['cta_primary'] ?? [];
                                    $style = $data['style'] ?? 'card';
                                    
                                    echo '<div class="blog-inline-cta blog-inline-cta-' . $style . '">';
                                    echo '<div class="blog-inline-cta-content">';
                                    echo '<h3>' . htmlspecialchars($headline) . '</h3>';
                                    if ($subheadline) {
                                        echo '<p>' . htmlspecialchars($subheadline) . '</p>';
                                    }
                                    echo '</div>';
                                    if (!empty($ctaPrimary['text'])) {
                                        $ctaUrl = $ctaPrimary['url'] ?? '/#templates';
                                        if ($affiliateCode && strpos($ctaUrl, 'aff=') === false) {
                                            $ctaUrl .= (strpos($ctaUrl, '?') !== false ? '&' : '?') . 'aff=' . urlencode($affiliateCode);
                                        }
                                        echo '<a href="' . htmlspecialchars($ctaUrl) . '" class="btn-premium btn-premium-gold">' . htmlspecialchars($ctaPrimary['text']) . '</a>';
                                    }
                                    echo '</div>';
                                    break;
                                    
                                case 'faq_seo':
                                    $heading = $data['heading'] ?? 'Frequently Asked Questions';
                                    $items = $data['items'] ?? [];
                                    $faqStyle = $data['style'] ?? 'accordion';
                                    
                                    if (!empty($items)) {
                                        $faqSchema = blogGenerateFAQSchema($items);
                                        echo '<script type="application/ld+json">' . json_encode($faqSchema, JSON_UNESCAPED_SLASHES) . '</script>';
                                    }
                                    
                                    echo '<div class="blog-faq blog-faq-' . $faqStyle . '">';
                                    if ($heading) {
                                        $anchorId = blogGenerateAnchorId($heading);
                                        echo '<h2 id="' . $anchorId . '">' . htmlspecialchars($heading) . '</h2>';
                                    }
                                    echo '<div class="blog-faq-list">';
                                    foreach ($items as $i => $faq) {
                                        $isOpen = $faq['is_open'] ?? false;
                                        echo '<details class="blog-faq-item" ' . ($isOpen ? 'open' : '') . '>';
                                        echo '<summary class="blog-faq-question"><h3>' . htmlspecialchars($faq['question']) . '</h3></summary>';
                                        echo '<div class="blog-faq-answer">' . ($faq['answer'] ?? '') . '</div>';
                                        echo '</details>';
                                    }
                                    echo '</div></div>';
                                    break;
                                    
                                case 'final_conversion':
                                    $headline = $data['headline'] ?? 'Ready to Launch Your Website?';
                                    $subheadline = $data['subheadline'] ?? '';
                                    $ctaConfig = $data['cta_config'] ?? [];
                                    $style = $data['style'] ?? 'hero';
                                    
                                    echo '<div class="blog-final-cta blog-final-cta-' . $style . '">';
                                    echo '<div class="blog-final-cta-content">';
                                    echo '<h2>' . htmlspecialchars($headline) . '</h2>';
                                    if ($subheadline) {
                                        echo '<p>' . htmlspecialchars($subheadline) . '</p>';
                                    }
                                    
                                    $ctaType = $ctaConfig['type'] ?? 'custom';
                                    if ($ctaType === 'whatsapp') {
                                        $waMessage = $ctaConfig['whatsapp']['message'] ?? 'Hi, I want to build a website';
                                        $waBtn = $ctaConfig['whatsapp']['button_text'] ?? 'Chat on WhatsApp';
                                        echo '<a href="https://wa.me/' . str_replace('+', '', WHATSAPP_NUMBER) . '?text=' . urlencode($waMessage) . '" class="btn-premium btn-premium-gold btn-premium-lg" target="_blank" rel="noopener">' . htmlspecialchars($waBtn) . '</a>';
                                    } else {
                                        $btnText = $ctaConfig['custom']['button_text'] ?? 'View Templates';
                                        $btnUrl = $ctaConfig['custom']['url'] ?? '/#templates';
                                        if ($affiliateCode && strpos($btnUrl, 'aff=') === false) {
                                            $btnUrl .= (strpos($btnUrl, '?') !== false ? '&' : '?') . 'aff=' . urlencode($affiliateCode);
                                        }
                                        echo '<a href="' . htmlspecialchars($btnUrl) . '" class="btn-premium btn-premium-gold btn-premium-lg">' . htmlspecialchars($btnText) . '</a>';
                                    }
                                    echo '</div></div>';
                                    break;
                                    
                                case 'internal_authority':
                                    echo '<div class="blog-related-section">';
                                    $heading = $data['heading'] ?? 'Related Articles';
                                    echo '<h3>' . htmlspecialchars($heading) . '</h3>';
                                    echo '<p class="blog-related-coming-soon">Related content links will appear here when more posts are published.</p>';
                                    echo '</div>';
                                    break;
                                    
                                default:
                                    if (isset($data['content'])) {
                                        echo '<div class="blog-generic-block">' . $data['content'] . '</div>';
                                    }
                            }
                            ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($tags)): ?>
                    <div class="blog-tags">
                        <span class="blog-tags-label">Tags:</span>
                        <?php foreach ($tags as $tag): ?>
                        <span class="blog-tag"><?= htmlspecialchars($tag['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <aside class="blog-sidebar blog-article-sidebar">
                    <div class="blog-sidebar-sticky">
                        <div class="blog-cta-card <?= $affiliateCode ? 'affiliate-referred' : '' ?>">
                            <h3>Get a Professional Website</h3>
                            <p>Browse our premium templates and launch your business online in 24 hours.</p>
                            <a href="/#templates<?= $affiliateCode ? '?aff=' . urlencode($affiliateCode) : '' ?>" class="btn-premium btn-premium-gold" data-cta-type="sidebar-templates" <?= $affiliateCode ? 'data-affiliate="yes"' : '' ?>>View Templates</a>
                        </div>
                        
                        <div class="blog-sidebar-section">
                            <h4 class="blog-sidebar-title">Need Help?</h4>
                            <a href="https://wa.me/<?= str_replace('+', '', WHATSAPP_NUMBER) ?>?text=Hi%20WebDaddy%2C%20I%20have%20a%20question%20about%20<?= urlencode($post['title']) ?>" 
                               class="blog-whatsapp-btn" target="_blank" rel="noopener">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                Chat on WhatsApp
                            </a>
                        </div>
                        
                        <?php if ($affiliateCode): ?>
                        <div class="blog-affiliate-notice">
                            <span class="blog-affiliate-badge">✓ Referred by Partner</span>
                            <p>You're browsing with a verified partner code: <strong><?= htmlspecialchars(substr($affiliateCode, 0, 15)) ?></strong>. Your template purchases support this partner!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
            
            <?php if (!empty($relatedPosts)): ?>
            <section class="blog-related-posts">
                <div class="blog-container">
                    <h2>Related Articles</h2>
                    <div class="blog-related-grid">
                        <?php foreach ($relatedPosts as $related): ?>
                        <article class="blog-card <?= $affiliateCode ? 'affiliate-aware' : '' ?>">
                            <a href="<?= blogGetPostUrl($related, $affiliateCode) ?>" class="blog-card-image-link">
                                <?php if ($related['featured_image']): ?>
                                <img src="<?= htmlspecialchars($related['featured_image']) ?>" 
                                     alt="<?= htmlspecialchars($related['title']) ?>"
                                     class="blog-card-image" loading="lazy">
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
                                <h3 class="blog-card-title">
                                    <a href="<?= blogGetPostUrl($related, $affiliateCode) ?>">
                                        <?= htmlspecialchars($related['title']) ?>
                                    </a>
                                </h3>
                                <span class="blog-card-reading-time"><?= $related['reading_time_minutes'] ?> min read</span>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </article>
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
        <a href="/#templates<?= $affiliateCode ? '?aff=' . urlencode($affiliateCode) : '' ?>" class="blog-mobile-cta-btn blog-mobile-cta-templates">
            View Templates
        </a>
    </div>

    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            var btn = document.querySelector('.blog-share-copy');
            btn.classList.add('copied');
            setTimeout(function() { btn.classList.remove('copied'); }, 2000);
        });
    }
    </script>

    <script src="/assets/js/blog/interactions.js"></script>
    <script src="/assets/js/blog/tracking.js"></script>
</body>
</html>
