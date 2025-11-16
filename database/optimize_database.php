<?php
/**
 * Database Optimization Script - Phase 9.3
 * Creates indexes for frequently queried columns
 * Optimizes database performance
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Only allow CLI execution or admin access
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/session.php';
    startSecureSession();
    if (!isAdmin()) {
        die('Access denied');
    }
}

$db = getDb();
$results = [];

echo "Starting database optimization...\n\n";

// Function to check if index exists
function indexExists($db, $table, $indexName) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name=?");
    $stmt->execute([$indexName]);
    return $stmt->fetchColumn() > 0;
}

// Function to create index safely
function createIndexSafe($db, $sql, $indexName, $description) {
    global $results;
    try {
        if (!indexExists($db, '', $indexName)) {
            $db->exec($sql);
            $message = "✅ Created index: $indexName - $description";
            echo $message . "\n";
            $results[] = ['status' => 'success', 'message' => $message];
        } else {
            $message = "⏭️  Index already exists: $indexName";
            echo $message . "\n";
            $results[] = ['status' => 'skipped', 'message' => $message];
        }
    } catch (Exception $e) {
        $message = "❌ Failed to create index $indexName: " . $e->getMessage();
        echo $message . "\n";
        $results[] = ['status' => 'error', 'message' => $message];
    }
}

// Templates table indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_templates_slug ON templates(slug)",
    "idx_templates_slug",
    "Fast lookup by slug (SEO URLs)"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_templates_category ON templates(category)",
    "idx_templates_category",
    "Fast filtering by category"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_templates_status ON templates(is_active)",
    "idx_templates_status",
    "Fast filtering by active status"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_templates_created ON templates(created_at DESC)",
    "idx_templates_created",
    "Fast ordering by creation date"
);

// Tools table indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_tools_category ON tools(category)",
    "idx_tools_category",
    "Fast filtering by category"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_tools_status ON tools(is_active)",
    "idx_tools_status",
    "Fast filtering by active status"
);

// Orders table indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)",
    "idx_orders_status",
    "Fast filtering by order status"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at DESC)",
    "idx_orders_created",
    "Fast ordering by creation date"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_orders_affiliate ON orders(affiliate_code)",
    "idx_orders_affiliate",
    "Fast lookup by affiliate code"
);

// Affiliates table indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_affiliates_code ON affiliates(code)",
    "idx_affiliates_code",
    "Fast lookup by affiliate code"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_affiliates_email ON affiliates(email)",
    "idx_affiliates_email",
    "Fast lookup by email"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_affiliates_status ON affiliates(status)",
    "idx_affiliates_status",
    "Fast filtering by status"
);

// Analytics tables indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_page_visits_url ON page_visits(page_url)",
    "idx_page_visits_url",
    "Fast analytics by URL"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_page_visits_session ON page_visits(session_id)",
    "idx_page_visits_session",
    "Fast lookup by session"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_page_visits_created ON page_visits(created_at DESC)",
    "idx_page_visits_created",
    "Fast ordering by visit time"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_template_views_template ON template_views(template_id)",
    "idx_template_views_template",
    "Fast analytics by template"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_template_views_created ON template_views(created_at DESC)",
    "idx_template_views_created",
    "Fast ordering by view time"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_search_queries_query ON search_queries(query)",
    "idx_search_queries_query",
    "Fast analytics by search term"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_search_queries_created ON search_queries(created_at DESC)",
    "idx_search_queries_created",
    "Fast ordering by search time"
);

// Session summary indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_session_summary_session ON session_summary(session_id)",
    "idx_session_summary_session",
    "Fast lookup by session ID"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_session_summary_first_visit ON session_summary(first_visit DESC)",
    "idx_session_summary_first_visit",
    "Fast ordering by first visit"
);

// Affiliate actions indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_affiliate_actions_affiliate ON affiliate_actions(affiliate_id)",
    "idx_affiliate_actions_affiliate",
    "Fast lookup by affiliate"
);

createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_affiliate_actions_created ON affiliate_actions(created_at DESC)",
    "idx_affiliate_actions_created",
    "Fast ordering by action time"
);

// Announcements indexes
createIndexSafe(
    $db,
    "CREATE INDEX IF NOT EXISTS idx_announcements_created ON announcements(created_at DESC)",
    "idx_announcements_created",
    "Fast ordering by creation date"
);

echo "\n";
echo "Running ANALYZE to update statistics...\n";
try {
    $db->exec("ANALYZE");
    echo "✅ Database statistics updated\n";
    $results[] = ['status' => 'success', 'message' => 'Database statistics updated'];
} catch (Exception $e) {
    echo "❌ ANALYZE failed: " . $e->getMessage() . "\n";
    $results[] = ['status' => 'error', 'message' => 'ANALYZE failed: ' . $e->getMessage()];
}

echo "\n";
echo "Running VACUUM to optimize database file...\n";
try {
    $db->exec("VACUUM");
    echo "✅ Database optimized\n";
    $results[] = ['status' => 'success', 'message' => 'Database file optimized'];
} catch (Exception $e) {
    echo "❌ VACUUM failed: " . $e->getMessage() . "\n";
    $results[] = ['status' => 'error', 'message' => 'VACUUM failed: ' . $e->getMessage()];
}

// Summary
echo "\n===========================================\n";
echo "DATABASE OPTIMIZATION COMPLETE\n";
echo "===========================================\n";

$successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
$skipCount = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
$errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));

echo "✅ Success: $successCount\n";
echo "⏭️  Skipped: $skipCount\n";
echo "❌ Errors: $errorCount\n";
echo "\n";

if (php_sapi_name() !== 'cli') {
    echo '<pre>';
    foreach ($results as $result) {
        echo $result['message'] . "\n";
    }
    echo '</pre>';
}
