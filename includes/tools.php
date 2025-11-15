<?php
/**
 * Working Tools Helper Functions
 * 
 * Provides data access and business logic for working tools product type.
 * Mirrors structure of template functions for consistency.
 */

require_once __DIR__ . '/db.php';

/**
 * Get all active working tools with optional filtering
 * 
 * @param bool $activeOnly If true, only return active tools
 * @param string $category Optional category filter
 * @param int $limit Optional result limit
 * @param int $offset Optional result offset for pagination
 * @param bool $inStockOnly If true, only return tools with stock (default true for customer view)
 * @return array Array of tool records
 */
function getTools($activeOnly = true, $category = null, $limit = null, $offset = null, $inStockOnly = true) {
    $db = getDb();
    
    $sql = "SELECT * FROM tools WHERE 1=1";
    $params = [];
    
    if ($activeOnly) {
        $sql .= " AND active = 1";
    }
    
    if ($category !== null && $category !== '') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    // Additional filter: only show tools with stock available (for customer-facing views)
    if ($inStockOnly) {
        $sql .= " AND (stock_unlimited = 1 OR stock_quantity > 0)";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit !== null) {
        $sql .= " LIMIT ?";
        $params[] = (int)$limit;
        
        if ($offset !== null) {
            $sql .= " OFFSET ?";
            $params[] = (int)$offset;
        }
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get total count of tools (for pagination)
 * 
 * @param bool $activeOnly Count only active tools
 * @param string $category Optional category filter
 * @param bool $inStockOnly If true, only count tools with stock (default true for customer view)
 * @return int Total number of tools
 */
function getToolsCount($activeOnly = true, $category = null, $inStockOnly = true) {
    $db = getDb();
    
    $sql = "SELECT COUNT(*) as count FROM tools WHERE 1=1";
    $params = [];
    
    if ($activeOnly) {
        $sql .= " AND active = 1";
    }
    
    if ($category !== null && $category !== '') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    // Count only tools with stock available (for customer-facing views)
    if ($inStockOnly) {
        $sql .= " AND (stock_unlimited = 1 OR stock_quantity > 0)";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}

/**
 * Get single tool by ID
 * 
 * @param int $id Tool ID
 * @return array|null Tool record or null if not found
 */
function getToolById($id) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM tools WHERE id = ?");
    $stmt->execute([(int)$id]);
    
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    return $tool ?: null;
}

/**
 * Get tool by slug (URL-friendly identifier)
 * 
 * @param string $slug Tool slug
 * @return array|null Tool record or null if not found
 */
function getToolBySlug($slug) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM tools WHERE slug = ? AND active = 1");
    $stmt->execute([$slug]);
    
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    return $tool ?: null;
}

/**
 * Get all unique tool categories
 * 
 * @return array Array of category names
 */
function getToolCategories() {
    $db = getDb();
    
    $stmt = $db->query("
        SELECT DISTINCT category 
        FROM tools 
        WHERE category IS NOT NULL 
          AND category != '' 
          AND active = 1
        ORDER BY category
    ");
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Search tools by query string
 * 
 * Searches in: name, category, short_description, description
 * 
 * @param string $query Search query
 * @param int $limit Optional result limit
 * @return array Array of matching tool records
 */
function searchTools($query, $limit = 20) {
    $db = getDb();
    
    $searchTerm = '%' . $query . '%';
    
    $sql = "SELECT * FROM tools 
            WHERE active = 1 
            AND (stock_unlimited = 1 OR stock_quantity > 0)
            AND (
                name LIKE ? 
                OR category LIKE ? 
                OR short_description LIKE ? 
                OR description LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1
                    WHEN category LIKE ? THEN 2
                    WHEN short_description LIKE ? THEN 3
                    ELSE 4
                END,
                created_at DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm, $searchTerm,
        (int)$limit
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create new tool (Admin only)
 * 
 * @param array $data Tool data
 * @return int|false New tool ID or false on failure
 */
function createTool($data) {
    $db = getDb();
    
    $sql = "INSERT INTO tools (
        name, slug, category, tool_type, short_description, description,
        features, price, thumbnail_url,
        delivery_instructions, stock_unlimited, stock_quantity,
        low_stock_threshold, active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['category'] ?? null,
            $data['tool_type'] ?? 'software',
            $data['short_description'] ?? null,
            $data['description'] ?? null,
            $data['features'] ?? null,
            $data['price'],
            $data['thumbnail_url'] ?? null,
            $data['delivery_instructions'] ?? null,
            $data['stock_unlimited'] ?? 1,
            $data['stock_quantity'] ?? 0,
            $data['low_stock_threshold'] ?? 5,
            $data['active'] ?? 1
        ]);
        
        if ($result) {
            return $db->lastInsertId();
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Error creating tool: " . $e->getMessage());
        return false;
    }
}

/**
 * Update existing tool (Admin only)
 * 
 * @param int $id Tool ID
 * @param array $data Tool data to update
 * @return bool Success status
 */
function updateTool($id, $data) {
    $db = getDb();
    
    $sql = "UPDATE tools SET 
        name = ?, slug = ?, category = ?, tool_type = ?,
        short_description = ?, description = ?, features = ?,
        price = ?, thumbnail_url = ?,
        delivery_instructions = ?, stock_unlimited = ?, stock_quantity = ?,
        low_stock_threshold = ?, active = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['category'] ?? null,
            $data['tool_type'] ?? 'software',
            $data['short_description'] ?? null,
            $data['description'] ?? null,
            $data['features'] ?? null,
            $data['price'],
            $data['thumbnail_url'] ?? null,
            $data['delivery_instructions'] ?? null,
            $data['stock_unlimited'] ?? 1,
            $data['stock_quantity'] ?? 0,
            $data['low_stock_threshold'] ?? 5,
            $data['active'] ?? 1,
            (int)$id
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating tool: " . $e->getMessage());
        return false;
    }
}

/**
 * Soft delete tool (set active = 0)
 * 
 * @param int $id Tool ID
 * @return bool Success status
 */
function deleteTool($id) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("UPDATE tools SET active = 0 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    } catch (PDOException $e) {
        error_log("Error deleting tool: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if sufficient stock is available
 * 
 * @param int $toolId Tool ID
 * @param int $quantity Desired quantity
 * @return bool True if stock available, false otherwise
 */
function checkToolStock($toolId, $quantity) {
    $tool = getToolById($toolId);
    
    if (!$tool) {
        return false;
    }
    
    // Unlimited stock
    if ($tool['stock_unlimited'] == 1) {
        return true;
    }
    
    // Check if enough stock
    return $tool['stock_quantity'] >= $quantity;
}

/**
 * Decrement tool stock after purchase
 * 
 * @param int $toolId Tool ID
 * @param int $quantity Quantity purchased
 * @return bool Success status
 */
function decrementToolStock($toolId, $quantity) {
    $db = getDb();
    $tool = getToolById($toolId);
    
    if (!$tool) {
        return false;
    }
    
    // Skip if unlimited stock
    if ($tool['stock_unlimited'] == 1) {
        return true;
    }
    
    // Check if enough stock
    if ($tool['stock_quantity'] < $quantity) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE tools 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ? AND stock_quantity >= ?
        ");
        
        return $stmt->execute([(int)$quantity, (int)$toolId, (int)$quantity]);
        
    } catch (PDOException $e) {
        error_log("Error decrementing stock: " . $e->getMessage());
        return false;
    }
}

/**
 * Increment tool stock (for refunds or restocking)
 * 
 * @param int $toolId Tool ID
 * @param int $quantity Quantity to add
 * @return bool Success status
 */
function incrementToolStock($toolId, $quantity) {
    $db = getDb();
    $tool = getToolById($toolId);
    
    if (!$tool) {
        return false;
    }
    
    // Skip if unlimited stock
    if ($tool['stock_unlimited'] == 1) {
        return true;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE tools 
            SET stock_quantity = stock_quantity + ? 
            WHERE id = ?
        ");
        
        return $stmt->execute([(int)$quantity, (int)$toolId]);
        
    } catch (PDOException $e) {
        error_log("Error incrementing stock: " . $e->getMessage());
        return false;
    }
}

/**
 * Get tools with low stock
 * 
 * @return array Array of tools below threshold
 */
function getLowStockTools() {
    $db = getDb();
    
    $sql = "SELECT * FROM tools 
            WHERE active = 1 
            AND stock_unlimited = 0 
            AND stock_quantity <= low_stock_threshold
            AND stock_quantity > 0
            ORDER BY stock_quantity ASC";
    
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get out of stock tools
 * 
 * @return array Array of tools with zero stock
 */
function getOutOfStockTools() {
    $db = getDb();
    
    $sql = "SELECT * FROM tools 
            WHERE active = 1 
            AND stock_unlimited = 0 
            AND stock_quantity = 0
            ORDER BY name ASC";
    
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate unique slug from tool name
 * 
 * @param string $name Tool name
 * @param int $id Tool ID (for updates, to exclude from uniqueness check)
 * @return string Unique slug
 */
function generateToolSlug($name, $id = null) {
    $db = getDb();
    
    // Convert to lowercase, replace spaces/special chars with hyphens
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Check if slug exists
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM tools WHERE slug = ?";
        $params = [$slug];
        
        if ($id !== null) {
            $sql .= " AND id != ?";
            $params[] = (int)$id;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
