<?php
/**
 * Blog Analytics Dashboard
 * Admin interface for viewing blog performance metrics
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/helpers.php';

startSecureSession();

// Verify admin access
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$db = getDb();
$filter = $_GET['filter'] ?? '7days'; // 7days, 30days, 90days, all
$sortBy = $_GET['sort'] ?? 'views'; // views, shares, cta_clicks, engagement
$postId = $_GET['post'] ?? null;

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

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Analytics - Admin | WebDaddy</title>
    <link rel="stylesheet" href="/assets/css/premium.css">
    <style>
        .admin-analytics {
            padding: 20px;
            background: #f5f5f5;
        }
        .analytics-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .analytics-header h1 {
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .filter-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-controls select,
        .filter-controls button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }
        .filter-controls button:hover {
            background: #f0f0f0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #d4af37;
        }
        .stat-card .subtext {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-container h2 {
            padding: 20px 20px 10px;
            margin: 0;
            font-size: 18px;
            border-bottom: 1px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f9f9f9;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #eee;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .post-title {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bar {
            height: 20px;
            background: linear-gradient(90deg, #d4af37 0%, #c89c3c 100%);
            border-radius: 3px;
            display: inline-block;
            min-width: 30px;
            color: white;
            font-size: 11px;
            padding: 2px 5px;
        }
        .scroll-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 10px;
            margin: 20px 0;
        }
        .scroll-bar {
            flex: 1;
            background: #d4af37;
            border-radius: 4px 4px 0 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 0;
            min-height: 50px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-analytics">
        <div class="analytics-header">
            <h1>📊 Blog Analytics Dashboard</h1>
            <div class="filter-controls">
                <select onchange="location.href='?filter=' + this.value + '&sort=<?= htmlspecialchars($sortBy) ?>'">
                    <option value="7days" <?= $filter === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30days" <?= $filter === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="90days" <?= $filter === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
                <select onchange="location.href='?filter=<?= htmlspecialchars($filter) ?>&sort=' + this.value">
                    <option value="views" <?= $sortBy === 'views' ? 'selected' : '' ?>>Sort by Views</option>
                    <option value="shares" <?= $sortBy === 'shares' ? 'selected' : '' ?>>Sort by Shares</option>
                    <option value="cta_clicks" <?= $sortBy === 'cta_clicks' ? 'selected' : '' ?>>Sort by CTA Clicks</option>
                    <option value="engagement" <?= $sortBy === 'engagement' ? 'selected' : '' ?>>Sort by Engagement</option>
                </select>
            </div>
        </div>

        <!-- Overall Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Views</h3>
                <div class="value"><?= number_format($stats['total_views'] ?? 0) ?></div>
                <div class="subtext"><?= number_format($stats['posts_viewed'] ?? 0) ?> posts viewed</div>
            </div>
            <div class="stat-card">
                <h3>Unique Visitors</h3>
                <div class="value"><?= number_format($stats['unique_visitors'] ?? 0) ?></div>
                <div class="subtext">New sessions</div>
            </div>
            <div class="stat-card">
                <h3>Full Reads</h3>
                <div class="value"><?= number_format($stats['full_reads'] ?? 0) ?></div>
                <div class="subtext"><?= $stats['total_views'] ? round(($stats['full_reads'] ?? 0) / $stats['total_views'] * 100, 1) : 0 ?>% read rate</div>
            </div>
            <div class="stat-card">
                <h3>CTA Clicks</h3>
                <div class="value"><?= number_format($stats['cta_clicks'] ?? 0) ?></div>
                <div class="subtext">Conversions</div>
            </div>
            <div class="stat-card">
                <h3>Shares</h3>
                <div class="value"><?= number_format($stats['shares'] ?? 0) ?></div>
                <div class="subtext">Social engagement</div>
            </div>
            <div class="stat-card">
                <h3>Affiliate Hits</h3>
                <div class="value"><?= number_format($stats['affiliate_referrers'] ?? 0) ?></div>
                <div class="subtext">Partner referrers</div>
            </div>
        </div>

        <!-- Scroll Depth Chart -->
        <div class="table-container">
            <h2>Scroll Depth Analysis</h2>
            <div style="padding: 20px;">
                <div class="scroll-chart">
                    <?php foreach ($scrollData as $depth): ?>
                    <div style="flex: 1; text-align: center;">
                        <div class="scroll-bar" style="height: <?= max(50, ($depth['count'] / max(array_column($scrollData, 'count'), 1)) * 180) ?>px">
                            <?= $depth['count'] ?>
                        </div>
                        <div style="margin-top: 10px; font-size: 12px; color: #666;">
                            <?= htmlspecialchars($depth['depth']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Posts Table -->
        <div class="table-container">
            <h2>Top Performing Posts</h2>
            <table>
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Views</th>
                        <th>Unique Visitors</th>
                        <th>Read Rate</th>
                        <th>CTA Clicks</th>
                        <th>Shares</th>
                        <th>Affiliate Hits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPosts as $post): ?>
                    <tr>
                        <td class="post-title">
                            <a href="/admin/blog/editor.php?id=<?= $post['id'] ?>" style="color: #333; text-decoration: none;">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </td>
                        <td><?= number_format($post['view_count']) ?></td>
                        <td><?= number_format($post['unique_visitors'] ?? 0) ?></td>
                        <td><?= $post['read_rate'] ?? 0 ?>%</td>
                        <td><span class="bar"><?= $post['cta_clicks'] ?? 0 ?></span></td>
                        <td><?= number_format($post['shares'] ?? 0) ?></td>
                        <td><?= number_format($post['affiliate_hits'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Affiliate Performance -->
        <?php if (!empty($affiliates)): ?>
        <div class="table-container">
            <h2>Top Affiliate Referrers</h2>
            <table>
                <thead>
                    <tr>
                        <th>Affiliate Code</th>
                        <th>Sessions</th>
                        <th>Posts Clicked</th>
                        <th>CTA Conversions</th>
                        <th>Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affiliates as $aff): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($aff['affiliate_code']) ?></code></td>
                        <td><?= number_format($aff['sessions']) ?></td>
                        <td><?= number_format($aff['posts_clicked']) ?></td>
                        <td><span class="bar"><?= $aff['cta_conversions'] ?></span></td>
                        <td><?= $aff['sessions'] ? round($aff['cta_conversions'] / $aff['sessions'] * 100, 1) : 0 ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
