-- Template Marketplace Database Schema - PostgreSQL Version
-- Compatible with Replit PostgreSQL
-- Production version uses MySQL (see schema.sql)

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS withdrawal_requests CASCADE;
DROP TABLE IF EXISTS sales CASCADE;
DROP TABLE IF EXISTS pending_orders CASCADE;
DROP TABLE IF EXISTS domains CASCADE;
DROP TABLE IF EXISTS affiliates CASCADE;
DROP TABLE IF EXISTS templates CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS settings CASCADE;

-- Settings Table
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_setting_key ON settings(setting_key);

-- Users Table (Admin and Affiliates)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'affiliate')),
    bank_details TEXT,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- Templates Table
CREATE TABLE templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    description TEXT,
    features TEXT,
    demo_url VARCHAR(500),
    thumbnail_url VARCHAR(500),
    video_links TEXT,
    active SMALLINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_templates_slug ON templates(slug);
CREATE INDEX idx_templates_active ON templates(active);
CREATE INDEX idx_templates_category ON templates(category);

-- Affiliates Table
CREATE TABLE affiliates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    total_clicks INTEGER DEFAULT 0,
    total_sales INTEGER DEFAULT 0,
    commission_earned DECIMAL(10,2) DEFAULT 0.00,
    commission_pending DECIMAL(10,2) DEFAULT 0.00,
    commission_paid DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_affiliates_code ON affiliates(code);
CREATE INDEX idx_affiliates_user_id ON affiliates(user_id);
CREATE INDEX idx_affiliates_status ON affiliates(status);

-- Domains Table
CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'available' CHECK (status IN ('available', 'in_use', 'suspended')),
    assigned_customer_id INTEGER NULL,
    assigned_order_id INTEGER NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
);

CREATE INDEX idx_domains_template_id ON domains(template_id);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_domain_name ON domains(domain_name);

-- Pending Orders Table
CREATE TABLE pending_orders (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL,
    chosen_domain_id INTEGER NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    business_name VARCHAR(255),
    custom_fields TEXT,
    affiliate_code VARCHAR(50) NULL,
    session_id VARCHAR(255),
    message_text TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (chosen_domain_id) REFERENCES domains(id) ON DELETE SET NULL
);

CREATE INDEX idx_pending_orders_template_id ON pending_orders(template_id);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_affiliate_code ON pending_orders(affiliate_code);
CREATE INDEX idx_pending_orders_customer_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_created_at ON pending_orders(created_at);

-- Sales Table
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    pending_order_id INTEGER NOT NULL,
    admin_id INTEGER NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    affiliate_id INTEGER NULL,
    payment_method VARCHAR(100) DEFAULT 'WhatsApp',
    payment_notes TEXT,
    payment_confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL
);

CREATE INDEX idx_sales_pending_order_id ON sales(pending_order_id);
CREATE INDEX idx_sales_admin_id ON sales(admin_id);
CREATE INDEX idx_sales_affiliate_id ON sales(affiliate_id);
CREATE INDEX idx_sales_payment_confirmed_at ON sales(payment_confirmed_at);

-- Withdrawal Requests Table
CREATE TABLE withdrawal_requests (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    bank_details_json TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'paid')),
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INTEGER NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_withdrawal_requests_affiliate_id ON withdrawal_requests(affiliate_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);
CREATE INDEX idx_withdrawal_requests_requested_at ON withdrawal_requests(requested_at);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_number', '+2348012345678'),
('site_name', 'Template Marketplace'),
('commission_rate', '0.30'),
('affiliate_cookie_days', '30');

-- Insert Default Admin User (password: admin123 - CHANGE IN PRODUCTION!)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin User', 'admin@example.com', '08012345678', '$2y$10$0pQ/10kvKYTQuZ.zKAPF2OWo4YPzDs472F3r.L/8gUvY04IAfivAe', 'admin');

-- Insert Sample Templates
INSERT INTO templates (name, slug, price, category, description, features, demo_url, thumbnail_url, active) VALUES
('E-commerce Store', 'ecommerce-store', 200000.00, 'Business', 'Complete online store with product catalog, cart, and checkout', 'Product Management, Shopping Cart, Order Tracking, Payment Integration', 'https://demo.example.com/ecommerce', 'https://via.placeholder.com/400x300/28a745/ffffff?text=E-commerce', 1),
('Portfolio Website', 'portfolio-site', 150000.00, 'Personal', 'Professional portfolio to showcase your work and skills', 'Gallery, About Section, Contact Form, Responsive Design', 'https://demo.example.com/portfolio', 'https://via.placeholder.com/400x300/007bff/ffffff?text=Portfolio', 1),
('Business Website', 'business-site', 180000.00, 'Business', 'Corporate website for businesses and startups', 'About Us, Services, Team, Contact Form, Blog', 'https://demo.example.com/business', 'https://via.placeholder.com/400x300/6c757d/ffffff?text=Business', 1);

-- Insert Sample Domains
INSERT INTO domains (template_id, domain_name, status) VALUES
(1, 'mystore.ng', 'available'),
(1, 'newshop.com.ng', 'available'),
(2, 'johnportfolio.ng', 'available'),
(2, 'janedesigns.com', 'available'),
(3, 'bizpro.ng', 'available'),
(3, 'startupco.com.ng', 'available');
