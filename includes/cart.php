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
 * Get all cart items for current session with product details
 * Supports both templates and tools based on product_type
 * 
 * @param string $sessionId Optional session ID (uses current if not provided)
 * @return array Array of cart items with product details
 */
function getCart($sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    $stmt = $db->prepare("SELECT * FROM cart_items WHERE session_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sessionId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($cartItems as $item) {
        $productType = $item['product_type'] ?? 'tool';
        $productId = $item['product_id'];
        
        if ($productType === 'tool') {
            $stmt = $db->prepare("SELECT name, slug, thumbnail_url, stock_unlimited, stock_quantity, active, price FROM tools WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $result[] = array_merge($item, [
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'thumbnail_url' => $product['thumbnail_url'],
                    'stock_unlimited' => $product['stock_unlimited'],
                    'stock_quantity' => $product['stock_quantity'],
                    'active' => $product['active'],
                    'current_price' => $product['price']
                ]);
            }
        } elseif ($productType === 'template') {
            $stmt = $db->prepare("SELECT name, slug, thumbnail_url, active, price FROM templates WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $result[] = array_merge($item, [
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'thumbnail_url' => $product['thumbnail_url'],
                    'stock_unlimited' => 1,
                    'stock_quantity' => 999999,
                    'active' => $product['active'],
                    'current_price' => $product['price']
                ]);
            }
        }
    }
    
    return $result;
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
 * Add product to cart (unified function for both templates and tools)
 * 
 * @param string $productType Product type ('tool' or 'template')
 * @param int $productId Product ID
 * @param int $quantity Quantity to add
 * @param string $sessionId Optional session ID
 * @return array Result with success status and message
 */
