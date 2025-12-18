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

            $valid_events = ['view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100', 'cta_click', 'share', 'template_click'];
            if (!in_array($event_type, $valid_events)) {
                throw new Exception('Invalid event type');
            }

            $stmt = $db->prepare('
                INSERT INTO blog_analytics 
                (post_id, event_type, session_id, referrer, affiliate_code, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $affiliate_code,
                $user_agent
            ]);

            if ($event_type === 'view') {
                $updateStmt = $db->prepare('
                    UPDATE blog_posts 
                    SET view_count = view_count + 1
                    WHERE id = ?
                ');
                $updateStmt->execute([$post_id]);
            }

            if ($event_type === 'share') {
                $updateStmt = $db->prepare('
                    UPDATE blog_posts 
                    SET share_count = share_count + 1
                    WHERE id = ?
                ');
                $updateStmt->execute([$post_id]);
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

            $stmt->execute([
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $affiliate_code,
                $user_agent
            ]);

            echo json_encode([
                'success' => true,
                'event' => $event_type,
                'scroll_percent' => $scroll_percent
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
