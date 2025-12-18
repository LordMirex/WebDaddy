<?php
/**
 * Dynamic XML Sitemap Generator
 * Updates automatically when templates/tools are added
 * Accessible at: webdaddy.online/sitemap.xml
 * 
 * For Google Search Console: https://webdaddy.online/sitemap.xml
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tools.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$db = getDb();

// Get all active templates
$templates = getTemplates(true);

// Get all active tools (including out of stock for SEO)
$tools = getTools(true, null, null, null, false);

// Get unique template categories
$templateCategories = [];
foreach ($templates as $t) {
    if (!empty($t['category']) && !in_array($t['category'], $templateCategories)) {
        $templateCategories[] = $t['category'];
    }
}

// Get unique tool categories
$toolCategories = [];
foreach ($tools as $t) {
    if (!empty($t['category']) && !in_array($t['category'], $toolCategories)) {
        $toolCategories[] = $t['category'];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    
    <!-- Homepage - Highest Priority -->
    <url>
        <loc><?php echo SITE_URL; ?>/</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- Cart/Checkout Page -->
    <url>
        <loc><?php echo SITE_URL; ?>/cart-checkout.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    
    <!-- Templates Section -->
    <?php foreach ($templates as $template): ?>
    <url>
        <loc><?php echo SITE_URL . '/' . htmlspecialchars($template['slug']); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($template['updated_at'] ?? $template['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <?php if (!empty($template['thumbnail_url'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($template['thumbnail_url']); ?></image:loc>
            <image:title><?php echo htmlspecialchars($template['name']); ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
    
    <!-- Tools Section -->
    <?php foreach ($tools as $tool): ?>
    <url>
        <loc><?php echo SITE_URL . '/tool/' . htmlspecialchars($tool['slug']); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($tool['updated_at'] ?? $tool['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <?php if (!empty($tool['thumbnail_url'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($tool['thumbnail_url']); ?></image:loc>
            <image:title><?php echo htmlspecialchars($tool['name']); ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
    
    <!-- Affiliate Program -->
    <url>
        <loc><?php echo SITE_URL; ?>/affiliate/register.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?php echo SITE_URL; ?>/affiliate/login.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    
    <!-- Customer Portal -->
    <url>
        <loc><?php echo SITE_URL; ?>/customer/login.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    
    <!-- Blog Posts -->
    <?php 
    require_once __DIR__ . '/includes/blog/helpers.php';
    $blogDb = getDb();
    $blogPosts = $blogDb->query("
        SELECT id, slug, updated_at, featured_image 
        FROM blog_posts 
        WHERE status = 'published'
        ORDER BY updated_at DESC
        LIMIT 50000
    ");
    
    while ($post = $blogPosts->fetch_assoc()): 
    ?>
    <url>
        <loc><?php echo SITE_URL; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>/</loc>
        <lastmod><?php echo date('Y-m-d', strtotime($post['updated_at'])); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
        <?php if (!empty($post['featured_image'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($post['featured_image']); ?></image:loc>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endwhile; ?>
    
    <!-- Blog Categories -->
    <?php 
    $blogCats = $blogDb->query("
        SELECT slug, updated_at 
        FROM blog_categories 
        WHERE status = 'active'
        ORDER BY updated_at DESC
    ");
    
    while ($cat = $blogCats->fetch_assoc()): 
    ?>
    <url>
        <loc><?php echo SITE_URL; ?>/blog/category/<?php echo htmlspecialchars($cat['slug']); ?>/</loc>
        <lastmod><?php echo date('Y-m-d', strtotime($cat['updated_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endwhile; ?>
    
</urlset>
