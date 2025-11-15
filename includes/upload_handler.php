<?php
/**
 * File Upload Handler
 * Handles secure image and video uploads with validation
 * 
 * Phase 4: File Upload Infrastructure
 * - Image uploads with validation
 * - Video uploads with validation
 * - File sanitization and security
 * - Size limit enforcement
 */

require_once __DIR__ . '/config.php';

class UploadHandler {
    
    /**
     * Upload an image file
     * 
     * @param array $file The $_FILES array element
     * @param string $category Category: 'templates' or 'tools'
     * @return array ['success' => bool, 'url' => string, 'path' => string, 'error' => string]
     */
    public static function uploadImage($file, $category = 'templates') {
        return self::uploadFile($file, 'image', $category);
    }
    
    /**
     * Upload a video file
     * 
     * @param array $file The $_FILES array element
     * @param string $category Category: 'templates' or 'tools'
     * @return array ['success' => bool, 'url' => string, 'path' => string, 'error' => string]
     */
    public static function uploadVideo($file, $category = 'templates') {
        return self::uploadFile($file, 'video', $category);
    }
    
    /**
     * Main upload handler
     * 
     * @param array $file The $_FILES array element
     * @param string $type 'image' or 'video'
     * @param string $category Category: 'templates' or 'tools'
     * @return array Result array
     */
    private static function uploadFile($file, $type, $category) {
        // Initialize response
        $response = [
            'success' => false,
            'url' => '',
            'path' => '',
            'error' => ''
        ];
        
        // Validate category
        if (!in_array($category, ['templates', 'tools'])) {
            $response['error'] = 'Invalid upload category';
            return $response;
        }
        
        // Step 1: Check if file was uploaded
        if (!isset($file) || !is_array($file)) {
            $response['error'] = 'No file uploaded';
            return $response;
        }
        
        // Step 2: Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['error'] = self::getUploadErrorMessage($file['error']);
            return $response;
        }
        
        // Step 3: Validate file exists and is uploaded file
        if (!is_uploaded_file($file['tmp_name'])) {
            $response['error'] = 'Invalid file upload';
            return $response;
        }
        
        // Step 4: Validate file type and size
        $validation = self::validateFile($file, $type);
        if (!$validation['valid']) {
            $response['error'] = $validation['error'];
            return $response;
        }
        
        // Step 5: Sanitize filename
        $sanitizedName = self::sanitizeFilename($file['name']);
        
        // Step 6: Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueFilename = self::generateUniqueFilename($sanitizedName, $extension, $type);
        
        // Step 7: Determine upload path
        $uploadSubDir = $category . '/' . $type . 's'; // templates/images or tools/videos
        $uploadDir = UPLOAD_DIR . '/' . $uploadSubDir;
        
        // Ensure directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . '/' . $uniqueFilename;
        $uploadUrl = UPLOAD_URL . '/' . $uploadSubDir . '/' . $uniqueFilename;
        
