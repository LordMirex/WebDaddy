# Infrastructure Improvements

## Overview

This document outlines infrastructure enhancements to improve performance, reliability, and maintainability of the platform.

---

## 1. Caching System

### Cache Layer Implementation

```php
/**
 * Simple file-based cache for SQLite environment
 */
class Cache {
    private $cacheDir;
    private $defaultTtl = 3600; // 1 hour
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/data/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || $data['expires_at'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);
        
        $data = [
            'key' => $key,
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Remember - get or set
     */
    public function remember($key, $ttl, $callback) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    private function getFilePath($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}

// Usage examples
$cache = new Cache();

// Cache product listings (5 minutes)
$templates = $cache->remember('templates_active', 300, function() {
    return getTemplates(true);
});

// Cache tool categories (1 hour)
$categories = $cache->remember('tool_categories', 3600, function() {
    return getToolCategories();
});

// Invalidate on update
function updateTemplate($id, $data) {
    // ... update logic
    $cache = new Cache();
    $cache->delete('templates_active');
    $cache->delete('template_' . $id);
}
```

### Cache Warming

```php
/**
 * Warm cache on startup or via cron
 */
function warmCache() {
    $cache = new Cache();
    
    // Pre-cache common data
    $cache->set('templates_active', getTemplates(true), 300);
    $cache->set('tools_active', getTools(true), 300);
    $cache->set('tool_categories', getToolCategories(), 3600);
    $cache->set('template_categories', getTemplateCategories(), 3600);
    $cache->set('site_stats', getSiteStats(), 600);
    
    error_log("Cache warmed at " . date('Y-m-d H:i:s'));
}
```

---

## 2. Background Job Queue

### Job Queue Implementation

```php
/**
 * Simple database-backed job queue
 */
class JobQueue {
    private $db;
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Add job to queue
     */
    public function dispatch($jobClass, $data = [], $delay = 0) {
        $runAt = $delay > 0 
            ? date('Y-m-d H:i:s', time() + $delay)
            : date('Y-m-d H:i:s');
        
        $stmt = $this->db->prepare("
            INSERT INTO job_queue (job_class, payload, run_at, created_at)
            VALUES (?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$jobClass, json_encode($data), $runAt]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Process pending jobs
     */
    public function processJobs($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM job_queue 
            WHERE status = 'pending' 
            AND run_at <= datetime('now')
            ORDER BY priority DESC, created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jobs as $job) {
            $this->processJob($job);
        }
        
        return count($jobs);
    }
    
    /**
     * Process single job
     */
    private function processJob($job) {
        // Mark as processing
        $stmt = $this->db->prepare("
            UPDATE job_queue 
            SET status = 'processing', started_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$job['id']]);
        
        try {
            $jobClass = $job['job_class'];
            $payload = json_decode($job['payload'], true);
            
            // Execute job
            if (class_exists($jobClass)) {
                $instance = new $jobClass();
                $instance->handle($payload);
            } elseif (function_exists($jobClass)) {
                call_user_func($jobClass, $payload);
            } else {
                throw new Exception("Job handler not found: {$jobClass}");
            }
            
            // Mark as completed
            $stmt = $this->db->prepare("
                UPDATE job_queue 
                SET status = 'completed', completed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$job['id']]);
            
        } catch (Exception $e) {
            // Mark as failed
            $stmt = $this->db->prepare("
                UPDATE job_queue 
                SET status = 'failed', 
                    error_message = ?,
                    attempts = attempts + 1,
                    completed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $job['id']]);
            
            // Retry if attempts < max
            if ($job['attempts'] < 3) {
                $this->retryJob($job['id']);
            }
            
            error_log("Job {$job['id']} failed: " . $e->getMessage());
        }
    }
    
    /**
     * Retry failed job
     */
    private function retryJob($jobId, $delay = 300) {
        $stmt = $this->db->prepare("
            UPDATE job_queue 
            SET status = 'pending',
                run_at = datetime('now', '+' || ? || ' seconds')
            WHERE id = ?
        ");
        $stmt->execute([$delay, $jobId]);
    }
}

// Database table
/*
CREATE TABLE job_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_class TEXT NOT NULL,
    payload TEXT,
    priority INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'processing', 'completed', 'failed')),
    attempts INTEGER DEFAULT 0,
    error_message TEXT,
    run_at TEXT DEFAULT CURRENT_TIMESTAMP,
    started_at TEXT,
    completed_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_jobs_status ON job_queue(status);
CREATE INDEX idx_jobs_run_at ON job_queue(run_at);
CREATE INDEX idx_jobs_priority ON job_queue(priority);
*/
```

