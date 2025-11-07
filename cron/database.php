<?php
/**
 * Scheduled Email Cron Job
 * Sends automated emails to affiliates on a scheduled basis
 * Usage: php cron/database.php [weekly|monthly]
 */

date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$emailType = $argv[1] ?? 'weekly';

$db = getDb();
$dayOfWeek = date('N'); // 1 (Monday) through 7 (Sunday)
$dayOfMonth = date('j');

if ($emailType === 'weekly') {
    // Twice weekly emails - Tuesday (2) and Friday (5)
    if ($dayOfWeek != 2 && $dayOfWeek != 5) {
        echo "Twice-weekly emails only run on Tuesday (2) and Friday (5). Today is day $dayOfWeek.\n";
        exit(0);
    }
    
    echo "📧 Starting twice-weekly email campaign...\n";
    
    try {
        // Get all active affiliates
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
        $failedCount = 0;
        
        foreach ($affiliates as $affiliate) {
            $subject = "📊 Your Weekly Performance Update - " . date('M d, Y');
            $recentEarnings = $affiliate['recent_earnings'] ?? 0;
            $recentSales = $affiliate['recent_sales'] ?? 0;
            $recentClicks = $affiliate['recent_clicks'] ?? 0;
            
            $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:20px;">📊 Your Weekly Performance Update</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Hi {$affiliate['name']}, here's a summary of your affiliate performance for the past 7 days:
</p>
<div style="background:#ffffff; padding:20px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <div style="margin-bottom:15px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Total Clicks</p>
        <p style="margin:0; color:#1f2937; font-size:28px; font-weight:700;">{$recentClicks}</p>
    </div>
    <div style="margin-bottom:15px;">
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Successful Sales</p>
        <p style="margin:0; color:#10b981; font-size:28px; font-weight:700;">{$recentSales}</p>
    </div>
    <div>
        <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Earnings (Last 7 Days)</p>
        <p style="margin:0; color:#3b82f6; font-size:28px; font-weight:700;">₦{$recentEarnings}</p>
    </div>
</div>
<div style="background:#f0f9ff; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #3b82f6;">
    <p style="margin:0; color:#1e40af; line-height:1.6;">
        <strong>💡 Tip:</strong> Share your affiliate link more frequently to increase your earnings!
        Your unique code: <strong>{$affiliate['code']}</strong>
    </p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Keep up the great work! Log in to your dashboard to view detailed statistics and manage your account.
</p>
HTML;
            
            try {
                $emailBody = createAffiliateEmailTemplate($subject, $content, $affiliate['name']);
                sendEmail($affiliate['email'], $subject, $emailBody);
                $sentCount++;
                echo "✅ Sent weekly email to: {$affiliate['email']}\n";
            } catch (Exception $e) {
                $failedCount++;
                echo "❌ Failed to send email to {$affiliate['email']}: {$e->getMessage()}\n";
            }
        }
        
        echo "📊 Weekly email campaign complete: $sentCount sent, $failedCount failed\n";
        logActivity('weekly_emails_sent', "Sent weekly emails to $sentCount affiliates", 1);
        
    } catch (PDOException $e) {
        echo "❌ Database error: {$e->getMessage()}\n";
        exit(1);
    }
    
} elseif ($emailType === 'monthly') {
    // Monthly emails - 1st of each month
    if ($dayOfMonth != 1) {
        echo "Monthly emails only run on the 1st of the month. Today is day $dayOfMonth.\n";
        exit(0);
    }
    
    echo "📧 Starting monthly summary email campaign...\n";
    
    try {
        // Get all active affiliates with monthly stats
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
        $failedCount = 0;
        $lastMonth = date('F Y', strtotime('-1 month'));
        
        foreach ($affiliates as $affiliate) {
            $subject = "📊 Monthly Summary Report - $lastMonth";
            $monthlyEarnings = $affiliate['monthly_earnings'] ?? 0;
            $monthlySales = $affiliate['monthly_sales'] ?? 0;
            $monthlyClicks = $affiliate['monthly_clicks'] ?? 0;
            $totalEarned = $affiliate['total_earned'] ?? 0;
            $totalPending = $affiliate['total_pending'] ?? 0;
            
            $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:22px;">📊 Monthly Summary Report - $lastMonth</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Hi {$affiliate['name']}, here's your complete performance summary for $lastMonth:
</p>
<div style="background:#ffffff; padding:20px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <h3 style="color:#1f2937; font-size:16px; margin:0 0 15px 0; font-weight:600;">Last Month's Performance</h3>
    <div style="display:grid; gap:15px;">
        <div style="background:#f9fafb; padding:15px; border-radius:4px;">
            <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Total Clicks</p>
            <p style="margin:0; color:#1f2937; font-size:24px; font-weight:700;">{$monthlyClicks}</p>
        </div>
        <div style="background:#f9fafb; padding:15px; border-radius:4px;">
            <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Successful Sales</p>
            <p style="margin:0; color:#10b981; font-size:24px; font-weight:700;">{$monthlySales}</p>
        </div>
        <div style="background:#f9fafb; padding:15px; border-radius:4px;">
            <p style="margin:0 0 5px 0; color:#6b7280; font-size:13px;">Monthly Earnings</p>
            <p style="margin:0; color:#3b82f6; font-size:24px; font-weight:700;">₦{$monthlyEarnings}</p>
        </div>
    </div>
</div>
<div style="background:#ffffff; padding:20px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <h3 style="color:#1f2937; font-size:16px; margin:0 0 15px 0; font-weight:600;">All-Time Summary</h3>
    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
        <span style="color:#6b7280;">Total Earned (Paid):</span>
        <strong style="color:#10b981;">₦{$totalEarned}</strong>
    </div>
    <div style="display:flex; justify-content:space-between;">
        <span style="color:#6b7280;">Pending Commissions:</span>
        <strong style="color:#f59e0b;">₦{$totalPending}</strong>
    </div>
</div>
<div style="background:#ecfdf5; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #10b981;">
    <p style="margin:0; color:#065f46; line-height:1.6;">
        <strong>💰 Want to earn more?</strong> Keep sharing your unique affiliate link and watch your commissions grow!
    </p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Thank you for being a valued WebDaddy Empire affiliate partner!
</p>
HTML;
            
            try {
                $emailBody = createAffiliateEmailTemplate($subject, $content, $affiliate['name']);
                sendEmail($affiliate['email'], $subject, $emailBody);
                $sentCount++;
                echo "✅ Sent monthly summary to: {$affiliate['email']}\n";
            } catch (Exception $e) {
                $failedCount++;
                echo "❌ Failed to send email to {$affiliate['email']}: {$e->getMessage()}\n";
            }
        }
        
        echo "📊 Monthly email campaign complete: $sentCount sent, $failedCount failed\n";
        logActivity('monthly_emails_sent', "Sent monthly summary emails to $sentCount affiliates", 1);
        
    } catch (PDOException $e) {
        echo "❌ Database error: {$e->getMessage()}\n";
        exit(1);
    }
} else {
    echo "Invalid email type. Use 'weekly' or 'monthly'\n";
    exit(1);
}

echo "✅ Email cron job completed successfully!\n";
