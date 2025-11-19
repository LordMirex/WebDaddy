<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');
?>
# Robots.txt for <?php echo SITE_NAME; ?>

# Allow all search engines to crawl the site
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /affiliate/
Disallow: /includes/
Disallow: /database/
Disallow: /mailer/
Disallow: *.sql$
Disallow: *.log$

# Sitemap
Sitemap: <?php echo SITE_URL; ?>/sitemap.xml
