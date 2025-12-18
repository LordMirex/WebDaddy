<?php
/**
 * Blog SEO Head - Include in <head> tag of all blog pages
 * Handles meta tags, schema markup, analytics, and OG tags
 * Requires: includes/blog/schema.php to be loaded
 */

// Ensure functions are available
require_once __DIR__ . '/../includes/blog/schema.php';

// Initialize SEO data - global variable for use in page templates
global $seoData;
if (!isset($seoData) || empty($seoData)) {
    // Default SEO for blog index/list pages
    $seoData = [
        'title' => 'WebDaddy Blog | Enterprise SEO & Digital Tips for Nigerian Businesses',
        'description' => 'Industry insights, SEO strategies, and digital marketing tips for Nigerian businesses. Premium content from WebDaddy Empire.',
        'canonical' => SITE_URL . '/blog/',
        'og' => [
            'title' => 'WebDaddy Blog',
            'description' => 'Enterprise SEO-first blog for Nigerian businesses',
            'image' => SITE_URL . '/assets/images/og-blog.jpg',
            'type' => 'website',
            'url' => SITE_URL . '/blog/'
        ],
        'twitter' => [
            'card' => 'summary_large_image',
            'title' => 'WebDaddy Blog',
            'description' => 'SEO & digital marketing insights for Nigerian businesses',
            'image' => SITE_URL . '/assets/images/og-blog.jpg'
        ]
    ];
}

// Render all SEO meta tags
echo blogRenderMetaTags($seoData);

// Initialize analytics with session
if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

// Track page view
?>
<script>
(function() {
    'use strict';
    
    // Set session ID
    sessionStorage.setItem('session_id', '<?php echo htmlspecialchars(session_id()); ?>');
    
    // Track affiliate code
    const params = new URLSearchParams(window.location.search);
    const affCode = params.get('aff');
    if (affCode) {
        sessionStorage.setItem('affiliate_code', affCode);
    }
    
    // Track page view
    const postId = document.querySelector('[data-post-id]')?.getAttribute('data-post-id');
    if (postId) {
        fetch('/api/blog/analytics.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'track',
                post_id: postId,
                event_type: 'view'
            })
        }).catch(err => console.log('Analytics initialized'));
    }
})();
</script>
