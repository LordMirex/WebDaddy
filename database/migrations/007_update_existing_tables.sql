-- Migration: Update existing tables
-- Add payment and delivery fields to pending_orders, tools, templates

-- Update pending_orders table
ALTER TABLE pending_orders ADD COLUMN payment_method TEXT DEFAULT 'manual';
ALTER TABLE pending_orders ADD COLUMN payment_verified_at TEXT NULL;
ALTER TABLE pending_orders ADD COLUMN delivery_status TEXT DEFAULT 'pending';
ALTER TABLE pending_orders ADD COLUMN email_verified INTEGER DEFAULT 0;
ALTER TABLE pending_orders ADD COLUMN paystack_payment_id TEXT;

-- Update tools table
ALTER TABLE tools ADD COLUMN delivery_type TEXT DEFAULT 'both';
ALTER TABLE tools ADD COLUMN has_attached_files INTEGER DEFAULT 0;
ALTER TABLE tools ADD COLUMN requires_email INTEGER DEFAULT 1;
ALTER TABLE tools ADD COLUMN email_subject TEXT;
ALTER TABLE tools ADD COLUMN email_instructions TEXT;
ALTER TABLE tools ADD COLUMN delivery_note TEXT;
ALTER TABLE tools ADD COLUMN delivery_description TEXT;
ALTER TABLE tools ADD COLUMN total_files INTEGER DEFAULT 0;

-- Update templates table
ALTER TABLE templates ADD COLUMN delivery_type TEXT DEFAULT 'hosted_domain';
ALTER TABLE templates ADD COLUMN requires_email INTEGER DEFAULT 1;
ALTER TABLE templates ADD COLUMN delivery_wait_hours INTEGER DEFAULT 24;
ALTER TABLE templates ADD COLUMN delivery_note TEXT;
ALTER TABLE templates ADD COLUMN delivery_description TEXT;
ALTER TABLE templates ADD COLUMN domain_template TEXT;
