-- Migration: Create download_tokens table
-- Secure, time-limited download links

CREATE TABLE IF NOT EXISTS download_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_id INTEGER NOT NULL,
  pending_order_id INTEGER NOT NULL,
  token TEXT NOT NULL UNIQUE,
  download_count INTEGER DEFAULT 0,
  max_downloads INTEGER DEFAULT 5,
  expires_at TEXT NOT NULL,
  last_downloaded_at TEXT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (file_id) REFERENCES tool_files(id) ON DELETE CASCADE,
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_download_tokens_token ON download_tokens(token);
CREATE INDEX IF NOT EXISTS idx_download_tokens_expires ON download_tokens(expires_at);
