<?php
/**
 * Production-Grade Chunked Upload Handler
 * Handles large files (200MB+) with concurrent queue management and retry logic
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $uploadId = $_POST['upload_id'] ?? null;
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 0);
    $toolId = (int)($_POST['tool_id'] ?? 0);
    $fileName = $_POST['file_name'] ?? null;
    
    if (!$uploadId || !$toolId || !$fileName || !isset($_FILES['chunk'])) {
        throw new Exception('Invalid upload parameters');
    }
    
    if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $_FILES['chunk']['error']);
    }
    
    // Create upload temp directory
    $uploadsDir = __DIR__ . '/../uploads/temp/' . $uploadId;
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Store chunk with atomic write (write to temp then move)
    $chunkFile = $uploadsDir . '/.chunk_' . $chunkIndex;
    $finalChunkFile = $uploadsDir . '/chunk_' . $chunkIndex;
    
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
        throw new Exception('Failed to save chunk');
    }
    
    // Atomic rename
    if (!rename($chunkFile, $finalChunkFile)) {
        unlink($chunkFile);
        throw new Exception('Failed to finalize chunk');
    }
    
    // Count uploaded chunks
    $chunks = glob($uploadsDir . '/chunk_*');
    $uploadedCount = count($chunks);
    
    $response = [
        'success' => true,
        'upload_id' => $uploadId,
        'chunk_index' => $chunkIndex,
        'uploaded_chunks' => $uploadedCount,
        'total_chunks' => $totalChunks
    ];
    
    // If all chunks received, combine them
    if ($uploadedCount === $totalChunks) {
        $response['combining'] = true;
        combineChunks($uploadsDir, $uploadId, $toolId, $fileName);
        $response['completed'] = true;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function combineChunks($uploadsDir, $uploadId, $toolId, $fileName) {
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $finalFileName = 'tool_' . $toolId . '_' . time() . '_' . substr(md5($uploadId), 0, 8) . '.' . $fileExtension;
    $finalPath = __DIR__ . '/../uploads/tools/files/' . $finalFileName;
    
    // Stream-combine chunks to avoid memory issues
    $outputHandle = fopen($finalPath, 'wb');
    if (!$outputHandle) {
        throw new Exception('Cannot create final file');
    }
    
    for ($i = 0; $i < 10000; $i++) { // Max 10000 chunks (200GB+ at 20MB each)
        $chunkPath = $uploadsDir . '/chunk_' . $i;
        if (!file_exists($chunkPath)) {
            break;
        }
        
        $chunkHandle = fopen($chunkPath, 'rb');
        if ($chunkHandle) {
            while (!feof($chunkHandle)) {
                $data = fread($chunkHandle, 1048576); // 1MB buffer
                if ($data === false) break;
                fwrite($outputHandle, $data);
            }
            fclose($chunkHandle);
            unlink($chunkPath);
        }
    }
    fclose($outputHandle);
    
    // Cleanup upload directory
    rmdir($uploadsDir);
    
    // Store in database
    $db = getDb();
    $fileSize = filesize($finalPath);
    $filePath = 'uploads/tools/files/' . $finalFileName;
    $mimeType = mime_content_type($finalPath) ?: 'application/octet-stream';
    
    $stmt = $db->prepare("
        INSERT INTO tool_files (
            tool_id, file_name, file_path, file_type, file_description,
            file_size, mime_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $toolId,
        $fileName,
        $filePath,
        'attachment',
        '',
        $fileSize,
        $mimeType
    ]);
}
