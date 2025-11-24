-- Migration: Create tool_files table
-- Stores downloadable files for digital tools

CREATE TABLE IF NOT EXISTS tool_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tool_id INTEGER NOT NULL,
  
  -- File Details
  file_name TEXT NOT NULL,
  file_path TEXT NOT NULL,
  file_type TEXT NOT NULL CHECK(file_type IN ('attachment', 'zip_archive', 'code', 'text_instructions', 'image', 'access_key', 'link', 'video')),
  file_description TEXT,
  
  -- File Information
  file_size INTEGER,
  mime_type TEXT,
  download_count INTEGER DEFAULT 0,
  
  -- Access Control
  is_public INTEGER DEFAULT 0,
  access_expires_after_days INTEGER DEFAULT 30,
  require_password INTEGER DEFAULT 0,
  
  -- Ordering
  sort_order INTEGER DEFAULT 0,
  
  -- Metadata
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tool_files_tool ON tool_files(tool_id);
CREATE INDEX IF NOT EXISTS idx_tool_files_type ON tool_files(file_type);
