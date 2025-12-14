<?php
/**
 * Migration: Add Customer Account System
 * 
 * Run with: php database/migrations/003_add_customer_accounts.php
 * 
 * Creates all tables for the customer account system:
 * - customers
 * - customer_sessions
 * - customer_otp_codes
 * - customer_password_resets
 * - customer_activity_log
 * - customer_support_tickets
 * - customer_ticket_replies
 * 
 * Also modifies existing tables to add customer_id columns
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

echo "Starting Customer Account System Migration...\n\n";

try {
    $db->beginTransaction();
    
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='customers'");
    if ($check->fetch()) {
        echo "Migration already applied (customers table exists). Exiting.\n";
        exit(0);
    }
    
    echo "Creating customers table...\n";
    $db->exec("
        CREATE TABLE customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            whatsapp_number TEXT,
            password_hash TEXT,
            username TEXT UNIQUE,
            full_name TEXT,
            
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended', 'unverified', 'pending_setup')),
            email_verified INTEGER DEFAULT 0,
            phone_verified INTEGER DEFAULT 0,
            phone_verified_at TEXT,
            
            avatar_url TEXT,
            preferred_language TEXT DEFAULT 'en',
            
            registration_step INTEGER DEFAULT 0,
            
            reset_token TEXT,
            reset_token_expires TEXT,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_login_at TEXT,
            password_changed_at TEXT
        )
    ");
    
    $db->exec("CREATE INDEX idx_customers_email ON customers(email)");
    $db->exec("CREATE INDEX idx_customers_phone ON customers(phone)");
    $db->exec("CREATE INDEX idx_customers_username ON customers(username)");
    $db->exec("CREATE INDEX idx_customers_status ON customers(status)");
    $db->exec("CREATE INDEX idx_customers_created ON customers(created_at)");
    echo "  - customers table created with indexes\n";
    
    echo "Creating customer_sessions table...\n";
    $db->exec("
        CREATE TABLE customer_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
            session_token TEXT NOT NULL UNIQUE,
            
            device_fingerprint TEXT,
            user_agent TEXT,
            ip_address TEXT,
            device_name TEXT,
            
            expires_at TEXT NOT NULL,
            last_activity_at TEXT DEFAULT CURRENT_TIMESTAMP,
            
            is_active INTEGER DEFAULT 1,
            revoked_at TEXT,
            revoke_reason TEXT,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX idx_customer_sessions_token ON customer_sessions(session_token)");
    $db->exec("CREATE INDEX idx_customer_sessions_customer ON customer_sessions(customer_id)");
    $db->exec("CREATE INDEX idx_customer_sessions_expires ON customer_sessions(expires_at)");
    $db->exec("CREATE INDEX idx_customer_sessions_active ON customer_sessions(is_active)");
    echo "  - customer_sessions table created with indexes\n";
    
    echo "Creating customer_otp_codes table...\n";
    $db->exec("
        CREATE TABLE customer_otp_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
            email TEXT NOT NULL,
            phone TEXT,
            otp_code TEXT NOT NULL,
            otp_type TEXT NOT NULL CHECK(otp_type IN ('email_verify', 'phone_verify', 'login', 'password_reset')),
            
            delivery_method TEXT DEFAULT 'email' CHECK(delivery_method IN ('email', 'sms', 'both')),
            sms_sent INTEGER DEFAULT 0,
            email_sent INTEGER DEFAULT 0,
            termii_message_id TEXT,
            
            is_used INTEGER DEFAULT 0,
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 5,
            
            expires_at TEXT NOT NULL,
            used_at TEXT,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX idx_customer_otp_email ON customer_otp_codes(email)");
    $db->exec("CREATE INDEX idx_customer_otp_code ON customer_otp_codes(otp_code)");
    $db->exec("CREATE INDEX idx_customer_otp_expires ON customer_otp_codes(expires_at)");
    $db->exec("CREATE INDEX idx_customer_otp_type ON customer_otp_codes(otp_type)");
    $db->exec("CREATE INDEX idx_customer_otp_customer ON customer_otp_codes(customer_id)");
    echo "  - customer_otp_codes table created with indexes\n";
    
    echo "Creating customer_password_resets table...\n";
    $db->exec("
        CREATE TABLE customer_password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
            reset_token TEXT NOT NULL UNIQUE,
            
            is_used INTEGER DEFAULT 0,
            used_at TEXT,
            
            expires_at TEXT NOT NULL,
            
            ip_address TEXT,
            user_agent TEXT,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX idx_password_resets_token ON customer_password_resets(reset_token)");
    $db->exec("CREATE INDEX idx_password_resets_customer ON customer_password_resets(customer_id)");
    echo "  - customer_password_resets table created with indexes\n";
    
    echo "Creating customer_activity_log table...\n";
    $db->exec("
        CREATE TABLE customer_activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX idx_customer_activity_customer ON customer_activity_log(customer_id)");
    $db->exec("CREATE INDEX idx_customer_activity_action ON customer_activity_log(action)");
    $db->exec("CREATE INDEX idx_customer_activity_created ON customer_activity_log(created_at)");
    echo "  - customer_activity_log table created with indexes\n";
    
    echo "Creating customer_support_tickets table...\n";
    $db->exec("
        CREATE TABLE customer_support_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
            order_id INTEGER REFERENCES pending_orders(id) ON DELETE SET NULL,
            
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            category TEXT DEFAULT 'general' CHECK(category IN ('general', 'order', 'delivery', 'refund', 'technical', 'account')),
            priority TEXT DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high', 'urgent')),
            status TEXT DEFAULT 'open' CHECK(status IN ('open', 'awaiting_reply', 'in_progress', 'resolved', 'closed')),
            
            attachments TEXT,
            
            assigned_admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            resolved_at TEXT,
            closed_at TEXT,
            last_reply_at TEXT,
            last_reply_by TEXT CHECK(last_reply_by IN ('customer', 'admin'))
        )
    ");
    
    $db->exec("CREATE INDEX idx_cst_customer ON customer_support_tickets(customer_id)");
    $db->exec("CREATE INDEX idx_cst_order ON customer_support_tickets(order_id)");
    $db->exec("CREATE INDEX idx_cst_status ON customer_support_tickets(status)");
    $db->exec("CREATE INDEX idx_cst_priority ON customer_support_tickets(priority)");
    echo "  - customer_support_tickets table created with indexes\n";
    
    echo "Creating customer_ticket_replies table...\n";
    $db->exec("
        CREATE TABLE customer_ticket_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES customer_support_tickets(id) ON DELETE CASCADE,
            
            author_type TEXT NOT NULL CHECK(author_type IN ('customer', 'admin')),
            author_id INTEGER NOT NULL,
            author_name TEXT,
            
            message TEXT NOT NULL,
            attachments TEXT,
            
            is_internal INTEGER DEFAULT 0,
            
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX idx_ctr_ticket ON customer_ticket_replies(ticket_id)");
    $db->exec("CREATE INDEX idx_ctr_author ON customer_ticket_replies(author_type, author_id)");
    echo "  - customer_ticket_replies table created with indexes\n";
    
    echo "\nAdding customer_id column to pending_orders...\n";
    $db->exec("ALTER TABLE pending_orders ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL");
    $db->exec("CREATE INDEX idx_pending_orders_customer ON pending_orders(customer_id)");
    echo "  - pending_orders updated\n";
    
    echo "Adding customer_id column to sales...\n";
    $db->exec("ALTER TABLE sales ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL");
    $db->exec("CREATE INDEX idx_sales_customer ON sales(customer_id)");
    echo "  - sales updated\n";
    
    echo "Adding customer fields to deliveries...\n";
    $db->exec("ALTER TABLE deliveries ADD COLUMN customer_viewed_at TEXT");
    $db->exec("ALTER TABLE deliveries ADD COLUMN customer_download_count INTEGER DEFAULT 0");
    echo "  - deliveries updated\n";
    
    echo "Adding customer_id column to download_tokens...\n";
    $db->exec("ALTER TABLE download_tokens ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL");
    $db->exec("CREATE INDEX idx_download_tokens_customer ON download_tokens(customer_id)");
    echo "  - download_tokens updated\n";
    
    echo "Adding customer_id column to cart_items...\n";
    $db->exec("ALTER TABLE cart_items ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE");
    $db->exec("CREATE INDEX idx_cart_items_customer ON cart_items(customer_id)");
    echo "  - cart_items updated\n";
    
    $db->commit();
    echo "\n========================================\n";
    echo "Migration completed successfully!\n";
    echo "========================================\n";
    
    $db->exec("ANALYZE");
    echo "Database statistics updated.\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
