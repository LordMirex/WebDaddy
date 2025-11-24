<?php
/**
 * Tool Files Management
 * Upload, download, and manage tool files
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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
 */
function generateDownloadLink($fileId, $orderId, $expiryDays = 7) {
    $db = getDb();
    
    // Get file info
    $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) return null;
    
    // Create secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    
    // Store download token
    $stmt = $db->prepare("
        INSERT INTO download_tokens (file_id, pending_order_id, token, expires_at, max_downloads)
        VALUES (?, ?, ?, ?, 5)
    ");
    $stmt->execute([$fileId, $orderId, $token, $expiresAt]);
    
    // Generate URL
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    $downloadUrl = $siteUrl . "/download.php?token={$token}";
    
    return [
        'name' => $file['file_name'],
        'url' => $downloadUrl,
        'expires_at' => $expiresAt,
        'file_type' => $file['file_type']
    ];
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
