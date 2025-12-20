<?php

class BlogValidator
{
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Check if a post meets quality standards
     * Returns array with status and issues found
     */
    public function validatePost($post)
    {
        $issues = [];
        $warnings = [];
        
        // Check required fields
        if (empty($post['title'])) {
            $issues[] = 'Missing title';
        }
        
        if (empty($post['featured_image'])) {
            $warnings[] = 'No featured image';
        }
        
        if (empty($post['excerpt']) || strlen($post['excerpt']) < 50) {
            $warnings[] = 'Excerpt too short (< 50 characters)';
        }
        
        if (empty($post['reading_time_minutes']) || $post['reading_time_minutes'] == 0) {
            $warnings[] = 'Reading time not set';
        }
        
        // Check if post has content blocks
        $blockCount = $this->getBlockCount($post['id']);
        if ($blockCount == 0) {
            $issues[] = 'Post has no content blocks (empty post)';
        }
        
        // Check category
        if (empty($post['category_id'])) {
            $warnings[] = 'No category assigned';
        }
        
        return [
            'is_valid' => empty($issues),
            'has_warnings' => !empty($warnings),
            'issues' => $issues,
            'warnings' => $warnings,
            'block_count' => $blockCount
        ];
    }
    
    /**
     * Get count of content blocks for a post
     */
    public function getBlockCount($postId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM blog_blocks WHERE post_id = ?");
        $stmt->execute([$postId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    /**
     * Filter posts to only include valid ones
     */
    public function filterValidPosts($posts)
    {
        return array_filter($posts, function($post) {
            $validation = $this->validatePost($post);
            return $validation['is_valid'];
        });
    }
    
    /**
     * Get validation class for styling
     */
    public function getValidationClass($post)
    {
        $validation = $this->validatePost($post);
        
        if (!$validation['is_valid']) {
            return 'post-invalid';
        }
        
        if ($validation['has_warnings']) {
            return 'post-warning';
        }
        
        return 'post-valid';
    }
}
