<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

try {
    echo "Adding expires_at column to announcements table...\n";
    
    $columns = $db->query("PRAGMA table_info(announcements)")->fetchAll(PDO::FETCH_ASSOC);
    $hasExpiresAt = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'expires_at') {
            $hasExpiresAt = true;
            break;
        }
    }
    
    if (!$hasExpiresAt) {
        $db->exec("ALTER TABLE announcements ADD COLUMN expires_at TEXT DEFAULT NULL");
        echo "✅ Added expires_at column successfully!\n";
    } else {
        echo "ℹ️  Column expires_at already exists.\n";
    }
    
    $hasExpiresAtIndex = false;
    $indexes = $db->query("PRAGMA index_list(announcements)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $index) {
        if ($index['name'] === 'idx_announcements_expires_at') {
            $hasExpiresAtIndex = true;
            break;
        }
    }
    
    if (!$hasExpiresAtIndex) {
        $db->exec("CREATE INDEX idx_announcements_expires_at ON announcements(expires_at)");
        echo "✅ Created index on expires_at column!\n";
    } else {
        echo "ℹ️  Index idx_announcements_expires_at already exists.\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
