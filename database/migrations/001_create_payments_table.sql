-- Migration: Create payments table
-- Tracks all payment transactions (manual and Paystack)

CREATE TABLE IF NOT EXISTS payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER NOT NULL UNIQUE,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('manual', 'paystack')),
  
  -- Amount & Currency
  amount_requested REAL NOT NULL,
  amount_paid REAL,
  currency TEXT DEFAULT 'NGN',
  
  -- Payment Status
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'completed', 'failed', 'cancelled', 'refunded')),
  payment_verified_at TEXT NULL,
  
  -- Paystack Specific
  paystack_reference TEXT UNIQUE,
  paystack_access_code TEXT,
  paystack_authorization_url TEXT,
  paystack_customer_code TEXT,
  paystack_response TEXT, -- JSON stored as TEXT
  
  -- Manual Payment Specific
  manual_verified_by INTEGER NULL,
  manual_verified_at TEXT NULL,
  payment_note TEXT,
  
  -- Tracking
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payments_order ON payments(pending_order_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
CREATE INDEX IF NOT EXISTS idx_payments_reference ON payments(paystack_reference);
