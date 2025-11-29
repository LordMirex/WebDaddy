#!/usr/bin/env php
<?php
// WebDaddy Empire - Automated Cron Jobs
// 
// CRITICAL DELIVERY JOBS (run frequently):
// 1. process-pending-deliveries  - Every 20-30 min: Find pending tool deliveries with new files, send emails
// 2. process-email-queue         - Every 5 min: Send queued emails
// 3. process-retries             - Every 15 min: Retry failed deliveries
// 
// MAINTENANCE JOBS (run less frequently):
// 4. cleanup-security            - Every hour: Clean old security logs
// 5. optimize                    - Weekly (Sunday 2 AM): Database optimization
// 6. weekly-report               - Weekly (Monday 3 AM): Generate analytics report
// 
// cPanel cron configuration (copy these to cPanel):
// */20 * * * * php /path/to/cron.php process-pending-deliveries
// */5 * * * * php /path/to/cron.php process-email-queue
// */15 * * * * php /path/to/cron.php process-retries
// 0 * * * * php /path/to/cron.php cleanup-security
// 0 2 * * 0 php /path/to/cron.php optimize
// 0 3 * * 1 php /path/to/cron.php weekly-report

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/report_generator.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/delivery.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/email_queue.php';
require_once __DIR__ . '/includes/tool_files.php';

$command = $argv[1] ?? '';

if (empty($command)) {
    echo "Usage: php cron.php [command]\n";
    echo "\nCritical Delivery Jobs (run frequently):\n";
    echo "  process-pending-deliveries  - Find pending deliveries with new files, send emails\n";
    echo "  process-email-queue         - Send all queued emails\n";
    echo "  process-retries             - Retry failed deliveries\n";
    echo "\nMaintenance Jobs:\n";
    echo "  cleanup-security            - Clean old security logs\n";
    echo "  optimize                    - Optimize database\n";
    echo "  weekly-report               - Generate weekly analytics report\n";
    exit(1);
}

$db = getDb();
$dbPath = __DIR__ . '/database/webdaddy.db';
$backupDir = __DIR__ . '/database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

