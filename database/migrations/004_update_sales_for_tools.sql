-- Migration: Extend sales table to support tools
-- Created: 2025-11-10
-- Description: Add columns to sales to handle both template and tool sales

-- Add new columns for tools support
ALTER TABLE sales ADD COLUMN order_type TEXT DEFAULT 'template';
ALTER TABLE sales ADD COLUMN tool_id INTEGER;
ALTER TABLE sales ADD COLUMN quantity INTEGER DEFAULT 1;

-- Note: All existing sales will have order_type='template' by default
