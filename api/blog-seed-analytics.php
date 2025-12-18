<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Get all published posts
$stmt = $db->query("SELECT id FROM blog_posts WHERE status = 'published' ORDER BY RANDOM()");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    echo "No published posts found.\n";
    exit;
}

$insertStmt = $db->prepare("
    INSERT INTO blog_analytics (post_id, event_type, session_id, referrer, affiliate_code, user_agent, created_at)
    VALUES (?, ?, ?, ?, ?, ?, datetime('now', '-' || ? || ' days'))
");

$eventTypes = ['view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100', 'cta_click', 'share'];
$referrers = ['organic', 'direct', 'social', 'referral', 'email'];
$affiliateCodes = [null, null, null, 'aff_001', 'aff_002', 'aff_003', 'aff_004'];
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15)',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)',
    'Mozilla/5.0 (Linux; Android 11)',
];

$analyticsCount = 0;
$eventsPerPost = 3;

foreach ($posts as $post) {
    // Generate 5-15 events per post over past 30 days
    $eventCount = rand(5, 15);
    
    for ($i = 0; $i < $eventCount; $i++) {
        $sessionId = 'sess_' . bin2hex(random_bytes(8));
        $eventType = $eventTypes[array_rand($eventTypes)];
        $referrer = $referrers[array_rand($referrers)];
        $affiliateCode = $affiliateCodes[array_rand($affiliateCodes)];
        $userAgent = $userAgents[array_rand($userAgents)];
        $daysAgo = rand(1, 30);
        
        try {
            $insertStmt->execute([
                $post['id'],
                $eventType,
                $sessionId,
                $referrer,
                $affiliateCode,
                $userAgent,
                $daysAgo
            ]);
            $analyticsCount++;
        } catch (Exception $e) {
            // Ignore constraint violations
        }
    }
}

echo "Created $analyticsCount analytics events successfully.\n";

// Show summary
$summaryStmt = $db->query("
    SELECT 
        COUNT(*) as total_events,
        COUNT(DISTINCT post_id) as posts_tracked,
        COUNT(DISTINCT session_id) as unique_sessions,
        COUNT(DISTINCT CASE WHEN affiliate_code IS NOT NULL THEN affiliate_code END) as affiliate_codes
    FROM blog_analytics
");
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
echo "Summary: {$summary['total_events']} events, {$summary['posts_tracked']} posts, {$summary['unique_sessions']} sessions, {$summary['affiliate_codes']} affiliate codes\n";
