<?php
/**
 * Upload API Endpoint
 * Handles AJAX file upload requests from admin panel
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload_handler.php';

// Set JSON response header
header('Content-Type: application/json');

// Start session
startSecureSession();

// Check if user is logged in as admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Get upload parameters
    $uploadType = $_POST['upload_type'] ?? ''; // 'image' or 'video'
    $category = $_POST['category'] ?? 'templates'; // 'templates' or 'tools'
    
    // Validate parameters
    if (!in_array($uploadType, ['image', 'video'])) {
        throw new Exception('Invalid upload type');
    }
    
    if (!in_array($category, ['templates', 'tools'])) {
        throw new Exception('Invalid category');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    
    // Process upload based on type
    if ($uploadType === 'image') {
        $result = UploadHandler::uploadImage($file, $category);
    } else {
        $result = UploadHandler::uploadVideo($file, $category);
    }
    
    // Check result
    if (!$result['success']) {
        throw new Exception($result['error']);
    }
    
    // Log activity
    $fileName = $result['filename'];
    $fileSize = UploadHandler::formatFileSize($result['size']);
    logActivity(
        'file_uploaded',
        "Uploaded {$uploadType} for {$category}: {$fileName} ({$fileSize})",
        getAdminId()
    );
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'url' => $result['url'],
        'path' => $result['path'],
        'filename' => $result['filename'],
        'size' => $result['size'],
        'size_formatted' => UploadHandler::formatFileSize($result['size']),
        'type' => $result['type']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
