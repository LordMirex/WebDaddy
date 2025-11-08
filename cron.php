#!/usr/bin/env php
<?php
/**
 * WebDaddy Empire - Cron Job Handler
 * Simple CLI script for scheduled tasks
 * 
 * Usage:
 *   php cron.php backup-daily     - Create daily database backup
 *   php cron.php backup-weekly    - Create weekly database backup (keeps 4)
 *   php cron.php backup-monthly   - Create monthly backup + email to admin (keeps 12)
 *   php cron.php cleanup          - Clean old logs and cancelled orders
 *   php cron.php emails-weekly    - Send weekly performance emails to affiliates
 *   php cron.php emails-monthly   - Send monthly summary emails to affiliates
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
    echo "Commands: backup-daily, backup-weekly, backup-monthly, cleanup, emails-weekly, emails-monthly\n";
    exit(1);
}

$db = getDb();
$dbPath = __DIR__ . '/database/webdaddy.db';
$backupDir = __DIR__ . '/database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

switch ($command) {
    
    case 'backup-daily':
        echo "📦 Starting daily backup...\n";
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/daily_backup_' . $timestamp . '.db';
        
        if (copy($dbPath, $backupFile)) {
            echo "✅ Daily backup created: " . basename($backupFile) . "\n";
            
            $dailyBackups = glob($backupDir . '/daily_backup_*.db');
            if (count($dailyBackups) > 7) {
                usort($dailyBackups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                $toDelete = array_slice($dailyBackups, 0, count($dailyBackups) - 7);
                foreach ($toDelete as $file) {
                    unlink($file);
                    echo "🗑️  Deleted old daily backup: " . basename($file) . "\n";
                }
            }
            echo "📊 Daily backups retained: " . min(count($dailyBackups), 7) . "/7\n";
        } else {
            echo "❌ Daily backup failed!\n";
            exit(1);
        }
        break;
    
    case 'backup-weekly':
        $dayOfWeek = date('N');
        if ($dayOfWeek != 2) {
            echo "Weekly backups run on Tuesdays. Today is day $dayOfWeek.\n";
            exit(0);
        }
        
        echo "📦 Starting weekly backup...\n";
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/weekly_backup_' . $timestamp . '.db';
        
        if (copy($dbPath, $backupFile)) {
            echo "✅ Weekly backup created: " . basename($backupFile) . "\n";
            
            $weeklyBackups = glob($backupDir . '/weekly_backup_*.db');
            if (count($weeklyBackups) > 4) {
                usort($weeklyBackups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                $toDelete = array_slice($weeklyBackups, 0, count($weeklyBackups) - 4);
                foreach ($toDelete as $file) {
                    unlink($file);
                    echo "🗑️  Deleted old weekly backup: " . basename($file) . "\n";
                }
            }
            echo "📊 Weekly backups retained: " . min(count($weeklyBackups), 4) . "/4\n";
        } else {
            echo "❌ Weekly backup failed!\n";
            exit(1);
        }
        break;
    
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
            
            logActivity('cron_cleanup', 'Automated cleanup completed', 1);
        } catch (PDOException $e) {
            echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'emails-weekly':
        $dayOfWeek = date('N');
        if ($dayOfWeek != 2 && $dayOfWeek != 5) {
            echo "Weekly emails run on Tuesday (2) and Friday (5). Today is day $dayOfWeek.\n";
            exit(0);
        }
        
        echo "📧 Sending weekly affiliate emails...\n";
        
        try {
            $stmt = $db->query("
                SELECT a.id, u.name, u.email, a.code,
                       (SELECT COUNT(*) FROM sales WHERE affiliate_id = a.id AND created_at >= date('now', '-7 days')) as recent_sales,
                       (SELECT SUM(commission_amount) FROM sales WHERE affiliate_id = a.id AND created_at >= date('now', '-7 days')) as recent_earnings,
                       (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = a.id AND clicked_at >= date('now', '-7 days')) as recent_clicks
                FROM affiliates a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'active' AND u.status = 'active'
            ");
            $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sentCount = 0;
            foreach ($affiliates as $aff) {
                $subject = "📊 Your Weekly Performance - " . date('M d, Y');
                $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:20px;">📊 Your Weekly Performance</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Hi {$aff['name']}, here's your affiliate performance for the past 7 days:
</p>
<div style="background:#ffffff; padding:20px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <div style="margin-bottom:15px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Total Clicks</p>
        <p style="margin:0; color:#1f2937; font-size:28px; font-weight:700;">{$aff['recent_clicks']}</p>
    </div>
    <div style="margin-bottom:15px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Successful Sales</p>
        <p style="margin:0; color:#10b981; font-size:28px; font-weight:700;">{$aff['recent_sales']}</p>
    </div>
    <div>
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Earnings (Last 7 Days)</p>
        <p style="margin:0; color:#3b82f6; font-size:28px; font-weight:700;">₦{$aff['recent_earnings']}</p>
    </div>
</div>
<div style="background:#f0f9ff; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #3b82f6;">
    <p style="margin:0; color:#1e40af; line-height:1.6;">
        <strong>💡 Tip:</strong> Share your affiliate link more frequently to boost earnings!
        Your code: <strong>{$aff['code']}</strong>
    </p>
</div>
HTML;
                
                $emailBody = createAffiliateEmailTemplate($subject, $content, $aff['name']);
                if (sendEmail($aff['email'], $subject, $emailBody)) {
                    $sentCount++;
                    echo "✅ Sent to: {$aff['email']}\n";
                }
            }
            echo "📊 Weekly emails sent: $sentCount\n";
            logActivity('weekly_emails_sent', "Sent weekly emails to $sentCount affiliates", 1);
        } catch (PDOException $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    case 'emails-monthly':
        $dayOfMonth = date('j');
        if ($dayOfMonth != 1) {
            echo "Monthly emails run on the 1st. Today is day $dayOfMonth.\n";
            exit(0);
        }
        
        echo "📧 Sending monthly affiliate emails...\n";
        
        try {
            $lastMonth = date('F Y', strtotime('-1 month'));
            $stmt = $db->query("
                SELECT a.id, u.name, u.email, a.code,
                       (SELECT COUNT(*) FROM sales WHERE affiliate_id = a.id AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', '-1 month')) as monthly_sales,
                       (SELECT SUM(commission_amount) FROM sales WHERE affiliate_id = a.id AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', '-1 month')) as monthly_earnings,
                       (SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = a.id AND strftime('%Y-%m', clicked_at) = strftime('%Y-%m', 'now', '-1 month')) as monthly_clicks,
                       (SELECT SUM(commission_amount) FROM sales WHERE affiliate_id = a.id AND commission_status = 'earned') as total_earned,
                       (SELECT SUM(commission_amount) FROM sales WHERE affiliate_id = a.id AND commission_status = 'pending') as total_pending
                FROM affiliates a
                JOIN users u ON a.user_id = u.id
                WHERE a.status = 'active' AND u.status = 'active'
            ");
            $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sentCount = 0;
            foreach ($affiliates as $aff) {
                $subject = "📊 Monthly Summary - $lastMonth";
                $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:22px;">📊 Monthly Summary - $lastMonth</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Hi {$aff['name']}, here's your complete performance summary for $lastMonth:
</p>
<div style="background:#ffffff; padding:20px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <h3 style="color:#1f2937; font-size:16px; margin:0 0 15px 0;">Last Month</h3>
    <div style="margin-bottom:10px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Clicks</p>
        <p style="margin:0; color:#1f2937; font-size:24px; font-weight:700;">{$aff['monthly_clicks']}</p>
    </div>
    <div style="margin-bottom:10px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Sales</p>
        <p style="margin:0; color:#10b981; font-size:24px; font-weight:700;">{$aff['monthly_sales']}</p>
    </div>
    <div>
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Earnings</p>
        <p style="margin:0; color:#3b82f6; font-size:24px; font-weight:700;">₦{$aff['monthly_earnings']}</p>
    </div>
</div>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <h3 style="color:#1f2937; font-size:16px; margin:0 0 10px 0;">All-Time</h3>
    <p style="margin:5px 0; color:#374151;">Earned: <strong style="color:#10b981;">₦{$aff['total_earned']}</strong></p>
    <p style="margin:5px 0; color:#374151;">Pending: <strong style="color:#f59e0b;">₦{$aff['total_pending']}</strong></p>
</div>
HTML;
                
                $emailBody = createAffiliateEmailTemplate($subject, $content, $aff['name']);
                if (sendEmail($aff['email'], $subject, $emailBody)) {
                    $sentCount++;
                    echo "✅ Sent to: {$aff['email']}\n";
                }
            }
            echo "📊 Monthly emails sent: $sentCount\n";
            logActivity('monthly_emails_sent', "Sent monthly emails to $sentCount affiliates", 1);
        } catch (PDOException $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
    
    default:
        echo "Unknown command: $command\n";
        echo "Valid commands: backup-daily, backup-weekly, backup-monthly, cleanup, emails-weekly, emails-monthly\n";
        exit(1);
}

echo "\n✅ Task completed successfully!\n";
exit(0);
