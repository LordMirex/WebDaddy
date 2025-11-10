-- Migration: Extend pending_orders table to support tools
-- Created: 2025-11-10
-- Description: Add columns to pending_orders to handle both template and tool orders
-- IMPORTANT: Maintains backward compatibility with existing template orders

-- Add new columns for tools support
ALTER TABLE pending_orders ADD COLUMN order_type TEXT DEFAULT 'template';
ALTER TABLE pending_orders ADD COLUMN tool_id INTEGER;
ALTER TABLE pending_orders ADD COLUMN quantity INTEGER DEFAULT 1;
ALTER TABLE pending_orders ADD COLUMN cart_snapshot TEXT;

-- Note: All existing orders will have order_type='template' by default
-- This ensures backward compatibility with existing queries
