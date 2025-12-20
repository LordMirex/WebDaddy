<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$query = $_GET['q'] ?? '';

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$db = getDb();

try {
    // Multi-field search with priority scoring
    $search = '%' . $query . '%';
    
    $stmt = $db->prepare("
        SELECT id, title, slug, excerpt,
               CASE 
                   WHEN LOWER(title) LIKE LOWER(?) THEN 3
                   WHEN LOWER(excerpt) LIKE LOWER(?) THEN 1
                   ELSE 0
               END as relevance
        FROM blog_posts 
        WHERE status = 'published' 
        AND (
            LOWER(title) LIKE LOWER(?) 
            OR LOWER(excerpt) LIKE LOWER(?)
        )
        ORDER BY relevance DESC, publish_date DESC
        LIMIT 15
    ");
    
    $stmt->execute([$search, $search, $search, $search]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format and return results
    $suggestions = array_map(function($post) use ($query) {
        $excerpt = $post['excerpt'] ? substr($post['excerpt'], 0, 100) : 'No description available';
        return [
            'title' => htmlspecialchars($post['title']),
            'slug' => $post['slug'],
            'excerpt' => htmlspecialchars($excerpt)
        ];
    }, $posts);
    
    echo json_encode($suggestions);
    
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode([]);
}
?>
