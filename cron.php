#!/usr/bin/env php
<?php
/**
 * WebDaddy Empire - Minimized Cron Job
 * Only two essential jobs:
 * 1. Weekly backup + analytics report (every Monday at 3 AM)
 * 2. Database optimization (every Sunday at 2 AM)
 * 
 * Usage:
 *   php cron.php weekly-report   - Generate and email weekly report + DB backup
 *   php cron.php optimize        - Optimize database
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/report_generator.php';

$command = $argv[1] ?? '';

if (empty($command)) {
    echo "Usage: php cron.php [command]\n";
    echo "Commands: weekly-report, optimize\n";
    exit(1);
}

$db = getDb();
$dbPath = __DIR__ . '/database/webdaddy.db';
$backupDir = __DIR__ . '/database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

switch ($command) {
    
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
    
    default:
        echo "Unknown command: $command\n";
        echo "Valid commands: weekly-report, optimize\n";
        exit(1);
}

echo "✅ Task completed successfully!\n";
exit(0);
