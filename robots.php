<?php
/**
 * Dynamic robots.txt generator
 * Accessible at: /robots.txt
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
?>
# Robots.txt for <?php echo SITE_NAME; ?>

# Generated: <?php echo date('Y-m-d H:i:s'); ?>


# Allow all search engines
User-agent: *
Allow: /
Allow: /tool/
Allow: /cart-checkout.php
Allow: /customer/login.php

# Block private areas
Disallow: /admin/
Disallow: /includes/
Disallow: /database/
Disallow: /mailer/
Disallow: /uploads/private/
Disallow: /api/
Disallow: /logs/

# Block sensitive file types
Disallow: /*.sql$
Disallow: /*.log$
Disallow: /*.bak$
Disallow: /*.env$

# Block utility pages
Disallow: /400.php
Disallow: /403.php
Disallow: /404.php
Disallow: /500.php
Disallow: /cron.php
Disallow: /health.php
Disallow: /trigger-email-processing.php

# Crawl delay (be nice to the server)
Crawl-delay: 1

# Specific rules for Googlebot
User-agent: Googlebot
Allow: /
Disallow: /admin/
Disallow: /api/

# Specific rules for Bingbot
User-agent: Bingbot
Allow: /
Disallow: /admin/
Disallow: /api/

# Sitemap location
Sitemap: <?php echo SITE_URL; ?>/sitemap.xml
