<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

try {
    echo "Creating support tickets system...\n\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT 'open',
        priority TEXT DEFAULT 'normal',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )");
    echo "✅ Created support_tickets table\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        user_id INTEGER,
        is_admin INTEGER DEFAULT 0,
        message TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "✅ Created ticket_replies table\n";
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_affiliate ON support_tickets(affiliate_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tickets_status ON support_tickets(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_replies_ticket ON ticket_replies(ticket_id)");
    echo "✅ Created indexes\n";
    
    echo "\n✅ Support tickets system created successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
