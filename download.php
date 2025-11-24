<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tool_files.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Invalid download link');
}

// Verify token
$db = getDb();
$stmt = $db->prepare("
    SELECT dt.*, tf.file_path, tf.file_name, tf.mime_type
    FROM download_tokens dt
    INNER JOIN tool_files tf ON dt.file_id = tf.id
    WHERE dt.token = ? AND dt.expires_at > datetime('now')
");
$stmt->execute([$token]);
$download = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$download) {
    http_response_code(404);
    die('Download link expired or invalid. Please contact support.');
}

// Check download limit
if ($download['download_count'] >= $download['max_downloads']) {
    http_response_code(403);
    die('Download limit exceeded. Please contact support for a new link.');
}

// Increment download count
$stmt = $db->prepare("
    UPDATE download_tokens 
    SET download_count = download_count + 1,
        last_downloaded_at = CURRENT_TIMESTAMP
    WHERE id = ?
");
$stmt->execute([$download['id']]);

// Update file download count
$stmt = $db->prepare("UPDATE tool_files SET download_count = download_count + 1 WHERE id = ?");
$stmt->execute([$download['file_id']]);

// Serve file
$filePath = __DIR__ . '/' . $download['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found. Please contact support.');
}

header('Content-Type: ' . $download['mime_type']);
header('Content-Disposition: attachment; filename="' . $download['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filePath);
exit;
