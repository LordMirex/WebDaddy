<?php
/**
 * Blog Search API
 * Returns search results for blog posts via AJAX
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['results' => [], 'query' => $query]);
    exit;
}

$db = getDb();
$blogPost = new BlogPost($db);

// Search in title, excerpt, and content
$search = '%' . $query . '%';
$stmt = $db->prepare("
    SELECT id, title, slug, excerpt, featured_image, publish_date, category_id
    FROM blog_posts
    WHERE status = 'published' 
    AND (title LIKE ? OR excerpt LIKE ?)
    ORDER BY publish_date DESC
    LIMIT 20
");

$stmt->execute([$search, $search]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format results
$formatted = array_map(function($post) {
    return [
        'id' => $post['id'],
        'title' => $post['title'],
        'excerpt' => substr($post['excerpt'], 0, 100) . '...',
        'url' => '/blog/' . $post['slug'] . '/',
        'featured_image' => $post['featured_image'],
        'publish_date' => date('M d, Y', strtotime($post['publish_date']))
    ];
}, $results);

echo json_encode([
    'results' => $formatted,
    'query' => $query,
    'count' => count($formatted)
]);
?>
