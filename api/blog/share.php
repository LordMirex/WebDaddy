<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'update':
            $post_id = $input['post_id'] ?? null;
            $platform = $input['platform'] ?? null;

            if (!$post_id) {
                throw new Exception('Post ID required');
            }

            $valid_platforms = ['whatsapp', 'twitter', 'facebook', 'linkedin', 'copy'];
            if ($platform && !in_array($platform, $valid_platforms)) {
                throw new Exception('Invalid platform');
            }

            $session_id = session_id();
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $event_type = 'share';

            $stmt = $db->prepare('
                INSERT INTO blog_analytics 
                (post_id, event_type, session_id, referrer, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $user_agent
            ]);

            $updateStmt = $db->prepare('
                UPDATE blog_posts 
                SET share_count = share_count + 1
                WHERE id = ?
            ');
            $updateStmt->execute([$post_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Share recorded',
                'platform' => $platform
            ]);
            break;

        case 'get_counts':
            $post_id = $input['post_id'] ?? $_GET['post_id'] ?? null;

            if (!$post_id) {
                throw new Exception('Post ID required');
            }

            $stmt = $db->prepare('SELECT view_count, share_count FROM blog_posts WHERE id = ?');
            $stmt->execute([$post_id]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'counts' => $counts ?? ['view_count' => 0, 'share_count' => 0]
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
