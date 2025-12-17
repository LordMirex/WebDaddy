<?php

class BlogTag
{
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function create($data)
    {
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);
        
        $stmt = $this->db->prepare("
            INSERT INTO blog_tags (name, slug, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$data['name'], $slug]);
        
        return $this->db->lastInsertId();
    }
    
    public function delete($id)
    {
        $this->db->prepare("DELETE FROM blog_post_tags WHERE tag_id = ?")->execute([$id]);
        
        $stmt = $this->db->prepare("DELETE FROM blog_tags WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_tags WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getBySlug($slug)
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_tags WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM blog_tags ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getWithPostCount()
    {
        $stmt = $this->db->query("
            SELECT t.*, COUNT(pt.post_id) as post_count
            FROM blog_tags t
            LEFT JOIN blog_post_tags pt ON t.id = pt.tag_id
            LEFT JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published'
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getByPost($postId)
    {
        $stmt = $this->db->prepare("
            SELECT t.* FROM blog_tags t
            INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?
            ORDER BY t.name ASC
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function attachToPost($tagId, $postId)
    {
        $checkStmt = $this->db->prepare("
            SELECT 1 FROM blog_post_tags WHERE post_id = ? AND tag_id = ?
        ");
        $checkStmt->execute([$postId, $tagId]);
        if ($checkStmt->fetch()) {
            return true;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)
        ");
        return $stmt->execute([$postId, $tagId]);
    }
    
    public function detachFromPost($tagId, $postId)
    {
        $stmt = $this->db->prepare("
            DELETE FROM blog_post_tags WHERE post_id = ? AND tag_id = ?
        ");
        return $stmt->execute([$postId, $tagId]);
    }
    
    public function syncPostTags($postId, $tagIds)
    {
        $this->db->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);
        
        if (empty($tagIds)) {
            return true;
        }
        
        $stmt = $this->db->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
        foreach ($tagIds as $tagId) {
            $stmt->execute([$postId, $tagId]);
        }
        
        return true;
    }
    
    public function getOrCreate($name)
    {
        $slug = $this->generateSlug($name);
        
        $existing = $this->getBySlug($slug);
        if ($existing) {
            return $existing;
        }
        
        $id = $this->create(['name' => $name, 'slug' => $slug]);
        return $this->getById($id);
    }
    
    public function generateSlug($name)
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    public function search($query, $limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM blog_tags
            WHERE name LIKE ?
            ORDER BY name ASC
            LIMIT ?
        ");
        $stmt->execute(['%' . $query . '%', $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
