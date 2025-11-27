<?php
/**
 * Chunked File Upload Handler
 * Allows uploading large files in 5MB chunks for faster, resumable uploads
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tool_files.php';

startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $toolId = (int)($_POST['tool_id'] ?? 0);
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 0);
    $fileType = $_POST['file_type'] ?? 'attachment';
    $description = $_POST['description'] ?? '';
    $fileName = $_POST['file_name'] ?? 'upload';
    $fileHash = $_POST['file_hash'] ?? '';
    
    if (!$toolId || !isset($_FILES['chunk'])) {
        throw new Exception('Invalid parameters');
    }
    
    if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Chunk upload error: ' . $_FILES['chunk']['error']);
    }
    
    // Create temp directory for chunks
    $tempDir = __DIR__ . '/../uploads/tools/chunks/' . $fileHash;
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Store chunk
    $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk');
    }
    
    // Check if all chunks received
    $uploadedChunks = 0;
    for ($i = 0; $i < $totalChunks; $i++) {
        if (file_exists($tempDir . '/chunk_' . $i)) {
            $uploadedChunks++;
        }
    }
    
    $response = [
        'success' => true,
        'chunk_index' => $chunkIndex,
        'uploaded_chunks' => $uploadedChunks,
        'total_chunks' => $totalChunks,
        'completed' => ($uploadedChunks === $totalChunks)
    ];
    
    // If all chunks uploaded, combine them
    if ($uploadedChunks === $totalChunks) {
        $uploadDir = __DIR__ . '/../uploads/tools/files/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalFileName = 'tool_' . $toolId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $finalPath = $uploadDir . $finalFileName;
        
        // Combine chunks
        $handle = fopen($finalPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . '/chunk_' . $i;
            $chunkHandle = fopen($chunkPath, 'rb');
            while (!feof($chunkHandle)) {
                fwrite($handle, fread($chunkHandle, 8192));
            }
            fclose($chunkHandle);
            unlink($chunkPath);
        }
        fclose($handle);
        rmdir($tempDir);
        
        // Store in database
        $db = getDb();
        $fileSize = filesize($finalPath);
        $filePath = 'uploads/tools/files/' . $finalFileName;
        
        $stmt = $db->prepare("
            INSERT INTO tool_files (
                tool_id, file_name, file_path, file_type, file_description,
                file_size, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $mimeType = mime_content_type($finalPath) ?: 'application/octet-stream';
        $stmt->execute([
            $toolId,
            $fileName,
            $filePath,
            $fileType,
            $description,
            $fileSize,
            $mimeType
        ]);
        
        $response['file_id'] = $db->lastInsertId();
        $response['file_size'] = $fileSize;
        $response['message'] = 'File uploaded successfully!';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
