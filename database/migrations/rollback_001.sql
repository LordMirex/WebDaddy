-- Rollback Migration: Remove Media Upload Fields
-- Date: 2025-11-15
-- Purpose: Revert changes from 001_add_media_upload_fields.sql

-- NOTE: SQLite does not support ALTER TABLE DROP COLUMN
-- To rollback, you must restore from backup:
-- cp database/backups/webdaddy_backup_YYYYMMDD_HHMMSS.db database/webdaddy.db

-- Alternative: Recreate tables without new columns (DATA LOSS WARNING!)
-- This is NOT recommended unless you have no data in the new columns

-- DROP the media_files table
DROP TABLE IF EXISTS media_files;

-- For columns added to templates and tools tables, you would need to:
-- 1. Create new table without the new columns
-- 2. Copy data from old table to new table
-- 3. Drop old table
-- 4. Rename new table to old name

-- RECOMMENDED: Always restore from backup instead of using this script
