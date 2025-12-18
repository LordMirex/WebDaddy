<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/blog/BlogTag.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$db = getDb();
$blogTag = new BlogTag($db);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $tagId = $blogTag->create([
                'name' => $input['name'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'tag_id' => $tagId]);
            break;
            
        case 'delete':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Tag ID required');
            }
            
            $blogTag->delete($id);
            echo json_encode(['success' => true, 'message' => 'Tag deleted']);
            break;
            
        case 'list':
            $tags = $blogTag->getAll();
            echo json_encode(['success' => true, 'tags' => $tags]);
            break;
            
        case 'get':
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Tag ID required');
            }
            
            $tag = $blogTag->getById($id);
            echo json_encode(['success' => true, 'tag' => $tag]);
            break;
            
        case 'get_by_post':
            $postId = $input['post_id'] ?? $_GET['post_id'] ?? null;
            if (!$postId) {
                throw new Exception('Post ID required');
            }
            
            $tags = $blogTag->getByPost($postId);
            echo json_encode(['success' => true, 'tags' => $tags]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
