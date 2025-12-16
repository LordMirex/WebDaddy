# Database Schema Changes

## Overview

This document details all database modifications required for the customer account system. Changes include new tables, modified existing tables, indexes, and data migration scripts.

## New Tables

### 1. customers

Primary table for customer accounts (separate from admin/affiliate users).

```sql
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT,
    username TEXT,
    full_name TEXT,
    
    -- Account Status
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended', 'unverified')),
    email_verified INTEGER DEFAULT 0,
    phone_verified INTEGER DEFAULT 0,
    
    -- Profile
    avatar_url TEXT,
    preferred_language TEXT DEFAULT 'en',
    
    -- Timestamps
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    last_login_at TEXT,
    password_changed_at TEXT
);

CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_status ON customers(status);
CREATE INDEX idx_customers_created ON customers(created_at);
```

### 2. customer_sessions

Long-lasting session tokens for "remember me" functionality.

```sql
CREATE TABLE customer_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    session_token TEXT NOT NULL UNIQUE,
    
    -- Device Info
    device_fingerprint TEXT,
    user_agent TEXT,
    ip_address TEXT,
    device_name TEXT,
    
    -- Expiry
    expires_at TEXT NOT NULL,
    last_activity_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    -- Status
    is_active INTEGER DEFAULT 1,
    revoked_at TEXT,
    revoke_reason TEXT,
    
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_customer_sessions_token ON customer_sessions(session_token);
CREATE INDEX idx_customer_sessions_customer ON customer_sessions(customer_id);
CREATE INDEX idx_customer_sessions_expires ON customer_sessions(expires_at);
CREATE INDEX idx_customer_sessions_active ON customer_sessions(is_active);
```

### 3. customer_otp_codes

OTP codes for email/phone verification.

```sql
CREATE TABLE customer_otp_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    phone TEXT,
    otp_code TEXT NOT NULL,
    otp_type TEXT NOT NULL CHECK(otp_type IN ('email_verify', 'phone_verify', 'login', 'password_reset')),
    
    -- Delivery
    delivery_method TEXT DEFAULT 'email' CHECK(delivery_method IN ('email', 'sms', 'both')),
    sms_sent INTEGER DEFAULT 0,
    email_sent INTEGER DEFAULT 0,
    sms-removed_message_id TEXT,
    
    -- Status
    is_used INTEGER DEFAULT 0,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 5,
    
    -- Expiry
    expires_at TEXT NOT NULL,
    used_at TEXT,
    
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_customer_otp_email ON customer_otp_codes(email);
CREATE INDEX idx_customer_otp_code ON customer_otp_codes(otp_code);
CREATE INDEX idx_customer_otp_expires ON customer_otp_codes(expires_at);
CREATE INDEX idx_customer_otp_type ON customer_otp_codes(otp_type);
```

### 4. customer_password_resets

Password reset tokens.

```sql
CREATE TABLE customer_password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    reset_token TEXT NOT NULL UNIQUE,
    
    -- Status
    is_used INTEGER DEFAULT 0,
    used_at TEXT,
    
    -- Expiry
    expires_at TEXT NOT NULL,
    
    -- Security
    ip_address TEXT,
    user_agent TEXT,
    
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_password_resets_token ON customer_password_resets(reset_token);
CREATE INDEX idx_password_resets_customer ON customer_password_resets(customer_id);
```

### 5. customer_activity_log

Audit trail for customer actions.

```sql
CREATE TABLE customer_activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_customer_activity_customer ON customer_activity_log(customer_id);
CREATE INDEX idx_customer_activity_action ON customer_activity_log(action);
CREATE INDEX idx_customer_activity_created ON customer_activity_log(created_at);
```

### 6. customer_support_tickets

Support tickets from customers (extends existing support_tickets concept).

```sql
CREATE TABLE customer_support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES pending_orders(id) ON DELETE SET NULL,
    
    -- Ticket Details
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    category TEXT DEFAULT 'general' CHECK(category IN ('general', 'order', 'delivery', 'refund', 'technical', 'account')),
    priority TEXT DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high', 'urgent')),
    status TEXT DEFAULT 'open' CHECK(status IN ('open', 'awaiting_reply', 'in_progress', 'resolved', 'closed')),
    
    -- Attachments
    attachments TEXT,
    
    -- Admin
    assigned_admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    
    -- Timestamps
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    resolved_at TEXT,
    closed_at TEXT,
    last_reply_at TEXT,
    last_reply_by TEXT CHECK(last_reply_by IN ('customer', 'admin'))
);

CREATE INDEX idx_cst_customer ON customer_support_tickets(customer_id);
CREATE INDEX idx_cst_order ON customer_support_tickets(order_id);
CREATE INDEX idx_cst_status ON customer_support_tickets(status);
CREATE INDEX idx_cst_priority ON customer_support_tickets(priority);
```

### 7. customer_ticket_replies

Replies to support tickets.

```sql
CREATE TABLE customer_ticket_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL REFERENCES customer_support_tickets(id) ON DELETE CASCADE,
    
    -- Author
    author_type TEXT NOT NULL CHECK(author_type IN ('customer', 'admin')),
    author_id INTEGER NOT NULL,
    author_name TEXT,
    
    -- Content
    message TEXT NOT NULL,
    attachments TEXT,
    
    -- Status
    is_internal INTEGER DEFAULT 0,
    
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ctr_ticket ON customer_ticket_replies(ticket_id);
CREATE INDEX idx_ctr_author ON customer_ticket_replies(author_type, author_id);
```

## Modified Tables

### 1. pending_orders - Add customer_id

```sql
-- Add new column
ALTER TABLE pending_orders ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL;

-- Add index
CREATE INDEX idx_pending_orders_customer ON pending_orders(customer_id);
```

