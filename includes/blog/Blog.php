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
        // Get posts from internal links table first (strategic linking)
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.reading_time_minutes
            FROM blog_posts p
            INNER JOIN blog_internal_links bil ON p.id = bil.target_post_id
            WHERE bil.source_post_id = ? AND p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ?
        ");
        $stmt->execute([$postId, $limit]);
        $relatedByLink = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If not enough from internal links, fallback to category matching
        if (count($relatedByLink) < $limit) {
            $remaining = $limit - count($relatedByLink);
            $linkedIds = array_column($relatedByLink, 'id');
            $placeholders = implode(',', array_fill(0, count($linkedIds) + 1, '?'));
            
            $stmt = $this->db->prepare("
                SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.reading_time_minutes
                FROM blog_posts p
                WHERE p.id NOT IN ($placeholders) AND p.category_id = ? AND p.status = 'published'
                AND p.publish_date <= datetime('now', '+1 hour')
                ORDER BY p.view_count DESC
                LIMIT ?
            ");
            $params = array_merge([$postId], $linkedIds, [$categoryId, $remaining]);
            $stmt->execute($params);
            $relatedByLink = array_merge($relatedByLink, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        return array_slice($relatedByLink, 0, $limit);
    }
    
    public function getTopPerformers($limit = 5, $days = 30)
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.title, p.slug, p.view_count, p.share_count, 
                   COUNT(DISTINCT ba.session_id) as unique_visitors,
                   SUM(CASE WHEN ba.event_type = 'scroll_100' THEN 1 ELSE 0 END) as full_reads
            FROM blog_posts p
            LEFT JOIN blog_analytics ba ON p.id = ba.post_id 
            WHERE p.status = 'published' 
            AND ba.created_at >= datetime('now', '-' || ? || ' days')
            GROUP BY p.id
            ORDER BY p.view_count DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAffiliatePerformance()
    {
        $stmt = $this->db->query("
            SELECT 
                ba.affiliate_code,
                COUNT(DISTINCT CASE WHEN ba.event_type = 'cta_click' THEN ba.session_id END) as clicks,
                COUNT(DISTINCT ba.post_id) as posts_referred,
                COUNT(DISTINCT ba.session_id) as unique_visitors
            FROM blog_analytics ba
            WHERE ba.affiliate_code IS NOT NULL
            GROUP BY ba.affiliate_code
            ORDER BY clicks DESC
            LIMIT 20
        ");
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
