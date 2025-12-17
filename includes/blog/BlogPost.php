<?php

class BlogPost
{
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO blog_posts (
                title, slug, excerpt, featured_image, featured_image_alt,
                category_id, author_name, author_avatar, status, publish_date,
                reading_time_minutes, meta_title, meta_description, canonical_url,
                focus_keyword, seo_score, og_title, og_description, og_image,
                twitter_title, twitter_description, twitter_image,
                primary_template_id, show_affiliate_ctas, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        
        $slug = $data['slug'] ?? $this->generateSlug($data['title']);
        
        $stmt->execute([
            $data['title'],
            $slug,
            $data['excerpt'] ?? null,
            $data['featured_image'] ?? null,
            $data['featured_image_alt'] ?? null,
            $data['category_id'] ?? null,
            $data['author_name'] ?? 'WebDaddy Team',
            $data['author_avatar'] ?? null,
            $data['status'] ?? 'draft',
            $data['publish_date'] ?? null,
            $data['reading_time_minutes'] ?? 5,
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['canonical_url'] ?? null,
            $data['focus_keyword'] ?? null,
            $data['seo_score'] ?? 0,
            $data['og_title'] ?? null,
            $data['og_description'] ?? null,
            $data['og_image'] ?? null,
            $data['twitter_title'] ?? null,
            $data['twitter_description'] ?? null,
            $data['twitter_image'] ?? null,
            $data['primary_template_id'] ?? null,
            $data['show_affiliate_ctas'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'title', 'slug', 'excerpt', 'featured_image', 'featured_image_alt',
            'category_id', 'author_name', 'author_avatar', 'status', 'publish_date',
            'reading_time_minutes', 'meta_title', 'meta_description', 'canonical_url',
            'focus_keyword', 'seo_score', 'og_title', 'og_description', 'og_image',
            'twitter_title', 'twitter_description', 'twitter_image',
            'primary_template_id', 'show_affiliate_ctas'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $sql = "UPDATE blog_posts SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function archive($id)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getBySlug($slug)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPublished($page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPublishedCount()
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM blog_posts
            WHERE status = 'published'
            AND publish_date <= datetime('now', '+1 hour')
        ");
        return (int)$stmt->fetchColumn();
    }
    
    public function getByCategory($categoryId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.category_id = ? AND p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$categoryId, $perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCategoryPostCount($categoryId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM blog_posts
            WHERE category_id = ? AND status = 'published'
            AND publish_date <= datetime('now', '+1 hour')
        ");
        $stmt->execute([$categoryId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function getByTag($tagId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            INNER JOIN blog_post_tags pt ON p.id = pt.post_id
            WHERE pt.tag_id = ? AND p.status = 'published'
            AND p.publish_date <= datetime('now', '+1 hour')
            ORDER BY p.publish_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tagId, $perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAll($page = 1, $perPage = 20, $status = null)
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $where = "1=1";
        if ($status) {
            $where .= " AND p.status = ?";
            $params[] = $status;
        }
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE $where
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllCount($status = null)
    {
        if ($status) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = ?");
            $stmt->execute([$status]);
        } else {
            $stmt = $this->db->query("SELECT COUNT(*) FROM blog_posts");
        }
        return (int)$stmt->fetchColumn();
    }
    
    public function publish($id)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts
            SET status = 'published',
                publish_date = COALESCE(publish_date, CURRENT_TIMESTAMP),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    public function unpublish($id)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts SET status = 'draft', updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    public function generateSlug($title)
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    protected function slugExists($slug, $excludeId = null)
    {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM blog_posts WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        return (bool)$stmt->fetch();
    }
    
    public function updateReadingTime($id)
    {
        $blockStmt = $this->db->prepare("SELECT data_payload FROM blog_blocks WHERE post_id = ?");
        $blockStmt->execute([$id]);
        $blocks = $blockStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $wordCount = 0;
        foreach ($blocks as $block) {
            $data = json_decode($block['data_payload'], true);
            if (isset($data['content'])) {
                $wordCount += str_word_count(strip_tags($data['content']));
            }
            if (isset($data['h1_title'])) {
                $wordCount += str_word_count($data['h1_title']);
            }
        }
        
        $readingTime = max(1, ceil($wordCount / 200));
        
        $stmt = $this->db->prepare("
            UPDATE blog_posts SET reading_time_minutes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$readingTime, $id]);
    }
    
    public function schedule($id, $publishDate)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_posts 
            SET status = 'scheduled', 
                publish_date = ?,
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$publishDate, $id]);
    }
    
    public function publishScheduled()
    {
        $stmt = $this->db->query("
            UPDATE blog_posts 
            SET status = 'published', updated_at = CURRENT_TIMESTAMP 
            WHERE status = 'scheduled' 
            AND publish_date <= datetime('now', '+1 hour')
        ");
        return $stmt->rowCount();
    }
    
    public function getScheduled()
    {
        $stmt = $this->db->query("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'scheduled'
            ORDER BY p.publish_date ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDrafts($limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'draft'
            ORDER BY p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
