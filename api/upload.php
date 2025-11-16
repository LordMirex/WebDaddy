<?php
/**
 * Upload API Endpoint
 * Handles AJAX file upload requests from admin panel
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../includes/upload_handler.php';
require_once __DIR__ . '/../includes/thumbnail_generator.php';
require_once __DIR__ . '/../includes/video_processor.php';
require_once __DIR__ . '/../includes/utilities.php';

// Set JSON response header
header('Content-Type: application/json');

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
        // Try to process video, but don't fail if FFmpeg is unavailable
        $videoProcessResult = VideoProcessor::processVideo($result['path'], $category);
        
        if ($videoProcessResult['success']) {
            $videoData = [
                'thumbnail_url' => $videoProcessResult['thumbnail_url'] ?? '',
                'video_versions' => $videoProcessResult['video_versions'] ?? [],
                'metadata' => $videoProcessResult['metadata'] ?? []
            ];
        } else {
            // Video processing failed (likely FFmpeg not available), but upload succeeded
            error_log('Upload API: Video uploaded but processing failed: ' . ($videoProcessResult['error'] ?? 'unknown'));
            $videoData = [
                'thumbnail_url' => '',
                'video_versions' => [],
                'metadata' => [],
                'processing_warning' => 'Video uploaded successfully but advanced processing unavailable'
            ];
        }
    }
    
    // Log activity
    $fileName = $result['filename'];
    $fileSize = Utilities::formatBytes($result['size']);
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
        'size_formatted' => Utilities::formatBytes($result['size']),
        'type' => $result['type'],
        'thumbnails' => $thumbnails,
        'video_data' => $videoData
    ]);
    
} catch (Exception $e) {
    // Log detailed error for debugging
    error_log('Upload API Error: ' . $e->getMessage());
    error_log('Upload API Stack Trace: ' . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
