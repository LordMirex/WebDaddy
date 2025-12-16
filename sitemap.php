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
</urlset>
