<?php
/**
 * ONE-TIME MIGRATION: Recalculate Affiliate Commissions
 * 
 * This script recalculates all affiliate commissions based on the NEW logic:
 * - Commission = 30% of DISCOUNTED PRICE (final_amount), not original price
 * - Updates all affiliate balances to reflect correct amounts
 * - Backfills final_amount in sales records where missing
 * 
 * BEFORE RUNNING: Ensure database backup exists at database/full_backup_2025_11_06.db
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

echo "=== AFFILIATE COMMISSION MIGRATION ===\n\n";
echo "This will recalculate all affiliate commissions using the NEW formula:\n";
echo "Commission = 30% of FINAL AMOUNT (discounted price customer paid)\n\n";

$affiliateCommissionRate = AFFILIATE_COMMISSION_RATE; // 0.30

try {
    $db->beginTransaction();
    
    // Step 1: Backfill final_amount in sales where it's NULL
    echo "Step 1: Backfilling final_amount in sales records...\n";
    $stmt = $db->query("
        UPDATE sales 
        SET final_amount = amount_paid
        WHERE final_amount IS NULL
    ");
    $backfilled = $stmt->rowCount();
    echo "  → Backfilled $backfilled records\n\n";
    
    // Step 2: Backfill original_price from templates
    echo "Step 2: Backfilling original_price from templates...\n";
    $stmt = $db->query("
        UPDATE sales 
        SET original_price = (
            SELECT t.price 
            FROM pending_orders po
            JOIN templates t ON po.template_id = t.id
            WHERE po.id = sales.pending_order_id
        )
        WHERE original_price IS NULL
    ");
    $backfilled2 = $stmt->rowCount();
    echo "  → Backfilled $backfilled2 records\n\n";
    
    // Step 3: Calculate correct discount_amount where missing
    echo "Step 3: Calculating discount amounts...\n";
    $stmt = $db->query("
        UPDATE sales 
        SET discount_amount = COALESCE(original_price, 0) - COALESCE(final_amount, amount_paid)
        WHERE discount_amount IS NULL OR discount_amount = 0
    ");
    $calculated = $stmt->rowCount();
    echo "  → Calculated discount for $calculated records\n\n";
    
    // Step 4: Recalculate ALL commission amounts based on final_amount
    echo "Step 4: Recalculating commission amounts (30% of final_amount)...\n";
    $stmt = $db->query("
        UPDATE sales 
        SET commission_amount = COALESCE(final_amount, amount_paid) * $affiliateCommissionRate
        WHERE affiliate_id IS NOT NULL
    ");
    $recalculated = $stmt->rowCount();
    echo "  → Recalculated $recalculated commission records\n\n";
    
    // Step 5: Rebuild affiliate balances from scratch
    echo "Step 5: Rebuilding affiliate balances from sales data...\n";
    
    // First, reset all affiliate balances
    $db->query("UPDATE affiliates SET commission_earned = 0, commission_pending = 0");
    
    // Recalculate commission_earned from all sales
    $db->query("
        UPDATE affiliates 
        SET commission_earned = COALESCE((
            SELECT SUM(commission_amount) 
            FROM sales 
            WHERE sales.affiliate_id = affiliates.id
        ), 0)
    ");
    
    // Recalculate commission_pending (earned - paid)
    $db->query("
        UPDATE affiliates 
        SET commission_pending = commission_earned - commission_paid
    ");
    
    echo "  → All affiliate balances rebuilt\n\n";
    
    // Step 6: Show summary
    echo "Step 6: Migration Summary\n";
    echo "========================\n\n";
    
    $stmt = $db->query("
        SELECT 
            a.id,
            u.name,
            a.code,
            COUNT(s.id) as total_sales,
            SUM(s.commission_amount) as total_commission,
            a.commission_earned,
            a.commission_pending,
            a.commission_paid
        FROM affiliates a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN sales s ON s.affiliate_id = a.id
        GROUP BY a.id, u.name, a.code, a.commission_earned, a.commission_pending, a.commission_paid
    ");
    
    $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($affiliates as $aff) {
        echo "Affiliate: " . $aff['name'] . " (" . $aff['code'] . ")\n";
        echo "  Sales: " . $aff['total_sales'] . "\n";
        echo "  Total Earned: ₦" . number_format($aff['commission_earned'], 2) . "\n";
        echo "  Pending: ₦" . number_format($aff['commission_pending'], 2) . "\n";
        echo "  Paid Out: ₦" . number_format($aff['commission_paid'], 2) . "\n";
        echo "\n";
    }
    
    // Commit all changes
    $db->commit();
    
    echo "\n✅ MIGRATION COMPLETED SUCCESSFULLY!\n\n";
    echo "All affiliate commissions have been recalculated based on discounted prices.\n";
    echo "Affiliates will now see accurate commission amounts.\n\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n\n";
    echo "Database rolled back to previous state.\n";
    echo "Backup is available at: database/full_backup_2025_11_06.db\n\n";
    exit(1);
}
