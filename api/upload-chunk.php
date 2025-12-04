<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();
header('Content-Type: application/json');

set_time_limit(300);
ini_set('memory_limit', '256M');

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
    
    if (!$uploadId || !$toolId || !$fileName || $totalChunks < 1) {
        throw new Exception('Missing required fields: upload_id, tool_id, file_name, total_chunks');
    }
    
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
        ];
        $errorCode = isset($_FILES['chunk']) ? $_FILES['chunk']['error'] : UPLOAD_ERR_NO_FILE;
        throw new Exception($errorMessages[$errorCode] ?? 'Unknown upload error: ' . $errorCode);
    }
    
    $safeUploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
    $tempDir = __DIR__ . '/../uploads/temp/' . $safeUploadId;
    
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            throw new Exception('Cannot create temp directory');
        }
    }
    
    $chunkPath = $tempDir . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
    
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk ' . $chunkIndex);
    }
    
    $manifestFile = $tempDir . '/manifest.json';
    $manifest = file_exists($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : [];
    $manifest['chunks'][$chunkIndex] = true;
    $manifest['total'] = $totalChunks;
    $manifest['fileName'] = $fileName;
    $manifest['toolId'] = $toolId;
    $manifest['lastUpdate'] = time();
    file_put_contents($manifestFile, json_encode($manifest));
    
    $uploadedChunks = count(glob($tempDir . '/chunk_*'));
    
    if ($uploadedChunks === $totalChunks) {
        $filesDir = __DIR__ . '/../uploads/tools/files/';
        if (!is_dir($filesDir)) {
            @mkdir($filesDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $safeExt = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? $ext : 'bin';
        $uniqueName = 'tool_' . $toolId . '_' . time() . '_' . substr(md5($uploadId), 0, 8) . '.' . $safeExt;
        $finalFile = $filesDir . $uniqueName;
        
        $out = fopen($finalFile, 'wb');
        if (!$out) {
            throw new Exception('Cannot create output file');
        }
        
        $errors = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $tempDir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            if (!file_exists($chunkFile)) {
                $errors[] = "Missing chunk $i";
                continue;
            }
            
            $in = fopen($chunkFile, 'rb');
            if ($in) {
                stream_copy_to_stream($in, $out);
                fclose($in);
                @unlink($chunkFile);
            } else {
                $errors[] = "Cannot read chunk $i";
            }
        }
        fclose($out);
        
        @unlink($manifestFile);
        @rmdir($tempDir);
        
        if (!empty($errors)) {
            @unlink($finalFile);
            throw new Exception('Assembly errors: ' . implode(', ', $errors));
        }
        
        $fileSize = filesize($finalFile);
        if ($fileSize === 0) {
            @unlink($finalFile);
            throw new Exception('Final file is empty');
        }
        
        $mimeType = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($finalFile);
            if ($detected) {
                $mimeType = $detected;
            }
        }
        
        $db = getDb();
        $relPath = 'uploads/tools/files/' . $uniqueName;
        
        $stmt = $db->prepare("
            INSERT INTO tool_files 
            (tool_id, file_name, file_path, file_type, file_description, file_size, mime_type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $stmt->execute([
            $toolId,
            $fileName,
            $relPath,
            'attachment',
            '',
            $fileSize,
            $mimeType
        ]);
        
        $db->exec("UPDATE tools SET total_files = (SELECT COUNT(*) FROM tool_files WHERE tool_id = $toolId), updated_at = datetime('now', '+1 hour') WHERE id = $toolId");
        
        error_log("Chunked upload complete: $fileName ($fileSize bytes) for tool $toolId");
        
        echo json_encode([
            'success' => true, 
            'completed' => true,
            'fileName' => $fileName,
            'fileSize' => $fileSize,
            'filePath' => $relPath
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'uploaded' => $uploadedChunks, 
            'total' => $totalChunks,
            'progress' => round(($uploadedChunks / $totalChunks) * 100)
        ]);
    }
} catch (Exception $e) {
    error_log('Chunk upload error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