### Job Classes

```php
/**
 * Send email job
 */
class SendEmailJob {
    public function handle($data) {
        $to = $data['to'];
        $subject = $data['subject'];
        $body = $data['body'];
        
        sendEmail($to, $subject, $body);
    }
}

/**
 * Process delivery job
 */
class ProcessDeliveryJob {
    public function handle($data) {
        $orderId = $data['order_id'];
        createDeliveryRecords($orderId);
    }
}

/**
 * Generate download tokens job
 */
class GenerateDownloadTokensJob {
    public function handle($data) {
        $deliveryId = $data['delivery_id'];
        generateDownloadTokens($deliveryId);
    }
}

// Usage
$queue = new JobQueue();

// Queue an email
$queue->dispatch(SendEmailJob::class, [
    'to' => 'customer@example.com',
    'subject' => 'Your order is ready',
    'body' => $emailContent
]);

// Queue a delivery with 5 second delay
$queue->dispatch(ProcessDeliveryJob::class, [
    'order_id' => $orderId
], 5);
```

### Cron Worker

```php
// cron/process_jobs.php
// Run every minute: * * * * * php /path/to/cron/process_jobs.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/job_queue.php';

$queue = new JobQueue();
$processed = $queue->processJobs(20);

if ($processed > 0) {
    error_log("Processed {$processed} jobs");
}
```

---

## 3. Error Monitoring

### Error Logger

```php
/**
 * Centralized error logging
 */
class ErrorLogger {
    private $db;
    private $logFile;
    
    public function __construct() {
        $this->db = getDb();
        $this->logFile = __DIR__ . '/../logs/errors.log';
    }
    
    /**
     * Log an error
     */
    public function log($message, $context = [], $severity = 'error') {
        // Get stack trace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[1] ?? $trace[0];
        
        $errorData = [
            'message' => $message,
            'severity' => $severity,
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? 0,
            'context' => json_encode($context),
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Log to database
        $stmt = $this->db->prepare("
            INSERT INTO error_logs 
            (message, severity, file, line, context, url, method, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $errorData['message'],
            $errorData['severity'],
            $errorData['file'],
            $errorData['line'],
            $errorData['context'],
            $errorData['url'],
            $errorData['method'],
            $errorData['ip'],
            $errorData['user_agent'],
            $errorData['created_at']
        ]);
        
        // Log to file
        $logLine = "[{$errorData['created_at']}] [{$severity}] {$message}";
        if (!empty($context)) {
            $logLine .= " | Context: " . json_encode($context);
        }
        $logLine .= " | {$errorData['file']}:{$errorData['line']}\n";
        
        error_log($logLine, 3, $this->logFile);
        
        // Alert on critical errors
        if ($severity === 'critical') {
            $this->alertAdmin($errorData);
        }
    }
    
    /**
     * Alert admin on critical errors
     */
    private function alertAdmin($errorData) {
        // Check if we've already alerted for this error recently
        $errorHash = md5($errorData['message'] . $errorData['file']);
        $cacheKey = 'error_alert_' . $errorHash;
        
        $cache = new Cache();
        if ($cache->get($cacheKey)) {
            return; // Already alerted in last hour
        }
        
        $cache->set($cacheKey, true, 3600);
        
        // Send alert
        sendAdminNotification("CRITICAL ERROR: {$errorData['message']}", [
            'file' => $errorData['file'],
            'line' => $errorData['line'],
            'url' => $errorData['url']
        ]);
    }
}

// Database table
/*
CREATE TABLE error_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message TEXT NOT NULL,
    severity TEXT DEFAULT 'error',
    file TEXT,
    line INTEGER,
    context TEXT,
    url TEXT,
    method TEXT,
    ip_address TEXT,
    user_agent TEXT,
    is_resolved INTEGER DEFAULT 0,
    resolved_by INTEGER REFERENCES users(id),
    resolved_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_errors_severity ON error_logs(severity);
CREATE INDEX idx_errors_created ON error_logs(created_at);
CREATE INDEX idx_errors_resolved ON error_logs(is_resolved);
*/

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logger = new ErrorLogger();
    
    $severity = match($errno) {
        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
        E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
        E_NOTICE, E_USER_NOTICE => 'notice',
        default => 'error'
    };
    
    $logger->log($errstr, [
        'errno' => $errno,
        'file' => $errfile,
        'line' => $errline
    ], $severity);
    
    return false; // Let PHP handle it too
});

// Exception handler
set_exception_handler(function($exception) {
    $logger = new ErrorLogger();
    
    $logger->log($exception->getMessage(), [
        'type' => get_class($exception),
        'trace' => $exception->getTraceAsString()
    ], 'critical');
    
    // Show user-friendly error page
    if (!headers_sent()) {
        http_response_code(500);
        include __DIR__ . '/../500.php';
    }
});
```

