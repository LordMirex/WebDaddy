<?php
/**
 * Dynamic XML Sitemap Generator
 * Updates automatically when templates/tools are added
 * Accessible at: webdaddy.online/sitemap.xml
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tools.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDb();

// Get all active templates
$templates = getTemplates(true);

// Get all active tools (including out of stock for SEO)
$tools = getTools(true, null, null, null, false);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    
    <!-- Homepage -->
    <url>
        <loc><?php echo SITE_URL; ?>/</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- Templates -->
    <?php foreach ($templates as $template): ?>
    <url>
        <loc><?php echo SITE_URL . '/' . $template['slug']; ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($template['updated_at'] ?? $template['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endforeach; ?>
    
    <!-- Tools -->
    <?php foreach ($tools as $tool): ?>
    <url>
        <loc><?php echo SITE_URL . '/tool/' . $tool['slug']; ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($tool['updated_at'] ?? $tool['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
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
</urlset>
