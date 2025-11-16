<?php
require_once __DIR__ . '/../includes/db.php';

echo "🔄 Rolling back media type migration...\n\n";

try {
    $db = getDb();
    $db->exec('BEGIN TRANSACTION');
    
    echo "1️⃣ Checking if columns exist...\n";
    $columns = $db->query("PRAGMA table_info(templates)")->fetchAll(PDO::FETCH_ASSOC);
    $hasMediaType = false;
    $hasDemoVideoUrl = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'media_type') $hasMediaType = true;
        if ($col['name'] === 'demo_video_url') $hasDemoVideoUrl = true;
    }
    
    if (!$hasMediaType && !$hasDemoVideoUrl) {
        echo "   ℹ️  Columns don't exist, nothing to rollback\n";
        $db->exec('ROLLBACK');
        exit(0);
    }
    
    echo "2️⃣ Creating backup table...\n";
    $db->exec("CREATE TABLE templates_backup AS SELECT * FROM templates");
    
    echo "3️⃣ Dropping indexes...\n";
    $db->exec("DROP INDEX IF EXISTS idx_templates_media_type");
    
    echo "4️⃣ Creating new table without media columns...\n";
    $db->exec("
        CREATE TABLE templates_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            price REAL NOT NULL DEFAULT 0.00,
            category TEXT,
            description TEXT,
            features TEXT,
            demo_url TEXT,
            thumbnail_url TEXT,
            video_links TEXT,
            active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            thumbnail_source TEXT DEFAULT 'url' CHECK(thumbnail_source IN ('url', 'upload')),
            thumbnail_file TEXT,
            thumbnail_metadata TEXT,
            video_source TEXT DEFAULT 'url' CHECK(video_source IN ('url', 'upload')),
            video_file TEXT,
            video_metadata TEXT
        )
    ");
    
    echo "5️⃣ Copying data from backup...\n";
    $db->exec("
        INSERT INTO templates_new 
        SELECT id, name, slug, price, category, description, features, demo_url, 
               thumbnail_url, video_links, active, created_at, updated_at,
               thumbnail_source, thumbnail_file, thumbnail_metadata,
               video_source, video_file, video_metadata
        FROM templates_backup
    ");
    
    echo "6️⃣ Dropping old table...\n";
    $db->exec("DROP TABLE templates");
    
    echo "7️⃣ Renaming new table...\n";
    $db->exec("ALTER TABLE templates_new RENAME TO templates");
    
    echo "8️⃣ Recreating indexes...\n";
    $db->exec("CREATE INDEX idx_templates_slug ON templates(slug)");
    $db->exec("CREATE INDEX idx_templates_active ON templates(active)");
    $db->exec("CREATE INDEX idx_templates_category ON templates(category)");
    $db->exec("CREATE INDEX idx_templates_created ON templates(created_at DESC)");
    
    echo "9️⃣ Dropping backup table...\n";
    $db->exec("DROP TABLE templates_backup");
    
    $db->exec('COMMIT');
    
    echo "\n✅ Rollback completed successfully!\n\n";
    
} catch (Exception $e) {
    $db->exec('ROLLBACK');
    echo "\n❌ Rollback failed: " . $e->getMessage() . "\n";
    exit(1);
}
