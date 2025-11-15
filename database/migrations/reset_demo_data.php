<?php
/**
 * Database Cleanup Script
 * 
 * This script safely removes all transactional data while preserving:
 * - Templates
 * - Tools  
 * - Admin user
 * - Settings
 * 
 * Run this to start fresh with a clean database.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          WebDaddy Empire - Database Cleanup Script          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = getDb();
    
    // Create backup first
    echo "📦 Creating backup...\n";
    $backupFile = 'database/backups/pre-cleanup-backup-' . date('Y-m-d_His') . '.db';
    $currentDb = 'database/webdaddy.db';
    
    if (file_exists($currentDb)) {
        copy($currentDb, $backupFile);
        echo "   ✅ Backup created: $backupFile\n\n";
    }
    
    echo "🗑️  Clearing transactional data...\n\n";
    
    $db->beginTransaction();
    
    // Tables to truncate (in dependency order)
    $tablesToClear = [
        'activity_logs' => 'Activity Logs',
        'announcement_emails' => 'Announcement Emails',
        'announcement' => 'Announcements',
        'page_interactions' => 'Page Interactions',
        'page_visits' => 'Page Visits',
        'session_summary' => 'Session Summary',
        'tool_sessions' => 'Tool Sessions',
        'cart_items' => 'Cart Items',
        'withdrawal_requests' => 'Withdrawal Requests',
        'sales' => 'Sales Records',
        'order_items' => 'Order Items',
        'pending_orders' => 'Pending Orders',
        'affiliates' => 'Affiliates',
        'support_tickets' => 'Support Tickets',
        'ticket_replies' => 'Ticket Replies'
    ];
    
    foreach ($tablesToClear as $table => $label) {
        try {
            // Check if table exists
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            if ($stmt->fetch()) {
                $stmt = $db->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $db->exec("DELETE FROM $table");
                    $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
                    echo "   ✅ Cleared $label ($count records)\n";
                } else {
                    echo "   ⏭️  Skipped $label (already empty)\n";
                }
            }
        } catch (PDOException $e) {
            echo "   ⚠️  Warning: Could not clear $table: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🔧 Updating settings...\n";
    
    // Update WhatsApp number
    $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'whatsapp_number'");
    $stmt->execute(['+2349132672126']);
    echo "   ✅ WhatsApp number updated to +2349132672126\n";
    
    // Reset commission rate to 30%
    $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'commission_rate'");
    $stmt->execute(['0.30']);
    echo "   ✅ Commission rate set to 30%\n";
    
    $db->commit();
    
    echo "\n📊 Final Status:\n";
    
    // Count remaining data
    $templates = $db->query("SELECT COUNT(*) FROM templates")->fetchColumn();
    $tools = $db->query("SELECT COUNT(*) FROM tools")->fetchColumn();
    $users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    
    echo "   ✅ Templates preserved: $templates\n";
    echo "   ✅ Tools preserved: $tools\n";
    echo "   ✅ Admin users preserved: $users\n";
    
    echo "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                   ✅ Cleanup Successful!                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "✨ Your database is now fresh and ready!\n";
    echo "📦 Backup saved to: $backupFile\n\n";
    
    echo "Next steps:\n";
    echo "1. Login to admin panel\n";
    echo "2. Verify templates and tools are intact\n";
    echo "3. Check that WhatsApp number is updated (+2349132672126)\n";
    echo "4. Start adding new orders!\n\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ Cleanup failed: " . $e->getMessage() . "\n";
    echo "Your database has not been modified.\n\n";
    exit(1);
}