### Error Dashboard

```html
<!-- Admin Error Log Viewer -->
<div class="error-dashboard">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Error Logs</h2>
        
        <div class="flex gap-2">
            <select x-model="severityFilter" class="input-sm">
                <option value="">All Severities</option>
                <option value="critical">Critical</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
                <option value="notice">Notice</option>
            </select>
            
            <button @click="clearResolved()" class="btn btn-sm btn-secondary">
                Clear Resolved
            </button>
        </div>
    </div>
    
    <!-- Error Summary -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <span class="stat-label">Last 24 Hours</span>
            <span class="stat-value text-red-600">23</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Critical</span>
            <span class="stat-value text-red-600">2</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Unresolved</span>
            <span class="stat-value text-yellow-600">5</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Resolution Rate</span>
            <span class="stat-value text-green-600">89%</span>
        </div>
    </div>
    
    <!-- Error List -->
    <div class="error-list">
        <div class="error-item critical">
            <div class="flex items-center justify-between">
                <div>
                    <span class="badge badge-red">CRITICAL</span>
                    <span class="font-medium ml-2">Database connection failed</span>
                </div>
                <span class="text-sm text-gray-500">2 min ago</span>
            </div>
            <p class="text-sm text-gray-600 mt-1">/includes/db.php:45</p>
            <div class="flex gap-2 mt-2">
                <button class="btn btn-sm btn-secondary">View Details</button>
                <button class="btn btn-sm btn-success">Mark Resolved</button>
            </div>
        </div>
    </div>
</div>
```

---

## 4. Automated Backups

### Backup System

```php
/**
 * Automated backup system
 */
class BackupManager {
    private $backupDir;
    private $maxBackups = 7; // Keep 7 days of backups
    
    public function __construct() {
        $this->backupDir = __DIR__ . '/../backups/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create full backup
     */
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "backup_{$timestamp}";
        $backupPath = $this->backupDir . $backupName;
        
        mkdir($backupPath, 0755, true);
        
        // Backup database
        $this->backupDatabase($backupPath);
        
        // Backup uploads
        $this->backupUploads($backupPath);
        
        // Backup configuration
        $this->backupConfig($backupPath);
        
        // Create archive
        $archivePath = $this->createArchive($backupPath, $backupName);
        
        // Cleanup temp directory
        $this->deleteDirectory($backupPath);
        
        // Cleanup old backups
        $this->cleanupOldBackups();
        
        // Log backup
        $this->logBackup($archivePath);
        
        return $archivePath;
    }
    
    /**
     * Backup SQLite database
     */
    private function backupDatabase($backupPath) {
        $dbPath = __DIR__ . '/../database/store.db';
        
        if (file_exists($dbPath)) {
            copy($dbPath, $backupPath . '/store.db');
        }
    }
    
    /**
     * Backup uploads directory
     */
    private function backupUploads($backupPath) {
        $uploadsPath = __DIR__ . '/../uploads/';
        
        if (is_dir($uploadsPath)) {
            $this->copyDirectory($uploadsPath, $backupPath . '/uploads/');
        }
    }
    
    /**
     * Backup configuration files
     */
    private function backupConfig($backupPath) {
        $configFiles = [
            __DIR__ . '/../includes/config.php',
            __DIR__ . '/../.htaccess'
        ];
        
        mkdir($backupPath . '/config/', 0755, true);
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                copy($file, $backupPath . '/config/' . basename($file));
            }
        }
    }
    
    /**
     * Create ZIP archive
     */
    private function createArchive($sourcePath, $name) {
        $archivePath = $this->backupDir . $name . '.zip';
        
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourcePath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        return $archivePath;
    }
    
    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups() {
        $backups = glob($this->backupDir . 'backup_*.zip');
        
        // Sort by date (oldest first)
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete excess backups
        while (count($backups) > $this->maxBackups) {
            unlink(array_shift($backups));
        }
    }
    
    /**
     * Log backup
     */
    private function logBackup($archivePath) {
        $db = getDb();
        
        $stmt = $db->prepare("
            INSERT INTO backup_logs (file_path, file_size, created_at)
            VALUES (?, ?, datetime('now'))
        ");
        $stmt->execute([$archivePath, filesize($archivePath)]);
    }
    
    /**
     * Restore from backup
     */
    public function restoreBackup($archivePath) {
        if (!file_exists($archivePath)) {
            throw new Exception('Backup file not found');
        }
        
        $restorePath = $this->backupDir . 'restore_temp/';
        mkdir($restorePath, 0755, true);
        
        // Extract archive
        $zip = new ZipArchive();
        $zip->open($archivePath);
        $zip->extractTo($restorePath);
        $zip->close();
        
        // Restore database
        if (file_exists($restorePath . 'store.db')) {
            copy($restorePath . 'store.db', __DIR__ . '/../database/store.db');
        }
        
        // Restore uploads
        if (is_dir($restorePath . 'uploads/')) {
            $this->copyDirectory($restorePath . 'uploads/', __DIR__ . '/../uploads/');
        }
        
        // Cleanup
        $this->deleteDirectory($restorePath);
        
        return true;
    }
    
    // Helper methods for directory operations
    private function copyDirectory($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        foreach (scandir($source) as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $srcFile = $source . $file;
            $dstFile = $dest . $file;
            
            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile . '/', $dstFile . '/');
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}

// Cron job: Daily backup at 2 AM
// 0 2 * * * php /path/to/cron/backup.php

// cron/backup.php
require_once __DIR__ . '/../includes/config.php';
$backup = new BackupManager();
$archivePath = $backup->createBackup();
error_log("Backup created: " . $archivePath);
```

