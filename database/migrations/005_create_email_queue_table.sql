-- Migration: Create email_queue table
-- Reliable email queue with retry logic

CREATE TABLE IF NOT EXISTS email_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipient_email TEXT NOT NULL,
  email_type TEXT NOT NULL CHECK(email_type IN ('payment_received', 'tools_ready', 'template_ready', 'delivery_link', 'payment_verified', 'order_confirmation')),
  
  -- Related Records
  pending_order_id INTEGER,
  delivery_id INTEGER,
  
  -- Email Content
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  html_body TEXT,
  
  -- Status Tracking
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'sent', 'failed', 'bounced', 'retry')),
  attempts INTEGER DEFAULT 0,
  max_attempts INTEGER DEFAULT 3,
  last_error TEXT,
  
  -- Scheduling
  scheduled_at TEXT DEFAULT CURRENT_TIMESTAMP,
  sent_at TEXT NULL,
  
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue(status);
CREATE INDEX IF NOT EXISTS idx_email_queue_type ON email_queue(email_type);
CREATE INDEX IF NOT EXISTS idx_email_queue_scheduled ON email_queue(scheduled_at);
