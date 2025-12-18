<?php
/**
 * Blog SEO Head - Include in <head> tag of all blog pages
 * Handles meta tags, schema markup, analytics, and OG tags
 */

if (!function_exists('blogInitSEO')) {
    function blogInitSEO($post = null, $pageType = 'blog-index', $customMeta = []) {
        global $seoData, $pageTitle, $pageDescription, $pageImage;
        
        if ($post && $pageType === 'post') {
            $seoData = blogGenerateSEOData($post);
            $pageTitle = $seoData['title'];
            $pageDescription = $seoData['description'];
            $pageImage = $seoData['og']['image'];
        } elseif ($pageType === 'category' && isset($customMeta['title'])) {
            $seoData = $customMeta;
            $pageTitle = $customMeta['title'];
            $pageDescription = $customMeta['description'];
            $pageImage = $customMeta['image'] ?? '';
        } else {
            $seoData = $customMeta ?? [];
            $pageTitle = $customMeta['title'] ?? 'WebDaddy Blog';
            $pageDescription = $customMeta['description'] ?? 'Enterprise SEO-first blog for Nigerian businesses';
            $pageImage = $customMeta['image'] ?? SITE_URL . '/assets/images/og-blog.jpg';
        }
    }
}

// Render SEO meta tags
echo blogRenderMetaTags($seoData ?? []);

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
