<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/upload_handler.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    if (!isset($_FILES['image'])) {
        throw new Exception('No image file provided');
    }
    
    $uploadDir = __DIR__ . '/../../../uploads/blog/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['image'];
    $fileName = basename($file['name']);
    $fileType = mime_content_type($file['tmp_name']);
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPEG, PNG, GIF, WebP');
    }
    
    // Generate unique filename
    $timestamp = time();
    $randomStr = substr(md5(rand()), 0, 8);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = "image_{$timestamp}_{$randomStr}.{$ext}";
    $uploadPath = $uploadDir . $newFileName;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload file');
    }
    
    // Return relative URL
    $imageUrl = '/uploads/blog/' . $newFileName;
    
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'filename' => $newFileName,
        'message' => 'Image uploaded successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
