<?php
/**
 * Upload API Endpoint
 * Handles AJAX file upload requests from admin panel
 */

// Start output buffering to catch any stray output
ob_start();

// Suppress all error display - we'll handle them ourselves
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON response header first
header('Content-Type: application/json');

// Clean any buffered output before our includes
ob_clean();

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/session.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../admin/includes/auth.php';
    require_once __DIR__ . '/../includes/upload_handler.php';
    require_once __DIR__ . '/../includes/thumbnail_generator.php';
    require_once __DIR__ . '/../includes/utilities.php';
} catch (Throwable $e) {
    // Clear any buffered output
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server initialization error: ' . $e->getMessage()
    ]);
    exit;
}

// Clear any output from includes
ob_clean();

// Start session
startSecureSession();

// Log upload attempt
error_log('Upload API: Request received - Type: ' . ($_POST['upload_type'] ?? 'unknown') . ', Category: ' . ($_POST['category'] ?? 'unknown'));
error_log('Upload API: Session ID: ' . session_id() . ', Admin status: ' . (isAdmin() ? 'yes' : 'no'));

// Check if user is logged in as admin
if (!isAdmin()) {
    error_log('Upload API: Authentication failed - not logged in as admin');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Admin login required.'
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
    
    // Generate thumbnails for images and process videos
    $thumbnails = [];
    $videoData = [];
    
    if ($uploadType === 'image') {
        $uploadDir = dirname($result['path']);
        $thumbnailResult = ThumbnailGenerator::generateThumbnails(
            $result['path'],
            $uploadDir,
            $result['filename']
        );
        
        if ($thumbnailResult['success']) {
            $thumbnails = $thumbnailResult['thumbnails'];
        }
    } elseif ($uploadType === 'video') {
        // Skip heavy video processing - just use the uploaded video as-is
        // This prevents timeouts and provides instant upload response
        $videoData = [
            'thumbnail_url' => '',
            'video_versions' => [],
            'metadata' => [],
            'note' => 'Video uploaded successfully - using original quality'
        ];
        
        error_log('Upload API: Video uploaded successfully without processing: ' . $result['filename']);
    }
    
    // Log activity
    $fileName = $result['filename'];
    $fileSize = Utilities::formatBytes($result['size']);
    logActivity(
        'file_uploaded',
        "Uploaded {$uploadType} for {$category}: {$fileName} ({$fileSize})",
        getAdminId()
    );
    
    // Convert relative URL to absolute for API response (backward compatibility)
    $absoluteUrl = $result['url'];
    if (strpos($absoluteUrl, '/') === 0 && strpos($absoluteUrl, '//') !== 0) {
        $absoluteUrl = SITE_URL . $absoluteUrl;
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'url' => $absoluteUrl,
        'path' => $result['path'],
        'filename' => $result['filename'],
        'size' => $result['size'],
        'size_formatted' => Utilities::formatBytes($result['size']),
        'type' => $result['type'],
        'thumbnails' => $thumbnails,
        'video_data' => $videoData
    ]);
    
} catch (Exception $e) {
    // Log detailed error for debugging
    error_log('Upload API Error: ' . $e->getMessage());
    error_log('Upload API Stack Trace: ' . $e->getTraceAsString());
    
    // Clear any buffered output
    ob_end_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Throwable $e) {
    // Catch any other errors (including fatal errors in PHP 7+)
    error_log('Upload API Fatal Error: ' . $e->getMessage());
    
    // Clear any buffered output
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

// Clean up output buffer at end
if (ob_get_level() > 0) {
    ob_end_flush();
}
