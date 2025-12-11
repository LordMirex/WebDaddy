<?php
/**
 * Bonus Codes Management Functions
 * 
 * Handles admin-managed discount codes that are separate from affiliate codes.
 * Only ONE bonus code can be active at a time.
 * Bonus codes give discounts to buyers but NO commission to affiliates.
 */

require_once __DIR__ . '/db.php';

/**
 * Create the bonus_codes table if it doesn't exist
 */
function initBonusCodesTable() {
    $db = getDb();
    $dbType = getDbType();
    
    if ($dbType === 'pgsql') {
        $sql = "
            CREATE TABLE IF NOT EXISTS bonus_codes (
                id SERIAL PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                discount_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
                is_active BOOLEAN DEFAULT FALSE,
                expires_at TIMESTAMP NULL,
                usage_count INTEGER DEFAULT 0,
                total_sales_generated DECIMAL(15,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
    } else {
        $sql = "
            CREATE TABLE IF NOT EXISTS bonus_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code VARCHAR(50) UNIQUE NOT NULL,
                discount_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
                is_active BOOLEAN DEFAULT 0,
                expires_at DATETIME NULL,
                usage_count INTEGER DEFAULT 0,
                total_sales_generated DECIMAL(15,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
    }
    
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log('Failed to create bonus_codes table: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all bonus codes
 * 
 * @return array List of all bonus codes
 */
function getAllBonusCodes() {
    $db = getDb();
    
    try {
        $stmt = $db->query("SELECT * FROM bonus_codes ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching bonus codes: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a bonus code by ID
 * 
 * @param int $id Bonus code ID
 * @return array|null Bonus code data or null
 */
function getBonusCodeById($id) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM bonus_codes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching bonus code: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get bonus code by code string
 * 
 * @param string $code The bonus code string
 * @return array|null Bonus code data or null
 */
function getBonusCodeByCode($code) {
    $db = getDb();
    $code = strtoupper(trim($code));
    
    try {
        $stmt = $db->prepare("SELECT * FROM bonus_codes WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching bonus code: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get the currently active and valid bonus code
 * 
 * @return array|null Active bonus code or null
 */
function getActiveBonusCode() {
    $db = getDb();
    $dbType = getDbType();
    
    try {
        if ($dbType === 'pgsql') {
            $sql = "
                SELECT * FROM bonus_codes 
                WHERE is_active = true 
                AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1
            ";
        } else {
            $sql = "
                SELECT * FROM bonus_codes 
                WHERE is_active = 1 
                AND (expires_at IS NULL OR expires_at > datetime('now'))
                LIMIT 1
            ";
        }
        
        $stmt = $db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching active bonus code: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if a bonus code is valid (exists, active, not expired)
 * 
 * @param string $code The bonus code to validate
 * @return array|false Bonus code data if valid, false otherwise
 */
function validateBonusCode($code) {
    $db = getDb();
    $code = strtoupper(trim($code));
    $dbType = getDbType();
    
    try {
        if ($dbType === 'pgsql') {
            $sql = "
                SELECT * FROM bonus_codes 
                WHERE code = ? 
                AND is_active = true 
                AND (expires_at IS NULL OR expires_at > NOW())
            ";
        } else {
            $sql = "
                SELECT * FROM bonus_codes 
                WHERE code = ? 
                AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > datetime('now'))
            ";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: false;
    } catch (PDOException $e) {
        error_log('Error validating bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a new bonus code
 * 
 * @param string $code The bonus code (will be uppercased)
 * @param float $discountPercent Discount percentage (e.g., 20.00 for 20%)
 * @param string|null $expiresAt Expiration date (Y-m-d H:i:s format) or null for no expiry
 * @return int|false New bonus code ID or false on failure
 */
function createBonusCode($code, $discountPercent, $expiresAt = null) {
    $db = getDb();
    $code = strtoupper(trim($code));
    
    if (!preg_match('/^[A-Z0-9]{3,30}$/', $code)) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO bonus_codes (code, discount_percent, expires_at, is_active)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$code, $discountPercent, $expiresAt]);
        
        $dbType = getDbType();
        if ($dbType === 'pgsql') {
            return $db->lastInsertId('bonus_codes_id_seq');
        }
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('Error creating bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a bonus code
 * 
 * @param int $id Bonus code ID
 * @param string $code The bonus code (will be uppercased)
 * @param float $discountPercent Discount percentage
 * @param string|null $expiresAt Expiration date or null
 * @return bool Success status
 */
function updateBonusCode($id, $code, $discountPercent, $expiresAt = null) {
    $db = getDb();
    $code = strtoupper(trim($code));
    
    if (!preg_match('/^[A-Z0-9]{3,30}$/', $code)) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE bonus_codes 
            SET code = ?, discount_percent = ?, expires_at = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$code, $discountPercent, $expiresAt, $id]);
    } catch (PDOException $e) {
        error_log('Error updating bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a bonus code
 * 
 * @param int $id Bonus code ID
 * @return bool Success status
 */
function deleteBonusCode($id) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("DELETE FROM bonus_codes WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Error deleting bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Activate a bonus code (deactivates any other active code first)
 * 
 * @param int $id Bonus code ID to activate
 * @return bool Success status
 */
function activateBonusCode($id) {
    $db = getDb();
    
    try {
        $db->beginTransaction();
        
        $dbType = getDbType();
        if ($dbType === 'pgsql') {
            $db->exec("UPDATE bonus_codes SET is_active = false, updated_at = CURRENT_TIMESTAMP");
        } else {
            $db->exec("UPDATE bonus_codes SET is_active = 0, updated_at = CURRENT_TIMESTAMP");
        }
        
        if ($dbType === 'pgsql') {
            $stmt = $db->prepare("UPDATE bonus_codes SET is_active = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE bonus_codes SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        }
        $stmt->execute([$id]);
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Error activating bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Deactivate a bonus code
 * 
 * @param int $id Bonus code ID to deactivate
 * @return bool Success status
 */
function deactivateBonusCode($id) {
    $db = getDb();
    $dbType = getDbType();
    
    try {
        if ($dbType === 'pgsql') {
            $stmt = $db->prepare("UPDATE bonus_codes SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE bonus_codes SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        }
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Error deactivating bonus code: ' . $e->getMessage());
        return false;
    }
}

/**
 * Record usage of a bonus code (increment usage count and add to total sales)
 * 
 * @param int $bonusCodeId Bonus code ID
 * @param float $saleAmount Sale amount to add
 * @return bool Success status
 */
function recordBonusCodeUsage($bonusCodeId, $saleAmount) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            UPDATE bonus_codes 
            SET usage_count = usage_count + 1, 
                total_sales_generated = total_sales_generated + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$saleAmount, $bonusCodeId]);
    } catch (PDOException $e) {
        error_log('Error recording bonus code usage: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check and deactivate expired bonus codes
 * 
 * @return int Number of codes deactivated
 */
function deactivateExpiredBonusCodes() {
    $db = getDb();
    $dbType = getDbType();
    
    try {
        if ($dbType === 'pgsql') {
            $stmt = $db->query("
                UPDATE bonus_codes 
                SET is_active = false, updated_at = CURRENT_TIMESTAMP
                WHERE is_active = true AND expires_at IS NOT NULL AND expires_at <= NOW()
            ");
        } else {
            $stmt = $db->query("
                UPDATE bonus_codes 
                SET is_active = 0, updated_at = CURRENT_TIMESTAMP
                WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= datetime('now')
            ");
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('Error deactivating expired codes: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get bonus code statistics
 * 
 * @return array Statistics array
 */
function getBonusCodeStats() {
    $db = getDb();
    $dbType = getDbType();
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as total_codes FROM bonus_codes");
        $totalCodes = $stmt->fetch(PDO::FETCH_ASSOC)['total_codes'];
        
        if ($dbType === 'pgsql') {
            $stmt = $db->query("SELECT COUNT(*) as active_codes FROM bonus_codes WHERE is_active = true");
        } else {
            $stmt = $db->query("SELECT COUNT(*) as active_codes FROM bonus_codes WHERE is_active = 1");
        }
        $activeCodes = $stmt->fetch(PDO::FETCH_ASSOC)['active_codes'];
        
        $stmt = $db->query("SELECT COALESCE(SUM(usage_count), 0) as total_usage FROM bonus_codes");
        $totalUsage = $stmt->fetch(PDO::FETCH_ASSOC)['total_usage'];
        
        $stmt = $db->query("SELECT COALESCE(SUM(total_sales_generated), 0) as total_sales FROM bonus_codes");
        $totalSales = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'];
        
        return [
            'total_codes' => (int)$totalCodes,
            'active_codes' => (int)$activeCodes,
            'total_usage' => (int)$totalUsage,
            'total_sales' => (float)$totalSales
        ];
    } catch (PDOException $e) {
        error_log('Error fetching bonus code stats: ' . $e->getMessage());
        return [
            'total_codes' => 0,
            'active_codes' => 0,
            'total_usage' => 0,
            'total_sales' => 0
        ];
    }
}

/**
 * Increment bonus code usage count and total sales
 * Called when an order with a bonus code is placed
 * 
 * @param int $bonusCodeId The bonus code ID
 * @param float $saleAmount The sale amount to add
 * @return bool Success status
 */
function incrementBonusCodeUsage($bonusCodeId, $saleAmount) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            UPDATE bonus_codes 
            SET usage_count = usage_count + 1,
                total_sales_generated = total_sales_generated + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$saleAmount, $bonusCodeId]);
    } catch (PDOException $e) {
        error_log('Error incrementing bonus code usage: ' . $e->getMessage());
        return false;
    }
}

initBonusCodesTable();
