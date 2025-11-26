<?php
/**
 * Analytics Reporting API
 * Provides comprehensive analytics data for conversions, views, clicks, and user behavior
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/access_log.php';

$startTime = microtime(true);
$action = $_GET['action'] ?? 'summary';

try {
    $db = getDb();
    
    switch ($action) {
        case 'summary':
            // Overall analytics summary
            $today = date('Y-m-d');
            $lastMonth = date('Y-m-d', strtotime('-30 days'));
            
            // Total visits
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_visits WHERE visit_date >= ?");
            $stmt->execute([$lastMonth]);
            $totalVisits = $stmt->fetchColumn();
            
            // Unique sessions
            $stmt = $db->prepare("SELECT COUNT(DISTINCT session_id) as total FROM page_visits WHERE visit_date >= ?");
            $stmt->execute([$lastMonth]);
            $uniqueSessions = $stmt->fetchColumn();
            
            // Total product views
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_interactions WHERE action_type = 'view' AND DATE(CAST(datetime('now', '+1 hour') as text)) >= ?");
            $stmt->execute([$lastMonth]);
            $productViews = $stmt->fetchColumn() ?? 0;
            
            // Template views
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_interactions WHERE action_type = 'view' AND action_target = 'template' AND DATE(CAST(datetime('now', '+1 hour') as text)) >= ?");
            $stmt->execute([$lastMonth]);
            $templateViews = $stmt->fetchColumn() ?? 0;
            
            // Tool views
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_interactions WHERE action_type = 'view' AND action_target = 'tool' AND DATE(CAST(datetime('now', '+1 hour') as text)) >= ?");
            $stmt->execute([$lastMonth]);
            $toolViews = $stmt->fetchColumn() ?? 0;
            
            // Total clicks
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_interactions WHERE action_type IN ('click', 'button_click') AND DATE(CAST(datetime('now', '+1 hour') as text)) >= ?");
            $stmt->execute([$lastMonth]);
            $totalClicks = $stmt->fetchColumn() ?? 0;
            
            // Searches
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM page_interactions WHERE action_type = 'search' AND DATE(CAST(datetime('now', '+1 hour') as text)) >= ?");
            $stmt->execute([$lastMonth]);
            $searches = $stmt->fetchColumn() ?? 0;
            
            echo json_encode([
                'success' => true,
                'period' => 'last_30_days',
                'analytics' => [
                    'total_visits' => (int)$totalVisits,
                    'unique_sessions' => (int)$uniqueSessions,
                    'product_views' => (int)$productViews,
                    'template_views' => (int)$templateViews,
                    'tool_views' => (int)$toolViews,
                    'total_clicks' => (int)$totalClicks,
                    'searches_performed' => (int)$searches,
                    'avg_views_per_session' => (int)$uniqueSessions > 0 ? round($productViews / $uniqueSessions, 2) : 0
                ]
            ]);
            break;
            
        case 'top_templates':
            // Most viewed templates
            $limit = min((int)($_GET['limit'] ?? 10), 50);
            
            $stmt = $db->prepare("
                SELECT 
                    t.id, 
                    t.name,
                    COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END) as views,
                    COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) as clicks
                FROM templates t
                LEFT JOIN page_interactions pi ON t.id = pi.template_id AND pi.action_target = 'template'
                GROUP BY t.id
                ORDER BY views DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'type' => 'templates',
                'data' => $templates
            ]);
            break;
            
        case 'top_tools':
            // Most viewed tools
            $limit = min((int)($_GET['limit'] ?? 10), 50);
            
            $stmt = $db->prepare("
                SELECT 
                    t.id, 
                    t.name,
                    COUNT(CASE WHEN pi.action_type = 'view' THEN 1 END) as views,
                    COUNT(CASE WHEN pi.action_type = 'click' THEN 1 END) as clicks
                FROM tools t
                LEFT JOIN page_interactions pi ON t.id = pi.tool_id AND pi.action_target = 'tool'
                GROUP BY t.id
                ORDER BY views DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'type' => 'tools',
                'data' => $tools
            ]);
            break;
            
        case 'search_analytics':
            // Search term analytics
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            
            $stmt = $db->prepare("
                SELECT 
                    action_target as search_term,
                    COUNT(*) as searches,
                    SUM(CAST(time_spent as INTEGER)) as total_results
                FROM page_interactions
                WHERE action_type = 'search'
                GROUP BY action_target
                ORDER BY searches DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $searches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'searches' => $searches
            ]);
            break;
            
        case 'device_breakdown':
            // Traffic by device type
            $stmt = $db->prepare("
                SELECT 
                    device_type,
                    COUNT(*) as visits,
                    COUNT(DISTINCT session_id) as unique_users,
                    ROUND(100.0 * COUNT(*) / SUM(COUNT(*)) OVER (), 2) as percentage
                FROM page_visits
                WHERE visit_date >= DATE('now', '-30 days')
                GROUP BY device_type
                ORDER BY visits DESC
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'device_breakdown' => $devices
            ]);
            break;
            
        case 'conversion_funnel':
            // User journey / conversion funnel
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT CASE WHEN action_type = 'view' THEN session_id END) as viewers,
                    COUNT(DISTINCT CASE WHEN action_type IN ('click', 'button_click') THEN session_id END) as clickers,
                    COUNT(DISTINCT CASE WHEN action_type = 'order_start' THEN session_id END) as checkout_starters,
                    COUNT(DISTINCT CASE WHEN action_type = 'search' THEN session_id END) as searchers
                FROM page_interactions
                WHERE action_type IN ('view', 'click', 'button_click', 'order_start', 'search')
            ");
            $stmt->execute();
            $funnel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $viewers = (int)($funnel['viewers'] ?? 0);
            $clickers = (int)($funnel['clickers'] ?? 0);
            $starters = (int)($funnel['checkout_starters'] ?? 0);
            
            echo json_encode([
                'success' => true,
                'funnel' => [
                    'views' => $viewers,
                    'clicks' => $clickers,
                    'click_rate' => $viewers > 0 ? round(($clickers / $viewers) * 100, 2) . '%' : '0%',
                    'checkout_starters' => $starters,
                    'conversion_rate' => $viewers > 0 ? round(($starters / $viewers) * 100, 2) . '%' : '0%'
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Available: summary, top_templates, top_tools, search_analytics, device_breakdown, conversion_funnel'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Analytics API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Analytics query failed: ' . $e->getMessage()
    ]);
}

$duration = (microtime(true) - $startTime) * 1000;
logApiAccess('/api/analytics-report.php?action=' . $action, 'GET', 200, $duration);
rotateAccessLogs();
?>
