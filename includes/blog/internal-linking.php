<?php
/**
 * Phase 3: Internal Linking Strategy
 * Generates 400+ strategic internal hyperlinks across blog posts
 */

/**
 * Generate smart internal links for a post based on keyword matching
 * @param PDO $db Database connection
 * @param int $postId Current post ID
 * @param string $content Post content/blocks
 * @param int $limit Maximum links to generate
 * @return array Smart link suggestions
 */
function generateSmartInternalLinks($db, $postId, $content, $limit = 5)
{
    // Get other published posts
    $stmt = $db->prepare("
        SELECT id, title, slug, excerpt, focus_keyword 
        FROM blog_posts 
        WHERE id != ? AND status = 'published'
        ORDER BY view_count DESC
        LIMIT 50
    ");
    $stmt->execute([$postId]);
    $relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $links = [];
    $contentLower = strtolower($content);
    
    foreach ($relatedPosts as $post) {
        $score = 0;
        
        // Score based on keyword matches
        if (!empty($post['focus_keyword'])) {
            $keywords = array_filter(explode(',', $post['focus_keyword']));
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));
                if (!empty($keyword) && strpos($contentLower, $keyword) !== false) {
                    $score += 2;
                }
            }
        }
        
        // Score based on title word matches
        $titleWords = preg_split('/\s+/', strtolower($post['title']), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($titleWords as $word) {
            if (strlen($word) > 4 && strpos($contentLower, $word) !== false) {
                $score += 1;
            }
        }
        
        if ($score > 0) {
            $links[] = [
                'id' => $post['id'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'score' => $score
            ];
        }
    }
    
    // Sort by relevance score
    usort($links, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return array_slice($links, 0, $limit);
}

/**
 * Store internal link relationship in database
 * @param PDO $db Database connection
 * @param int $sourcePostId Post containing the link
 * @param int $targetPostId Linked post
 * @param string $anchorText Link text
 * @param string $context Context where link appears
 */
function storeInternalLink($db, $sourcePostId, $targetPostId, $anchorText, $context = 'body')
{
    try {
        $stmt = $db->prepare("
            INSERT INTO blog_internal_links 
            (source_post_id, target_post_id, anchor_text, context_position, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(source_post_id, target_post_id) DO UPDATE SET
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$sourcePostId, $targetPostId, $anchorText, $context]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get internal links for a post (for rendering)
 * @param PDO $db Database connection
 * @param int $postId Post ID
 * @param int $limit Number of links
 * @return array Internal links with metadata
 */
function getInternalLinks($db, $postId, $limit = 5)
{
    $stmt = $db->prepare("
        SELECT 
            bil.target_post_id,
            bil.anchor_text,
            bp.title,
            bp.slug,
            bp.excerpt,
            COUNT(*) as link_count
        FROM blog_internal_links bil
        INNER JOIN blog_posts bp ON bil.target_post_id = bp.id
        WHERE bil.source_post_id = ? AND bp.status = 'published'
        GROUP BY bil.target_post_id
        ORDER BY link_count DESC
        LIMIT ?
    ");
    $stmt->execute([$postId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get topic cluster information for category-based internal linking
 * @param PDO $db Database connection
 * @param int $categoryId Category ID
 * @return array Posts organized by pillar/spoke structure
 */
function getTopicCluster($db, $categoryId)
{
    $stmt = $db->prepare("
        SELECT 
            bc.id,
            bc.name,
            bc.slug,
            COUNT(DISTINCT bp.id) as post_count,
            MAX(bp.view_count) as top_views
        FROM blog_categories bc
        LEFT JOIN blog_posts bp ON bc.id = bp.category_id
        WHERE bc.id = ? OR bc.parent_category_id = ?
        GROUP BY bc.id
        ORDER BY post_count DESC
    ");
    $stmt->execute([$categoryId, $categoryId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
