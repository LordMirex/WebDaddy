<?php
/**
 * Database Migration Runner
 * Executes SQL migration files in order
 */

require_once __DIR__ . '/../includes/db.php';

function runMigrations() {
    $db = getDb();
    $migrationDir = __DIR__ . '/migrations';
    
    // Get all .sql files sorted by name
    $migrationFiles = glob($migrationDir . '/*.sql');
    sort($migrationFiles);
    
    if (empty($migrationFiles)) {
        echo "No migration files found.\n";
        return;
    }
    
    echo "=================================\n";
    echo "Database Migration Runner\n";
    echo "=================================\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        echo "Running: $filename\n";
        
        try {
            $sql = file_get_contents($file);
            
            // Execute the SQL
            $db->exec($sql);
            
            echo "✓ SUCCESS: $filename\n\n";
            $successCount++;
            
        } catch (PDOException $e) {
            echo "✗ ERROR in $filename: " . $e->getMessage() . "\n\n";
            $errorCount++;
        }
    }
    
    echo "=================================\n";
    echo "Migration Summary\n";
    echo "=================================\n";
    echo "Successful: $successCount\n";
    echo "Failed: $errorCount\n";
    echo "Total: " . count($migrationFiles) . "\n";
    
    if ($errorCount === 0) {
        echo "\n✓ All migrations completed successfully!\n";
    } else {
        echo "\n✗ Some migrations failed. Please review errors above.\n";
    }
}

// Run migrations
runMigrations();
