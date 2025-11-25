-- Migration 008: Add template credentials fields to deliveries table
-- Phase 1: Template Credentials System
-- Date: November 25, 2025

-- Add credential storage fields for template delivery
ALTER TABLE deliveries ADD COLUMN template_admin_username TEXT NULL;
ALTER TABLE deliveries ADD COLUMN template_admin_password TEXT NULL;
ALTER TABLE deliveries ADD COLUMN template_login_url TEXT NULL;
ALTER TABLE deliveries ADD COLUMN hosting_provider TEXT NULL CHECK(hosting_provider IN ('wordpress', 'cpanel', 'custom', 'static', NULL));
ALTER TABLE deliveries ADD COLUMN credentials_sent_at TEXT NULL;

-- Add index for faster credential-related queries
CREATE INDEX IF NOT EXISTS idx_deliveries_credentials ON deliveries(template_admin_username, credentials_sent_at);
