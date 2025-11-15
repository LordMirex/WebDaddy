<?php
/**
 * Migration: Fix Bounce Tracking
 * 
 * This migration backfills the is_bounce field for all existing sessions
 * to ensure accurate bounce rate statistics.
 * 
 * - Sets is_bounce=1 for sessions with total_pages=1 (single-page visits)
 * - Sets is_bounce=0 for sessions with total_pages>1 (multi-page visits)
 * 
 * Run this once after deploying the analytics.php bounce tracking fix.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

echo "Starting bounce tracking migration...\n\n";

try {
    $db = getDb();
    
    $stmt = $db->query("SELECT COUNT(*) FROM session_summary WHERE total_pages = 1 AND is_bounce = 0");
    $affectedSingle = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM session_summary WHERE total_pages > 1 AND is_bounce = 1");
    $affectedMulti = $stmt->fetchColumn();
    
    echo "Sessions to update:\n";
    echo "  - Single-page visits (set is_bounce=1): $affectedSingle\n";
    echo "  - Multi-page visits (set is_bounce=0): $affectedMulti\n\n";
    
    if ($affectedSingle > 0 || $affectedMulti > 0) {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE session_summary SET is_bounce = 1, updated_at = CURRENT_TIMESTAMP WHERE total_pages = 1");
        $stmt->execute();
        $updated1 = $stmt->rowCount();
        
        $stmt = $db->prepare("UPDATE session_summary SET is_bounce = 0, updated_at = CURRENT_TIMESTAMP WHERE total_pages > 1");
        $stmt->execute();
        $updated2 = $stmt->rowCount();
        
        $db->commit();
        
        echo "✅ Migration completed successfully!\n";
        echo "  - Updated $updated1 single-page sessions to is_bounce=1\n";
        echo "  - Updated $updated2 multi-page sessions to is_bounce=0\n\n";
        
        $stmt = $db->query("SELECT COUNT(*) as total, SUM(is_bounce) as bounces FROM session_summary");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $bounceRate = $stats['total'] > 0 ? round(($stats['bounces'] / $stats['total']) * 100, 1) : 0;
        
        echo "New bounce statistics:\n";
        echo "  - Total sessions: " . number_format($stats['total']) . "\n";
        echo "  - Bounce sessions: " . number_format($stats['bounces']) . "\n";
        echo "  - Bounce rate: " . $bounceRate . "%\n";
    } else {
        echo "✅ No updates needed - bounce tracking is already correct!\n";
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete!\n";
