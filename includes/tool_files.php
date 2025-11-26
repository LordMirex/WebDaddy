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
 * Generate download link with token
 * Phase 3: Updated with configurable expiry (30 days default)
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
    foreach ($files as $file) {
        $filePath = __DIR__ . '/../' . $file['file_path'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, $file['file_name']);
            $addedFiles++;
        }
    }
    
    $readmeContent = "===========================================\n";
    $readmeContent .= "WebDaddy Empire - " . $tool['name'] . "\n";
    $readmeContent .= "===========================================\n\n";
    $readmeContent .= "Order #: " . $orderId . "\n";
    $readmeContent .= "Customer: " . ($order['customer_name'] ?? 'N/A') . "\n";
    $readmeContent .= "Downloaded: " . date('Y-m-d H:i:s') . "\n\n";
    $readmeContent .= "Included Files:\n";
    $readmeContent .= "---------------\n";
    foreach ($files as $file) {
        $readmeContent .= "- " . $file['file_name'] . " (" . formatFileSize($file['file_size']) . ")\n";
        if (!empty($file['file_description'])) {
            $readmeContent .= "  " . $file['file_description'] . "\n";
        }
    }
    $readmeContent .= "\n";
    $readmeContent .= "Support: " . (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@webdaddyempire.com') . "\n";
    $readmeContent .= "Website: " . (defined('SITE_URL') ? SITE_URL : 'https://webdaddyempire.com') . "\n";
    $readmeContent .= "\nThank you for your purchase!\n";
    
    $zip->addFromString('README.txt', $readmeContent);
    $zip->close();
    
    if ($addedFiles === 0) {
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
 */
function generateBundleDownloadToken($orderId, $toolId, $expiryDays = null) {
    $db = getDb();
    
    if ($expiryDays === null) {
        $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    }
    
    $result = generateToolZipBundle($orderId, $toolId);
    if (!$result['success']) {
        return $result;
    }
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    
    $stmt = $db->prepare("
        INSERT INTO download_tokens (
            file_id, pending_order_id, token, expires_at, max_downloads, is_bundle
        ) VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([-$toolId, $orderId, $token, $expiresAt, $maxDownloads]);
    
    $tokenId = $db->lastInsertId();
    
    $bundleStmt = $db->prepare("
        INSERT INTO bundle_downloads (token_id, tool_id, order_id, zip_path, zip_name, file_count)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $bundleStmt->execute([
        $tokenId,
        $toolId,
        $orderId,
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
}

/**
 * Get download tokens for an order and specific tool
 * Phase 3.2: Returns array of tokens with file information for admin UI
 */
function getDownloadTokens($orderId, $toolId = null) {
    $db = getDb();
    
    $sql = "
        SELECT dt.*, tf.file_name, tf.file_size, tf.file_type
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
 * Get bundle download info by token
 * Phase 3.3: Retrieves bundle info for download processing
 */
function getBundleByToken($token) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT dt.*, bd.zip_path, bd.zip_name, bd.file_count, bd.tool_id
        FROM download_tokens dt
        JOIN bundle_downloads bd ON bd.token_id = dt.id
        WHERE dt.token = ? AND dt.is_bundle = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
