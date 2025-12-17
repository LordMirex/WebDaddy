<?php

class BlogBlock
{
    protected $db;
    
    const BLOCK_TYPES = [
        'hero_editorial',
        'rich_text',
        'section_divider',
        'visual_explanation',
        'inline_conversion',
        'internal_authority',
        'faq_seo',
        'final_conversion'
    ];
    
    const SEMANTIC_ROLES = [
        'primary_content',
        'supporting_content',
        'conversion_content',
        'authority_content'
    ];
    
    const LAYOUT_VARIANTS = [
        'default',
        'split_left',
        'split_right',
        'wide',
        'contained',
        'card_grid',
        'timeline'
    ];
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function create($data)
    {
        if (!in_array($data['block_type'], self::BLOCK_TYPES)) {
            throw new InvalidArgumentException('Invalid block type: ' . $data['block_type']);
        }
        
        $maxOrder = $this->getMaxOrder($data['post_id']);
        $displayOrder = $data['display_order'] ?? ($maxOrder + 1);
        
        $stmt = $this->db->prepare("
            INSERT INTO blog_blocks (
                post_id, block_type, display_order, semantic_role, layout_variant,
                data_payload, behavior_config, is_visible, visibility_conditions,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $dataPayload = is_array($data['data_payload']) ? json_encode($data['data_payload']) : $data['data_payload'];
        $behaviorConfig = isset($data['behavior_config']) 
            ? (is_array($data['behavior_config']) ? json_encode($data['behavior_config']) : $data['behavior_config'])
            : null;
        $visibilityConditions = isset($data['visibility_conditions'])
            ? (is_array($data['visibility_conditions']) ? json_encode($data['visibility_conditions']) : $data['visibility_conditions'])
            : null;
        
        $stmt->execute([
            $data['post_id'],
            $data['block_type'],
            $displayOrder,
            $data['semantic_role'] ?? 'primary_content',
            $data['layout_variant'] ?? 'default',
            $dataPayload,
            $behaviorConfig,
            $data['is_visible'] ?? 1,
            $visibilityConditions
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'block_type', 'display_order', 'semantic_role', 'layout_variant',
            'data_payload', 'behavior_config', 'is_visible', 'visibility_conditions'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $value = $data[$field];
                if (in_array($field, ['data_payload', 'behavior_config', 'visibility_conditions']) && is_array($value)) {
                    $value = json_encode($value);
                }
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $sql = "UPDATE blog_blocks SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function delete($id)
    {
        $block = $this->getById($id);
        if (!$block) {
            return false;
        }
        
        $stmt = $this->db->prepare("DELETE FROM blog_blocks WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $this->reorderAfterDelete($block['post_id'], $block['display_order']);
        }
        
        return $result;
    }
    
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_blocks WHERE id = ?");
        $stmt->execute([$id]);
        $block = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($block) {
            $block['data_payload'] = json_decode($block['data_payload'], true);
            if ($block['behavior_config']) {
                $block['behavior_config'] = json_decode($block['behavior_config'], true);
            }
            if ($block['visibility_conditions']) {
                $block['visibility_conditions'] = json_decode($block['visibility_conditions'], true);
            }
        }
        
        return $block;
    }
    
    public function getByPost($postId, $visibleOnly = true)
    {
        $where = $visibleOnly ? "AND is_visible = 1" : "";
        $stmt = $this->db->prepare("
            SELECT * FROM blog_blocks
            WHERE post_id = ? $where
            ORDER BY display_order ASC
        ");
        $stmt->execute([$postId]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($blocks as &$block) {
            $block['data_payload'] = json_decode($block['data_payload'], true);
            if ($block['behavior_config']) {
                $block['behavior_config'] = json_decode($block['behavior_config'], true);
            }
            if ($block['visibility_conditions']) {
                $block['visibility_conditions'] = json_decode($block['visibility_conditions'], true);
            }
        }
        
        return $blocks;
    }
    
    public function reorder($postId, $orderedIds)
    {
        $order = 0;
        foreach ($orderedIds as $blockId) {
            $stmt = $this->db->prepare("
                UPDATE blog_blocks SET display_order = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND post_id = ?
            ");
            $stmt->execute([$order, $blockId, $postId]);
            $order++;
        }
        return true;
    }
    
    protected function reorderAfterDelete($postId, $deletedOrder)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_blocks
            SET display_order = display_order - 1, updated_at = CURRENT_TIMESTAMP
            WHERE post_id = ? AND display_order > ?
        ");
        return $stmt->execute([$postId, $deletedOrder]);
    }
    
    protected function getMaxOrder($postId)
    {
        $stmt = $this->db->prepare("SELECT MAX(display_order) FROM blog_blocks WHERE post_id = ?");
        $stmt->execute([$postId]);
        $max = $stmt->fetchColumn();
        return $max !== null ? (int)$max : -1;
    }
    
    public function duplicate($id)
    {
        $block = $this->getById($id);
        if (!$block) {
            return false;
        }
        
        $newData = $block;
        unset($newData['id']);
        $newData['display_order'] = $block['display_order'] + 1;
        
        $this->shiftBlocksDown($block['post_id'], $newData['display_order']);
        
        return $this->create($newData);
    }
    
    protected function shiftBlocksDown($postId, $fromOrder)
    {
        $stmt = $this->db->prepare("
            UPDATE blog_blocks
            SET display_order = display_order + 1, updated_at = CURRENT_TIMESTAMP
            WHERE post_id = ? AND display_order >= ?
        ");
        return $stmt->execute([$postId, $fromOrder]);
    }
    
    public function moveUp($id)
    {
        $block = $this->getById($id);
        if (!$block || $block['display_order'] <= 0) {
            return false;
        }
        
        $this->db->prepare("
            UPDATE blog_blocks SET display_order = ?
            WHERE post_id = ? AND display_order = ?
        ")->execute([$block['display_order'], $block['post_id'], $block['display_order'] - 1]);
        
        $this->db->prepare("
            UPDATE blog_blocks SET display_order = display_order - 1 WHERE id = ?
        ")->execute([$id]);
        
        return true;
    }
    
    public function moveDown($id)
    {
        $block = $this->getById($id);
        if (!$block) {
            return false;
        }
        
        $maxOrder = $this->getMaxOrder($block['post_id']);
        if ($block['display_order'] >= $maxOrder) {
            return false;
        }
        
        $this->db->prepare("
            UPDATE blog_blocks SET display_order = ?
            WHERE post_id = ? AND display_order = ?
        ")->execute([$block['display_order'], $block['post_id'], $block['display_order'] + 1]);
        
        $this->db->prepare("
            UPDATE blog_blocks SET display_order = display_order + 1 WHERE id = ?
        ")->execute([$id]);
        
        return true;
    }
    
    public function validateDataPayload($blockType, $data)
    {
        $required = $this->getRequiredFields($blockType);
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return ['valid' => false, 'error' => "Missing required field: $field"];
            }
        }
        return ['valid' => true];
    }
    
    protected function getRequiredFields($blockType)
    {
        $requirements = [
            'hero_editorial' => ['h1_title'],
            'rich_text' => ['content'],
            'section_divider' => ['divider_type'],
            'visual_explanation' => ['heading', 'content'],
            'inline_conversion' => ['headline', 'cta_primary'],
            'internal_authority' => ['heading', 'display_type'],
            'faq_seo' => ['heading', 'items'],
            'final_conversion' => ['headline', 'cta_config']
        ];
        
        return $requirements[$blockType] ?? [];
    }
}
