<?php
/**
 * Tool Files Management
 * Upload, download, and manage tool files
 * Phase 3: Enhanced with analytics, regeneration, and improved delivery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Format file size to human readable format
 * Phase 3: Helper function (defined first for use by other functions)
 */
function formatFileSize($bytes) {
    if ($bytes === null || $bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
}

/**
 * Get all files for a tool
 */
function getToolFiles($toolId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM tool_files 
        WHERE tool_id = ? 
        ORDER BY sort_order ASC, created_at ASC
    ");
    $stmt->execute([$toolId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if a tool file has been delivered to any orders
 * Used to prevent deletion of files that customers have already received
 * 
 * @param int $fileId The tool file ID to check
 * @return array ['is_protected' => bool, 'delivered_count' => int, 'message' => string]
 */
function isFileDeliveredToOrders($fileId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT dt.pending_order_id) as delivered_count
        FROM download_tokens dt
        INNER JOIN pending_orders po ON dt.pending_order_id = po.id
        WHERE dt.file_id = ? 
        AND po.status IN ('paid', 'completed')
    ");
    $stmt->execute([$fileId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $deliveredCount = (int)($result['delivered_count'] ?? 0);
    
    if ($deliveredCount > 0) {
        return [
            'is_protected' => true,
            'delivered_count' => $deliveredCount,
            'message' => "This file has been delivered to {$deliveredCount} customer(s). Deletion would break their download links. Archive instead or contact support."
        ];
    }
    
    return [
        'is_protected' => false,
        'delivered_count' => 0,
        'message' => ''
    ];
}

/**
 * Check if a tool file can be safely deleted
 * Returns true if file has no active deliveries, false otherwise
 * 
 * @param int $fileId The tool file ID to check
 * @param bool $forceDelete If true, allows deletion even if delivered (admin override)
 * @return array ['can_delete' => bool, 'reason' => string]
 */
function canDeleteToolFile($fileId, $forceDelete = false) {
    if ($forceDelete) {
        return ['can_delete' => true, 'reason' => 'Admin force delete enabled'];
    }
    
    $deliveryCheck = isFileDeliveredToOrders($fileId);
    
    if ($deliveryCheck['is_protected']) {
        return [
            'can_delete' => false, 
            'reason' => $deliveryCheck['message']
        ];
    }
    
    return ['can_delete' => true, 'reason' => ''];
}

/**
 * Check if a tool has any completed orders (used for order completion locking)
 * When a tool has completed orders, file modifications require confirmation
 * ENHANCED: Also checks download_tokens to catch cases where deliveries are pending but customers have access
 * 
 * @param int $toolId The tool ID to check
 * @return array ['has_completed_orders' => bool, 'order_count' => int, 'message' => string]
 */
function hasCompletedOrders($toolId) {
    $db = getDb();
    
    // Check deliveries marked as delivered
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT d.pending_order_id) as order_count
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.product_id = ? 
        AND d.product_type = 'tool'
        AND d.delivery_status = 'delivered'
        AND po.status IN ('paid', 'completed')
    ");
    $stmt->execute([$toolId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deliveredCount = (int)($result['order_count'] ?? 0);
    
    // Also check for ANY download tokens - customers may have received links even if delivery not marked 'delivered'
    $tokenStmt = $db->prepare("
        SELECT COUNT(DISTINCT dt.pending_order_id) as token_count
        FROM download_tokens dt
        INNER JOIN tool_files tf ON dt.file_id = tf.id
        INNER JOIN pending_orders po ON dt.pending_order_id = po.id
        WHERE tf.tool_id = ? 
        AND po.status IN ('paid', 'completed')
    ");
    $tokenStmt->execute([$toolId]);
    $tokenResult = $tokenStmt->fetch(PDO::FETCH_ASSOC);
    $tokenCount = (int)($tokenResult['token_count'] ?? 0);
    
    // Use the higher of the two counts
    $orderCount = max($deliveredCount, $tokenCount);
    
    if ($orderCount > 0) {
        return [
            'has_completed_orders' => true,
            'order_count' => $orderCount,
            'message' => "This tool has {$orderCount} order(s) with active download access. File changes require unmarking the tool first."
        ];
    }
    
    return [
        'has_completed_orders' => false,
        'order_count' => 0,
        'message' => ''
    ];
}

/**
 * Check if file modifications are allowed for a tool
 * If tool has completed orders and is marked as complete, changes are locked
 * 
 * @param int $toolId The tool ID
 * @return array ['allowed' => bool, 'reason' => string, 'requires_unmark' => bool]
 */
function canModifyToolFiles($toolId) {
    $db = getDb();
    
    // Check if tool is marked as upload_complete
    $stmt = $db->prepare("SELECT upload_complete, name FROM tools WHERE id = ?");
    $stmt->execute([$toolId]);
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tool) {
        return ['allowed' => false, 'reason' => 'Tool not found', 'requires_unmark' => false];
    }
    
    // If tool is NOT marked as complete, modifications are allowed
    if (empty($tool['upload_complete'])) {
        return ['allowed' => true, 'reason' => '', 'requires_unmark' => false];
    }
    
    // Tool is marked as complete - check if there are completed orders
    $orderCheck = hasCompletedOrders($toolId);
    
    if ($orderCheck['has_completed_orders']) {
        return [
            'allowed' => false,
            'reason' => "This tool ({$tool['name']}) has {$orderCheck['order_count']} completed order(s) and is marked as complete. Unmark the tool (toggle 'Files Ready') before making changes to prevent breaking customer downloads.",
            'requires_unmark' => true,
            'order_count' => $orderCheck['order_count']
        ];
    }
    
    // No completed orders, allow modifications
    return ['allowed' => true, 'reason' => '', 'requires_unmark' => false];
}

/**
 * Add external link for a tool
 * Stores the URL in file_path column with file_type='link'
 */
function addToolLink($toolId, $externalUrl, $description = '', $sortOrder = 0) {
    $db = getDb();
    
    // Extract domain from URL for display
    $parsedUrl = parse_url($externalUrl);
    $linkName = $parsedUrl['host'] ?? 'External Link';
    
    $stmt = $db->prepare("
        INSERT INTO tool_files (
            tool_id, file_name, file_path, file_type, file_description,
            file_size, mime_type, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $toolId,
        $linkName,
        $externalUrl,
        'link',
        $description,
        0,
        'text/plain',
        $sortOrder
    ]);
    
    $db->exec("UPDATE tools SET total_files = (SELECT COUNT(*) FROM tool_files WHERE tool_id = {$toolId}) WHERE id = {$toolId}");
    
    return $db->lastInsertId();
}

/**
 * Generate download link with token
 * Phase 3: Updated with configurable expiry (30 days default)
 * Fixed: Now checks for existing valid tokens before creating new ones
 */
function generateDownloadLink($fileId, $orderId, $expiryDays = null) {
    $db = getDb();
    
    if ($expiryDays === null) {
        $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    }
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    
    $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) return null;
    
    $existingStmt = $db->prepare("
        SELECT * FROM download_tokens 
        WHERE file_id = ? AND pending_order_id = ? 
        AND expires_at > datetime('now')
        AND download_count < max_downloads
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $existingStmt->execute([$fileId, $orderId]);
    $existingToken = $existingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingToken) {
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $downloadUrl = $siteUrl . "/download.php?token={$existingToken['token']}";
        
        return [
            'id' => $existingToken['id'],
            'name' => $file['file_name'],
            'url' => $downloadUrl,
            'expires_at' => $existingToken['expires_at'],
            'expires_formatted' => date('F j, Y', strtotime($existingToken['expires_at'])),
            'file_type' => $file['file_type'],
            'file_path' => $file['file_path'],
            'file_size' => $file['file_size'],
            'file_size_formatted' => formatFileSize($file['file_size']),
            'max_downloads' => $existingToken['max_downloads']
        ];
    }
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    
    $stmt = $db->prepare("
        INSERT INTO download_tokens (file_id, pending_order_id, token, expires_at, max_downloads)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fileId, $orderId, $token, $expiresAt, $maxDownloads]);
    
    $tokenId = $db->lastInsertId();
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    $downloadUrl = $siteUrl . "/download.php?token={$token}";
    
    return [
        'id' => $tokenId,
        'name' => $file['file_name'],
        'url' => $downloadUrl,
        'expires_at' => $expiresAt,
        'expires_formatted' => date('F j, Y', strtotime($expiresAt)),
        'file_type' => $file['file_type'],
        'file_path' => $file['file_path'],
        'file_size' => $file['file_size'],
        'file_size_formatted' => formatFileSize($file['file_size']),
        'max_downloads' => $maxDownloads
    ];
}

/**
 * Regenerate expired download link for admin
 * Phase 3: Allow admin to regenerate expired links
 */
function regenerateDownloadLink($tokenId, $newExpiryDays = null) {
    $db = getDb();
    
    if ($newExpiryDays === null) {
        $newExpiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    }
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    
    $stmt = $db->prepare("SELECT dt.*, tf.file_name, tf.file_size, tf.file_type FROM download_tokens dt JOIN tool_files tf ON dt.file_id = tf.id WHERE dt.id = ?");
    $stmt->execute([$tokenId]);
    $existingToken = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingToken) {
        return ['success' => false, 'message' => 'Download token not found'];
    }
    
    $newToken = bin2hex(random_bytes(32));
    $newExpiresAt = date('Y-m-d H:i:s', strtotime("+{$newExpiryDays} days"));
    
    $stmt = $db->prepare("
        INSERT INTO download_tokens (file_id, pending_order_id, token, expires_at, max_downloads)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $existingToken['file_id'], 
        $existingToken['pending_order_id'], 
        $newToken, 
        $newExpiresAt,
        $maxDownloads
    ]);
    
    $newTokenId = $db->lastInsertId();
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    $downloadUrl = $siteUrl . "/download.php?token={$newToken}";
    
    return [
        'success' => true,
        'message' => 'Download link regenerated successfully',
        'link' => [
            'id' => $newTokenId,
            'name' => $existingToken['file_name'],
            'url' => $downloadUrl,
            'expires_at' => $newExpiresAt,
            'expires_formatted' => date('F j, Y', strtotime($newExpiresAt)),
            'file_type' => $existingToken['file_type'],
            'file_size_formatted' => formatFileSize($existingToken['file_size']),
            'max_downloads' => $maxDownloads
        ]
    ];
}

/**
 * Get all download tokens for an order
 * Phase 3: For admin viewing and regeneration
 */
function getOrderDownloadTokens($orderId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT dt.*, tf.file_name, tf.file_size, tf.file_type, tf.tool_id,
               CASE WHEN dt.expires_at < datetime('now', '+1 hour') THEN 1 ELSE 0 END as is_expired,
               CASE WHEN dt.download_count >= dt.max_downloads THEN 1 ELSE 0 END as limit_exceeded
        FROM download_tokens dt
        JOIN tool_files tf ON dt.file_id = tf.id
        WHERE dt.pending_order_id = ?
        ORDER BY dt.created_at DESC
    ");
    $stmt->execute([$orderId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tokens as &$token) {
        $token['file_size_formatted'] = formatFileSize($token['file_size']);
        $token['expires_formatted'] = date('F j, Y g:i A', strtotime($token['expires_at']));
        $token['status'] = 'active';
        if ($token['is_expired']) {
            $token['status'] = 'expired';
        } elseif ($token['limit_exceeded']) {
            $token['status'] = 'limit_exceeded';
        }
    }
    
    return $tokens;
}

/**
 * Get download analytics for a tool
 * Phase 3: Download tracking analytics
 */
function getToolDownloadAnalytics($toolId = null) {
    $db = getDb();
    
    if ($toolId) {
        $stmt = $db->prepare("
            SELECT tf.tool_id, t.name as tool_name,
                   SUM(tf.download_count) as total_downloads,
                   COUNT(DISTINCT dt.pending_order_id) as unique_customers,
                   MAX(dt.last_downloaded_at) as last_download
            FROM tool_files tf
            LEFT JOIN download_tokens dt ON tf.id = dt.file_id
            LEFT JOIN tools t ON tf.tool_id = t.id
            WHERE tf.tool_id = ?
            GROUP BY tf.tool_id, t.name
        ");
        $stmt->execute([$toolId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $stmt = $db->query("
        SELECT tf.tool_id, t.name as tool_name,
               SUM(tf.download_count) as total_downloads,
               COUNT(DISTINCT dt.pending_order_id) as unique_customers,
               MAX(dt.last_downloaded_at) as last_download
        FROM tool_files tf
        LEFT JOIN download_tokens dt ON tf.id = dt.file_id
        LEFT JOIN tools t ON tf.tool_id = t.id
        GROUP BY tf.tool_id, t.name
        ORDER BY total_downloads DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get overall download statistics
 * Phase 3: Analytics dashboard data
 */
function getDownloadStatistics() {
    $db = getDb();
    
    $stats = [];
    
    $totalStmt = $db->query("SELECT SUM(download_count) as total FROM tool_files");
    $stats['total_downloads'] = (int)($totalStmt->fetchColumn() ?? 0);
    
    $todayStmt = $db->query("
        SELECT COUNT(*) as today_downloads FROM download_tokens 
        WHERE date(last_downloaded_at) = date('now') AND download_count > 0
    ");
    $stats['downloads_today'] = (int)($todayStmt->fetchColumn() ?? 0);
    
    $expiredStmt = $db->query("
        SELECT COUNT(*) as expired FROM download_tokens 
        WHERE expires_at < datetime('now', '+1 hour') AND download_count = 0
    ");
    $stats['expired_unused'] = (int)($expiredStmt->fetchColumn() ?? 0);
    
    $activeStmt = $db->query("
        SELECT COUNT(*) as active FROM download_tokens 
        WHERE expires_at >= datetime('now', '+1 hour') AND download_count < max_downloads
    ");
    $stats['active_links'] = (int)($activeStmt->fetchColumn() ?? 0);
    
    return $stats;
}

/**
 * Upload tool file
 */
function uploadToolFile($toolId, $uploadedFile, $fileType, $description = '', $sortOrder = 0) {
    // Validate file
    if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        throw new Exception('Invalid file upload');
    }
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../uploads/tools/files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $fileName = 'tool_' . $toolId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = 'uploads/tools/files/' . $fileName;
    $fullPath = __DIR__ . '/../' . $filePath;
    
    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Store in database
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO tool_files (
            tool_id, file_name, file_path, file_type, file_description,
            file_size, mime_type, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $toolId,
        $uploadedFile['name'],
        $filePath,
        $fileType,
        $description,
        $uploadedFile['size'],
        $uploadedFile['type'],
        $sortOrder
    ]);
    
    // Update tool's total files count
    $db->exec("UPDATE tools SET total_files = (SELECT COUNT(*) FROM tool_files WHERE tool_id = {$toolId}) WHERE id = {$toolId}");
    
    return $db->lastInsertId();
}

/**
 * Track file download
 */
function trackDownload($fileId, $orderId) {
    $db = getDb();
    
    // Increment download count in tool_files
    $stmt = $db->prepare("UPDATE tool_files SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$fileId]);
}

/**
 * Generate ZIP bundle for all tool files in an order
 * Phase 3.3: Bundle download feature
 */
function generateToolZipBundle($orderId, $toolId) {
    $db = getDb();
    
    $orderStmt = $db->prepare("SELECT * FROM pending_orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return ['success' => false, 'message' => 'Order not found'];
    }
    
    $toolStmt = $db->prepare("SELECT * FROM tools WHERE id = ?");
    $toolStmt->execute([$toolId]);
    $tool = $toolStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tool) {
        return ['success' => false, 'message' => 'Tool not found'];
    }
    
    $files = getToolFiles($toolId);
    if (empty($files)) {
        return ['success' => false, 'message' => 'No files found for this tool'];
    }
    
    $zipDir = __DIR__ . '/../uploads/tools/bundles/';
    if (!is_dir($zipDir)) {
        mkdir($zipDir, 0755, true);
    }
    
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tool['name']);
    $zipFileName = $safeName . '_bundle_' . time() . '_' . bin2hex(random_bytes(4)) . '.zip';
    $zipPath = $zipDir . $zipFileName;
    
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== true) {
        return ['success' => false, 'message' => 'Failed to create ZIP archive'];
    }
    
    $addedFiles = 0;
    $externalLinks = [];
    
    foreach ($files as $file) {
        // Check if this is an external link (URL) vs a local file
        $isExternalUrl = preg_match('/^https?:\/\//i', $file['file_path']);
        
        if ($isExternalUrl) {
            // Collect external links for the README
            $externalLinks[] = [
                'name' => $file['file_name'] ?? $file['file_description'] ?? 'External Link',
                'url' => $file['file_path'],
                'description' => $file['file_description'] ?? ''
            ];
        } else {
            // Add local file to ZIP
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file['file_name']);
                $addedFiles++;
            }
        }
    }
    
    $readmeContent = "===========================================\n";
    $readmeContent .= "WebDaddy Empire - " . $tool['name'] . "\n";
    $readmeContent .= "===========================================\n\n";
    $readmeContent .= "Order #: " . $orderId . "\n";
    $readmeContent .= "Customer: " . ($order['customer_name'] ?? 'N/A') . "\n";
    $readmeContent .= "Downloaded: " . date('Y-m-d H:i:s') . "\n\n";
    
    // List included files
    if ($addedFiles > 0) {
        $readmeContent .= "Included Files:\n";
        $readmeContent .= "---------------\n";
        foreach ($files as $file) {
            $isExternalUrl = preg_match('/^https?:\/\//i', $file['file_path']);
            if (!$isExternalUrl) {
                $readmeContent .= "- " . $file['file_name'] . " (" . formatFileSize($file['file_size']) . ")\n";
                if (!empty($file['file_description'])) {
                    $readmeContent .= "  " . $file['file_description'] . "\n";
                }
            }
        }
        $readmeContent .= "\n";
    }
    
    // List external links
    if (!empty($externalLinks)) {
        $readmeContent .= "External Download Links:\n";
        $readmeContent .= "------------------------\n";
        $readmeContent .= "The following resources are available via external links.\n";
        $readmeContent .= "Please open these URLs in your browser to access them:\n\n";
        foreach ($externalLinks as $link) {
            $readmeContent .= "- " . $link['name'] . "\n";
            $readmeContent .= "  URL: " . $link['url'] . "\n";
            if (!empty($link['description'])) {
                $readmeContent .= "  Description: " . $link['description'] . "\n";
            }
            $readmeContent .= "\n";
        }
    }
    
    $readmeContent .= "Support: " . (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'admin@webdaddy.online') . "\n";
    $readmeContent .= "Website: " . (defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online') . "\n";
    $readmeContent .= "\nThank you for your purchase!\n";
    
    $zip->addFromString('README.txt', $readmeContent);
    $zip->close();
    
    // Allow ZIP creation even if only external links exist (no local files)
    if ($addedFiles === 0 && empty($externalLinks)) {
        unlink($zipPath);
        return ['success' => false, 'message' => 'No files could be added to the ZIP'];
    }
    
    return [
        'success' => true,
        'zip_path' => 'uploads/tools/bundles/' . $zipFileName,
        'zip_name' => $tool['name'] . ' Bundle.zip',
        'file_count' => $addedFiles,
        'zip_size' => filesize($zipPath)
    ];
}

/**
 * Generate bundle download token
 * Phase 3.3: Creates a secure download token for the ZIP bundle
 * FIXED: Uses bundle_tokens table instead of download_tokens to avoid FK constraint issues
 */
function generateBundleDownloadToken($orderId, $toolId, $expiryDays = null) {
    $db = getDb();
    
    if ($expiryDays === null) {
        $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    }
    
    try {
        $result = generateToolZipBundle($orderId, $toolId);
        if (!$result['success']) {
            return $result;
        }
        
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
        $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
        
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bundle_tokens'");
        if (!$tableCheck->fetch()) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS bundle_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tool_id INTEGER NOT NULL,
                    pending_order_id INTEGER NOT NULL,
                    token TEXT NOT NULL UNIQUE,
                    download_count INTEGER DEFAULT 0,
                    max_downloads INTEGER DEFAULT 5,
                    expires_at TIMESTAMP NOT NULL,
                    last_downloaded_at TIMESTAMP NULL,
                    zip_path TEXT,
                    zip_name TEXT,
                    file_count INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
                )
            ");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_bundle_tokens_token ON bundle_tokens(token)");
        }
        
        $stmt = $db->prepare("
            INSERT INTO bundle_tokens (
                tool_id, pending_order_id, token, expires_at, max_downloads, zip_path, zip_name, file_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $toolId, 
            $orderId, 
            $token, 
            $expiresAt, 
            $maxDownloads,
            $result['zip_path'],
            $result['zip_name'],
            $result['file_count']
        ]);
        
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        
        return [
            'success' => true,
            'token' => $token,
            'url' => $siteUrl . '/download.php?token=' . $token . '&bundle=1',
            'expires_at' => $expiresAt,
            'file_count' => $result['file_count'],
            'zip_size' => $result['zip_size']
        ];
    } catch (Exception $e) {
        error_log("Bundle token generation failed for order $orderId, tool $toolId: " . $e->getMessage());
        return ['success' => false, 'message' => 'Bundle generation failed: ' . $e->getMessage()];
    }
}

/**
 * Get download tokens for an order and specific tool
 * Phase 3.2: Returns array of tokens with file information for admin UI
 * Includes file_type to differentiate between files and external links
 */
function getDownloadTokens($orderId, $toolId = null) {
    $db = getDb();
    
    $sql = "
        SELECT dt.*, tf.file_name, tf.file_size, tf.file_type, tf.file_path
        FROM download_tokens dt
        LEFT JOIN tool_files tf ON dt.file_id = tf.id
        WHERE dt.pending_order_id = ? AND (dt.is_bundle = 0 OR dt.is_bundle IS NULL)
    ";
    $params = [$orderId];
    
    if ($toolId !== null) {
        $sql .= " AND tf.tool_id = ?";
        $params[] = $toolId;
    }
    
    $sql .= " ORDER BY dt.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Filter tokens to show only the best one per file
 * Keeps the token with available downloads, or newest if all are used
 */
function filterBestDownloadTokens($tokens) {
    if (empty($tokens)) {
        return [];
    }
    
    $filtered = [];
    
    foreach ($tokens as $token) {
        $fileName = $token['file_name'] ?? 'unknown';
        
        if (!isset($filtered[$fileName])) {
            $filtered[$fileName] = $token;
        } else {
            $existing = $filtered[$fileName];
            $existingUsed = ($existing['download_count'] ?? 0) >= ($existing['max_downloads'] ?? 10);
            $currentUsed = ($token['download_count'] ?? 0) >= ($token['max_downloads'] ?? 10);
            
            if (!$currentUsed && $existingUsed) {
                $filtered[$fileName] = $token;
            } elseif (!$currentUsed && !$existingUsed) {
                if (strtotime($token['created_at']) > strtotime($existing['created_at'])) {
                    $filtered[$fileName] = $token;
                }
            } elseif ($currentUsed && $existingUsed) {
                if (strtotime($token['created_at']) > strtotime($existing['created_at'])) {
                    $filtered[$fileName] = $token;
                }
            }
        }
    }
    
    return array_values($filtered);
}

/**
 * Get bundle download info by token
 * Phase 3.3: Retrieves bundle info for download processing
 * FIXED: Query from bundle_tokens table directly (not the broken JOIN)
 */
function getBundleByToken($token) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM bundle_tokens
        WHERE token = ? AND expires_at > datetime('now', '+1 hour')
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
