#!/usr/bin/env php
<?php
/**
 * Database Migration Runner
 * Safely applies schema changes to SQLite database
 * 
 * Usage: php run_migration.php 001_add_media_upload_fields.sql
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

// Check CLI mode
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Get migration file from argument
$migrationFile = $argv[1] ?? null;

if (!$migrationFile) {
    echo "Usage: php run_migration.php <migration_file.sql>\n";
    echo "Example: php run_migration.php 001_add_media_upload_fields.sql\n";
    exit(1);
}

$migrationPath = __DIR__ . '/' . $migrationFile;

if (!file_exists($migrationPath)) {
    echo "Error: Migration file not found: $migrationPath\n";
    exit(1);
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║           DATABASE MIGRATION TOOL                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Backup database
echo "[1/4] Creating database backup...\n";

$dbPath = __DIR__ . '/../webdaddy.db';
$backupDir = __DIR__ . '/../backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupFile = $backupDir . '/webdaddy_backup_' . date('Y-m-d_His') . '.db';

if (!copy($dbPath, $backupFile)) {
    echo "✗ Failed to create backup!\n";
    exit(1);
}

echo "✓ Backup created: $backupFile\n";
echo "  Size: " . formatBytes(filesize($backupFile)) . "\n\n";

// Step 2: Read migration SQL
echo "[2/4] Reading migration file...\n";
$sql = file_get_contents($migrationPath);

// Remove comments and split into statements
$statements = array_filter(
    array_map('trim', 
        preg_split('/;[\s]*\n/', 
            preg_replace('/--.*$/m', '', $sql)
        )
    ),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^(PRAGMA|CREATE INDEX|ALTER TABLE|CREATE TABLE)/i', $stmt) === false;
    }
);

echo "✓ Found " . count($statements) . " SQL statements\n\n";

// Step 3: Apply migration
echo "[3/4] Applying migration...\n";

try {
    $db = getDb();
    $db->beginTransaction();
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $index => $statement) {
        // Skip empty statements
        if (trim($statement) === '') continue;
        
        try {
            $db->exec($statement);
            $success++;
            
            // Show first 60 chars of statement
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 60);
            echo "  ✓ Statement " . ($index + 1) . ": " . $preview . "...\n";
            
        } catch (PDOException $e) {
            $failed++;
            echo "  ✗ Statement " . ($index + 1) . " failed: " . $e->getMessage() . "\n";
            
            // Show the statement that failed
            echo "    SQL: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    if ($failed > 0) {
        echo "\n⚠ Migration had errors. Rolling back...\n";
        $db->rollBack();
        echo "✓ Rollback complete. Database unchanged.\n";
        echo "✓ Backup available at: $backupFile\n";
        exit(1);
    }
    
    $db->commit();
    echo "\n✓ All statements executed successfully\n\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "✓ Database rolled back to previous state\n";
    echo "✓ Backup available at: $backupFile\n";
    exit(1);
}

// Step 4: Verify migration
echo "[4/4] Verifying migration...\n";

try {
    // Check if new columns exist in templates table
    $result = $db->query("PRAGMA table_info(templates)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($result, 'name');
    
    $requiredColumns = ['thumbnail_source', 'thumbnail_file', 'thumbnail_metadata', 'video_source', 'video_file', 'video_metadata'];
    $found = 0;
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columnNames)) {
            $found++;
        }
    }
    
    if ($found === count($requiredColumns)) {
        echo "✓ Templates table: All new columns present\n";
    } else {
        echo "⚠ Templates table: Only $found/" . count($requiredColumns) . " columns found\n";
    }
    
    // Check if media_files table exists
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='media_files'")->fetchAll();
    
    if (count($tables) > 0) {
        echo "✓ media_files table created successfully\n";
    } else {
        echo "⚠ media_files table not found\n";
    }
    
} catch (Exception $e) {
    echo "⚠ Verification failed: " . $e->getMessage() . "\n";
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║                 MIGRATION COMPLETE ✓                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Next steps:\n";
echo "  1. Test the application to ensure everything works\n";
echo "  2. If issues occur, restore backup:\n";
echo "     cp $backupFile $dbPath\n";
echo "  3. Delete backup after confirming migration success\n\n";

function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow = floor(log($bytes) / log(1024));
    return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
}