---

## 5. Database Optimization

### Query Optimization

```php
/**
 * Database optimization utilities
 */
class DatabaseOptimizer {
    private $db;
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Run optimization tasks
     */
    public function optimize() {
        // Vacuum database (reclaim space)
        $this->db->exec('VACUUM');
        
        // Analyze tables (update statistics)
        $this->db->exec('ANALYZE');
        
        // Reindex
        $this->db->exec('REINDEX');
        
        error_log("Database optimized at " . date('Y-m-d H:i:s'));
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup() {
        // Delete old sessions (expired > 30 days)
        $this->db->exec("
            DELETE FROM customer_sessions 
            WHERE expires_at < datetime('now', '-30 days')
        ");
        
        // Delete old OTP codes (> 1 day)
        $this->db->exec("
            DELETE FROM customer_otp_codes 
            WHERE created_at < datetime('now', '-1 day')
        ");
        
        // Delete old rate limit logs (> 1 day)
        $this->db->exec("
            DELETE FROM rate_limit_log 
            WHERE created_at < datetime('now', '-1 day')
        ");
        
        // Delete old job queue entries (completed > 7 days)
        $this->db->exec("
            DELETE FROM job_queue 
            WHERE status = 'completed' 
            AND completed_at < datetime('now', '-7 days')
        ");
        
        // Delete old error logs (resolved > 30 days)
        $this->db->exec("
            DELETE FROM error_logs 
            WHERE is_resolved = 1 
            AND resolved_at < datetime('now', '-30 days')
        ");
        
        error_log("Database cleanup completed at " . date('Y-m-d H:i:s'));
    }
}

// Weekly optimization
// 0 3 * * 0 php /path/to/cron/optimize_db.php
```

---

## 6. Implementation Checklist

### Phase 1: Caching
- [ ] Create cache directory
- [ ] Implement Cache class
- [ ] Add caching to templates listing
- [ ] Add caching to tools listing
- [ ] Add cache invalidation on updates

### Phase 2: Job Queue
- [ ] Create job_queue table
- [ ] Implement JobQueue class
- [ ] Create job worker cron
- [ ] Migrate email sending to queue
- [ ] Migrate delivery creation to queue

### Phase 3: Error Monitoring
- [ ] Create error_logs table
- [ ] Implement ErrorLogger class
- [ ] Set up error handlers
- [ ] Build error dashboard
- [ ] Configure admin alerts

### Phase 4: Backups
- [ ] Create backups directory
- [ ] Implement BackupManager
- [ ] Set up daily backup cron
- [ ] Build backup management UI
- [ ] Test restore functionality

### Phase 5: Optimization
- [ ] Implement DatabaseOptimizer
- [ ] Set up weekly optimization cron
- [ ] Add database cleanup tasks
- [ ] Monitor performance

---

## Related Documents

- [01_DATABASE_SCHEMA.md](./01_DATABASE_SCHEMA.md) - Database structure
- [19_ADMIN_AUTOMATION.md](./19_ADMIN_AUTOMATION.md) - Admin tools
- [14_DEPLOYMENT_GUIDE.md](./14_DEPLOYMENT_GUIDE.md) - Deployment
