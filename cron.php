#!/usr/bin/env php
<?php
/**
 * WebDaddy Empire - Cron Job Handler
 * Simple CLI script for scheduled tasks
 * 
 * Usage:
 *   php cron.php backup-monthly   - Create monthly backup + email to admin (keeps 12)
 *   php cron.php cleanup          - Clean old logs and cancelled orders
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$command = $argv[1] ?? '';

if (empty($command)) {
    echo "Usage: php cron.php [command]\n";
    echo "Commands: backup-monthly, cleanup\n";
    exit(1);
}

$db = getDb();
$dbPath = __DIR__ . '/database/webdaddy.db';
$backupDir = __DIR__ . '/database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

switch ($command) {
    
    case 'backup-monthly':
        $dayOfMonth = date('j');
        if ($dayOfMonth != 1) {
            echo "Monthly backups run on the 1st. Today is day $dayOfMonth.\n";
            exit(0);
        }
        
        echo "📦 Starting monthly backup...\n";
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/monthly_backup_' . $timestamp . '.db';
        
        if (copy($dbPath, $backupFile)) {
            echo "✅ Monthly backup created: " . basename($backupFile) . "\n";
            
            $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
            $subject = "📦 Monthly Database Backup - " . date('F Y');
            $backupSize = formatBytes(filesize($backupFile));
            
            $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">📦 Monthly Database Backup</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Your monthly database backup has been created successfully.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <p style="margin:5px 0; color:#374151;"><strong>Backup Date:</strong> {$timestamp}</p>
    <p style="margin:5px 0; color:#374151;"><strong>File Size:</strong> {$backupSize}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Database:</strong> webdaddy.db</p>
</div>
<div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:0; color:#92400e;"><strong>⚠️ Important:</strong> Download this backup and store it securely in a safe location separate from your hosting server.</p>
</div>
HTML;
            
            $emailBody = createEmailTemplate($subject, $content, 'Admin');
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
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
                $mail->addAttachment($backupFile, basename($backupFile));
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $emailBody;
                $mail->send();
                echo "📧 Monthly backup emailed to: $adminEmail\n";
            } catch (Exception $e) {
                echo "⚠️  Email failed: {$mail->ErrorInfo}\n";
            }
            
            $monthlyBackups = glob($backupDir . '/monthly_backup_*.db');
            if (count($monthlyBackups) > 12) {
                usort($monthlyBackups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                $toDelete = array_slice($monthlyBackups, 0, count($monthlyBackups) - 12);
                foreach ($toDelete as $file) {
                    unlink($file);
                    echo "🗑️  Deleted old monthly backup: " . basename($file) . "\n";
                }
            }
            echo "📊 Monthly backups retained: " . min(count($monthlyBackups), 12) . "/12\n";
        } else {
            echo "❌ Monthly backup failed!\n";
            exit(1);
        }
        break;
    
    case 'cleanup':
        echo "🧹 Starting database cleanup...\n";
        
        try {
            $stmt = $db->exec("DELETE FROM activity_logs WHERE created_at < date('now', '-90 days')");
            echo "✅ Deleted old activity logs (>90 days): $stmt rows\n";
            
            $stmt = $db->exec("DELETE FROM pending_orders WHERE status IN ('cancelled', 'pending') AND created_at < date('now', '-30 days')");
            echo "✅ Deleted old pending orders (>30 days): $stmt rows\n";
            
            logActivity('cron_cleanup', 'Automated cleanup completed', null);
        } catch (PDOException $e) {
            echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    default:
        echo "Unknown command: $command\n";
        echo "Valid commands: backup-monthly, cleanup\n";
        exit(1);
}

echo "\n✅ Task completed successfully!\n";
exit(0);
