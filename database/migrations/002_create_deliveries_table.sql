-- Migration: Create deliveries table
-- Tracks individual product deliveries within orders

CREATE TABLE IF NOT EXISTS deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER NOT NULL,
  order_item_id INTEGER NULL,
  product_id INTEGER NOT NULL,
  product_type TEXT NOT NULL CHECK(product_type IN ('template', 'tool')),
  product_name TEXT,
  
  -- Delivery Configuration
  delivery_method TEXT NOT NULL CHECK(delivery_method IN ('email', 'download', 'hosted', 'manual')),
  delivery_type TEXT NOT NULL CHECK(delivery_type IN ('immediate', 'pending_24h', 'manual')),
  delivery_status TEXT DEFAULT 'pending' CHECK(delivery_status IN ('pending', 'in_progress', 'ready', 'sent', 'delivered', 'failed')),
  
  -- Delivery Content & Links
  delivery_link TEXT, -- JSON stored as TEXT
  delivery_instructions TEXT,
  delivery_note TEXT,
  file_path TEXT,
  hosted_domain TEXT,
  hosted_url TEXT,
  
  -- For Templates Only
  template_ready_at TEXT NULL,
  template_expires_at TEXT NULL,
  
  -- Delivery Tracking
  email_sent_at TEXT NULL,
  sent_to_email TEXT,
  delivered_at TEXT NULL,
  delivery_attempts INTEGER DEFAULT 0,
  last_attempt_at TEXT NULL,
  last_error TEXT,
  
  -- Admin Notes
  admin_notes TEXT,
  prepared_by INTEGER NULL,
  
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_deliveries_order ON deliveries(pending_order_id);
CREATE INDEX IF NOT EXISTS idx_deliveries_status ON deliveries(delivery_status);
CREATE INDEX IF NOT EXISTS idx_deliveries_type ON deliveries(product_type);
CREATE INDEX IF NOT EXISTS idx_deliveries_ready ON deliveries(template_ready_at);
