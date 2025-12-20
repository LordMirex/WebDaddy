<?php
/**
 * CTA (Call-To-Action) Builder
 * Generates strategic CTAs for content pages
 */

function generateBlogPostCTA($postId, $postTitle, $category = '') {
    $ctaOptions = [
        'template_upsell' => [
            'title' => 'Ready to Create Your Website?',
            'description' => 'Use our professional templates to launch your site in minutes',
            'button_text' => 'Browse Templates',
            'icon' => 'bi-layout-wtf',
            'link' => '/?view=templates#templates'
        ],
        'tool_showcase' => [
            'title' => 'Try Our Tools',
            'description' => 'Explore digital tools to boost your business',
            'button_text' => 'See All Tools',
            'icon' => 'bi-tools',
            'link' => '/?view=tools#tools'
        ],
        'newsletter' => [
            'title' => 'Stay Updated',
            'description' => 'Get the latest tips and updates directly to your email',
            'button_text' => 'Join Newsletter',
            'icon' => 'bi-envelope-check',
            'link' => '#newsletter-popup'
        ],
        'affiliate' => [
            'title' => 'Earn with Us',
            'description' => 'Join our affiliate program and earn commissions',
            'button_text' => 'Learn More',
            'icon' => 'bi-percent',
            'link' => '/affiliate'
        ]
    ];
    
    // Rotate CTAs to avoid banner blindness
    $ctaKey = array_keys($ctaOptions)[($postId % count($ctaOptions))];
    return $ctaOptions[$ctaKey];
}

function renderCTA($cta, $position = 'inline') {
    $classes = [
        'inline' => 'bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-200',
        'sidebar' => 'bg-gradient-to-br from-amber-500 to-orange-500 text-white',
        'footer' => 'bg-gray-900 text-white'
    ];
    
    $bgClass = $classes[$position] ?? $classes['inline'];
    
    return "
    <div class='cta-block $position $bgClass rounded-lg p-6 my-6 shadow-lg hover:shadow-xl transition-all'>
        <div class='flex items-start gap-4'>
            <i class='bi {$cta['icon']} text-2xl'></i>
            <div class='flex-1'>
                <h3 class='font-bold text-lg mb-2'>{$cta['title']}</h3>
                <p class='text-sm mb-4 opacity-90'>{$cta['description']}</p>
                <a href='{$cta['link']}' class='inline-block px-6 py-2 rounded-lg font-semibold transition-colors' 
                   onclick='trackCTAClick(this)'>
                    {$cta['button_text']} →
                </a>
            </div>
        </div>
    </div>
    ";
}

function generateProductRecommendation($type = 'template', $limit = 3) {
    // Generate smart product recommendations based on content
    return [
        'type' => $type,
        'title' => $type === 'template' ? 'Related Templates' : 'Helpful Tools',
        'limit' => $limit
    ];
}

function trackCTAClick($ctaType, $postId = null) {
    // Client-side JS call - see below
    return "
    <script>
    function trackCTAClick(element) {
        fetch('/includes/monetization/tracking.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=track_click&link_type=cta&link_id=$ctaType&post_id=$postId'
        }).catch(e => console.log('Tracking:', e));
    }
    </script>
    ";
}

/**
 * Sidebar CTA Manager
 * Rotates CTAs in the blog sidebar
 */
function getSidebarCTA($postId) {
    $rotation = $postId % 4;
    
    $ctaConfigs = [
        0 => ['type' => 'template_upsell', 'prominent' => true],
        1 => ['type' => 'newsletter', 'prominent' => true],
        2 => ['type' => 'affiliate', 'prominent' => false],
        3 => ['type' => 'tool_showcase', 'prominent' => false]
    ];
    
    return $ctaConfigs[$rotation];
}

/**
 * Ad Space Placeholder
 * Ready for Google AdSense integration
 */
function renderAdSpace($position = 'in_content', $size = 'responsive') {
    $sizes = [
        'responsive' => '100%',
        'leaderboard' => '728x90',
        'rectangle' => '300x250',
        'mobile' => '320x50'
    ];
    
    return "
    <!-- Google AdSense Space: $position ($size) -->
    <div id='ad-$position' class='ad-space my-4 bg-gray-100 rounded p-4 text-center text-gray-500 text-sm'>
        <p>Advertisement Space</p>
        <small>Ads will display here when Google AdSense is connected</small>
    </div>
    <script>
        // Google AdSense will replace this div
        // (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    ";
}

?>