switch ($command) {
    
    case 'process-pending-deliveries':
        echo "📦 Processing pending tool deliveries...\n";
        
        try {
            $result = processAllPendingToolDeliveries();
            
            echo "✅ Pending deliveries processed:\n";
            echo "   - Tools scanned: {$result['tools_scanned']}\n";
            echo "   - Pending deliveries found: {$result['pending_found']}\n";
            echo "   - Emails sent: {$result['emails_sent']}\n";
            echo "   - Update notifications: {$result['updates_sent']}\n";
            
            if (!empty($result['errors'])) {
                echo "   - Errors: " . count($result['errors']) . "\n";
                foreach ($result['errors'] as $error) {
                    echo "     ⚠️  $error\n";
                }
            }
        } catch (Exception $e) {
            error_log("Pending deliveries processing failed: " . $e->getMessage());
            echo "❌ Pending deliveries processing failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'process-email-queue':
        echo "📧 Processing email queue...\n";
        
        try {
            // Process all pending emails
            $stmt = $db->query("
                SELECT COUNT(*) as count FROM email_queue 
                WHERE status = 'pending' AND attempts < 5
            ");
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);
            $pendingCount = $pending['count'] ?? 0;
            
            if ($pendingCount > 0) {
                echo "Found $pendingCount pending emails. Processing...\n";
                processEmailQueue();
                
                // Check results
                $stmt = $db->query("
                    SELECT status, COUNT(*) as count FROM email_queue 
                    WHERE status IN ('sent', 'retry', 'failed')
                    GROUP BY status
                ");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    echo "  ✓ {$row['count']} emails {$row['status']}\n";
                }
                
                echo "✅ Email queue processing complete!\n";
            } else {
                echo "✓ No pending emails to process\n";
            }
        } catch (Exception $e) {
            error_log("Email queue processing failed: " . $e->getMessage());
            echo "❌ Email queue processing failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'weekly-report':
        // Only run on Mondays
        $dayOfWeek = date('N'); // 1=Monday, 7=Sunday
        if ($dayOfWeek != 1) {
            echo "Weekly reports run on Mondays. Today is day $dayOfWeek.\n";
            exit(0);
        }
        
        echo "📊 Generating weekly report...\n";
        
        try {
            // Generate report
            $report = ReportGenerator::generateWeeklyReport($db);
            
            if (isset($report['error'])) {
                throw new Exception($report['error']);
            }
            
            // Create ZIP with report + DB backup
            $zipPath = ReportGenerator::createReportZip($report, $dbPath);
            echo "✅ Report and backup created: " . basename($zipPath) . "\n";
            
            // Send via email
            $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
            $subject = "📊 WebDaddy Empire - Weekly Report " . date('Y-m-d');
            
            $mailContent = <<<HTML
<h2 style="color:#1e3a8a;">📊 Weekly Report Summary</h2>
<p><strong>Period:</strong> {$report['period']}</p>

<h3 style="color:#2563eb;">💰 Financial</h3>
<ul>
  <li><strong>This Week's Profit:</strong> ₦{$report['profit_this_week']}</li>
  <li><strong>All-Time Profit:</strong> ₦{$report['profit_all_time']}</li>
</ul>

<h3 style="color:#2563eb;">📈 Traffic</h3>
<ul>
  <li><strong>Template Views (Week):</strong> {$report['template_views_week']}</li>
  <li><strong>Tool Views (Week):</strong> {$report['tool_views_week']}</li>
  <li><strong>Orders (Week):</strong> {$report['orders_this_week']}</li>
</ul>

<div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:0;"><strong>📎 Attached:</strong> Detailed report (HTML) + Database backup</p>
</div>
HTML;
            
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
            $mail->Encoding = 'base64';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($adminEmail);
            $mail->addAttachment($zipPath, 'WebDaddy_Report_' . date('Y-m-d') . '.zip');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $emailBody;
            $mail->send();
            
            echo "📧 Report sent to: $adminEmail\n";
            
            // Cleanup
            unlink($zipPath);
            
        } catch (Exception $e) {
            echo "❌ Report generation failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'optimize':
        // Run database optimization weekly
        echo "🔧 Optimizing database...\n";
        
        try {
            $sizeBefore = filesize($dbPath);
            
            $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $db->exec('VACUUM');
            $db->exec('ANALYZE');
            $db->exec('PRAGMA optimize');
            
            clearstatcache();
            $sizeAfter = filesize($dbPath);
            $saved = $sizeBefore - $sizeAfter;
            
            echo "✅ Database optimized! Space saved: " . formatBytes($saved) . "\n";
            
        } catch (PDOException $e) {
            echo "❌ Optimization failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'process-retries':
        // Process failed delivery retries
        echo "🔄 Processing delivery retries...\n";
        
        try {
            $result = processDeliveryRetries();
            echo "✅ Processed {$result['processed']} delivery retries\n";
            if ($result['successful'] > 0) {
                echo "   - Successful: {$result['successful']}\n";
            }
            if ($result['failed'] > 0) {
                echo "   - Failed (will retry later): {$result['failed']}\n";
            }
        } catch (Exception $e) {
            echo "❌ Retry processing failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'cleanup-security':
        // Cleanup old security logs and rate limit entries
        echo "🧹 Cleaning up security logs...\n";
        
        try {
            // Clean rate limits older than 1 hour
            $stmt = $db->prepare("DELETE FROM webhook_rate_limits WHERE request_time < ?");
            $stmt->execute([time() - 3600]);
            $rateDeleted = $stmt->rowCount();
            
            // Clean security logs older than 30 days
            $stmt = $db->query("DELETE FROM security_logs WHERE created_at < datetime('now', '-30 days', '+1 hour')");
            $logsDeleted = $stmt->rowCount();
            
            echo "✅ Cleanup complete:\n";
            echo "   - Rate limit entries removed: $rateDeleted\n";
            echo "   - Old security logs removed: $logsDeleted\n";
            
        } catch (Exception $e) {
            echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    default:
        echo "Unknown command: $command\n";
        echo "Valid commands: process-pending-deliveries, process-email-queue, process-retries, cleanup-security, optimize, weekly-report\n";
        exit(1);
}

echo "✅ Task completed successfully!\n";
exit(0);
