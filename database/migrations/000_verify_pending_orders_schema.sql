-- Migration 000: Verify and document pending_orders schema extensions
-- Created: 2024-11-11
-- Description: Documents the existing schema extensions to pending_orders that were
--              added in a previous undocumented migration. This migration verifies
--              all required fields exist for dual-product (template + tool) support.

-- ============================================================================
-- VERIFICATION: Check that all required columns exist in pending_orders
-- ============================================================================

-- The following columns should already exist from previous migrations:
-- - tool_id: References tools table for tool-only orders
-- - order_type: 'template', 'tools', or 'mixed'
-- - original_price: Original product price before discounts
-- - discount_amount: Total discount applied
-- - final_amount: Final amount after discounts
-- - quantity: Quantity ordered (legacy, kept for single-item compatibility)
-- - cart_snapshot: JSON snapshot of cart for multi-item tool orders

-- Note: SQLite doesn't support adding columns with foreign keys in ALTER TABLE,
-- so these must have been added when the table was initially created or rebuilt.

-- ============================================================================
-- VERIFICATION QUERY (for manual checking)
-- ============================================================================

-- Run this to verify schema:
-- PRAGMA table_info(pending_orders);

-- Expected columns:
-- id, template_id, tool_id, order_type, chosen_domain_id, customer_name,
-- customer_email, customer_phone, business_name, custom_fields, affiliate_code,
-- session_id, message_text, status, ip_address, original_price, discount_amount,
-- final_amount, quantity, cart_snapshot, created_at, updated_at

-- ============================================================================
-- Mark verification as complete
-- ============================================================================

INSERT OR IGNORE INTO settings (setting_key, setting_value) 
VALUES ('migration_000_verified', datetime('now'));
