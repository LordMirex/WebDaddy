<?php
$pageTitle = 'Blog Analytics';

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();
requireAdmin();

$db = getDb();
$filter = $_GET['filter'] ?? '7days';
$sortBy = $_GET['sort'] ?? 'views';

// Calculate date range for SQLite
$dateCondition = '';
if ($filter === '7days') {
    $dateCondition = "AND ba.created_at >= datetime('now', '-7 days')";
} elseif ($filter === '30days') {
    $dateCondition = "AND ba.created_at >= datetime('now', '-30 days')";
} elseif ($filter === '90days') {
    $dateCondition = "AND ba.created_at >= datetime('now', '-90 days')";
}

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
    WHERE 1=1 $dateCondition
");
$stats = $statsQuery ? $statsQuery->fetch(PDO::FETCH_ASSOC) : null;
if (!$stats) $stats = ['unique_visitors' => 0, 'posts_viewed' => 0, 'total_views' => 0, 'full_reads' => 0, 'cta_clicks' => 0, 'shares' => 0, 'affiliate_referrers' => 0];

// Build order clause for SQLite
$orderBy = 'bp.view_count DESC';
if ($sortBy === 'shares') {
    $orderBy = 'bp.share_count DESC';
} elseif ($sortBy === 'cta_clicks') {
    $orderBy = 'COUNT(DISTINCT CASE WHEN ba.event_type = "cta_click" THEN 1 END) DESC';
} elseif ($sortBy === 'engagement') {
    $orderBy = '(COALESCE(SUM(CASE WHEN ba.event_type = "scroll_100" THEN 1 ELSE 0 END), 0) * 1.5 + COALESCE(COUNT(DISTINCT CASE WHEN ba.event_type = "cta_click" THEN 1 ELSE 0 END), 0) * 3 + COALESCE(bp.share_count, 0)) DESC';
}

// Get top posts
$topPostsQuery = $db->query("
    SELECT 
        bp.id,
        bp.title,
        bp.slug,
        bp.view_count,
        bp.share_count,
        bp.created_at,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.session_id END) as unique_visitors,
        SUM(CASE WHEN ba.event_type = 'scroll_100' THEN 1 ELSE 0 END) as full_reads,
        SUM(CASE WHEN ba.event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks,
        SUM(CASE WHEN ba.event_type = 'share' THEN 1 ELSE 0 END) as shares,
        COUNT(DISTINCT ba.affiliate_code) as affiliate_hits,
        ROUND(CAST(SUM(CASE WHEN ba.event_type = 'scroll_100' THEN 1 ELSE 0 END) AS FLOAT) / NULLIF(COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.session_id END), 0) * 100, 1) as read_rate
    FROM blog_posts bp
    LEFT JOIN blog_analytics ba ON bp.id = ba.post_id AND ba.created_at >= datetime('now', '-' || COALESCE(CAST((CASE WHEN '$filter' = '30days' THEN '30' WHEN '$filter' = '90days' THEN '90' ELSE '7' END) AS TEXT) || ' days', '7 days') || '', 'unixepoch')
    WHERE bp.status = 'published'
    GROUP BY bp.id
    ORDER BY $orderBy
    LIMIT 50
");
$topPosts = [];
if ($topPostsQuery) {
    while ($post = $topPostsQuery->fetch(PDO::FETCH_ASSOC)) {
        $topPosts[] = $post;
    }
}

// Get scroll depth data
$scrollData = [
    ['depth' => '0-25%', 'count' => 0],
    ['depth' => '25-50%', 'count' => 0],
    ['depth' => '50-75%', 'count' => 0],
    ['depth' => '75-100%', 'count' => 0],
    ['depth' => '100%', 'count' => 0],
];