function addProductToCart($productType, $productId, $quantity = 1, $sessionId = null) {
    $db = getDb();
    
    if ($sessionId === null) {
        $sessionId = getCartSessionId();
    }
    
    // Validate product type
    if (!in_array($productType, ['tool', 'template'])) {
        return ['success' => false, 'message' => 'Invalid product type'];
    }
    
    // Get product details
    if ($productType === 'tool') {
        $stmt = $db->prepare("SELECT * FROM tools WHERE id = ? AND active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Tool not found or inactive'];
        }
        
        // Check stock
        if (!checkToolStock($productId, $quantity)) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM templates WHERE id = ? AND active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Template not found or inactive'];
        }
        
        // Templates are digital products with unlimited stock, quantity always 1
        $quantity = 1;
    }
    
    try {
        // Check if item already in cart
        $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE session_id = ? AND product_type = ? AND product_id = ?");
        $stmt->execute([$sessionId, $productType, $productId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($productType === 'template') {
                return ['success' => false, 'message' => 'Template already in cart'];
            }
            
            // Update existing tool
            $newQuantity = $existing['quantity'] + $quantity;
            
            if (!checkToolStock($productId, $newQuantity)) {
                return ['success' => false, 'message' => 'Insufficient stock for requested quantity'];
            }
            
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $existing['id']]);
            
            return ['success' => true, 'message' => 'Cart updated', 'action' => 'updated'];
        } else {
            // Add new item
            $stmt = $db->prepare("
                INSERT INTO cart_items (session_id, product_type, product_id, tool_id, quantity, price_at_add)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $toolId = $productType === 'tool' ? $productId : null;
            $stmt->execute([$sessionId, $productType, $productId, $toolId, $quantity, $product['price']]);
            
            return ['success' => true, 'message' => 'Added to cart', 'action' => 'added'];
        }
        
    } catch (PDOException $e) {
        error_log("Error adding to cart: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Add tool to cart (backward compatibility wrapper)
 * 
 * @param int $toolId Tool ID
 * @param int $quantity Quantity to add
 * @param string $sessionId Optional session ID
 * @return array Result with success status and message
 */
function addToCart($toolId, $quantity = 1, $sessionId = null) {
    return addProductToCart('tool', $toolId, $quantity, $sessionId);
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
        
        // Templates cannot have quantity changed
        if ($cartItem['product_type'] === 'template') {
            return ['success' => false, 'message' => 'Cannot change quantity for templates'];
        }
        
        // Check stock for tools
        if ($cartItem['product_type'] === 'tool' && !checkToolStock($cartItem['product_id'], $quantity)) {
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
 * Calculate cart total with bonus code, affiliate discount, or user referral discount
 * 
 * Priority: Bonus codes > Affiliate codes > User Referral codes
 * Bonus codes provide discount with NO commission
 * Affiliate codes provide 20% discount WITH 30% affiliate commission
 * User Referral codes provide 20% discount WITH 30% referrer commission
 * 
 * @param string $sessionId Optional session ID
 * @param string $affiliateCode Optional affiliate code for discount
 * @param string $bonusCode Optional bonus code for discount (takes priority)
 * @param string $userReferralCode Optional user referral code for discount
 * @param string $customerEmail Optional customer email to prevent self-referral
 * @return array Cart totals with breakdown
 */
function getCartTotal($sessionId = null, $affiliateCode = null, $bonusCode = null, $userReferralCode = null, $customerEmail = null) {
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
    
    $discount = 0;
    $discountType = null;
    $discountCode = null;
    $discountPercent = 0;
    $bonusCodeId = null;
    $referralCode = null;
    
    // PRIORITY 1: Check bonus code first (no commission when bonus code is used)
    if ($bonusCode) {
        require_once __DIR__ . '/bonus_codes.php';
        $bonusCodeData = getBonusCodeByCode($bonusCode);
        if ($bonusCodeData && $bonusCodeData['is_active'] && 
            (!$bonusCodeData['expires_at'] || strtotime($bonusCodeData['expires_at']) >= time())) {
            $discountPercent = $bonusCodeData['discount_percent'];
            $discount = $subtotal * ($discountPercent / 100);
            $discountType = 'bonus_code';
            $discountCode = $bonusCode;
            $bonusCodeId = $bonusCodeData['id'];
        }
    }
    
    // PRIORITY 2: Apply affiliate discount if no valid bonus code (20% discount)
    if ($discount == 0 && $affiliateCode) {
        $discount = $subtotal * CUSTOMER_DISCOUNT_RATE; // 20% affiliate discount
        $discountType = 'affiliate';
        $discountCode = $affiliateCode;
        $discountPercent = CUSTOMER_DISCOUNT_RATE * 100;
    }
    
    // PRIORITY 3: Apply user referral discount if no affiliate or bonus code (20% discount)
    // SELF-REFERRAL PREVENTION: Users cannot use their own referral code
    if ($discount == 0 && $userReferralCode) {
        require_once __DIR__ . '/functions.php';
        require_once __DIR__ . '/customer_session.php';
        $userReferral = getUserReferralByCode($userReferralCode);
        if ($userReferral && $userReferral['status'] === 'active') {
            // Check if this is the user's own referral code (self-referral prevention)
            $isSelfReferral = false;
            
            // Check by logged-in customer ID
            $currentCustomer = getCurrentCustomer();
            if ($currentCustomer && $currentCustomer['id'] == $userReferral['customer_id']) {
                $isSelfReferral = true;
                error_log("⚠️  SELF-REFERRAL BLOCKED: Customer #{$currentCustomer['id']} tried to use their own referral code {$userReferralCode}");
            }
            
            // Also check by email (for guest checkout or email-based comparison)
            if (!$isSelfReferral && $customerEmail && strtolower($customerEmail) === strtolower($userReferral['customer_email'] ?? '')) {
                $isSelfReferral = true;
                error_log("⚠️  SELF-REFERRAL BLOCKED: Email '{$customerEmail}' tried to use their own referral code {$userReferralCode}");
            }
            
            if (!$isSelfReferral) {
                $discount = $subtotal * CUSTOMER_DISCOUNT_RATE; // 20% user referral discount
                $discountType = 'user_referral';
                $discountCode = $userReferralCode;
                $discountPercent = CUSTOMER_DISCOUNT_RATE * 100;
                $referralCode = $userReferralCode;
            }
        }
    }
    
    $total = $subtotal - $discount;
    
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'item_count' => $itemCount,
        'has_discount' => $discount > 0,
        'discount_type' => $discountType,
        'discount_code' => $discountCode,
        'discount_percent' => $discountPercent,
        'bonus_code_id' => $bonusCodeId,
        'affiliate_code' => $discountType === 'affiliate' ? $affiliateCode : null,
        'referral_code' => $discountType === 'user_referral' ? $referralCode : null
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
        $productType = $item['product_type'] ?? 'tool';
        
        // Check if product still active
        if ($item['active'] != 1) {
            $issues[] = [
                'cart_item_id' => $item['id'],
                'tool_name' => $item['name'],
                'product_name' => $item['name'],
                'product_type' => $productType,
                'issue' => ucfirst($productType) . ' is no longer available'
            ];
            $valid = false;
            continue;
        }
        
        // Check stock for tools only
        if ($productType === 'tool' && !checkToolStock($item['product_id'], $item['quantity'])) {
            $tool = getToolById($item['product_id']);
            $available = $tool['stock_quantity'] ?? 0;
            $issues[] = [
                'cart_item_id' => $item['id'],
                'tool_name' => $item['name'],
                'product_name' => $item['name'],
                'product_type' => 'tool',
                'issue' => 'Insufficient stock (Requested: ' . $item['quantity'] . ', Available: ' . $available . ')',
                'requested' => $item['quantity'],
                'available' => $available
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
