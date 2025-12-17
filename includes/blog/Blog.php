<?php

class Blog
{
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function getRecentPosts($limit = 5)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPopularPosts($limit = 5)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.view_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRelatedPosts($postId, $categoryId, $limit = 3)
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.reading_time_minutes
            FROM blog_posts p
            WHERE p.id != ? AND p.category_id = ? AND p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ?
        ");
        $stmt->execute([$postId, $categoryId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function search($query, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $searchTerm = '%' . $query . '%';
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.focus_keyword LIKE ?)
            ORDER BY p.publish_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function incrementViewCount($postId)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts SET view_count = view_count + 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$postId]);
    }
    
    public function incrementShareCount($postId)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts SET share_count = share_count + 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$postId]);
    }
    
    public function getStats()
    {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
        $stats['published_posts'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'");
        $stats['draft_posts'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT SUM(view_count) FROM blog_posts");
        $stats['total_views'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM blog_categories WHERE is_active = 1");
        $stats['categories'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
}