$scrollQuery = $db->query("
    SELECT 
        ba.event_type,
        COUNT(*) as count
    FROM blog_analytics ba
    WHERE ba.event_type IN ('view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100') $dateCondition
    GROUP BY ba.event_type
");

if ($scrollQuery) {
    while ($row = $scrollQuery->fetch(PDO::FETCH_ASSOC)) {
        $map = ['view' => 0, 'scroll_25' => 1, 'scroll_50' => 2, 'scroll_75' => 3, 'scroll_100' => 4];
        if (isset($map[$row['event_type']])) {
            $scrollData[$map[$row['event_type']]]['count'] = $row['count'];
        }
    }
}

// Get affiliate performance
$affiliates = [];
$affiliateQuery = $db->query("
    SELECT 
        ba.affiliate_code,
        COUNT(DISTINCT ba.session_id) as sessions,
        COUNT(DISTINCT CASE WHEN ba.event_type = 'view' THEN ba.post_id END) as posts_clicked,
        SUM(CASE WHEN ba.event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_conversions
    FROM blog_analytics ba
    WHERE ba.affiliate_code IS NOT NULL $dateCondition
    GROUP BY ba.affiliate_code
    ORDER BY sessions DESC
    LIMIT 20
");

if ($affiliateQuery) {
    while ($aff = $affiliateQuery->fetch(PDO::FETCH_ASSOC)) {
        $affiliates[] = $aff;
    }
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
            <select onchange="location.href='?filter=' + this.value + '&sort=<?php echo htmlspecialchars($sortBy); ?>'" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="7days" <?php echo $filter === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $filter === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo $filter === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Time</option>
            </select>
            <select onchange="location.href='?filter=<?php echo htmlspecialchars($filter); ?>&sort=' + this.value" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="views" <?php echo $sortBy === 'views' ? 'selected' : ''; ?>>Sort by Views</option>
                <option value="shares" <?php echo $sortBy === 'shares' ? 'selected' : ''; ?>>Sort by Shares</option>
                <option value="cta_clicks" <?php echo $sortBy === 'cta_clicks' ? 'selected' : ''; ?>>Sort by CTAs</option>
                <option value="engagement" <?php echo $sortBy === 'engagement' ? 'selected' : ''; ?>>Sort by Engagement</option>
            </select>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg p-6 shadow border-l-4 border-l-blue-600">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Views</h3>
            <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2"><?php echo number_format($stats['posts_viewed'] ?? 0); ?> posts viewed</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow border-l-4 border-l-green-600">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Unique Visitors</h3>
            <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['unique_visitors'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">New sessions</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow border-l-4 border-l-purple-600">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Full Reads</h3>
            <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['full_reads'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2"><?php echo ($stats['total_views'] ?? 0) > 0 ? round(($stats['full_reads'] ?? 0) / $stats['total_views'] * 100, 1) : 0; ?>% read rate</p>
        </div>
        
        <div class="bg-white rounded-lg p-6 shadow border-l-4 border-l-orange-600">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">CTA Clicks</h3>
            <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['cta_clicks'] ?? 0); ?></div>
            <p class="text-xs text-gray-500 mt-2">Conversion actions</p>
        </div>
    </div>

    <!-- Top Posts Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Top Performing Posts</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Post Title</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Views</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Read Rate</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">CTAs</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Shares</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Affiliate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (count($topPosts) > 0): ?>
                        <?php foreach ($topPosts as $post): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="text-blue-600 hover:underline font-medium">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-900 font-semibold"><?php echo number_format($post['view_count'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-center text-gray-600"><?php echo $post['read_rate'] ?? 0; ?>%</td>
                                <td class="px-6 py-4 text-center text-gray-600"><?php echo number_format($post['cta_clicks'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-center text-gray-600"><?php echo number_format($post['share_count'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-center text-gray-600"><?php echo number_format($post['affiliate_hits'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                No analytics data available for this period
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Scroll Depth Chart -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Scroll Depth Distribution</h2>
        <div class="space-y-3">
            <?php foreach ($scrollData as $depth): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600"><?php echo htmlspecialchars($depth['depth']); ?></span>
                        <span class="text-gray-900 font-semibold"><?php echo number_format($depth['count']); ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $stats['total_views'] > 0 ? round($depth['count'] / $stats['total_views'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Affiliate Performance -->
    <?php if (count($affiliates) > 0): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Affiliate Performance</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Affiliate Code</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Sessions</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Posts Clicked</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">CTA Conversions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($affiliates as $aff): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-sm text-gray-900"><?php echo htmlspecialchars($aff['affiliate_code'] ?? 'Direct'); ?></td>
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold"><?php echo number_format($aff['sessions'] ?? 0); ?></td>
                            <td class="px-6 py-4 text-center text-gray-600"><?php echo number_format($aff['posts_clicked'] ?? 0); ?></td>
                            <td class="px-6 py-4 text-center text-gray-600"><?php echo number_format($aff['cta_conversions'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
