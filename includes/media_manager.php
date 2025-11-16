<?php
/**
 * Media Manager Class
 * Provides unified media file operations across the platform
 * 
 * Phase 10: Code Organization & Architecture
 * - Centralized media management
 * - File listing and organization
 * - Cleanup and optimization
 * - Media statistics
 * 
 * @package WebDaddyEmpire
 * @since Phase 10
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utilities.php';

class MediaManager {
    
    private static $uploadDir = __DIR__ . '/../uploads/';
    private static $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private static $allowedVideoExtensions = ['mp4', 'webm', 'mov', 'avi'];
    
    /**
     * Get all media files in a specific category
     * 
     * @param string $category Category: 'templates' or 'tools'
     * @param string $type Type: 'images', 'videos', or 'all'
     * @param bool $includeMetadata Include file size, modified date, etc.
     * @return array List of media files with metadata
     */
    public static function listMedia($category = 'all', $type = 'all', $includeMetadata = true) {
        $files = [];
        $basePath = self::$uploadDir;
        
        $categories = ($category === 'all') ? ['templates', 'tools'] : [$category];
        
        foreach ($categories as $cat) {
            $categoryPath = $basePath . $cat . '/';
            
            if (!is_dir($categoryPath)) {
                continue;
            }
            
            if ($type === 'images' || $type === 'all') {
                $imagePath = $categoryPath . 'images/';
                if (is_dir($imagePath)) {
                    $files = array_merge($files, self::scanDirectory($imagePath, 'image', $cat, $includeMetadata));
                }
            }
            
            if ($type === 'videos' || $type === 'all') {
                $videoPath = $categoryPath . 'videos/';
                if (is_dir($videoPath)) {
                    $files = array_merge($files, self::scanDirectory($videoPath, 'video', $cat, $includeMetadata));
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Scan directory for media files
     * 
     * @param string $directory Directory path to scan
     * @param string $type Media type: 'image' or 'video'
     * @param string $category Category name
     * @param bool $includeMetadata Include file metadata
     * @return array List of files with metadata
     */
    private static function scanDirectory($directory, $type, $category, $includeMetadata) {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $items = scandir($directory);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $filePath = $directory . $item;
            
            if (!is_file($filePath)) {
                continue;
            }
            
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            $validExtensions = ($type === 'image') 
                ? self::$allowedImageExtensions 
                : self::$allowedVideoExtensions;
            
            if (!in_array($extension, $validExtensions)) {
                continue;
            }
            
            $fileInfo = [
                'filename' => $item,
                'path' => $filePath,
                'relative_path' => str_replace(self::$uploadDir, '', $filePath),
                'url' => '/uploads/' . str_replace(self::$uploadDir, '', $filePath),
                'type' => $type,
                'category' => $category,
                'extension' => $extension
            ];
            
            if ($includeMetadata) {
                $fileInfo['size'] = filesize($filePath);
                $fileInfo['size_formatted'] = Utilities::formatBytes(filesize($filePath));
                $fileInfo['modified'] = filemtime($filePath);
                $fileInfo['modified_formatted'] = date('Y-m-d H:i:s', filemtime($filePath));
                
                if ($type === 'image') {
                    $imageInfo = getimagesize($filePath);
                    if ($imageInfo) {
                        $fileInfo['width'] = $imageInfo[0];
                        $fileInfo['height'] = $imageInfo[1];
                        $fileInfo['dimensions'] = $imageInfo[0] . 'x' . $imageInfo[1];
                    }
                }
            }
            
            $files[] = $fileInfo;
        }
        
        usort($files, function($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });
        
        return $files;
    }
    
    /**
     * Get media file information
     * 
     * @param string $filePath Full or relative file path
     * @return array|false File information or false if not found
     */
    public static function getFileInfo($filePath) {
        $fullPath = self::resolveFilePath($filePath);
        
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return false;
        }
        
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $type = in_array($extension, self::$allowedImageExtensions) ? 'image' : 'video';
        
        $category = '';
        if (strpos($fullPath, '/templates/') !== false) {
            $category = 'templates';
        } elseif (strpos($fullPath, '/tools/') !== false) {
            $category = 'tools';
        }
        
        $info = [
            'filename' => basename($fullPath),
            'path' => $fullPath,
            'relative_path' => str_replace(self::$uploadDir, '', $fullPath),
            'url' => '/uploads/' . str_replace(self::$uploadDir, '', $fullPath),
            'type' => $type,
            'category' => $category,
            'extension' => $extension,
            'size' => filesize($fullPath),
            'size_formatted' => Utilities::formatBytes(filesize($fullPath)),
            'modified' => filemtime($fullPath),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'exists' => true
        ];
        
        if ($type === 'image') {
            $imageInfo = getimagesize($fullPath);
            if ($imageInfo) {
                $info['width'] = $imageInfo[0];
                $info['height'] = $imageInfo[1];
                $info['dimensions'] = $imageInfo[0] . 'x' . $imageInfo[1];
                $info['mime_type'] = $imageInfo['mime'];
            }
        }
        
        return $info;
    }
    
    /**
     * Delete a media file
     * 
     * @param string $filePath Full or relative file path
     * @param bool $deleteThumbnails Also delete associated thumbnails
     * @return array Result with success status and message
     */
    public static function deleteFile($filePath, $deleteThumbnails = true) {
        $fullPath = self::resolveFilePath($filePath);
        
        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => 'File not found'
            ];
        }
        
        if (!is_file($fullPath)) {
            return [
                'success' => false,
                'error' => 'Path is not a file'
            ];
        }
        
        if (!self::isInUploadDirectory($fullPath)) {
            return [
                'success' => false,
                'error' => 'File is outside upload directory'
            ];
        }
        
        $deleted = [];
        
        if ($deleteThumbnails) {
            $thumbnails = self::findRelatedThumbnails($fullPath);
            foreach ($thumbnails as $thumb) {
                if (@unlink($thumb)) {
                    $deleted[] = basename($thumb);
                }
            }
        }
        
        if (@unlink($fullPath)) {
            return [
                'success' => true,
                'deleted_file' => basename($fullPath),
                'deleted_thumbnails' => $deleted,
                'total_deleted' => count($deleted) + 1
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to delete file'
        ];
    }
    
    /**
     * Find thumbnails related to a media file
     * 
     * @param string $filePath Full path to media file
     * @return array List of thumbnail file paths
     */
    private static function findRelatedThumbnails($filePath) {
        $thumbnails = [];
        $directory = dirname($filePath);
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $patterns = [
            $directory . '/' . $filename . '_thumb.*',
            $directory . '/' . $filename . '_small.*',
            $directory . '/' . $filename . '_medium.*',
            $directory . '/' . $filename . '_large.*',
            $directory . '/thumbnails/' . $filename . '.*'
        ];
        
        foreach ($patterns as $pattern) {
            $matches = glob($pattern);
            if ($matches) {
                $thumbnails = array_merge($thumbnails, $matches);
            }
        }
        
        return array_unique($thumbnails);
    }
    
    /**
     * Get orphaned files (files not referenced in database)
     * 
     * @param string $category Category: 'templates', 'tools', or 'all'
     * @return array List of orphaned files
     */
    public static function getOrphanedFiles($category = 'all') {
        $db = getDb();
        $allFiles = self::listMedia($category, 'all', true);
        $orphaned = [];
        
        $referencedUrls = [];
        
        if ($category === 'templates' || $category === 'all') {
            $stmt = $db->query("SELECT thumbnail_url, demo_url FROM templates");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['thumbnail_url'])) {
                    $referencedUrls[] = $row['thumbnail_url'];
                }
                if (!empty($row['demo_url'])) {
                    $referencedUrls[] = $row['demo_url'];
                }
            }
        }
        
        if ($category === 'tools' || $category === 'all') {
            $stmt = $db->query("SELECT thumbnail_url FROM tools");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['thumbnail_url'])) {
                    $referencedUrls[] = $row['thumbnail_url'];
                }
            }
        }
        
        foreach ($allFiles as $file) {
            $isReferenced = false;
            foreach ($referencedUrls as $url) {
                if (strpos($url, $file['filename']) !== false) {
                    $isReferenced = true;
                    break;
                }
            }
            
            if (!$isReferenced) {
                $orphaned[] = $file;
            }
        }
        
        return $orphaned;
    }
    
    /**
     * Get media statistics
     * 
     * @return array Statistics about media files
     */
    public static function getStatistics() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'images' => [
                'count' => 0,
                'size' => 0
            ],
            'videos' => [
                'count' => 0,
                'size' => 0
            ],
            'by_category' => [
                'templates' => [
                    'count' => 0,
                    'size' => 0
                ],
                'tools' => [
                    'count' => 0,
                    'size' => 0
                ]
            ]
        ];
        
        $allFiles = self::listMedia('all', 'all', true);
        
        foreach ($allFiles as $file) {
            $stats['total_files']++;
            $stats['total_size'] += $file['size'];
            
            if ($file['type'] === 'image') {
                $stats['images']['count']++;
                $stats['images']['size'] += $file['size'];
            } elseif ($file['type'] === 'video') {
                $stats['videos']['count']++;
                $stats['videos']['size'] += $file['size'];
            }
            
            if (isset($stats['by_category'][$file['category']])) {
                $stats['by_category'][$file['category']]['count']++;
                $stats['by_category'][$file['category']]['size'] += $file['size'];
            }
        }
        
        $stats['total_size_formatted'] = Utilities::formatBytes($stats['total_size']);
        $stats['images']['size_formatted'] = Utilities::formatBytes($stats['images']['size']);
        $stats['videos']['size_formatted'] = Utilities::formatBytes($stats['videos']['size']);
        $stats['by_category']['templates']['size_formatted'] = Utilities::formatBytes($stats['by_category']['templates']['size']);
        $stats['by_category']['tools']['size_formatted'] = Utilities::formatBytes($stats['by_category']['tools']['size']);
        
        return $stats;
    }
    
    /**
     * Clean up old or unused media files
     * 
     * @param int $daysOld Only delete files older than this many days
     * @param bool $dryRun If true, only return what would be deleted without actually deleting
     * @return array Result with deleted files list
     */
    public static function cleanup($daysOld = 30, $dryRun = true) {
        $orphaned = self::getOrphanedFiles('all');
        $toDelete = [];
        $deleted = [];
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        
        foreach ($orphaned as $file) {
            if ($file['modified'] < $cutoffTime) {
                $toDelete[] = $file;
            }
        }
        
        if (!$dryRun) {
            foreach ($toDelete as $file) {
                $result = self::deleteFile($file['path'], true);
                if ($result['success']) {
                    $deleted[] = $file['filename'];
                }
            }
        }
        
        return [
            'dry_run' => $dryRun,
            'found_orphaned' => count($orphaned),
            'eligible_for_deletion' => count($toDelete),
            'deleted' => $deleted,
            'total_deleted' => count($deleted),
            'files' => $dryRun ? $toDelete : $deleted
        ];
    }
    
    /**
     * Resolve file path (handle relative and absolute paths)
     * 
     * @param string $filePath File path
     * @return string Full file path
     */
    private static function resolveFilePath($filePath) {
        if (strpos($filePath, self::$uploadDir) === 0) {
            return $filePath;
        }
        
        if (strpos($filePath, '/uploads/') === 0) {
            return self::$uploadDir . substr($filePath, 9);
        }
        
        if (strpos($filePath, 'uploads/') === 0) {
            return self::$uploadDir . substr($filePath, 8);
        }
        
        return self::$uploadDir . ltrim($filePath, '/');
    }
    
    /**
     * Check if path is within upload directory
     * 
     * @param string $filePath Full file path
     * @return bool True if path is within upload directory
     */
    private static function isInUploadDirectory($filePath) {
        $realPath = realpath($filePath);
        $realUploadDir = realpath(self::$uploadDir);
        
        if ($realPath === false || $realUploadDir === false) {
            return false;
        }
        
        return strpos($realPath, $realUploadDir) === 0;
    }
}
