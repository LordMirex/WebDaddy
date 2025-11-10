<?php
/**
 * Shopping Cart Helper Functions
 * 
 * Session-based cart management for working tools.
 * Handles add, update, remove, and calculation operations.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools.php';

/**
 * Get current session's cart ID
 * Ensures session is started
 * 
 * @return string Session ID
 */
function getCartSessionId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return session_id();
}

/**
 * Get all cart items for current session
 * 
 * @param string $sessionId Optional session ID (uses current if not provided)
 * @return array Array of cart items with tool details
 */
function getCart($sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    $sql = "SELECT c.*, t.name, t.slug, t.thumbnail_url, t.stock_unlimited, t.stock_quantity, t.active
            FROM cart_items c
            JOIN tools t ON c.tool_id = t.id
            WHERE c.session_id = ?
            ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$sessionId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get total number of items in cart
 * 
 * @param string $sessionId Optional session ID
 * @return int Total item count
 */
function getCartCount($sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM cart_items WHERE session_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$sessionId]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['total'];
}

/**
 * Add item to cart (or update quantity if already exists)
 * 
 * @param int $toolId Tool ID
 * @param int $quantity Quantity to add
 * @param string $sessionId Optional session ID
 * @return array Result with success status and message
 */
function addToCart($toolId, $quantity = 1, $sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    // Validate tool exists and is active
    $tool = getToolById($toolId);
    if (!$tool || $tool['active'] != 1) {
        return ['success' => false, 'message' => 'Tool not found or inactive'];
    }
    
    // Check stock availability
    if (!checkToolStock($toolId, $quantity)) {
        return ['success' => false, 'message' => 'Insufficient stock'];
    }
    
    try {
        // Check if item already in cart
        $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE session_id = ? AND tool_id = ?");
        $stmt->execute([$sessionId, $toolId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing item
            $newQuantity = $existing['quantity'] + $quantity;
            
            // Check stock for new total quantity
            if (!checkToolStock($toolId, $newQuantity)) {
                return ['success' => false, 'message' => 'Insufficient stock for requested quantity'];
            }
            
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $existing['id']]);
            
            return ['success' => true, 'message' => 'Cart updated', 'action' => 'updated'];
        } else {
            // Add new item
            $stmt = $db->prepare("
                INSERT INTO cart_items (session_id, tool_id, quantity, price_at_add)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$sessionId, $toolId, $quantity, $tool['price']]);
            
            return ['success' => true, 'message' => 'Added to cart', 'action' => 'added'];
        }
        
    } catch (PDOException $e) {
        error_log("Error adding to cart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * Update cart item quantity
 * 
 * @param int $cartItemId Cart item ID
 * @param int $quantity New quantity (must be >= 1)
 * @param string $sessionId Optional session ID (for security verification)
 * @return array Result with success status and message
 */
function updateCartQuantity($cartItemId, $quantity, $sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    if ($quantity < 1) {
        return ['success' => false, 'message' => 'Quantity must be at least 1'];
    }
    
    try {
        // Get cart item AND verify session ownership (security)
        $stmt = $db->prepare("SELECT * FROM cart_items WHERE id = ? AND session_id = ?");
        $stmt->execute([$cartItemId, $sessionId]);
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }
        
        // Check stock
        if (!checkToolStock($cartItem['tool_id'], $quantity)) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }
        
        // Update quantity (with session verification)
        $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND session_id = ?");
        $stmt->execute([$quantity, $cartItemId, $sessionId]);
        
        return ['success' => true, 'message' => 'Quantity updated'];
        
    } catch (PDOException $e) {
        error_log("Error updating cart quantity: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * Remove item from cart
 * 
 * @param int $cartItemId Cart item ID
 * @param string $sessionId Optional session ID (for security check)
 * @return array Result with success status and message
 */
function removeFromCart($cartItemId, $sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    try {
        // Verify item belongs to session (security)
        $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND session_id = ?");
        $result = $stmt->execute([$cartItemId, $sessionId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Item removed'];
        } else {
            return ['success' => false, 'message' => 'Item not found'];
        }
        
    } catch (PDOException $e) {
        error_log("Error removing from cart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * Clear entire cart for session
 * 
 * @param string $sessionId Optional session ID
 * @return bool Success status
 */
function clearCart($sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM cart_items WHERE session_id = ?");
        return $stmt->execute([$sessionId]);
    } catch (PDOException $e) {
        error_log("Error clearing cart: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate cart total with affiliate discount
 * 
 * @param string $sessionId Optional session ID
 * @param string $affiliateCode Optional affiliate code for discount
 * @return array Cart totals with breakdown
 */
function getCartTotal($sessionId = null, $affiliateCode = null) {
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    $cartItems = getCart($sessionId);
    
    $subtotal = 0;
    $itemCount = 0;
    
    foreach ($cartItems as $item) {
        $subtotal += $item['price_at_add'] * $item['quantity'];
        $itemCount += $item['quantity'];
    }
    
    // Apply affiliate discount (20%)
    $discount = 0;
    if ($affiliateCode) {
        $discount = $subtotal * 0.20; // 20% discount
    }
    
    $total = $subtotal - $discount;
    
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'item_count' => $itemCount,
        'has_discount' => $affiliateCode ? true : false,
        'affiliate_code' => $affiliateCode
    ];
}

/**
 * Validate cart (check stock, active status for all items)
 * 
 * @param string $sessionId Optional session ID
 * @return array Validation result with issues
 */
function validateCart($sessionId = null) {
    $cartItems = getCart($sessionId);
    
    $issues = [];
    $valid = true;
    
    foreach ($cartItems as $item) {
        // Check if tool still active
        if ($item['active'] != 1) {
            $issues[] = [
                'cart_item_id' => $item['id'],
                'tool_name' => $item['name'],
                'issue' => 'Tool is no longer available'
            ];
            $valid = false;
            continue;
        }
        
        // Check stock
        if (!checkToolStock($item['tool_id'], $item['quantity'])) {
            $tool = getToolById($item['tool_id']);
            $issues[] = [
                'cart_item_id' => $item['id'],
                'tool_name' => $item['name'],
                'issue' => 'Insufficient stock',
                'requested' => $item['quantity'],
                'available' => $tool['stock_quantity']
            ];
            $valid = false;
        }
    }
    
    return [
        'valid' => $valid,
        'issues' => $issues
    ];
}

/**
 * Get cart snapshot as JSON (for storing with order)
 * 
 * @param string $sessionId Optional session ID
 * @return string JSON snapshot of cart
 */
function getCartSnapshot($sessionId = null) {
    $cartItems = getCart($sessionId);
    
    $snapshot = [];
    foreach ($cartItems as $item) {
        $snapshot[] = [
            'tool_id' => $item['tool_id'],
            'tool_name' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price_at_add'],
            'subtotal' => $item['price_at_add'] * $item['quantity']
        ];
    }
    
    return json_encode($snapshot);
}

/**
 * Cleanup old cart items (for cron job)
 * Removes cart items older than specified days
 * 
 * @param int $days Number of days to keep (default 7)
 * @return int Number of items deleted
 */
function cleanupOldCartItems($days = 7) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            DELETE FROM cart_items 
            WHERE created_at < datetime('now', '-' || ? || ' days')
        ");
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
        
    } catch (PDOException $e) {
        error_log("Error cleaning up cart items: " . $e->getMessage());
        return 0;
    }
}

/**
 * Transfer cart from one session to another (for login scenarios)
 * 
 * @param string $oldSessionId Old session ID
 * @param string $newSessionId New session ID
 * @return bool Success status
 */
function transferCart($oldSessionId, $newSessionId) {
    $db = getDb();
    
    try {
        // Get items from new session
        $stmt = $db->prepare("SELECT tool_id, quantity FROM cart_items WHERE session_id = ?");
        $stmt->execute([$newSessionId]);
        $newSessionItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create lookup array
        $newSessionToolIds = [];
        foreach ($newSessionItems as $item) {
            $newSessionToolIds[$item['tool_id']] = $item['quantity'];
        }
        
        // Get items from old session
        $stmt = $db->prepare("SELECT * FROM cart_items WHERE session_id = ?");
        $stmt->execute([$oldSessionId]);
        $oldSessionItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($oldSessionItems as $item) {
            if (isset($newSessionToolIds[$item['tool_id']])) {
                // Merge quantities
                $newQuantity = $newSessionToolIds[$item['tool_id']] + $item['quantity'];
                $stmt = $db->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE session_id = ? AND tool_id = ?
                ");
                $stmt->execute([$newQuantity, $newSessionId, $item['tool_id']]);
            } else {
                // Move item to new session
                $stmt = $db->prepare("
                    UPDATE cart_items 
                    SET session_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$newSessionId, $item['id']]);
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error transferring cart: " . $e->getMessage());
        return false;
    }
}
