<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Get all published posts grouped by category
$stmt = $db->query("
    SELECT id, title, category_id FROM blog_posts 
    WHERE status = 'published' 
    ORDER BY category_id, title
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    echo "No published posts found.\n";
    exit;
}

$linkCount = 0;
$existingLinks = [];

// Get existing links to avoid duplicates
$existingStmt = $db->query("SELECT source_post_id, target_post_id FROM blog_internal_links");
$existing = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($existing as $link) {
    $existingLinks[$link['source_post_id']][$link['target_post_id']] = true;
}

// Group posts by category
$postsByCategory = [];
foreach ($posts as $post) {
    $categoryId = $post['category_id'] ?? 0;
    if (!isset($postsByCategory[$categoryId])) {
        $postsByCategory[$categoryId] = [];
    }
    $postsByCategory[$categoryId][] = $post;
}

// Create internal links strategy:
// 1. Within same category (3-5 links per post)
// 2. Cross-category related topics (2-3 links per post)
// 3. Back links (2-3 links per post)

$linkTypes = ['related', 'series', 'prerequisite', 'followup'];
$insertStmt = $db->prepare("
    INSERT OR IGNORE INTO blog_internal_links (source_post_id, target_post_id, anchor_text, link_type, created_at)
    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
");

foreach ($posts as $sourcePost) {
    $categoryId = $sourcePost['category_id'] ?? 0;
    $targetCount = 0;
    
    // Link to other posts in same category (3-5)
    if (isset($postsByCategory[$categoryId])) {
        $otherPostsInCategory = array_filter(
            $postsByCategory[$categoryId],
            fn($p) => $p['id'] != $sourcePost['id']
        );
        
        $sameCategoryLinks = min(5, count($otherPostsInCategory));
        $selectedPosts = array_slice($otherPostsInCategory, 0, $sameCategoryLinks);
        
        foreach ($selectedPosts as $targetPost) {
            if (!isset($existingLinks[$sourcePost['id']][$targetPost['id']])) {
                $insertStmt->execute([
                    $sourcePost['id'],
                    $targetPost['id'],
                    "Read: {$targetPost['title']}",
                    $linkTypes[$targetCount % count($linkTypes)],
                ]);
                $existingLinks[$sourcePost['id']][$targetPost['id']] = true;
                $linkCount++;
                $targetCount++;
            }
        }
    }
    
    // Link to cross-category related posts (2-3)
    $crossCategoryLimit = min(3, count($posts) - 1);
    $crossLinks = array_filter(
        $posts,
        fn($p) => $p['id'] != $sourcePost['id'] && 
                  ($p['category_id'] ?? 0) != $categoryId
    );
    
    $crossLinks = array_slice($crossLinks, 0, $crossCategoryLimit);
    foreach ($crossLinks as $targetPost) {
        if (!isset($existingLinks[$sourcePost['id']][$targetPost['id']])) {
            $insertStmt->execute([
                $sourcePost['id'],
                $targetPost['id'],
                "Learn more: {$targetPost['title']}",
                $linkTypes[($targetCount + 1) % count($linkTypes)],
            ]);
            $existingLinks[$sourcePost['id']][$targetPost['id']] = true;
            $linkCount++;
        }
    }
}

echo "Created $linkCount internal links successfully.\n";
echo "Total links in system: ";

$totalStmt = $db->query("SELECT COUNT(*) as total FROM blog_internal_links");
$result = $totalStmt->fetch(PDO::FETCH_ASSOC);
echo $result['total'] . "\n";
