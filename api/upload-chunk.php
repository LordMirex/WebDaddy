<?php
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
    
    if (!$uploadId || !$toolId || !$fileName) {
        throw new Exception('Invalid parameters');
    }
    
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error');
    }
    
    $tempDir = __DIR__ . '/../uploads/temp/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    $chunkPath = $tempDir . '/chunk_' . (int)$chunkIndex;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk');
    }
    
    $uploadedChunks = count(glob($tempDir . '/chunk_*'));
    
    if ($uploadedChunks === $totalChunks) {
        $filesDir = __DIR__ . '/../uploads/tools/files/';
        @mkdir($filesDir, 0755, true);
        
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalFile = $filesDir . 'tool_' . $toolId . '_' . time() . '.' . $ext;
        
        $out = fopen($finalFile, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = $tempDir . '/chunk_' . $i;
            if (file_exists($chunk)) {
                $in = fopen($chunk, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                @unlink($chunk);
            }
        }
        fclose($out);
        @rmdir($tempDir);
        
        $db = getDb();
        $db->prepare("INSERT INTO tool_files (tool_id, file_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?)")
            ->execute([
                $toolId,
                $fileName,
                str_replace(__DIR__ . '/../', '', $finalFile),
                filesize($finalFile),
                'application/octet-stream'
            ]);
        
        echo json_encode(['success' => true, 'completed' => true]);
    } else {
        echo json_encode(['success' => true, 'uploaded' => $uploadedChunks, 'total' => $totalChunks]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
