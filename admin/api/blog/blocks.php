<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/blog/BlogBlock.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfToken) && !empty($_SESSION['csrf_token'])) {
    }
}

$db = getDb();
$blogBlock = new BlogBlock($db);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $postId = $input['post_id'] ?? null;
            $blockType = $input['block_type'] ?? null;
            
            if (!$postId || !$blockType) {
                throw new Exception('Post ID and block type required');
            }
            
            $blockId = $blogBlock->create([
                'post_id' => $postId,
                'block_type' => $blockType,
                'data_payload' => $input['data_payload'] ?? [],
                'layout_variant' => $input['layout_variant'] ?? 'default',
                'semantic_role' => $input['semantic_role'] ?? 'primary_content',
                'behavior_config' => $input['behavior_config'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'block_id' => $blockId]);
            break;
            
        case 'update':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $updateData = [];
            if (isset($input['data_payload'])) {
                $updateData['data_payload'] = $input['data_payload'];
            }
            if (isset($input['layout_variant'])) {
                $updateData['layout_variant'] = $input['layout_variant'];
            }
            if (isset($input['semantic_role'])) {
                $updateData['semantic_role'] = $input['semantic_role'];
            }
            if (isset($input['behavior_config'])) {
                $updateData['behavior_config'] = $input['behavior_config'];
            }
            if (isset($input['is_visible'])) {
                $updateData['is_visible'] = $input['is_visible'] ? 1 : 0;
            }
            
            if (!empty($updateData)) {
                $blogBlock->update($id, $updateData);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $blogBlock->delete($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $block = $blogBlock->getById($id);
            if (!$block) {
                throw new Exception('Block not found');
            }
            
            echo json_encode(['success' => true, 'block' => $block]);
            break;
            
        case 'get_by_post':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                throw new Exception('Post ID required');
            }
            
            $blocks = $blogBlock->getByPost($postId, false);
            echo json_encode(['success' => true, 'blocks' => $blocks]);
            break;
            
        case 'duplicate':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $newId = $blogBlock->duplicate($id);
            echo json_encode(['success' => true, 'block_id' => $newId]);
            break;
            
        case 'move_up':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $blogBlock->moveUp($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'move_down':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Block ID required');
            }
            
            $blogBlock->moveDown($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'reorder':
            $postId = $input['post_id'] ?? null;
            $orderedIds = $input['ordered_ids'] ?? [];
            
            if (!$postId || empty($orderedIds)) {
                throw new Exception('Post ID and ordered IDs required');
            }
            
            $blogBlock->reorder($postId, $orderedIds);
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
