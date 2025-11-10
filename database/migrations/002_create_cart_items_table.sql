-- Migration: Create cart_items table for shopping cart
-- Created: 2025-11-10
-- Description: Session-based shopping cart for working tools

CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Session identification
    session_id TEXT NOT NULL,
    
    -- Product reference
    tool_id INTEGER NOT NULL,
    
    -- Purchase details
    quantity INTEGER NOT NULL DEFAULT 1,
    price_at_add REAL NOT NULL,
    
    -- Metadata
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
);

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_cart_session ON cart_items(session_id);
CREATE INDEX IF NOT EXISTS idx_cart_tool ON cart_items(tool_id);
CREATE INDEX IF NOT EXISTS idx_cart_session_tool ON cart_items(session_id, tool_id);
