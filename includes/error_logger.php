<?php
/**
 * Centralized Error Logging System
 * Logs errors to database and file with severity levels
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Error Logger Class
 * Provides centralized error logging with database and file storage
 */
class ErrorLogger {
    private $db;
    private $logFile;
    
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_NOTICE = 'notice';
    
    public function __construct() {
        $this->db = getDb();
        $this->logFile = __DIR__ . '/../logs/errors.log';
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log an error
     * @param string $message Error message
     * @param array $context Additional context data
     * @param string $severity Severity level
     */
    public function log($message, $context = [], $severity = self::SEVERITY_ERROR) {
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
        
        $this->logToDatabase($errorData);
        $this->logToFile($errorData);
        
        if ($severity === self::SEVERITY_CRITICAL) {
            $this->alertAdmin($errorData);
        }
    }
    
    /**
     * Log critical error
     */
    public function critical($message, $context = []) {
        $this->log($message, $context, self::SEVERITY_CRITICAL);
    }
    
    /**
     * Log error
     */
    public function error($message, $context = []) {
        $this->log($message, $context, self::SEVERITY_ERROR);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $context = []) {
        $this->log($message, $context, self::SEVERITY_WARNING);
    }
    
    /**
     * Log notice
     */
    public function notice($message, $context = []) {
        $this->log($message, $context, self::SEVERITY_NOTICE);
    }
    
    /**
     * Log error to database
     * @param array $errorData Error data
     */
    private function logToDatabase($errorData) {
        try {
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
        } catch (Exception $e) {
            error_log("ErrorLogger DB write failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log error to file
     * @param array $errorData Error data
     */
    private function logToFile($errorData) {
        $logLine = "[{$errorData['created_at']}] [{$errorData['severity']}] {$errorData['message']}";
        if (!empty($errorData['context']) && $errorData['context'] !== '[]') {
            $logLine .= " | Context: {$errorData['context']}";
        }
        $logLine .= " | {$errorData['file']}:{$errorData['line']}\n";
        
        error_log($logLine, 3, $this->logFile);
    }
    
    /**
     * Alert admin on critical errors
     * @param array $errorData Error data
     */
    public function alertAdmin($errorData) {
        require_once __DIR__ . '/cache.php';
        
        $errorHash = md5($errorData['message'] . $errorData['file']);
        $cacheKey = 'error_alert_' . $errorHash;
        
        $cache = new Cache();
        if ($cache->get($cacheKey)) {
            return;
        }
        
        $cache->set($cacheKey, true, 3600);
        
        if (function_exists('sendAdminNotification')) {
            sendAdminNotification("CRITICAL ERROR: {$errorData['message']}", [
                'file' => $errorData['file'],
                'line' => $errorData['line'],
                'url' => $errorData['url']
            ]);
        }
        
        error_log("CRITICAL ALERT: {$errorData['message']} at {$errorData['file']}:{$errorData['line']}");
    }
    
    /**
     * Get recent errors
     * @param int $limit Number of errors to return
     * @param string|null $severity Filter by severity
     * @return array Error records
     */
    public function getRecent($limit = 50, $severity = null) {
        try {
            if ($severity) {
                $stmt = $this->db->prepare("
                    SELECT * FROM error_logs 
                    WHERE severity = ?
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$severity, $limit]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT * FROM error_logs 
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get unresolved errors
     * @param int $limit Number of errors to return
     * @return array Error records
     */
    public function getUnresolved($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM error_logs 
                WHERE is_resolved = 0
                ORDER BY 
                    CASE severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'error' THEN 2 
                        WHEN 'warning' THEN 3 
                        ELSE 4 
                    END,
                    created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Mark error as resolved
     * @param int $errorId Error ID
     * @param string $resolvedBy User who resolved it
     */
    public function resolve($errorId, $resolvedBy = 'admin') {
        try {
            $stmt = $this->db->prepare("
                UPDATE error_logs 
                SET is_resolved = 1, 
                    resolved_by = ?,
                    resolved_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$resolvedBy, $errorId]);
        } catch (Exception $e) {
            error_log("ErrorLogger resolve failed: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up old resolved errors
     * @param int $daysOld Number of days to keep
     */
    public function cleanup($daysOld = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM error_logs 
                WHERE is_resolved = 1 
                AND resolved_at < datetime('now', '-' || ? || ' days')
            ");
            $stmt->execute([$daysOld]);
        } catch (Exception $e) {
            error_log("ErrorLogger cleanup failed: " . $e->getMessage());
        }
    }
}

/**
 * Custom error handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $logger = new ErrorLogger();
    
    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $logger->critical($errstr, ['errno' => $errno, 'file' => $errfile, 'line' => $errline]);
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $logger->warning($errstr, ['errno' => $errno, 'file' => $errfile, 'line' => $errline]);
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $logger->notice($errstr, ['errno' => $errno, 'file' => $errfile, 'line' => $errline]);
            break;
        default:
            $logger->error($errstr, ['errno' => $errno, 'file' => $errfile, 'line' => $errline]);
    }
    
    return false;
}

/**
 * Custom exception handler
 */
function customExceptionHandler($exception) {
    $logger = new ErrorLogger();
    
    $logger->critical($exception->getMessage(), [
        'exception' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
}

/**
 * Register custom error handlers (call this to enable)
 */
function registerErrorHandlers() {
    set_error_handler('customErrorHandler');
    set_exception_handler('customExceptionHandler');
}

/**
 * Helper function to log an error
 * @param string $message Error message
 * @param array $context Additional context
 * @param string $severity Severity level
 */
function logSystemError($message, $context = [], $severity = 'error') {
    $logger = new ErrorLogger();
    $logger->log($message, $context, $severity);
}
