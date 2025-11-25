-- Migration: Add bundle downloads support
-- Phase 3.3: ZIP bundle download feature

-- Add is_bundle flag to download_tokens
ALTER TABLE download_tokens ADD COLUMN is_bundle INTEGER DEFAULT 0;

-- Create bundle_downloads table to track ZIP bundle metadata
CREATE TABLE IF NOT EXISTS bundle_downloads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id INTEGER NOT NULL,
    tool_id INTEGER NOT NULL,
    order_id INTEGER NOT NULL,
    zip_path TEXT NOT NULL,
    zip_name TEXT NOT NULL,
    file_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES download_tokens(id),
    FOREIGN KEY (tool_id) REFERENCES tools(id),
    FOREIGN KEY (order_id) REFERENCES pending_orders(id)
);

-- Create index for bundle lookups
CREATE INDEX IF NOT EXISTS idx_bundle_downloads_token ON bundle_downloads(token_id);
CREATE INDEX IF NOT EXISTS idx_bundle_downloads_order ON bundle_downloads(order_id);
