<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/blog/BlogCategory.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$db = getDb();
$blogCategory = new BlogCategory($db);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $categoryId = $blogCategory->create([
                'name' => $input['name'] ?? null,
                'description' => $input['description'] ?? null,
                'parent_id' => $input['parent_id'] ?? null,
                'meta_title' => $input['meta_title'] ?? null,
                'meta_description' => $input['meta_description'] ?? null,
                'display_order' => $input['display_order'] ?? 0,
                'is_active' => $input['is_active'] ?? 1
            ]);
            
            echo json_encode(['success' => true, 'category_id' => $categoryId]);
            break;
            
        case 'update':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Category ID required');
            }
            
            $updateData = [];
            $fields = ['name', 'description', 'parent_id', 'meta_title', 'meta_description', 'display_order', 'is_active'];
            
            foreach ($fields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            $blogCategory->update($id, $updateData);
            echo json_encode(['success' => true, 'message' => 'Category updated']);
            break;
            
        case 'delete':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Category ID required');
            }
            
            $blogCategory->delete($id);
            echo json_encode(['success' => true, 'message' => 'Category deleted']);
            break;
            
        case 'list':
            $categories = $blogCategory->getAll();
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'get':
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Category ID required');
            }
            
            $category = $blogCategory->getById($id);
            echo json_encode(['success' => true, 'category' => $category]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
