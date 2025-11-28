<?php
$pageTitle = 'Database Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$dbPath = __DIR__ . '/../database/webdaddy.db';

if (isset($_GET['action']) && $_GET['action'] === 'get_schema' && isset($_GET['table'])) {
    header('Content-Type: application/json');
    $tableName = $_GET['table'];
    try {
        $schema = $db->query("PRAGMA table_info(\"$tableName\")")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($schema);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$successMessage = '';
$errorMessage = '';
$queryResult = null;
$queryExecutionTime = 0;

if (!isset($_SESSION['query_history'])) {
    $_SESSION['query_history'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'execute_query') {
        $query = trim($_POST['query'] ?? '');
        
        if (!empty($query)) {
            $startTime = microtime(true);
            
            try {
                $stmt = $db->query($query);
                $queryExecutionTime = round((microtime(true) - $startTime) * 1000, 2);
                
                if (stripos($query, 'SELECT') === 0 || stripos($query, 'PRAGMA') === 0) {
                    $queryResult = [
                        'type' => 'select',
                        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                        'rows' => $stmt->rowCount()
                    ];
                    $successMessage = "Query executed successfully in {$queryExecutionTime}ms";
                } else {
                    $affectedRows = $stmt->rowCount();
                    $queryResult = [
                        'type' => 'modify',
                        'affected_rows' => $affectedRows
                    ];
                    $successMessage = "Query executed successfully. {$affectedRows} row(s) affected in {$queryExecutionTime}ms";
                    
                    logActivity('database_query', "Executed: " . substr($query, 0, 100), getAdminId());
                }
                
                array_unshift($_SESSION['query_history'], [
                    'query' => $query,
                    'time' => date('Y-m-d H:i:s'),
                    'execution_time' => $queryExecutionTime
                ]);
                $_SESSION['query_history'] = array_slice($_SESSION['query_history'], 0, 5);
                
            } catch (PDOException $e) {
                $errorMessage = "Query Error: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'backup_database') {
        $backupDir = __DIR__ . '/../database/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/webdaddy_backup_' . date('Y-m-d_H-i-s') . '.db';
        
        if (copy($dbPath, $backupFile)) {
            $successMessage = "Database backup created successfully: " . basename($backupFile);
            logActivity('database_backup', 'Created database backup: ' . basename($backupFile), getAdminId());
        } else {
            $errorMessage = "Failed to create database backup";
        }
    }
    
    elseif ($action === 'vacuum') {
        try {
            $startTime = microtime(true);
            $sizeBefore = filesize($dbPath);
            
            $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            
            $db->exec('VACUUM');
            
            $db->exec('ANALYZE');
            
            $db->exec('PRAGMA optimize');
            
            $db->exec('REINDEX');
            
            clearstatcache();
            $sizeAfter = filesize($dbPath);
            $saved = $sizeBefore - $sizeAfter;
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $successMessage = "✅ Full database optimization complete! Space saved: " . formatBytes($saved) . " | Execution time: {$executionTime}ms | Applied: VACUUM, ANALYZE, OPTIMIZE, REINDEX";
            logActivity('database_vacuum', 'Full optimization: VACUUM + ANALYZE + OPTIMIZE + REINDEX', getAdminId());
        } catch (PDOException $e) {
            $errorMessage = "Optimization failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cleanup_logs') {
        try {
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < datetime('now', '-90 days')");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $successMessage = "Deleted {$deleted} old activity log(s)";
            logActivity('database_cleanup', "Cleaned up {$deleted} old activity logs", getAdminId());
        } catch (PDOException $e) {
            $errorMessage = "Cleanup failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cleanup_orders') {
        try {
            $stmt = $db->prepare("DELETE FROM pending_orders WHERE status IN ('cancelled', 'pending') AND created_at < datetime('now', '-30 days')");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $successMessage = "Deleted {$deleted} old order(s)";
            logActivity('database_cleanup', "Cleaned up {$deleted} old orders", getAdminId());
        } catch (PDOException $e) {
            $errorMessage = "Cleanup failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'empty_table') {
        $tableName = $_POST['table_name'] ?? '';
        
        if (!empty($tableName)) {
            try {
                $stmt = $db->prepare("DELETE FROM \"$tableName\"");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                $successMessage = "Table '{$tableName}' emptied successfully. {$deleted} record(s) deleted.";
                logActivity('database_table_empty', "Emptied table: {$tableName} ({$deleted} records)", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = "Failed to empty table: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'cleanup_backups') {
        try {
            $backupDir = __DIR__ . '/../database/backups';
            $deleted = 0;
            
            if (is_dir($backupDir)) {
                $backups = glob($backupDir . '/webdaddy_backup_*.db');
                if (count($backups) > 5) {
                    usort($backups, function($a, $b) {
                        return filemtime($a) - filemtime($b);
                    });
                    
                    $toDelete = array_slice($backups, 0, count($backups) - 5);
                    foreach ($toDelete as $file) {
                        if (unlink($file)) {
                            $deleted++;
                        }
                    }
                }
            }
            
            $successMessage = "Cleanup completed. {$deleted} old backup(s) deleted. Keeping the 5 most recent backups.";
            logActivity('database_backup_cleanup', "Cleaned up {$deleted} old backups", getAdminId());
        } catch (Exception $e) {
            $errorMessage = "Backup cleanup failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cron_process_email_queue') {
        require_once __DIR__ . '/../includes/email_queue.php';
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending' AND attempts < 5");
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);
            $pendingCount = $pending['count'] ?? 0;
            
            if ($pendingCount > 0) {
                processEmailQueue();
                $stmt = $db->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $summary = [];
                foreach ($results as $row) {
                    $summary[] = "{$row['count']} {$row['status']}";
                }
                $successMessage = "Email queue processed! Found {$pendingCount} pending. Results: " . implode(', ', $summary);
            } else {
                $successMessage = "No pending emails to process.";
            }
            logActivity('cron_email_queue', "Processed email queue", getAdminId());
        } catch (Exception $e) {
            $errorMessage = "Email queue processing failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cron_process_retries') {
        require_once __DIR__ . '/../includes/delivery.php';
        try {
            $result = processDeliveryRetries();
            $successMessage = "Delivery retries processed! Processed: {$result['processed']}, Successful: {$result['successful']}, Failed: {$result['failed']}";
            logActivity('cron_process_retries', "Processed {$result['processed']} delivery retries", getAdminId());
        } catch (Exception $e) {
            $errorMessage = "Delivery retry processing failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cron_cleanup_security') {
        try {
            $stmt = $db->prepare("DELETE FROM webhook_rate_limits WHERE request_time < ?");
            $stmt->execute([time() - 3600]);
            $rateDeleted = $stmt->rowCount();
            
            $stmt = $db->query("DELETE FROM security_logs WHERE created_at < datetime('now', '-30 days', '+1 hour')");
            $logsDeleted = $stmt->rowCount();
            
            $successMessage = "Security cleanup complete! Rate limits removed: {$rateDeleted}, Old security logs removed: {$logsDeleted}";
            logActivity('cron_cleanup_security', "Cleaned rate limits: {$rateDeleted}, logs: {$logsDeleted}", getAdminId());
        } catch (Exception $e) {
            $errorMessage = "Security cleanup failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'cron_weekly_report') {
        require_once __DIR__ . '/../includes/report_generator.php';
        require_once __DIR__ . '/../mailer/PHPMailer.php';
        require_once __DIR__ . '/../mailer/SMTP.php';
        require_once __DIR__ . '/../mailer/Exception.php';
        
        try {
            $report = ReportGenerator::generateWeeklyReport($db);
            
            if (isset($report['error'])) {
                throw new Exception($report['error']);
            }
            
            $zipPath = ReportGenerator::createReportZip($report, $dbPath);
            
            $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
            $subject = "WebDaddy Empire - Weekly Report " . date('Y-m-d');
            
            $mailContent = "<h2>Weekly Report Summary</h2>
                <p><strong>Period:</strong> {$report['period']}</p>
                <h3>Financial</h3>
                <ul>
                    <li><strong>This Week's Profit:</strong> NGN {$report['profit_this_week']}</li>
                    <li><strong>All-Time Profit:</strong> NGN {$report['profit_all_time']}</li>
                </ul>
                <h3>Traffic</h3>
                <ul>
                    <li><strong>Template Views (Week):</strong> {$report['template_views_week']}</li>
                    <li><strong>Tool Views (Week):</strong> {$report['tool_views_week']}</li>
                    <li><strong>Orders (Week):</strong> {$report['orders_this_week']}</li>
                </ul>
                <p><strong>Attached:</strong> Detailed report (HTML) + Database backup</p>";
            
            $emailBody = createEmailTemplate($subject, $mailContent, 'Admin');
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->Port = SMTP_PORT;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($adminEmail);
            $mail->addAttachment($zipPath, 'WebDaddy_Report_' . date('Y-m-d') . '.zip');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $emailBody;
            $mail->send();
            
            unlink($zipPath);
            
            $successMessage = "Weekly report generated and sent to {$adminEmail}!";
            logActivity('cron_weekly_report', "Generated and sent weekly report", getAdminId());
        } catch (Exception $e) {
            $errorMessage = "Weekly report generation failed: " . $e->getMessage();
        }
    }
}

// Handle backup download requests
if (isset($_GET['download_backup'])) {
    $backupFile = basename($_GET['download_backup']);
    $backupPath = __DIR__ . '/../database/backups/' . $backupFile;
    
    if (file_exists($backupPath) && strpos($backupFile, 'webdaddy_backup_') === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backupFile . '"');
        header('Content-Length: ' . filesize($backupPath));
        header('Cache-Control: no-cache');
        readfile($backupPath);
        exit;
    }
}

// Handle current database download
if (isset($_GET['download_current'])) {
    $currentDbFile = 'webdaddy_current_' . date('Y-m-d_H-i-s') . '.db';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $currentDbFile . '"');
    header('Content-Length: ' . filesize($dbPath));
    header('Cache-Control: no-cache');
    readfile($dbPath);
    exit;
}

$dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;

$tables = $db->query("
    SELECT name 
    FROM sqlite_master 
    WHERE type = 'table' 
    AND name NOT LIKE 'sqlite_%'
    ORDER BY name
")->fetchAll(PDO::FETCH_COLUMN);

$tableStats = [];
$totalRecords = 0;

foreach ($tables as $table) {
    $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
    $totalRecords += $count;
    $tableStats[] = [
        'name' => $table,
        'count' => $count
    ];
}

$backupDir = __DIR__ . '/../database/backups';
$lastBackup = null;
if (is_dir($backupDir)) {
    $backups = glob($backupDir . '/webdaddy_backup_*.db');
    if (!empty($backups)) {
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $lastBackup = $backups[0];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-database-gear text-primary-600"></i> Database Management
    </h1>
    <p class="text-gray-600 mt-2">Manage, query, and optimize your database</p>
</div>

<!-- Important SQLite Notice -->
<div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-5 rounded-lg">
    <div class="flex items-start gap-3">
        <i class="bi bi-info-circle text-blue-600 text-2xl flex-shrink-0 mt-0.5"></i>
        <div class="flex-1">
            <h3 class="font-bold text-blue-900 text-lg mb-2">ℹ️ Important: This Project Uses SQLite</h3>
            <div class="text-blue-800 space-y-2">
                <p><strong>Why you see "Failed to load schema" in phpMyAdmin:</strong></p>
                <ul class="list-disc ml-5 space-y-1">
                    <li>phpMyAdmin is designed for MySQL/MariaDB databases only</li>
                    <li>This project uses SQLite - a lightweight, file-based database</li>
                    <li>SQLite stores everything in a single file: <code class="bg-blue-100 px-2 py-0.5 rounded">database/webdaddy.db</code></li>
                </ul>
                <p class="font-semibold mt-3">✅ Use this page instead for all database operations:</p>
                <ul class="list-disc ml-5 space-y-1">
                    <li>Execute SQL queries directly below</li>
                    <li>View table structure with: <code class="bg-blue-100 px-2 py-0.5 rounded">PRAGMA table_info(table_name);</code></li>
                    <li>List all tables with: <code class="bg-blue-100 px-2 py-0.5 rounded">SELECT name FROM sqlite_master WHERE type='table';</code></li>
                    <li>Optimize database with the VACUUM button</li>
                    <li>Create backups with one click</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg flex items-center gap-3" x-data="{ show: true }" x-show="show">
    <i class="bi bi-check-circle text-xl"></i>
    <span class="flex-1"><?php echo htmlspecialchars($successMessage); ?></span>
    <button @click="show = false" class="text-green-600 hover:text-green-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg flex items-center gap-3" x-data="{ show: true }" x-show="show">
    <i class="bi bi-exclamation-triangle text-xl"></i>
    <span class="flex-1"><?php echo htmlspecialchars($errorMessage); ?></span>
    <button @click="show = false" class="text-red-600 hover:text-red-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Database Size</h6>
            <i class="bi bi-hdd text-2xl text-primary-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo formatBytes($dbSize); ?></div>
        <small class="text-sm text-gray-500">webdaddy.db</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Total Tables</h6>
            <i class="bi bi-table text-2xl text-blue-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo count($tables); ?></div>
        <small class="text-sm text-gray-500">Active tables</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Total Records</h6>
            <i class="bi bi-file-text text-2xl text-green-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($totalRecords); ?></div>
        <small class="text-sm text-gray-500">All tables combined</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase">Last Backup</h6>
            <i class="bi bi-cloud-download text-2xl text-purple-600"></i>
        </div>
        <div class="text-lg font-bold text-gray-900">
            <?php if ($lastBackup): ?>
                <?php echo date('M d, Y', filemtime($lastBackup)); ?>
            <?php else: ?>
                Never
            <?php endif; ?>
        </div>
        <small class="text-sm text-gray-500">
            <?php if ($lastBackup): ?>
                <?php echo date('H:i:s', filemtime($lastBackup)); ?>
            <?php else: ?>
                No backups found
            <?php endif; ?>
        </small>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-tools text-primary-600"></i> Backup & Maintenance
            </h5>
        </div>
        <div class="p-6 space-y-3">
            <form method="POST" class="space-y-3">
                <button type="submit" name="action" value="backup_database" class="w-full flex items-center justify-between px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-download"></i> Create New Backup
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <a href="?download_current=1" class="w-full flex items-center justify-between px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                <span class="flex items-center gap-2">
                    <i class="bi bi-file-earmark-arrow-down"></i> Download Current Database
                </span>
                <i class="bi bi-arrow-right"></i>
            </a>
            
            <?php if ($lastBackup): ?>
            <a href="?download_backup=<?php echo urlencode(basename($lastBackup)); ?>" class="w-full flex items-center justify-between px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                <span class="flex items-center gap-2">
                    <i class="bi bi-cloud-arrow-down"></i> Download Latest Backup
                </span>
                <i class="bi bi-arrow-right"></i>
            </a>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to optimize the database? This may take a few moments.');" class="space-y-3">
                <button type="submit" name="action" value="vacuum" class="w-full flex items-center justify-between px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-cpu"></i> Optimize Database (VACUUM)
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <form method="POST" onsubmit="return confirm('Delete old backups? This will keep only the 5 most recent backups.');">
                <button type="submit" name="action" value="cleanup_backups" class="w-full flex items-center justify-between px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-folder-x"></i> Cleanup Old Backups
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-gear-wide-connected text-teal-600"></i> System Tasks (Run Manually)
            </h5>
            <p class="text-sm text-gray-500 mt-1">Execute cron jobs directly from here instead of command line</p>
        </div>
        <div class="p-6 space-y-3">
            <form method="POST">
                <button type="submit" name="action" value="cron_process_email_queue" class="w-full flex items-center justify-between px-4 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-envelope-arrow-up"></i> Process Email Queue
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <form method="POST">
                <button type="submit" name="action" value="cron_process_retries" class="w-full flex items-center justify-between px-4 py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-arrow-repeat"></i> Process Delivery Retries
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <form method="POST">
                <button type="submit" name="action" value="cron_cleanup_security" class="w-full flex items-center justify-between px-4 py-3 bg-slate-600 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-shield-check"></i> Cleanup Security Logs
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <form method="POST" onsubmit="return confirm('Generate weekly report and send to admin email?');">
                <button type="submit" name="action" value="cron_weekly_report" class="w-full flex items-center justify-between px-4 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-file-earmark-bar-graph"></i> Generate Weekly Report
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <div class="bg-teal-50 border-l-4 border-teal-500 text-teal-800 p-3 rounded-lg text-sm">
                <i class="bi bi-info-circle"></i> These tasks can be run anytime. No CLI/cPanel cron jobs needed.
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-trash text-orange-600"></i> Database Cleanup
            </h5>
        </div>
        <div class="p-6 space-y-3">
            <form method="POST" onsubmit="return confirm('Delete activity logs older than 90 days?');">
                <button type="submit" name="action" value="cleanup_logs" class="w-full flex items-center justify-between px-4 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-clock-history"></i> Clear Old Activity Logs (>90 days)
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <form method="POST" onsubmit="return confirm('Delete cancelled/pending orders older than 30 days?');">
                <button type="submit" name="action" value="cleanup_orders" class="w-full flex items-center justify-between px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-cart-x"></i> Clear Old Orders (>30 days)
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-3 rounded-lg text-sm">
                <i class="bi bi-exclamation-triangle"></i> Cleanup actions are permanent and cannot be undone.
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-info-circle text-blue-600"></i> System Info
            </h5>
        </div>
        <div class="p-6">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600">Admin Email:</span>
                    <span class="font-medium"><?php echo defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'Not set'; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600">Email Queue:</span>
                    <span class="font-medium"><?php 
                        $pendingEmails = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
                        echo $pendingEmails . ' pending';
                    ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600">Pending Deliveries:</span>
                    <span class="font-medium"><?php 
                        $pendingDeliveries = $db->query("SELECT COUNT(*) FROM deliveries WHERE status = 'pending'")->fetchColumn();
                        echo $pendingDeliveries . ' pending';
                    ?></span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-600">Security Logs:</span>
                    <span class="font-medium"><?php 
                        $securityLogs = $db->query("SELECT COUNT(*) FROM security_logs")->fetchColumn();
                        echo $securityLogs . ' entries';
                    ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6" x-data="{ queryInput: '', showWarning: false, isDestructive: false }" x-init="$watch('queryInput', value => { const upper = value.trim().toUpperCase(); isDestructive = upper.startsWith('DELETE') || upper.startsWith('DROP') || upper.startsWith('TRUNCATE') || upper.startsWith('UPDATE') || upper.startsWith('ALTER') || upper.startsWith('INSERT'); })">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-code-square text-primary-600"></i> SQL Query Interface
        </h5>
    </div>
    <div class="p-6">
        <form method="POST" @submit="if(isDestructive && !confirm('⚠️ WARNING: This is a destructive query that will modify your data. Are you sure you want to execute this?')) { $event.preventDefault(); }">
            <input type="hidden" name="action" value="execute_query">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Enter SQL Query:</label>
                <textarea 
                    name="query" 
                    x-model="queryInput"
                    rows="6" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                    placeholder="SELECT * FROM templates LIMIT 10;"
                    required
                ></textarea>
            </div>
            
            <div x-show="isDestructive" class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg flex items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill text-xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-bold">Destructive Query Warning!</p>
                    <p class="text-sm mt-1">This query will modify your database. Make sure you have a recent backup before proceeding.</p>
                </div>
            </div>
            
            <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="bi bi-play-fill"></i> Execute Query
            </button>
        </form>
        
        <?php if ($queryResult !== null): ?>
            <div class="mt-6 border-t border-gray-200 pt-6">
                <?php if ($queryResult['type'] === 'select' && !empty($queryResult['data'])): ?>
                    <h6 class="text-lg font-bold text-gray-900 mb-4">Query Results (<?php echo count($queryResult['data']); ?> rows)</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100">
                                    <?php foreach (array_keys($queryResult['data'][0]) as $column): ?>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-b border-gray-200"><?php echo htmlspecialchars($column); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queryResult['data'] as $row): ?>
                                    <tr class="hover:bg-gray-50">
                                        <?php foreach ($row as $value): ?>
                                            <td class="px-4 py-2 text-sm text-gray-700 border-b border-gray-200">
                                                <?php 
                                                if (is_null($value)) {
                                                    echo '<span class="text-gray-400 italic">NULL</span>';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($queryResult['type'] === 'select' && empty($queryResult['data'])): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                        <i class="bi bi-info-circle"></i> Query returned no results
                    </div>
                <?php elseif ($queryResult['type'] === 'modify'): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                        <i class="bi bi-check-circle"></i> Query executed successfully. <?php echo $queryResult['affected_rows']; ?> row(s) affected.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['query_history'])): ?>
            <div class="mt-6 border-t border-gray-200 pt-6">
                <h6 class="text-lg font-bold text-gray-900 mb-4">Recent Query History</h6>
                <div class="space-y-2">
                    <?php foreach ($_SESSION['query_history'] as $index => $history): ?>
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <code class="text-sm text-gray-800 font-mono flex-1"><?php echo htmlspecialchars($history['query']); ?></code>
                                <button 
                                    onclick="document.querySelector('textarea[name=query]').value = <?php echo htmlspecialchars(json_encode($history['query'])); ?>; window.scrollTo({top: 0, behavior: 'smooth'});"
                                    class="ml-3 px-2 py-1 bg-primary-100 text-primary-700 rounded text-xs hover:bg-primary-200 flex-shrink-0"
                                >
                                    <i class="bi bi-arrow-clockwise"></i> Reuse
                                </button>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span><i class="bi bi-clock"></i> <?php echo $history['time']; ?></span>
                                <span><i class="bi bi-speedometer"></i> <?php echo $history['execution_time']; ?>ms</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-list-ul text-primary-600"></i> Table Management
        </h5>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($tableStats as $table): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:border-primary-300 transition-colors" x-data="{ showSchema: false, schema: null }">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="bi bi-table text-primary-600"></i>
                            <?php echo htmlspecialchars($table['name']); ?>
                        </h6>
                        <span class="px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs font-semibold">
                            <?php echo number_format($table['count']); ?>
                        </span>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <button 
                            @click="
                                if (!schema) {
                                    fetch('?action=get_schema&table=<?php echo urlencode($table['name']); ?>')
                                        .then(r => r.json())
                                        .then(d => { schema = d; showSchema = true; })
                                        .catch(e => alert('Failed to load schema'));
                                } else {
                                    showSchema = !showSchema;
                                }
                            "
                            class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium transition-colors"
                        >
                            <i class="bi bi-info-circle"></i> Structure
                        </button>
                        
                        <button 
                            onclick="if(confirm('⚠️ This will DELETE ALL DATA from the <?php echo htmlspecialchars($table['name']); ?> table. This action CANNOT be undone!\n\nAre you absolutely sure?')) { document.getElementById('empty-form-<?php echo $table['name']; ?>').submit(); }"
                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition-colors"
                        >
                            <i class="bi bi-trash"></i> Empty
                        </button>
                        
                        <form id="empty-form-<?php echo $table['name']; ?>" method="POST" class="hidden">
                            <input type="hidden" name="action" value="empty_table">
                            <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($table['name']); ?>">
                        </form>
                    </div>
                    
                    <div x-show="showSchema" x-collapse class="mt-3 pt-3 border-t border-gray-300">
                        <template x-if="schema && schema.length">
                            <div class="bg-white rounded p-3 text-xs space-y-2">
                                <p class="font-semibold text-gray-700 mb-2"><i class="bi bi-list-columns"></i> Columns:</p>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="bg-gray-100 border-b">
                                                <th class="px-2 py-1 text-left font-semibold">Name</th>
                                                <th class="px-2 py-1 text-left font-semibold">Type</th>
                                                <th class="px-2 py-1 text-left font-semibold">Null</th>
                                                <th class="px-2 py-1 text-left font-semibold">Default</th>
                                                <th class="px-2 py-1 text-left font-semibold">Key</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="col in schema" :key="col.cid">
                                                <tr class="border-b hover:bg-gray-50">
                                                    <td class="px-2 py-1 font-mono" x-text="col.name"></td>
                                                    <td class="px-2 py-1 text-blue-600 font-mono" x-text="col.type"></td>
                                                    <td class="px-2 py-1" x-text="col.notnull ? 'NO' : 'YES'"></td>
                                                    <td class="px-2 py-1 text-gray-500 italic" x-text="col.dflt_value || 'NULL'"></td>
                                                    <td class="px-2 py-1">
                                                        <span x-show="col.pk" class="bg-yellow-100 text-yellow-800 px-1 py-0.5 rounded text-[10px] font-semibold">PK</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                        <template x-if="!schema">
                            <div class="bg-white rounded p-3 text-xs">
                                <p class="text-gray-600">Loading schema...</p>
                            </div>
                        </template>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
