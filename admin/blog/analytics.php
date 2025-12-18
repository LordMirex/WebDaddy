<?php
$pageTitle = 'Blog Analytics';

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$filter = $_GET['filter'] ?? '7days';
$sortBy = $_GET['sort'] ?? 'views';

// Calculate date range
$dateRange = match($filter) {
    '7days' => 'WHERE ba.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    '30days' => 'WHERE ba.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90days' => 'WHERE ba.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
    default => ''
};

// Get overall blog stats
$statsQuery = $db->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.session_id END) as unique_visitors,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.post_id END) as posts_viewed,
        SUM(CASE WHEN ba.event_type = 'view' THEN 1 ELSE 0 END) as total_views,
        SUM(CASE WHEN ba.event_type = 'scroll_100' THEN 1 ELSE 0 END) as full_reads,
        SUM(CASE WHEN ba.event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks,
        SUM(CASE WHEN ba.event_type = 'share' THEN 1 ELSE 0 END) as shares,
        COUNT(DISTINCT ba.affiliate_code) as affiliate_referrers
    FROM blog_analytics ba
    $dateRange
");
$stats = $statsQuery->fetch_assoc();

// Get top posts
$sortColumn = match($sortBy) {
    'shares' => 'share_count',
    'cta_clicks' => 'cta_clicks',
    'engagement' => '(COALESCE(full_reads, 0) * 1.5 + COALESCE(cta_clicks, 0) * 3 + share_count)',
    default => 'view_count'
};

$topPostsQuery = $db->query("
    SELECT 
        bp.id,
        bp.title,
        bp.slug,
        bp.view_count,
        bp.share_count,
        bp.created_at,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.session_id END) as unique_visitors,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'scroll_100' THEN 1 END) as full_reads,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'cta_click' THEN 1 END) as cta_clicks,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'share' THEN 1 END) as shares,
        COUNT(DISTINCT ba.affiliate_code) as affiliate_hits,
        ROUND(COUNT(DISTINCT CASE WHEN ba.event_type = 'scroll_100' THEN 1 END) / 
              NULLIF(COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.session_id END), 0) * 100, 1) as read_rate
    FROM blog_posts bp
    LEFT JOIN blog_analytics ba ON bp.id = ba.post_id $dateRange
    WHERE bp.status = 'published'
    GROUP BY bp.id
    ORDER BY $sortColumn DESC
    LIMIT 50
");
$topPosts = [];
while ($post = $topPostsQuery->fetch_assoc()) {
    $topPosts[] = $post;
}

// Get scroll depth data
$scrollDepthQuery = $db->query("
    SELECT 
        CASE 
            WHEN ba.event_type = 'view' THEN '0-25%'
            WHEN ba.event_type = 'scroll_25' THEN '25-50%'
            WHEN ba.event_type = 'scroll_50' THEN '50-75%'
            WHEN ba.event_type = 'scroll_75' THEN '75-100%'
            WHEN ba.event_type = 'scroll_100' THEN '100%'
        END as depth,
        COUNT(*) as count
    FROM blog_analytics ba
    WHERE ba.event_type IN ('view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100')
    $dateRange
    GROUP BY ba.event_type
    ORDER BY FIELD(ba.event_type, 'view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100')
");
$scrollData = [];
while ($row = $scrollDepthQuery->fetch_assoc()) {
    $scrollData[] = $row;
}

// Get affiliate performance
$affiliateQuery = $db->query("
    SELECT 
        ba.affiliate_code,
        COUNT(DISTINCT ba.session_id) as sessions,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.post_id END) as posts_clicked,
        SUM(CASE WHEN ba.event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_conversions
    FROM blog_analytics ba
    WHERE ba.affiliate_code IS NOT NULL
    $dateRange
    GROUP BY ba.affiliate_code
    ORDER BY sessions DESC
    LIMIT 20
");
$affiliates = [];
while ($aff = $affiliateQuery->fetch_assoc()) {
    $affiliates[] = $aff;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📊 Blog Analytics</h1>
            <p class="text-gray-600 mt-1">Track blog performance and engagement</p>
        </div>
        <div class="flex gap-3">
            <select onchange="location.href='?filter=' + this.value + '&sort=<?php echo htmlspecialchars($sortBy); ?>'" class="border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="7days" <?php echo $filter === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $filter === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo $filter === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Time</option>
            </select>
            <select onchange="location.href='?filter=<?php echo htmlspecialchars($filter); ?>&sort=' + this.value" class="border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="views" <?php echo $sortBy === 'views' ? 'selected' : ''; ?>>Sort by Views</option>
                <option value="shares" <?php echo $sortBy === 'shares' ? 'selected' : ''; ?>>Sort by Shares</option>
                <option value="cta_clicks" <?php echo $sortBy === 'cta_clicks' ? 'selected' : ''; ?>>Sort by CTAs</option>
                <option value="engagement" <?php echo $sortBy === 'engagement' ? 'selected' : ''; ?>>Sort by Engagement</option>
            </select>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-primary-600">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Total Views</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2"><?php echo number_format($stats['posts_viewed'] ?? 0); ?> posts viewed</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-blue-500">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Unique Visitors</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['unique_visitors'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">New sessions</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-green-500">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Full Reads</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['full_reads'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2"><?php echo $stats['total_views'] ? round(($stats['full_reads'] ?? 0) / $stats['total_views'] * 100, 1) : 0; ?>% read rate</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-yellow-500">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">CTA Clicks</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['cta_clicks'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">Conversions</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-purple-500">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Shares</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['shares'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">Social engagement</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-l-red-500">
            <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Affiliate Hits</h3>
            <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['affiliate_referrers'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">Partner referrers</p>
        </div>
    </div>

    <!-- Top Posts -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Top Performing Posts</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Post Title</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Visitors</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Read Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">CTAs</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Shares</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Aff Hits</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($topPosts as $post): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 max-w-xs truncate">
                                <a href="editor.php?post_id=<?php echo $post['id']; ?>" class="text-primary-600 hover:text-primary-700">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($post['view_count']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($post['unique_visitors'] ?? 0); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo $post['read_rate'] ?? 0; ?>%</td>
                            <td class="px-6 py-4 text-sm"><span class="inline-block bg-yellow-100 text-yellow-800 px-2 py-1 rounded font-medium"><?php echo $post['cta_clicks'] ?? 0; ?></span></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($post['shares'] ?? 0); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($post['affiliate_hits'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Affiliate Performance -->
    <?php if (!empty($affiliates)): ?>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Top Affiliate Referrers</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Affiliate Code</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Sessions</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Posts Clicked</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">CTA Conversions</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($affiliates as $aff): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-mono text-gray-900"><?php echo htmlspecialchars($aff['affiliate_code']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($aff['sessions']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($aff['posts_clicked']); ?></td>
                            <td class="px-6 py-4 text-sm"><span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded font-medium"><?php echo $aff['cta_conversions']; ?></span></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo $aff['sessions'] ? round($aff['cta_conversions'] / $aff['sessions'] * 100, 1) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
