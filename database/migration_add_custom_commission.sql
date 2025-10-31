-- Migration: Add custom commission rate per affiliate
-- Date: 2025-10-31
-- Description: Allows admin to set custom commission rates for individual affiliates

-- Add custom_commission_rate column to affiliates table
-- NULL means use the default AFFILIATE_COMMISSION_RATE from config
ALTER TABLE affiliates 
ADD COLUMN custom_commission_rate DECIMAL(5,4) DEFAULT NULL;

-- Add comment
COMMENT ON COLUMN affiliates.custom_commission_rate IS 'Custom commission rate for this affiliate (e.g., 0.35 for 35%). NULL uses default rate from config.';

-- Index for querying affiliates with custom rates
CREATE INDEX idx_affiliates_custom_rate ON affiliates(custom_commission_rate) WHERE custom_commission_rate IS NOT NULL;
