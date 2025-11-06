<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

try {
    echo "Creating analytics tables...\n\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS page_visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        page_url TEXT NOT NULL,
        page_title TEXT,
        referrer TEXT,
        user_agent TEXT,
        ip_address TEXT,
        country_code TEXT,
        visit_date DATE NOT NULL,
        visit_time TIME NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Created page_visits table\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS page_interactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        page_url TEXT NOT NULL,
        action_type TEXT NOT NULL,
        action_target TEXT,
        template_id INTEGER,
        time_spent INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
    )");
    echo "✅ Created page_interactions table\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS session_summary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT UNIQUE NOT NULL,
        first_visit TEXT NOT NULL,
        last_visit TEXT NOT NULL,
        total_pages INTEGER DEFAULT 1,
        total_time_seconds INTEGER DEFAULT 0,
        is_bounce INTEGER DEFAULT 0,
        converted INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Created session_summary table\n";
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_page_visits_session ON page_visits(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_page_visits_date ON page_visits(visit_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_page_visits_url ON page_visits(page_url)");
    echo "✅ Created indexes on page_visits\n";
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_interactions_session ON page_interactions(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_interactions_template ON page_interactions(template_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_interactions_action ON page_interactions(action_type)");
    echo "✅ Created indexes on page_interactions\n";
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_session_summary_date ON session_summary(first_visit)");
    echo "✅ Created indexes on session_summary\n";
    
    echo "\n✅ Analytics tables created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
