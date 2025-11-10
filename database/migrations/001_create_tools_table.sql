-- Migration: Create tools table for Working Tools marketplace
-- Created: 2025-11-10
-- Description: Adds tools table to store digital products/working tools inventory

CREATE TABLE IF NOT EXISTS tools (
    -- Primary identification
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    
    -- Categorization
    category TEXT,
    tool_type TEXT DEFAULT 'software',
    
    -- Descriptions
    short_description TEXT,
    description TEXT,
    features TEXT,
    
    -- Pricing
    price REAL NOT NULL DEFAULT 0,
    
    -- Media
    thumbnail_url TEXT,
    demo_url TEXT,
    
    -- Delivery
    download_url TEXT,
    delivery_instructions TEXT,
    
    -- Inventory management
    stock_unlimited INTEGER DEFAULT 1,
    stock_quantity INTEGER DEFAULT 0,
    low_stock_threshold INTEGER DEFAULT 5,
    
    -- Status
    active INTEGER DEFAULT 1,
    
    -- Metadata
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_tools_active ON tools(active);
CREATE INDEX IF NOT EXISTS idx_tools_category ON tools(category);
CREATE INDEX IF NOT EXISTS idx_tools_slug ON tools(slug);
CREATE INDEX IF NOT EXISTS idx_tools_stock ON tools(stock_unlimited, stock_quantity);
