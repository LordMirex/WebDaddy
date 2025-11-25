-- Migration: Add delivery retry and analytics columns
-- Phase 3: Tools Delivery Optimization

-- Add retry tracking columns to deliveries table
ALTER TABLE deliveries ADD COLUMN retry_count INTEGER DEFAULT 0;
ALTER TABLE deliveries ADD COLUMN next_retry_at TEXT NULL;

-- Update delivery_status constraint to include new statuses
-- SQLite doesn't support ALTER CONSTRAINT, but we can use CHECK constraints on INSERT/UPDATE

-- Add index for retry processing
CREATE INDEX IF NOT EXISTS idx_deliveries_retry ON deliveries(delivery_status, next_retry_at);
CREATE INDEX IF NOT EXISTS idx_deliveries_retry_count ON deliveries(retry_count);

-- Note: In SQLite, we can't modify CHECK constraints directly
-- The application code will validate the new statuses: pending_retry, failed
