<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);
header('Access-Control-Allow-Origin: ' . SITE_URL);

startSecureSession();

$db = getDb();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'track':
            $post_id = $input['post_id'] ?? null;
            $event_type = $input['event_type'] ?? 'view';
            $session_id = $input['session_id'] ?? session_id();
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $affiliate_code = $input['affiliate_code'] ?? $_SESSION['affiliate_code'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if (!$post_id) {
                throw new Exception('Post ID required');
            }

            // Validate event type
            $valid_events = ['view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100', 'cta_click', 'share', 'template_click'];
            if (!in_array($event_type, $valid_events)) {
                throw new Exception('Invalid event type');
            }

            // Insert analytics record
            $stmt = $db->prepare('
                INSERT INTO blog_analytics 
                (post_id, event_type, session_id, referrer, affiliate_code, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->bind_param(
                'isssss',
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $affiliate_code,
                $user_agent
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to record analytics');
            }

            // Update view count for 'view' events
            if ($event_type === 'view') {
                $updateStmt = $db->prepare('
                    UPDATE blog_posts 
                    SET view_count = view_count + 1, updated_at = NOW()
                    WHERE id = ?
                ');
                $updateStmt->bind_param('i', $post_id);
                $updateStmt->execute();
            }

            // Update share count for 'share' events
            if ($event_type === 'share') {
                $updateStmt = $db->prepare('
                    UPDATE blog_posts 
                    SET share_count = share_count + 1, updated_at = NOW()
                    WHERE id = ?
                ');
                $updateStmt->bind_param('i', $post_id);
                $updateStmt->execute();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Event tracked',
                'event' => $event_type,
                'post_id' => $post_id
            ]);
            break;

        case 'scroll':
            $post_id = $input['post_id'] ?? null;
            $scroll_percent = $input['scroll_percent'] ?? 0;

            if (!$post_id || $scroll_percent < 0 || $scroll_percent > 100) {
                throw new Exception('Invalid parameters');
            }

            // Map scroll percentage to event
            $event_type = 'view';
            if ($scroll_percent >= 25 && $scroll_percent < 50) $event_type = 'scroll_25';
            elseif ($scroll_percent >= 50 && $scroll_percent < 75) $event_type = 'scroll_50';
            elseif ($scroll_percent >= 75 && $scroll_percent < 100) $event_type = 'scroll_75';
            elseif ($scroll_percent >= 100) $event_type = 'scroll_100';

            $session_id = session_id();
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $affiliate_code = $_SESSION['affiliate_code'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmt = $db->prepare('
                INSERT INTO blog_analytics 
                (post_id, event_type, session_id, referrer, affiliate_code, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ');

            $stmt->bind_param(
                'isssss',
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $affiliate_code,
                $user_agent
            );

            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Scroll event recorded',
                'scroll_percent' => $scroll_percent
            ]);
            break;

        case 'stats':
            $post_id = $input['post_id'] ?? $_GET['post_id'] ?? null;

            if (!$post_id) {
                throw new Exception('Post ID required');
            }

            // Get post stats
            $stmt = $db->prepare('
                SELECT 
                    view_count, 
                    share_count,
                    (SELECT COUNT(*) FROM blog_analytics WHERE post_id = ? AND event_type = "view") as total_views,
                    (SELECT COUNT(*) FROM blog_analytics WHERE post_id = ? AND event_type = "cta_click") as cta_clicks,
                    (SELECT COUNT(DISTINCT session_id) FROM blog_analytics WHERE post_id = ?) as unique_visitors
                FROM blog_posts 
                WHERE id = ?
            ');

            $stmt->bind_param('iiii', $post_id, $post_id, $post_id, $post_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        case 'top_posts':
            $limit = $input['limit'] ?? 10;
            $days = $input['days'] ?? 30;

            $limit = min($limit, 50); // Cap at 50

            $result = $db->query("
                SELECT 
                    p.id,
                    p.title,
                    p.slug,
                    p.view_count,
                    p.share_count,
                    (SELECT COUNT(*) FROM blog_analytics WHERE post_id = p.id AND event_type = 'view' AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)) as recent_views,
                    (SELECT COUNT(DISTINCT session_id) FROM blog_analytics WHERE post_id = p.id AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)) as recent_unique
                FROM blog_posts p
                WHERE p.status = 'published'
                ORDER BY recent_views DESC
                LIMIT $limit
            ");

            $posts = [];
            while ($row = $result->fetch_assoc()) {
                $posts[] = $row;
            }

            echo json_encode([
                'success' => true,
                'posts' => $posts
            ]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
