<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();
header('Content-Type: application/json');

// Log all requests for debugging
$logfile = __DIR__ . '/../uploads/upload.log';
file_put_contents($logfile, date('Y-m-d H:i:s') . " - POST " . json_encode($_POST) . " FILES: " . (isset($_FILES['chunk']) ? 'YES' : 'NO') . "\n", FILE_APPEND);

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
        throw new Exception('Missing required fields');
    }
    
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        $error = isset($_FILES['chunk']) ? $_FILES['chunk']['error'] : 'No file';
        throw new Exception('Upload error: ' . $error);
    }
    
    $tempDir = __DIR__ . '/../uploads/temp/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    $chunkPath = $tempDir . '/chunk_' . (int)$chunkIndex;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk to: ' . $chunkPath);
    }
    
    $uploadedChunks = count(glob($tempDir . '/chunk_*'));
    
    if ($uploadedChunks === $totalChunks) {
        $filesDir = __DIR__ . '/../uploads/tools/files/';
        @mkdir($filesDir, 0755, true);
        
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalFile = $filesDir . 'tool_' . $toolId . '_' . time() . '.' . $ext;
        
        $out = fopen($finalFile, 'wb');
        if (!$out) {
            throw new Exception('Cannot create output file');
        }
        
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = $tempDir . '/chunk_' . $i;
            if (file_exists($chunk)) {
                $in = fopen($chunk, 'rb');
                if ($in) {
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    @unlink($chunk);
                }
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
    file_put_contents($logfile, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
