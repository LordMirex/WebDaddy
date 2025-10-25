-- Template Marketplace Database Schema
-- MySQL 5.7+ Compatible
-- Production-Ready with Foreign Keys and Indexes

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS withdrawal_requests;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS pending_orders;
DROP TABLE IF EXISTS domains;
DROP TABLE IF EXISTS affiliates;
DROP TABLE IF EXISTS templates;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- Settings Table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table (Admin and Affiliates)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','affiliate') NOT NULL,
    bank_details TEXT,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Templates Table
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    description TEXT,
    features TEXT,
    demo_url VARCHAR(500),
    thumbnail_url VARCHAR(500),
    video_links TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (active),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliates Table
CREATE TABLE affiliates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    total_clicks INT DEFAULT 0,
    total_sales INT DEFAULT 0,
    commission_earned DECIMAL(10,2) DEFAULT 0.00,
    commission_pending DECIMAL(10,2) DEFAULT 0.00,
    commission_paid DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domains Table
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('available','in_use','suspended') DEFAULT 'available',
    assigned_customer_id INT NULL,
    assigned_order_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_status (status),
    INDEX idx_domain_name (domain_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pending Orders Table
CREATE TABLE pending_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    chosen_domain_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    business_name VARCHAR(255),
    custom_fields TEXT,
    affiliate_code VARCHAR(50) NULL,
    session_id VARCHAR(255),
    message_text TEXT,
    status ENUM('pending','paid','cancelled') DEFAULT 'pending',
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (chosen_domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    INDEX idx_template_id (template_id),
    INDEX idx_status (status),
    INDEX idx_affiliate_code (affiliate_code),
    INDEX idx_customer_email (customer_email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Table
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pending_order_id INT NOT NULL,
    admin_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    affiliate_id INT NULL,
    payment_method VARCHAR(100) DEFAULT 'WhatsApp',
    payment_notes TEXT,
    payment_confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL,
    INDEX idx_pending_order_id (pending_order_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_affiliate_id (affiliate_id),
    INDEX idx_payment_confirmed_at (payment_confirmed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawal Requests Table
CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    bank_details_json TEXT NOT NULL,
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_affiliate_id (affiliate_id),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_number', '+2348012345678'),
('site_name', 'Template Marketplace'),
('commission_rate', '0.30'),
('affiliate_cookie_days', '30');

-- Insert Default Admin User (password: admin123 - CHANGE IN PRODUCTION!)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin User', 'admin@example.com', '08012345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

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
