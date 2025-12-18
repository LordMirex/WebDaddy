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
            $platform = $input['platform'] ?? null; // whatsapp, twitter, facebook, linkedin, copy

            if (!$post_id) {
                throw new Exception('Post ID required');
            }

            $valid_platforms = ['whatsapp', 'twitter', 'facebook', 'linkedin', 'copy'];
            if ($platform && !in_array($platform, $valid_platforms)) {
                throw new Exception('Invalid platform');
            }

            // Log share event
            $session_id = session_id();
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $event_type = 'share';

            $stmt = $db->prepare('
                INSERT INTO blog_analytics 
                (post_id, event_type, session_id, referrer, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ');

            $stmt->bind_param(
                'issss',
                $post_id,
                $event_type,
                $session_id,
                $referrer,
                $user_agent
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to record share');
            }

            // Update share count
            $updateStmt = $db->prepare('
                UPDATE blog_posts 
                SET share_count = share_count + 1, updated_at = NOW()
                WHERE id = ?
            ');
            $updateStmt->bind_param('i', $post_id);
            $updateStmt->execute();

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

            $stmt = $db->prepare('
                SELECT view_count, share_count FROM blog_posts WHERE id = ?
            ');
            $stmt->bind_param('i', $post_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $counts = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'counts' => $counts
            ]);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
