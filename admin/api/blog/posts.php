<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/blog/BlogPost.php';
require_once __DIR__ . '/../../../includes/blog/BlogTag.php';

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
$blogPost = new BlogPost($db);
$blogTag = new BlogTag($db);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $postId = $blogPost->create([
                'title' => $input['title'] ?? 'Untitled',
                'slug' => $input['slug'] ?? null,
                'excerpt' => $input['excerpt'] ?? null,
                'category_id' => $input['category_id'] ?? null,
                'featured_image' => $input['featured_image'] ?? null,
                'featured_image_alt' => $input['featured_image_alt'] ?? null,
                'author_name' => $input['author_name'] ?? 'WebDaddy Team',
                'status' => $input['status'] ?? 'draft',
                'publish_date' => $input['publish_date'] ?? null,
                'focus_keyword' => $input['focus_keyword'] ?? null,
                'meta_title' => $input['meta_title'] ?? null,
                'meta_description' => $input['meta_description'] ?? null,
                'og_title' => $input['og_title'] ?? null,
                'og_description' => $input['og_description'] ?? null,
                'og_image' => $input['og_image'] ?? null
            ]);
            
            if (!empty($input['tags'])) {
                $blogTag->syncPostTags($postId, $input['tags']);
            }
            
            echo json_encode(['success' => true, 'post_id' => $postId]);
            break;
            
        case 'update':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $updateData = [];
            $fields = ['title', 'slug', 'excerpt', 'category_id', 'featured_image', 
                       'featured_image_alt', 'author_name', 'status', 'publish_date',
                       'focus_keyword', 'meta_title', 'meta_description',
                       'og_title', 'og_description', 'og_image'];
            
            foreach ($fields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (!empty($updateData)) {
                $blogPost->update($id, $updateData);
            }
            
            if (isset($input['tags'])) {
                $blogTag->syncPostTags($id, $input['tags']);
            }
            
            $blogPost->updateReadingTime($id);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $blogPost->delete($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $post = $blogPost->getById($id);
            if (!$post) {
                throw new Exception('Post not found');
            }
            
            echo json_encode(['success' => true, 'post' => $post]);
            break;
            
        case 'publish':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $blogPost->publish($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'unpublish':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $blogPost->unpublish($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'archive':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Post ID required');
            }
            
            $blogPost->archive($id);
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
