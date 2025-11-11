-- Migration 001: Add order_items table for normalized multi-product support
-- Created: 2024-11-11
-- Description: Introduces order_items table to properly support templates, tools, and mixed carts

-- ============================================================================
-- STEP 1: Create order_items table (normalized line items)
-- ============================================================================

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    product_type TEXT NOT NULL CHECK(product_type IN ('template', 'tool')),
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL,
    discount_amount REAL DEFAULT 0.00,
    final_amount REAL NOT NULL,
    metadata_json TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_order_items_pending_order_id ON order_items(pending_order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product_type_id ON order_items(product_type, product_id);
CREATE INDEX IF NOT EXISTS idx_order_items_created_at ON order_items(created_at);

-- ============================================================================
-- STEP 2: Backfill existing orders into order_items
-- ============================================================================

-- Backfill template-only orders (where template_id is set and tool_id is null)
INSERT INTO order_items (pending_order_id, product_type, product_id, quantity, unit_price, discount_amount, final_amount)
SELECT 
    id as pending_order_id,
    'template' as product_type,
    template_id as product_id,
    COALESCE(quantity, 1) as quantity,
    COALESCE(original_price, 0) as unit_price,
    COALESCE(discount_amount, 0) as discount_amount,
    COALESCE(final_amount, original_price, 0) as final_amount
FROM pending_orders
WHERE template_id IS NOT NULL 
  AND (tool_id IS NULL OR tool_id = 0)
  AND NOT EXISTS (
      SELECT 1 FROM order_items oi WHERE oi.pending_order_id = pending_orders.id
  );

-- Backfill tool-only orders (where tool_id is set and cart_snapshot is null)
INSERT INTO order_items (pending_order_id, product_type, product_id, quantity, unit_price, discount_amount, final_amount)
SELECT 
    id as pending_order_id,
    'tool' as product_type,
    tool_id as product_id,
    COALESCE(quantity, 1) as quantity,
    COALESCE(original_price, 0) as unit_price,
    COALESCE(discount_amount, 0) as discount_amount,
    COALESCE(final_amount, original_price, 0) as final_amount
FROM pending_orders
WHERE tool_id IS NOT NULL 
  AND (cart_snapshot IS NULL OR cart_snapshot = '')
  AND NOT EXISTS (
      SELECT 1 FROM order_items oi WHERE oi.pending_order_id = pending_orders.id
  );

-- ============================================================================
-- STEP 3: Mark migration as complete
-- ============================================================================

INSERT OR IGNORE INTO settings (setting_key, setting_value) 
VALUES ('migration_001_completed', datetime('now'));
