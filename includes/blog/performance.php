<?php
// Phase 5: Performance Optimization Functions

/**
 * Set aggressive caching headers for static assets
 */
function setBlogCacheHeaders($maxAge = 2592000) {
    header('Cache-Control: public, max-age=' . $maxAge, false);
    header('Pragma: cache', false);
}

/**
 * Set no-cache headers for dynamic blog content
 */
function setNoCacheHeaders() {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0', false);
    header('Pragma: no-cache', false);
    header('Expires: 0', false);
}

/**
 * Optimize image output with lazy loading attributes
 */
function getBlogImageHTML($src, $alt, $width = null, $height = null, $class = '') {
    $html = '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '"';
    if ($width) $html .= ' width="' . intval($width) . '"';
    if ($height) $html .= ' height="' . intval($height) . '"';
    $html .= ' loading="lazy" decoding="async" class="' . htmlspecialchars($class) . '">';
    return $html;
}

/**
 * Inline critical CSS for above-the-fold content
 */
function inlineCriticalCSS() {
    $criticalCSS = <<<'CSS'
    body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; line-height: 1.6; color: #1a1a1a; }
    article { max-width: 800px; margin: 0 auto; padding: 2rem; }
    h1, h2, h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; margin: 1.5rem 0 1rem; }
    h1 { font-size: 2.5rem; line-height: 1.2; }
    h2 { font-size: 1.875rem; }
    h3 { font-size: 1.25rem; }
    p { margin: 1rem 0; }
    CSS;
    echo '<style>' . $criticalCSS . '</style>';
}

/**
 * Generate WebP alternative images for modern browsers
 */
function getResponsiveImage($src, $alt, $sizes = 'sizes="(max-width: 768px) 100vw, 800px"') {
    $jpg = htmlspecialchars($src);
    $webp = str_replace(['.jpg', '.jpeg', '.png'], '.webp', $jpg);
    
    return <<<HTML
    <picture>
        <source srcset="$webp" type="image/webp" $sizes>
        <img src="$jpg" alt="$alt" $sizes loading="lazy" decoding="async">
    </picture>
    HTML;
}

/**
 * Defer non-critical JavaScript until page is fully interactive
 */
function deferScript($src) {
    echo '<script src="' . htmlspecialchars($src) . '" defer></script>';
}

/**
 * Get Core Web Vitals metrics from analytics
 */
function getBlogCoreWebVitals($db, $postId, $days = 7) {
    $stmt = $db->prepare("
        SELECT 
            AVG(metric_value) as average,
            MAX(metric_value) as max,
            MIN(metric_value) as min
        FROM blog_performance_metrics
        WHERE post_id = ? AND metric_name = ? 
        AND created_at >= datetime('now', '-' || ? || ' days')
    ");
    
    $metrics = [];
    
    // LCP (Largest Contentful Paint)
    $stmt->execute([$postId, 'lcp', $days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['lcp'] = $result['average'] ?? 0; // Target: < 2.5s
    
    // FID (First Input Delay)
    $stmt->execute([$postId, 'fid', $days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['fid'] = $result['average'] ?? 0; // Target: < 100ms
    
    // CLS (Cumulative Layout Shift)
    $stmt->execute([$postId, 'cls', $days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['cls'] = $result['average'] ?? 0; // Target: < 0.1
    
    return $metrics;
}
?>
