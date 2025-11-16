<?php
/**
 * File Cleanup and Garbage Collection
 * 
 * Phase 4.7: Create file cleanup/garbage collection
 * 
 * Features:
 * - Remove temporary files older than 24 hours
 * - Remove orphaned files not referenced in database
 * - Log cleanup activities
 * 
 * Can be run via:
 * - Cron job: php includes/cleanup.php
 * - Admin panel manually
 * - Automatic trigger on specific actions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/utilities.php';

class FileCleanup {
    
    private $db;
    private $deletedCount = 0;
    private $freedSpace = 0;
    private $errors = [];
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Run complete cleanup process
     * 
     * @return array Cleanup statistics
     */
    public function runCleanup() {
        $this->deletedCount = 0;
        $this->freedSpace = 0;
        $this->errors = [];
        
        // Step 1: Clean temporary files
        $this->cleanTempFiles();
        
        // Step 2: Clean orphaned files
        $this->cleanOrphanedFiles();
        
        return [
            'success' => true,
            'deleted_count' => $this->deletedCount,
            'freed_space' => $this->freedSpace,
            'freed_space_formatted' => Utilities::formatBytes($this->freedSpace),
            'errors' => $this->errors
        ];
    }
    
    /**
     * Clean temporary files older than 24 hours
     */
    private function cleanTempFiles() {
        $tempDir = UPLOAD_DIR . '/temp';
        
        if (!is_dir($tempDir)) {
            return;
        }
        
        $cutoffTime = time() - TEMP_FILE_LIFETIME;
        
        $files = glob($tempDir . '/*');
        if ($files === false) {
            return;
        }
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            // Check file age
            $fileTime = filemtime($file);
            if ($fileTime < $cutoffTime) {
                $fileSize = filesize($file);
                
                if (unlink($file)) {
                    $this->deletedCount++;
                    $this->freedSpace += $fileSize;
                } else {
                    $this->errors[] = "Failed to delete temp file: " . basename($file);
                }
            }
        }
    }
    
    /**
     * Clean orphaned files not referenced in database
     */
    private function cleanOrphanedFiles() {
        // Get all files referenced in templates table
        $templateFiles = $this->getReferencedFiles('templates');
        
        // Get all files referenced in tools table
        $toolFiles = $this->getReferencedFiles('tools');
        
        // Combine all referenced files
        $referencedFiles = array_merge($templateFiles, $toolFiles);
        
        // Clean templates directory
        $this->cleanDirectory(UPLOAD_DIR . '/templates/images', $referencedFiles);
        $this->cleanDirectory(UPLOAD_DIR . '/templates/videos', $referencedFiles);
        
        // Clean tools directory
        $this->cleanDirectory(UPLOAD_DIR . '/tools/images', $referencedFiles);
        $this->cleanDirectory(UPLOAD_DIR . '/tools/videos', $referencedFiles);
    }
    
    /**
     * Get all files referenced in database table
     * 
     * @param string $table Table name ('templates' or 'tools')
     * @return array Array of normalized file paths
     */
    private function getReferencedFiles($table) {
        $filePaths = [];
        
        try {
            // Get all thumbnail_url and demo_url from table
            $stmt = $this->db->prepare("SELECT thumbnail_url, demo_url, video_links FROM {$table}");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                // Process thumbnail_url
                if (!empty($row['thumbnail_url'])) {
                    $path = $this->normalizeFilePath($row['thumbnail_url']);
                    if ($path) {
                        $filePaths[] = $path;
                    }
                }
                
                // Process demo_url (might be external URL, ignore those)
                if (!empty($row['demo_url'])) {
                    $path = $this->normalizeFilePath($row['demo_url']);
                    if ($path) {
                        $filePaths[] = $path;
                    }
                }
                
                // Process video_links (might contain multiple URLs)
                if (!empty($row['video_links'])) {
                    $videoLinks = explode(',', $row['video_links']);
                    foreach ($videoLinks as $link) {
                        $link = trim($link);
                        if (!empty($link)) {
                            $path = $this->normalizeFilePath($link);
                            if ($path) {
                                $filePaths[] = $path;
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
        }
        
        return $filePaths;
    }
    
    /**
     * Normalize file path from database URL to local file path
     * Returns false for external URLs
     * 
     * @param string $url URL from database
     * @return string|false Local file path or false if external
     */
    private function normalizeFilePath($url) {
        if (empty($url)) {
            return false;
        }
        
        // Parse the URL
        $parsedUrl = parse_url($url);
        
        // If URL has a host, check if it's our site (with domain normalization)
        if (isset($parsedUrl['host'])) {
            $urlHost = strtolower(trim($parsedUrl['host']));
            $ourParsed = parse_url(SITE_URL);
            $ourHost = isset($ourParsed['host']) ? strtolower(trim($ourParsed['host'])) : '';
            
            // Normalize hosts (remove www. prefix for comparison)
            $normalizedUrlHost = preg_replace('/^www\./', '', $urlHost);
            $normalizedOurHost = preg_replace('/^www\./', '', $ourHost);
            
            if ($normalizedUrlHost !== $normalizedOurHost) {
                return false; // External URL, not our upload
            }
        }
        
        // Get the path component
        $path = $parsedUrl['path'] ?? $url;
        
        // Remove query strings and fragments
        $path = strtok($path, '?');
        $path = strtok($path, '#');
        
        // Convert URL path to file path
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Check if path starts with 'uploads/'
        if (strpos($path, 'uploads/') === 0) {
            // Convert to absolute file path
            $filePath = UPLOAD_DIR . '/' . substr($path, 8); // Remove 'uploads/' prefix
            
            // Only use realpath if file exists, otherwise return the constructed path
            // This prevents false negatives for files that exist but haven't been normalized yet
            if (file_exists($filePath)) {
                return realpath($filePath);
            } else {
                // File doesn't exist - return the expected path anyway
                // (it might be a stale reference in DB)
                return $filePath;
            }
        }
        
        // If path doesn't start with uploads/, it's not an upload file
        return false;
    }
    
    /**
     * Clean directory by removing unreferenced files
     * 
     * @param string $directory Directory path
     * @param array $referencedPaths Array of referenced file paths (absolute)
     */
    private function cleanDirectory($directory, $referencedPaths) {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = glob($directory . '/*');
        if ($files === false) {
            return;
        }
        
        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            
            // Get real path of file
            $realPath = realpath($filePath);
            if (!$realPath) {
                continue;
            }
            
            // Check if file is referenced
            $isReferenced = false;
            foreach ($referencedPaths as $refPath) {
                // Try realpath first for existing files
                $normalizedRef = realpath($refPath);
                
                // If realpath works and matches, file is referenced
                if ($normalizedRef && $realPath === $normalizedRef) {
                    $isReferenced = true;
                    break;
                }
                
                // If realpath failed but paths match exactly, still count as referenced
                // This handles cases where DB has paths to non-existent files
                if (!$normalizedRef && $realPath === $refPath) {
                    $isReferenced = true;
                    break;
                }
            }
            
            // Delete if not referenced and file is older than 1 hour (safety buffer)
            // The 1-hour buffer prevents deletion of newly uploaded files that haven't been saved to DB yet
            if (!$isReferenced && (time() - filemtime($filePath)) > 3600) {
                $fileSize = filesize($filePath);
                
                if (unlink($filePath)) {
                    $this->deletedCount++;
                    $this->freedSpace += $fileSize;
                } else {
                    $this->errors[] = "Failed to delete orphaned file: " . basename($filePath);
                }
            }
        }
    }
}

// If run directly from command line
if (php_sapi_name() === 'cli') {
    echo "Starting file cleanup...\n";
    
    $cleanup = new FileCleanup();
    $result = $cleanup->runCleanup();
    
    echo "Cleanup complete!\n";
    echo "Files deleted: " . $result['deleted_count'] . "\n";
    echo "Space freed: " . $result['freed_space_formatted'] . "\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- " . $error . "\n";
        }
    }
}
