<?php

class BlogCategory
{
    protected $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO blog_categories (
                name, slug, description, meta_title, meta_description,
                parent_id, display_order, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);
        
        $stmt->execute([
            $data['name'],
            $slug,
            $data['description'] ?? null,
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['parent_id'] ?? null,
            $data['display_order'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'name', 'slug', 'description', 'meta_title', 'meta_description',
            'parent_id', 'display_order', 'is_active'
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
        
        $sql = "UPDATE blog_categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function delete($id)
    {
        $this->db->prepare("UPDATE blog_posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
        
        $stmt = $this->db->prepare("DELETE FROM blog_categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getBySlug($slug)
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAll($activeOnly = false)
    {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $stmt = $this->db->query("
            SELECT * FROM blog_categories $where ORDER BY display_order ASC, name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getWithPostCount($activeOnly = true)
    {
        $where = $activeOnly ? "WHERE c.is_active = 1" : "";
        $stmt = $this->db->query("
            SELECT c.*, COUNT(p.id) as post_count
            FROM blog_categories c
            LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
            $where
            GROUP BY c.id
            ORDER BY c.display_order ASC, c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getChildren($parentId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM blog_categories WHERE parent_id = ? ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getParent($categoryId)
    {
        $stmt = $this->db->prepare("
            SELECT p.* FROM blog_categories c
            INNER JOIN blog_categories p ON c.parent_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$categoryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getHierarchy()
    {
        $all = $this->getAll(false);
        return $this->buildTree($all);
    }
    
    protected function buildTree(array $categories, $parentId = null)
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = $this->buildTree($categories, $category['id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $tree[] = $category;
            }
        }
        return $tree;
    }
    
    public function generateSlug($name)
    {
        $slug = strtolower(trim($name));
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
            $stmt = $this->db->prepare("SELECT id FROM blog_categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM blog_categories WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        return (bool)$stmt->fetch();
    }
    
    public function reorder($orderedIds)
    {
        $order = 0;
        foreach ($orderedIds as $id) {
            $stmt = $this->db->prepare("UPDATE blog_categories SET display_order = ? WHERE id = ?");
            $stmt->execute([$order, $id]);
            $order++;
        }
        return true;
    }
}
