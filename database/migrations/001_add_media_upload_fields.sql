-- Migration: Add Media Upload Fields to Templates and Tools Tables
-- Date: 2025-11-15
-- Purpose: Support file uploads (images/videos) alongside existing URL functionality

-- BACKUP REMINDER: Always backup database before running migrations!
-- To backup: php database/backup_database.php
-- Or manually: cp database/webdaddy.db database/backups/webdaddy_backup_$(date +%Y%m%d_%H%M%S).db

-- =========================================
-- TEMPLATES TABLE MODIFICATIONS
-- =========================================

-- Add image upload fields
ALTER TABLE templates ADD COLUMN thumbnail_source TEXT DEFAULT 'url' CHECK(thumbnail_source IN ('url', 'upload'));
ALTER TABLE templates ADD COLUMN thumbnail_file TEXT;
ALTER TABLE templates ADD COLUMN thumbnail_metadata TEXT;

-- Add video upload fields
ALTER TABLE templates ADD COLUMN video_source TEXT DEFAULT 'url' CHECK(video_source IN ('url', 'upload'));
ALTER TABLE templates ADD COLUMN video_file TEXT;
ALTER TABLE templates ADD COLUMN video_metadata TEXT;

-- =========================================
-- TOOLS TABLE MODIFICATIONS
-- =========================================

-- Add image upload fields
ALTER TABLE tools ADD COLUMN thumbnail_source TEXT DEFAULT 'url' CHECK(thumbnail_source IN ('url', 'upload'));
ALTER TABLE tools ADD COLUMN thumbnail_file TEXT;
ALTER TABLE tools ADD COLUMN thumbnail_metadata TEXT;

-- Add video upload fields (tools don't have videos yet, but future-proof)
ALTER TABLE tools ADD COLUMN video_source TEXT DEFAULT 'url' CHECK(video_source IN ('url', 'upload'));
ALTER TABLE tools ADD COLUMN video_file TEXT;
ALTER TABLE tools ADD COLUMN video_metadata TEXT;

-- =========================================
-- MEDIA FILES TABLE (NEW)
-- =========================================

-- Track all uploaded media files for management
CREATE TABLE IF NOT EXISTS media_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL UNIQUE,
    file_size INTEGER NOT NULL,
    mime_type TEXT NOT NULL,
    media_type TEXT NOT NULL CHECK(media_type IN ('image', 'video')),
    entity_type TEXT NOT NULL CHECK(entity_type IN ('template', 'tool')),
    entity_id INTEGER NOT NULL,
    metadata TEXT,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_media_files_entity ON media_files(entity_type, entity_id);
CREATE INDEX idx_media_files_type ON media_files(media_type);
CREATE INDEX idx_media_files_path ON media_files(file_path);

-- =========================================
-- MIGRATION NOTES
-- =========================================

-- Field Descriptions:
-- 
-- thumbnail_source: 'url' (default, use thumbnail_url) or 'upload' (use thumbnail_file)
-- thumbnail_file: Relative path to uploaded thumbnail (e.g., 'uploads/templates/123/thumbnail.jpg')
-- thumbnail_metadata: JSON string with: {"width": 1920, "height": 1080, "size": 245632, "mime": "image/jpeg"}
--
-- video_source: 'url' (default, use demo_url/video_links) or 'upload' (use video_file)
-- video_file: Relative path to uploaded video (e.g., 'uploads/templates/123/demo.mp4')
-- video_metadata: JSON string with: {"duration": 120, "width": 1920, "height": 1080, "size": 15728640, "mime": "video/mp4", "thumbnail": "path/to/thumb.jpg"}
--
-- Backward Compatibility:
-- - Existing records default to 'url' source, continue using thumbnail_url and demo_url
-- - No data loss - old URL fields remain intact
-- - Admin panel will show toggle: "Use URL" or "Upload File"
