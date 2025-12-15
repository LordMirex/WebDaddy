-- Migration: Add payment_notified columns to pending_orders
-- Date: 2024-12-15
-- Description: Tracks when customers click "I Have Paid" for manual bank transfers

-- Add payment_notified flag (0 = not notified, 1 = customer clicked I Have Paid)
ALTER TABLE pending_orders ADD COLUMN IF NOT EXISTS payment_notified INTEGER DEFAULT 0;

-- Add timestamp for when notification was sent
ALTER TABLE pending_orders ADD COLUMN IF NOT EXISTS payment_notified_at TEXT;
