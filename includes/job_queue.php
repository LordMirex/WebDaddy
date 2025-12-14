<?php
/**
 * Background Job Queue System
 * Simple database-backed job queue for background processing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Job Queue Class
 * Manages background job processing with retry logic
 */
class JobQueue {
    private $db;
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Add job to queue
     * @param string $jobClass Job class name or function name
     * @param array $data Job payload data
     * @param int $delay Delay in seconds before job runs
     * @param int $priority Priority level (higher = more important)
     * @return int|false Job ID or false on failure
     */
    public function dispatch($jobClass, $data = [], $delay = 0, $priority = 0) {
        $runAt = $delay > 0 
            ? date('Y-m-d H:i:s', time() + $delay)
            : date('Y-m-d H:i:s');
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO job_queue (job_class, payload, priority, run_at, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$jobClass, json_encode($data), $priority, $runAt]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("JobQueue dispatch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process pending jobs
     * @param int $limit Maximum number of jobs to process
     * @return int Number of jobs processed
     */
    public function processJobs($limit = 10) {
        try {
            // SQLite doesn't allow parameter binding for LIMIT, so we inline it
            $limit = intval($limit);
            $stmt = $this->db->prepare("
                SELECT * FROM job_queue 
                WHERE status = 'pending' 
                AND run_at <= datetime('now')
                ORDER BY priority DESC, created_at ASC
                LIMIT " . $limit . "
            ");
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            foreach ($jobs as $job) {
                if ($this->processJob($job)) {
                    $processed++;
                }
            }
            
            return $processed;
        } catch (Exception $e) {
            error_log("JobQueue processJobs error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Process a single job
     * @param array $job Job data
     * @return bool Success status
     */
    private function processJob($job) {
        $stmt = $this->db->prepare("
            UPDATE job_queue 
            SET status = 'processing', started_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$job['id']]);
        
        try {
            $jobClass = $job['job_class'];
            $payload = json_decode($job['payload'], true);
            
            if (class_exists($jobClass)) {
                $instance = new $jobClass();
                $instance->handle($payload);
            } elseif (function_exists($jobClass)) {
                call_user_func($jobClass, $payload);
            } else {
                throw new Exception("Job handler not found: {$jobClass}");
            }
            
            $stmt = $this->db->prepare("
                UPDATE job_queue 
                SET status = 'completed', completed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$job['id']]);
            
            return true;
            
        } catch (Exception $e) {
            $attempts = ($job['attempts'] ?? 0) + 1;
            
            $stmt = $this->db->prepare("
                UPDATE job_queue 
                SET status = 'failed', 
                    error_message = ?,
                    attempts = ?,
                    completed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $attempts, $job['id']]);
            
            if ($attempts < 3) {
                $this->retryJob($job['id'], 300 * $attempts);
            }
            
            error_log("Job {$job['id']} failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retry a failed job
     * @param int $jobId Job ID
     * @param int $delay Delay in seconds
     */
    private function retryJob($jobId, $delay = 300) {
        try {
            $stmt = $this->db->prepare("
                UPDATE job_queue 
                SET status = 'pending',
                    run_at = datetime('now', '+' || ? || ' seconds')
                WHERE id = ?
            ");
            $stmt->execute([$delay, $jobId]);
        } catch (Exception $e) {
            error_log("JobQueue retryJob error: " . $e->getMessage());
        }
    }
    
    /**
     * Get job by ID
     * @param int $jobId Job ID
     * @return array|null Job data
     */
    public function getJob($jobId) {
        $stmt = $this->db->prepare("SELECT * FROM job_queue WHERE id = ?");
        $stmt->execute([$jobId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get pending jobs count
     * @return int Count of pending jobs
     */
    public function getPendingCount() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM job_queue WHERE status = 'pending'");
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get failed jobs
     * @param int $limit Maximum number to return
     * @return array Failed jobs
     */
    public function getFailedJobs($limit = 50) {
        // SQLite doesn't allow parameter binding for LIMIT, so we inline it
        $limit = intval($limit);
        $stmt = $this->db->prepare("
            SELECT * FROM job_queue 
            WHERE status = 'failed' AND attempts >= 3
            ORDER BY created_at DESC
            LIMIT " . $limit . "
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean up old completed jobs
     * @param int $daysOld Number of days to keep
     */
    public function cleanup($daysOld = 7) {
        $stmt = $this->db->prepare("
            DELETE FROM job_queue 
            WHERE status = 'completed' 
            AND completed_at < datetime('now', '-' || ? || ' days')
        ");
        $stmt->execute([$daysOld]);
    }
}

/**
 * Send Email Job
 * Handles sending emails in the background
 */
class SendEmailJob {
    public function handle($data) {
        $to = $data['to'] ?? null;
        $subject = $data['subject'] ?? '';
        $body = $data['body'] ?? '';
        
        if (!$to) {
            throw new Exception('Email recipient is required');
        }
        
        if (function_exists('sendEmail')) {
            $result = sendEmail($to, $subject, $body);
            if (!$result) {
                throw new Exception('Failed to send email');
            }
        } else {
            throw new Exception('sendEmail function not available');
        }
    }
}

/**
 * Process Delivery Job
 * Handles creating delivery records for orders
 */
class ProcessDeliveryJob {
    public function handle($data) {
        $orderId = $data['order_id'] ?? null;
        
        if (!$orderId) {
            throw new Exception('Order ID is required');
        }
        
        if (function_exists('createDeliveryRecords')) {
            createDeliveryRecords($orderId);
        } else {
            throw new Exception('createDeliveryRecords function not available');
        }
    }
}

/**
 * Generate Download Tokens Job
 * Handles generating download tokens for deliveries
 */
class GenerateDownloadTokensJob {
    public function handle($data) {
        $deliveryId = $data['delivery_id'] ?? null;
        
        if (!$deliveryId) {
            throw new Exception('Delivery ID is required');
        }
        
        if (function_exists('generateDownloadTokens')) {
            generateDownloadTokens($deliveryId);
        } else {
            throw new Exception('generateDownloadTokens function not available');
        }
    }
}

/**
 * Helper function to dispatch a job
 * @param string $jobClass Job class name
 * @param array $data Job payload
 * @param int $delay Delay in seconds
 * @return int|false Job ID or false
 */
function dispatchJob($jobClass, $data = [], $delay = 0) {
    $queue = new JobQueue();
    return $queue->dispatch($jobClass, $data, $delay);
}
