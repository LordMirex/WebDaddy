-- Migration: Create payment_logs table
-- Complete audit trail of all payment events

CREATE TABLE IF NOT EXISTS payment_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER,
  payment_id INTEGER,
  
  -- Event Details
  event_type TEXT,
  provider TEXT DEFAULT 'system' CHECK(provider IN ('paystack', 'manual', 'system')),
  status TEXT,
  amount REAL,
  
  -- Data
  request_data TEXT, -- JSON stored as TEXT
  response_data TEXT, -- JSON stored as TEXT
  error_message TEXT,
  
  -- Client Info
  ip_address TEXT,
  user_agent TEXT,
  
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payment_logs_order ON payment_logs(pending_order_id);
CREATE INDEX IF NOT EXISTS idx_payment_logs_event ON payment_logs(event_type);
