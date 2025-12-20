<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$db = getDb();
$search = '%' . $query . '%';

$stmt = $db->prepare("
    SELECT id, title, slug, excerpt 
    FROM blog_posts 
    WHERE status = 'published' 
    AND (title LIKE ? OR excerpt LIKE ?)
    ORDER BY publish_date DESC
    LIMIT 10
");
$stmt->execute([$search, $search]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$suggestions = array_map(function($post) {
    return [
        'title' => htmlspecialchars($post['title']),
        'slug' => $post['slug'],
        'excerpt' => htmlspecialchars(substr($post['excerpt'], 0, 80) . '...')
    ];
}, $posts);

echo json_encode($suggestions);
?>
