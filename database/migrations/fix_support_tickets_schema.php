<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

try {
    echo "Fixing support tickets schema...\n\n";
    
    $db->exec("DROP TABLE IF EXISTS ticket_replies");
    echo "✅ Dropped old ticket_replies table\n";
    
    $db->exec("CREATE TABLE ticket_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        admin_id INTEGER DEFAULT NULL,
        affiliate_id INTEGER DEFAULT NULL,
        message TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL
    )");
    echo "✅ Created new ticket_replies table with separate admin_id and affiliate_id columns\n";
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_replies_ticket ON ticket_replies(ticket_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_replies_admin ON ticket_replies(admin_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_replies_affiliate ON ticket_replies(affiliate_id)");
    echo "✅ Created indexes\n";
    
    echo "\n✅ Support tickets schema fixed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
