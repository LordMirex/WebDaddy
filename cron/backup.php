#!/usr/bin/env php
<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$backupType = $argv[1] ?? 'weekly';

$dbPath = __DIR__ . '/../database/webdaddy.db';
$backupDir = __DIR__ . '/../database/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$dayOfWeek = date('N');
$dayOfMonth = date('j');

if ($backupType === 'weekly') {
    if ($dayOfWeek != 2 && $dayOfWeek != 5) {
        echo "Weekly backups only run on Tuesday (2) and Friday (5). Today is day $dayOfWeek.\n";
        exit(0);
    }
    
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
    
} elseif ($backupType === 'monthly') {
    if ($dayOfMonth != 1) {
        echo "Monthly backups only run on the 1st of the month. Today is day $dayOfMonth.\n";
        exit(0);
    }
    
    $backupFile = $backupDir . '/monthly_backup_' . $timestamp . '.db';
    
    if (copy($dbPath, $backupFile)) {
        echo "✅ Monthly backup created: " . basename($backupFile) . "\n";
        
        $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
        $subject = "📦 Monthly Database Backup - " . date('F Y');
        
        $backupSize = filesize($backupFile);
        $formattedSize = formatBytes($backupSize);
        
        $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">📦 Monthly Database Backup</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Your monthly database backup for <strong>{$subject}</strong> has been created successfully.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Backup Date:</strong> {$timestamp}</p>
    <p style="margin:5px 0; color:#374151;"><strong>File Size:</strong> {$formattedSize}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Database:</strong> webdaddy.db</p>
</div>
<div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:0; color:#92400e;"><strong>⚠️ Important:</strong> The backup file is attached to this email. Please download and store it securely in a safe location separate from your hosting server.</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    This is an automated monthly backup. We recommend testing the backup file periodically to ensure data integrity.
</p>
HTML;
        
        $emailBody = createEmailTemplate($subject, $content, 'Admin');
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USER') ? SMTP_USER : 'noreply@example.com';
            $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            $mail->setFrom(
                defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@example.com',
                defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME
            );
            $mail->addAddress($adminEmail);
            $mail->addAttachment($backupFile, basename($backupFile));
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $emailBody;
            
            $mail->send();
            echo "📧 Monthly backup emailed to: $adminEmail\n";
        } catch (Exception $e) {
            echo "⚠️  Email sending failed: {$mail->ErrorInfo}\n";
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
}

echo "\n✅ Backup process completed successfully!\n";
exit(0);

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