        // Step 8: Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $response['error'] = 'Failed to save uploaded file';
            return $response;
        }
        
        // Step 9: Set proper file permissions
        chmod($uploadPath, 0644);
        
        // Step 10: Verify file was saved correctly
        if (!file_exists($uploadPath)) {
            $response['error'] = 'File upload verification failed';
            return $response;
        }
        
        // Success!
        $response['success'] = true;
        $response['url'] = $uploadUrl;
        $response['path'] = $uploadPath;
        $response['filename'] = $uniqueFilename;
        $response['size'] = filesize($uploadPath);
        $response['type'] = $file['type'];
        
        return $response;
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file File array from $_FILES
     * @param string $type 'image' or 'video'
     * @return array ['valid' => bool, 'error' => string]
     */
    private static function validateFile($file, $type) {
        $result = ['valid' => false, 'error' => ''];
        
        // Get file info
        $fileSize = $file['size'];
        $fileTmpPath = $file['tmp_name'];
        $fileName = $file['name'];
        
        // Get file extension
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate based on type
        if ($type === 'image') {
            // Check file size
            if ($fileSize > MAX_IMAGE_SIZE) {
                $maxSizeMB = MAX_IMAGE_SIZE / (1024 * 1024);
                $result['error'] = "Image size exceeds maximum allowed size of {$maxSizeMB}MB";
                return $result;
            }
            
            // Check extension
            if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
                $result['error'] = 'Invalid image file type. Allowed: ' . implode(', ', ALLOWED_IMAGE_EXTENSIONS);
                return $result;
            }
            
            // Verify actual file type (MIME type)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);
            
            if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                $result['error'] = 'File is not a valid image (MIME type check failed)';
                return $result;
            }
            
            // Additional image validation - try to get image info
            if ($extension === 'svg') {
                // SVG files can contain XSS vulnerabilities - block them for security
                $result['error'] = 'SVG files are not allowed for security reasons. Please use PNG, JPG, or WebP instead.';
                return $result;
            } else {
                $imageInfo = @getimagesize($fileTmpPath);
                if ($imageInfo === false) {
                    $result['error'] = 'File is not a valid image (corrupt or invalid)';
                    return $result;
                }
            }
            
        } elseif ($type === 'video') {
            // Check file size
            if ($fileSize > MAX_VIDEO_SIZE) {
                $maxSizeMB = MAX_VIDEO_SIZE / (1024 * 1024);
                $result['error'] = "Video size exceeds maximum allowed size of {$maxSizeMB}MB";
                return $result;
            }
            
            // Check extension
            if (!in_array($extension, ALLOWED_VIDEO_EXTENSIONS)) {
                $result['error'] = 'Invalid video file type. Allowed: ' . implode(', ', ALLOWED_VIDEO_EXTENSIONS);
                return $result;
            }
            
            // Verify actual file type (MIME type)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);
            
            if (!in_array($mimeType, ALLOWED_VIDEO_TYPES)) {
                $result['error'] = 'File is not a valid video (MIME type check failed)';
                return $result;
            }
        }
        
        // Check for PHP code and malicious content (security)
        // Scan entire file for small files, or first/last chunks for large files
        $fileContent = '';
        $fullSize = $file['size'];
        
        if ($fullSize <= 1048576) { // 1MB or less - scan entire file
            $fileContent = file_get_contents($fileTmpPath);
        } else {
            // For large files, scan first 512KB and last 512KB
            $handle = fopen($fileTmpPath, 'rb');
            $fileContent = fread($handle, 524288); // First 512KB
            fseek($handle, -524288, SEEK_END);
            $fileContent .= fread($handle, 524288); // Last 512KB
            fclose($handle);
        }
        
        if (self::containsPhpCode($fileContent) || self::containsMaliciousContent($fileContent)) {
            $result['error'] = 'File contains forbidden or malicious content';
            return $result;
        }
        
        // Additional validation: Ensure extension matches MIME type category
        if (!self::extensionMatchesMime($extension, $file['type'], $type)) {
            $result['error'] = 'File extension does not match file content';
            return $result;
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * Sanitize filename to prevent security issues
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private static function sanitizeFilename($filename) {
        // Remove extension
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Remove special characters, keep only alphanumeric, dash, underscore
        $name = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $name);
        
        // Remove multiple consecutive dashes
        $name = preg_replace('/-+/', '-', $name);
        
        // Trim dashes from start/end
        $name = trim($name, '-');
        
        // Limit length
        $name = substr($name, 0, 50);
        
        // If empty after sanitization, use default
        if (empty($name)) {
            $name = 'upload';
        }
        
        return strtolower($name);
    }
    
    /**
     * Generate unique filename
     * 
     * @param string $sanitizedName Sanitized base name
     * @param string $extension File extension
     * @param string $type File type (image/video)
     * @return string Unique filename
     */
    private static function generateUniqueFilename($sanitizedName, $extension, $type) {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "{$type}_{$timestamp}_{$random}_{$sanitizedName}.{$extension}";
    }
    
    /**
     * Check if file contains PHP code
     * 
     * @param string $content File content
     * @return bool True if PHP code detected
     */
    private static function containsPhpCode($content) {
        // Check for PHP opening tags and variations
        $phpPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?\s/i',
            '/<\?$/i',
            '/<script[^>]*language\s*=\s*["\']?php["\']?/i',
            '/<%/i',  // ASP-style tags
        ];
        
        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if file contains malicious content (scripts, etc)
     * 
     * @param string $content File content
     * @return bool True if malicious content detected
     */
    private static function containsMaliciousContent($content) {
        // Check for potentially dangerous content
        $maliciousPatterns = [
            '/<script[\s>]/i',          // JavaScript
            '/javascript:/i',           // JavaScript protocol
            '/on\w+\s*=/i',            // Event handlers (onclick, onerror, etc)
            '/eval\s*\(/i',            // eval()
            '/base64_decode/i',        // base64_decode
            '/gzinflate/i',            // gzinflate
            '/system\s*\(/i',          // system()
            '/exec\s*\(/i',            // exec()
            '/shell_exec/i',           // shell_exec
            '/passthru/i',             // passthru
            '/__halt_compiler/i',      // __halt_compiler
            '/proc_open/i',            // proc_open
            '/popen/i',                // popen
            '/curl_exec/i',            // curl_exec
            '/curl_multi_exec/i',      // curl_multi_exec
            '/parse_ini_file/i',       // parse_ini_file
            '/show_source/i',          // show_source
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verify extension matches MIME type category
     * 
     * @param string $extension File extension
     * @param string $mimeType MIME type
     * @param string $expectedType Expected type ('image' or 'video')
     * @return bool True if match is valid
     */
    private static function extensionMatchesMime($extension, $mimeType, $expectedType) {
        // Define strict mapping of extensions to MIME types
        $validMappings = [
            // Images
            'jpg'  => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            
            // Videos
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
            'mov'  => ['video/quicktime'],
            'avi'  => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        ];
        
        // Check if extension is in our mapping
        if (!isset($validMappings[$extension])) {
            return false;
        }
        
        // Check if MIME type matches extension
        if (!in_array($mimeType, $validMappings[$extension])) {
            return false;
        }
        
        // Verify type category matches
        if ($expectedType === 'image' && strpos($mimeType, 'image/') !== 0) {
            return false;
        }
        
        if ($expectedType === 'video' && strpos($mimeType, 'video/') !== 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user-friendly upload error message
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds server upload limit';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds form upload limit';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary upload directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by PHP extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Delete uploaded file
     * 
     * @param string $filePath Full path to file
     * @return bool Success
     */
    public static function deleteFile($filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            // Security check - file must be in uploads directory
            $realPath = realpath($filePath);
            $uploadDir = realpath(UPLOAD_DIR);
            
            if ($realPath && $uploadDir && strpos($realPath, $uploadDir) === 0) {
                return unlink($filePath);
            }
        }
        return false;
    }
    
    /**
     * Get file size in human-readable format
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    public static function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
