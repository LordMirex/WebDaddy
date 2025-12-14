<?php
/**
 * Backup Manager System
 * Handles database and file backups with cleanup
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Backup Manager Class
 * Creates and manages backups of database, uploads, and config
 */
class BackupManager {
    private $db;
    private $backupDir;
    private $retentionDays = 7;
    
    public function __construct() {
        $this->db = getDb();
        $this->backupDir = __DIR__ . '/../database/backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create a full backup (database, uploads, config)
     * @param bool $includeUploads Whether to include uploads folder
     * @return array|false Backup info or false on failure
     */
    public function createFullBackup($includeUploads = true) {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "webdaddy_backup_{$timestamp}";
        
        try {
            $zipFile = $this->backupDir . $backupName . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Cannot create backup ZIP file");
            }
            
            $this->backupDatabase($zip, $backupName);
            $this->backupConfig($zip);
            
            if ($includeUploads) {
                $this->backupUploads($zip);
            }
            
            $zip->close();
            
            $fileSize = filesize($zipFile);
            $this->logBackup($zipFile, $fileSize);
            
            $this->cleanupOldBackups();
            
            return [
                'file' => $zipFile,
                'name' => $backupName,
                'size' => $fileSize,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Backup failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create database-only backup
     * @return array|false Backup info or false on failure
     */
    public function createDatabaseBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "webdaddy_db_{$timestamp}";
        $backupFile = $this->backupDir . $backupName . '.db';
        
        try {
            $sourceDb = __DIR__ . '/../database/webdaddy.db';
            
            if (!file_exists($sourceDb)) {
                throw new Exception("Source database not found");
            }
            
            copy($sourceDb, $backupFile);
            
            $fileSize = filesize($backupFile);
            $this->logBackup($backupFile, $fileSize);
            
            return [
                'file' => $backupFile,
                'name' => $backupName,
                'size' => $fileSize,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Database backup failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Backup database to ZIP
     * @param ZipArchive $zip ZIP archive instance
     * @param string $backupName Backup name for the folder
     */
    private function backupDatabase($zip, $backupName) {
        $sourceDb = __DIR__ . '/../database/webdaddy.db';
        
        if (file_exists($sourceDb)) {
            $zip->addFile($sourceDb, 'database/webdaddy.db');
        }
        
        $storeDb = __DIR__ . '/../database/store.db';
        if (file_exists($storeDb)) {
            $zip->addFile($storeDb, 'database/store.db');
        }
    }
    
    /**
     * Backup config files to ZIP
     * @param ZipArchive $zip ZIP archive instance
     */
    private function backupConfig($zip) {
        $configFile = __DIR__ . '/config.php';
        
        if (file_exists($configFile)) {
            $zip->addFile($configFile, 'includes/config.php');
        }
    }
    
    /**
     * Backup uploads folder to ZIP
     * @param ZipArchive $zip ZIP archive instance
     */
    private function backupUploads($zip) {
        $uploadsDir = __DIR__ . '/../uploads/';
        
        if (!is_dir($uploadsDir)) {
            return;
        }
        
        $this->addDirectoryToZip($zip, $uploadsDir, 'uploads');
    }
    
    /**
     * Add directory contents to ZIP recursively
     * @param ZipArchive $zip ZIP archive instance
     * @param string $dirPath Directory path
     * @param string $zipPath Path inside ZIP
     */
    private function addDirectoryToZip($zip, $dirPath, $zipPath) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . '/' . substr($filePath, strlen($dirPath));
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                if (filesize($filePath) < 100 * 1024 * 1024) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }
    
    /**
     * Log backup to database
     * @param string $filePath Backup file path
     * @param int $fileSize File size in bytes
     */
    private function logBackup($filePath, $fileSize) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO backup_logs (file_path, file_size, created_at)
                VALUES (?, ?, datetime('now'))
            ");
            $stmt->execute([$filePath, $fileSize]);
        } catch (Exception $e) {
            error_log("Failed to log backup: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up old backups (keep last 7 days)
     */
    public function cleanupOldBackups() {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->retentionDays} days"));
        
        try {
            $stmt = $this->db->prepare("
                SELECT file_path FROM backup_logs 
                WHERE created_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            $oldBackups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($oldBackups as $backup) {
                if (file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }
            }
            
            $stmt = $this->db->prepare("
                DELETE FROM backup_logs 
                WHERE created_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            
        } catch (Exception $e) {
            error_log("Backup cleanup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Restore from backup
     * @param string $backupFile Path to backup file
     * @return bool Success status
     */
    public function restore($backupFile) {
        if (!file_exists($backupFile)) {
            error_log("Backup file not found: {$backupFile}");
            return false;
        }
        
        try {
            $extension = pathinfo($backupFile, PATHINFO_EXTENSION);
            
            if ($extension === 'db') {
                return $this->restoreDatabase($backupFile);
            } elseif ($extension === 'zip') {
                return $this->restoreFromZip($backupFile);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Restore failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restore database from .db file
     * @param string $backupFile Backup file path
     * @return bool Success status
     */
    private function restoreDatabase($backupFile) {
        $targetDb = __DIR__ . '/../database/webdaddy.db';
        
        $preRestoreBackup = $this->backupDir . 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
        copy($targetDb, $preRestoreBackup);
        
        return copy($backupFile, $targetDb);
    }
    
    /**
     * Restore from ZIP backup
     * @param string $backupFile Backup ZIP file path
     * @return bool Success status
     */
    private function restoreFromZip($backupFile) {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile) !== true) {
            return false;
        }
        
        $targetDb = __DIR__ . '/../database/webdaddy.db';
        $preRestoreBackup = $this->backupDir . 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
        copy($targetDb, $preRestoreBackup);
        
        $tempDir = sys_get_temp_dir() . '/webdaddy_restore_' . time();
        mkdir($tempDir, 0755, true);
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        $extractedDb = $tempDir . '/database/webdaddy.db';
        if (file_exists($extractedDb)) {
            copy($extractedDb, $targetDb);
        }
        
        $this->recursiveDelete($tempDir);
        
        return true;
    }
    
    /**
     * Recursively delete directory
     * @param string $dir Directory path
     */
    private function recursiveDelete($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $path = $dir . "/" . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Get backup history
     * @param int $limit Number of records to return
     * @return array Backup records
     */
    public function getHistory($limit = 20) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM backup_logs 
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get available backups that can be restored
     * @return array Available backup files
     */
    public function getAvailableBackups() {
        $backups = [];
        
        $files = glob($this->backupDir . '*.{db,zip}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $backups[] = [
                'file' => $file,
                'name' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backups;
    }
    
    /**
     * Set retention days
     * @param int $days Number of days to keep backups
     */
    public function setRetentionDays($days) {
        $this->retentionDays = max(1, (int) $days);
    }
}

/**
 * Helper function to create a backup
 * @param bool $includeUploads Whether to include uploads
 * @return array|false Backup info or false
 */
function createBackup($includeUploads = true) {
    $manager = new BackupManager();
    return $manager->createFullBackup($includeUploads);
}
