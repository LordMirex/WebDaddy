<?php
/**
 * Dynamic XML Sitemap Generator
 * Updates automatically when templates/tools are added
 * Accessible at: webdaddy.online/sitemap.xml
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDb();

// Get all active templates
$templates = getTemplates(true);

// Get all active tools
$tools = getTools(true);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    
    <!-- Homepage -->
    <url>
        <loc><?php echo SITE_URL; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    </url>
    
    <?php foreach ($templates as $template): ?>
    <!-- Template: <?php echo htmlspecialchars($template['name']); ?> -->
    <url>
        <loc><?php echo SITE_URL . '/' . $template['slug']; ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
        <lastmod><?php echo date('Y-m-d', strtotime($template['updated_at'] ?? $template['created_at'])); ?></lastmod>
        <?php if (!empty($template['thumbnail_url'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($template['thumbnail_url']); ?></image:loc>
            <image:title><?php echo htmlspecialchars($template['name']); ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
    
    <!-- Affiliate Registration -->
    <url>
        <loc><?php echo SITE_URL; ?>/affiliate/register.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    
    <!-- Affiliate Login -->
    <url>
        <loc><?php echo SITE_URL; ?>/affiliate/login.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
</urlset>