### 2. sales - Add customer_id

```sql
ALTER TABLE sales ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL;
CREATE INDEX idx_sales_customer ON sales(customer_id);
```

### 3. deliveries - Add customer access fields

```sql
-- Customer can view delivery from dashboard
ALTER TABLE deliveries ADD COLUMN customer_viewed_at TEXT;
ALTER TABLE deliveries ADD COLUMN customer_download_count INTEGER DEFAULT 0;
```

### 4. download_tokens - Add customer_id

```sql
ALTER TABLE download_tokens ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL;
CREATE INDEX idx_download_tokens_customer ON download_tokens(customer_id);
```

### 5. cart_items - Add customer_id (for logged-in users)

```sql
ALTER TABLE cart_items ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE;
CREATE INDEX idx_cart_items_customer ON cart_items(customer_id);
```

## Data Migration Scripts

### Migration 1: Create New Tables

```sql
-- Run in order: customers, customer_sessions, customer_otp_codes, etc.
-- See individual CREATE TABLE statements above
```

### Migration 2: Backfill Customers from Orders

Create customer accounts from historical orders based on email addresses.

```sql
-- Step 1: Insert unique customers from pending_orders
INSERT INTO customers (email, phone, full_name, status, email_verified, created_at)
SELECT DISTINCT 
    customer_email,
    customer_phone,
    customer_name,
    'active',
    1,  -- Assume verified since they completed purchase
    MIN(created_at)
FROM pending_orders 
WHERE customer_email IS NOT NULL 
  AND customer_email != ''
GROUP BY LOWER(customer_email);

-- Step 2: Link pending_orders to customers
UPDATE pending_orders 
SET customer_id = (
    SELECT c.id FROM customers c 
    WHERE LOWER(c.email) = LOWER(pending_orders.customer_email)
    LIMIT 1
)
WHERE customer_email IS NOT NULL AND customer_email != '';

-- Step 3: Link sales to customers
UPDATE sales 
SET customer_id = (
    SELECT po.customer_id 
    FROM pending_orders po 
    WHERE po.id = sales.pending_order_id
)
WHERE pending_order_id IS NOT NULL;

-- Step 4: Link download_tokens to customers
UPDATE download_tokens 
SET customer_id = (
    SELECT po.customer_id 
    FROM pending_orders po 
    WHERE po.id = download_tokens.pending_order_id
)
WHERE pending_order_id IS NOT NULL;
```

### Migration 3: Update Indexes

```sql
-- Ensure all new indexes are created
-- Run ANALYZE to update query planner statistics
ANALYZE;
```

## Migration Script (PHP)

Location: `database/migrations/add_customer_accounts.php`

```php
<?php
/**
 * Migration: Add Customer Account System
 * 
 * Run with: php database/migrations/add_customer_accounts.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

echo "Starting Customer Account System Migration...\n\n";

try {
    $db->beginTransaction();
    
    // Check if migration already run
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='customers'");
    if ($check->fetch()) {
        echo "Migration already applied. Exiting.\n";
        exit(0);
    }
    
    // Create tables
    echo "Creating customers table...\n";
    // [Execute CREATE TABLE customers SQL]
    
    echo "Creating customer_sessions table...\n";
    // [Execute CREATE TABLE customer_sessions SQL]
    
    echo "Creating customer_otp_codes table...\n";
    // [Execute CREATE TABLE customer_otp_codes SQL]
    
    // ... etc for all tables
    
    // Alter existing tables
    echo "Adding customer_id to pending_orders...\n";
    // [Execute ALTER TABLE pending_orders SQL]
    
    // Backfill data
    echo "Backfilling customers from historical orders...\n";
    // [Execute backfill SQL]
    
    $db->commit();
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Rollback Script

Location: `database/migrations/rollback_customer_accounts.php`

```php
<?php
/**
 * Rollback: Remove Customer Account System
 * WARNING: This will delete all customer data!
 */

// Drop columns from existing tables (SQLite requires table recreation)
// Drop new tables in reverse order of creation
```

## Database Diagram

```
┌─────────────────┐       ┌──────────────────────┐
│   customers     │       │   customer_sessions  │
├─────────────────┤       ├──────────────────────┤
│ id (PK)         │◄──────│ customer_id (FK)     │
│ email           │       │ session_token        │
│ phone           │       │ device_fingerprint   │
│ password_hash   │       │ expires_at           │
│ full_name       │       └──────────────────────┘
│ status          │
│ email_verified  │       ┌──────────────────────┐
└────────┬────────┘       │ customer_otp_codes   │
         │                ├──────────────────────┤
         │                │ email                │
         │                │ otp_code             │
         │                │ otp_type             │
         ▼                │ delivery_method      │
┌─────────────────┐       └──────────────────────┘
│ pending_orders  │
├─────────────────┤       ┌──────────────────────────┐
│ id (PK)         │       │ customer_support_tickets │
│ customer_id(FK) │◄──────├──────────────────────────┤
│ customer_email  │       │ customer_id (FK)         │
│ customer_phone  │       │ order_id (FK)            │
│ ...             │       │ subject                  │
└────────┬────────┘       │ status                   │
         │                └──────────────────────────┘
         ▼
┌─────────────────┐
│     sales       │
├─────────────────┤
│ customer_id(FK) │
│ pending_order_id│
└─────────────────┘
```

## Testing Checklist

- [ ] All new tables created successfully
- [ ] All indexes created and optimized
- [ ] Foreign key constraints working
- [ ] Historical orders linked to customers
- [ ] No orphaned records
- [ ] Backfill statistics correct
- [ ] Query performance acceptable
