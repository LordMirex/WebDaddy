-- Template Marketplace Database Schema - PostgreSQL Version
-- WebDaddy Empire Database Schema

-- Enable UUID extension if needed for future use
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS announcement_emails CASCADE;
DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS announcements CASCADE;
DROP TABLE IF EXISTS withdrawal_requests CASCADE;
DROP TABLE IF EXISTS sales CASCADE;
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS pending_orders CASCADE;
DROP TABLE IF EXISTS domains CASCADE;
DROP TABLE IF EXISTS affiliates CASCADE;
DROP TABLE IF EXISTS tools CASCADE;
DROP TABLE IF EXISTS templates CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS settings CASCADE;

-- Settings Table
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_settings_key ON settings(setting_key);

-- Users Table (Admin and Affiliates)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK(role IN ('admin', 'affiliate')),
    bank_details TEXT,
    status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
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
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    description TEXT,
    features TEXT,
    demo_url TEXT,
    thumbnail_url TEXT,
    video_links TEXT,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_templates_slug ON templates(slug);
CREATE INDEX idx_templates_active ON templates(active);
CREATE INDEX idx_templates_category ON templates(category);

-- Tools Table (Digital Products)
CREATE TABLE tools (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    description TEXT,
    features TEXT,
    icon_class VARCHAR(100),
    thumbnail_url TEXT,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tools_slug ON tools(slug);
CREATE INDEX idx_tools_active ON tools(active);
CREATE INDEX idx_tools_category ON tools(category);

-- Affiliates Table
CREATE TABLE affiliates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code VARCHAR(50) NOT NULL UNIQUE,
    total_clicks INTEGER DEFAULT 0,
    total_sales INTEGER DEFAULT 0,
    commission_earned DECIMAL(10, 2) DEFAULT 0.00,
    commission_pending DECIMAL(10, 2) DEFAULT 0.00,
    commission_paid DECIMAL(10, 2) DEFAULT 0.00,
    custom_commission_rate DECIMAL(5, 2) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_affiliates_code ON affiliates(code);
CREATE INDEX idx_affiliates_user_id ON affiliates(user_id);
CREATE INDEX idx_affiliates_status ON affiliates(status);

-- Domains Table
CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE CASCADE,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'available' CHECK(status IN ('available', 'in_use', 'suspended')),
    assigned_customer_id INTEGER,
    assigned_order_id INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_domains_template_id ON domains(template_id);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_domain_name ON domains(domain_name);

-- Pending Orders Table (Order Header)
CREATE TABLE pending_orders (
    id SERIAL PRIMARY KEY,
    template_id INTEGER REFERENCES templates(id) ON DELETE RESTRICT,
    tool_id INTEGER REFERENCES tools(id) ON DELETE RESTRICT,
    order_type VARCHAR(20) DEFAULT 'template' CHECK(order_type IN ('template', 'tools', 'mixed')),
    chosen_domain_id INTEGER REFERENCES domains(id) ON DELETE SET NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    business_name VARCHAR(255),
    custom_fields TEXT,
    affiliate_code VARCHAR(50) REFERENCES affiliates(code) ON DELETE SET NULL,
    session_id VARCHAR(255),
    message_text TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK(status IN ('pending', 'paid', 'cancelled')),
    ip_address VARCHAR(45),
    original_price DECIMAL(10, 2),
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    final_amount DECIMAL(10, 2),
    quantity INTEGER DEFAULT 1,
    cart_snapshot TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pending_orders_template_id ON pending_orders(template_id);
CREATE INDEX idx_pending_orders_tool_id ON pending_orders(tool_id);
CREATE INDEX idx_pending_orders_order_type ON pending_orders(order_type);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_affiliate_code ON pending_orders(affiliate_code);
CREATE INDEX idx_pending_orders_customer_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_created_at ON pending_orders(created_at);

-- Order Items Table (Normalized Line Items for Multi-Product Orders)
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    product_type VARCHAR(20) NOT NULL CHECK(product_type IN ('template', 'tool')),
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    final_amount DECIMAL(10, 2) NOT NULL,
    metadata_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_order_items_pending_order_id ON order_items(pending_order_id);
CREATE INDEX idx_order_items_product_type_id ON order_items(product_type, product_id);
CREATE INDEX idx_order_items_created_at ON order_items(created_at);

-- Sales Table
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE RESTRICT,
    admin_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    amount_paid DECIMAL(10, 2) NOT NULL,
    commission_amount DECIMAL(10, 2) DEFAULT 0.00,
    affiliate_id INTEGER REFERENCES affiliates(id) ON DELETE SET NULL,
    payment_method VARCHAR(50) DEFAULT 'WhatsApp',
    payment_notes TEXT,
    payment_confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sales_pending_order_id ON sales(pending_order_id);
CREATE INDEX idx_sales_admin_id ON sales(admin_id);
CREATE INDEX idx_sales_affiliate_id ON sales(affiliate_id);
CREATE INDEX idx_sales_payment_confirmed_at ON sales(payment_confirmed_at);

-- Withdrawal Requests Table
CREATE TABLE withdrawal_requests (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    bank_details_json TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected', 'paid')),
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    processed_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_withdrawal_requests_affiliate_id ON withdrawal_requests(affiliate_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);
CREATE INDEX idx_withdrawal_requests_requested_at ON withdrawal_requests(requested_at);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Announcements Table (for affiliate dashboard)
CREATE TABLE announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'info',
    is_active BOOLEAN DEFAULT true,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    affiliate_id INTEGER DEFAULT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    expires_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_announcements_is_active ON announcements(is_active);
CREATE INDEX idx_announcements_created_at ON announcements(created_at);
CREATE INDEX idx_announcements_affiliate_id ON announcements(affiliate_id);
CREATE INDEX idx_announcements_expires_at ON announcements(expires_at);

-- Announcement Email Delivery Tracking
CREATE TABLE announcement_emails (
    id SERIAL PRIMARY KEY,
    announcement_id INTEGER NOT NULL REFERENCES announcements(id) ON DELETE CASCADE,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    email_address VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    failed BOOLEAN DEFAULT false,
    error_message TEXT
);

CREATE INDEX idx_announcement_emails_announcement_id ON announcement_emails(announcement_id);
CREATE INDEX idx_announcement_emails_affiliate_id ON announcement_emails(affiliate_id);
CREATE INDEX idx_announcement_emails_sent_at ON announcement_emails(sent_at);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_number', '+2349132672126'),
('site_name', 'WebDaddy Empire'),
('commission_rate', '0.30'),
('affiliate_cookie_days', '30');

-- Insert Default Admin User (password: admin123)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin User', 'admin@example.com', '08012345678', '$2y$10$.c8E/ZBHQ9cVaJWxo6G9EekJyqPAZ8C2LbC8CptJ2OAYCnvKs0dwC', 'admin');

-- Insert Sample Templates
INSERT INTO templates (name, slug, price, category, description, features, demo_url, thumbnail_url, active) VALUES
('E-commerce Store', 'ecommerce-store', 200000.00, 'Business', 'Complete online store with product catalog, cart, and checkout', 'Product Management, Shopping Cart, Order Tracking, Payment Integration', 'https://startbootstrap.com/previews/shop-homepage', 'https://via.placeholder.com/400x300/28a745/ffffff?text=E-commerce', true),
('Portfolio Website', 'portfolio-site', 150000.00, 'Personal', 'Professional portfolio to showcase your work and skills', 'Gallery, About Section, Contact Form, Responsive Design', 'https://startbootstrap.com/previews/freelancer', 'https://via.placeholder.com/400x300/007bff/ffffff?text=Portfolio', true),
('Business Website', 'business-site', 180000.00, 'Business', 'Corporate website for businesses and startups', 'About Us, Services, Team, Contact Form, Blog', 'https://startbootstrap.com/previews/creative', 'https://via.placeholder.com/400x300/6c757d/ffffff?text=Business', true),
('Restaurant & Cafe', 'restaurant-cafe', 160000.00, 'Food & Beverage', 'Beautiful restaurant website with menu display, reservations, and gallery', 'Online Menu, Reservation System, Photo Gallery, Location Map, Customer Reviews', 'https://startbootstrap.com/previews/grayscale', 'https://via.placeholder.com/400x300/DC3545/ffffff?text=Restaurant', true),
('Real Estate Agency', 'real-estate-agency', 220000.00, 'Real Estate', 'Professional real estate website with property listings and agent profiles', 'Property Listings, Search Filters, Agent Profiles, Mortgage Calculator, Property Details', 'https://startbootstrap.com/previews/heroic-features', 'https://via.placeholder.com/400x300/17A2B8/ffffff?text=Real+Estate', true),
('Fitness & Gym', 'fitness-gym', 140000.00, 'Health & Fitness', 'Modern gym website with class schedules, trainer profiles, and membership options', 'Class Schedule, Trainer Profiles, Membership Plans, BMI Calculator, Gallery', 'https://startbootstrap.com/previews/bare', 'https://via.placeholder.com/400x300/28A745/ffffff?text=Fitness+Gym', true),
('Beauty Salon', 'beauty-salon', 130000.00, 'Beauty & Spa', 'Elegant beauty salon website with service booking and stylist profiles', 'Service Booking, Stylist Profiles, Price List, Appointment System, Gallery', 'https://startbootstrap.com/previews/stylish-portfolio', 'https://via.placeholder.com/400x300/E83E8C/ffffff?text=Beauty+Salon', true),
('Blog & Magazine', 'blog-magazine', 120000.00, 'Media', 'Modern blog and magazine website with multiple layouts and categories', 'Multiple Layouts, Categories, Author Profiles, Social Sharing, Newsletter', 'https://startbootstrap.com/previews/clean-blog', 'https://via.placeholder.com/400x300/6F42C1/ffffff?text=Blog+Magazine', true),
('Photography Studio', 'photography-studio', 170000.00, 'Creative', 'Stunning photography portfolio with gallery and booking system', 'Photo Gallery, Portfolio, Client Showcase, Booking System, About Page', 'https://startbootstrap.com/previews/photography', 'https://via.placeholder.com/400x300/20C997/ffffff?text=Photography', true),
('Consulting Firm', 'consulting-firm', 190000.00, 'Business', 'Professional consulting website with services, case studies, and team profiles', 'Service Pages, Case Studies, Team Profiles, Testimonials, Contact Forms', 'https://startbootstrap.com/previews/business-frontpage', 'https://via.placeholder.com/400x300/FD7E14/ffffff?text=Consulting', true),
('Law Firm', 'law-firm', 210000.00, 'Legal', 'Authoritative law firm website with practice areas and attorney profiles', 'Practice Areas, Attorney Profiles, Case Results, Contact Forms, Blog', 'https://startbootstrap.com/previews/modern-business', 'https://via.placeholder.com/400x300/6C757D/ffffff?text=Law+Firm', true);

-- Insert Sample Tools
INSERT INTO tools (name, slug, price, category, description, features, icon_class, active) VALUES
('ChatGPT API Key', 'chatgpt-api-key', 50000.00, 'AI & Automation', 'OpenAI ChatGPT API access for AI-powered applications', 'GPT-4 Access, Chat Completions, Text Generation, AI Integration', 'fas fa-robot', true),
('Google Maps API', 'google-maps-api', 35000.00, 'APIs', 'Google Maps integration for location-based features', 'Maps Display, Geocoding, Directions, Places API', 'fas fa-map-marked-alt', true),
('Stripe Payment Gateway', 'stripe-payment', 45000.00, 'Payment', 'Accept online payments with Stripe integration', 'Card Payments, Subscriptions, Invoicing, Analytics', 'fas fa-credit-card', true),
('SMS Service (Twilio)', 'sms-service-twilio', 40000.00, 'Communication', 'Send SMS messages and notifications via Twilio', 'SMS Sending, 2FA, Notifications, Multi-Country', 'fas fa-sms', true),
('Email Automation', 'email-automation', 30000.00, 'Marketing', 'Automated email marketing and transactional emails', 'Campaign Management, Templates, Analytics, Scheduling', 'fas fa-envelope-open-text', true),
('Social Media Manager', 'social-media-manager', 55000.00, 'Marketing', 'Manage and schedule social media posts across platforms', 'Multi-Platform Posting, Analytics, Scheduling, Content Calendar', 'fas fa-share-alt', true),
('Cloud Storage', 'cloud-storage', 25000.00, 'Storage', 'Secure cloud storage for your business files', 'File Storage, Backup, Sharing, Version Control', 'fas fa-cloud-upload-alt', true),
('Analytics Dashboard', 'analytics-dashboard', 60000.00, 'Analytics', 'Comprehensive analytics and reporting dashboard', 'Real-time Analytics, Custom Reports, Data Visualization', 'fas fa-chart-line', true);

-- Insert Sample Domains (44 total - matching templates)
INSERT INTO domains (template_id, domain_name, status) VALUES
-- E-commerce Store (template_id: 1)
(1, 'mystore.ng', 'available'),
(1, 'newshop.com.ng', 'available'),
(1, 'shoponline.ng', 'available'),
(1, 'ecommerce.ng', 'available'),
-- Portfolio Website (template_id: 2)
(2, 'johnportfolio.ng', 'available'),
(2, 'janedesigns.com', 'available'),
(2, 'myportfolio.ng', 'available'),
(2, 'creativework.ng', 'available'),
-- Business Website (template_id: 3)
(3, 'bizpro.ng', 'available'),
(3, 'startupco.com.ng', 'available'),
(3, 'corporate.ng', 'available'),
(3, 'businesspro.ng', 'available'),
-- Restaurant & Cafe (template_id: 4)
(4, 'delicious.ng', 'available'),
(4, 'cafe-corner.ng', 'available'),
(4, 'restaurant.ng', 'available'),
(4, 'foodie.ng', 'available'),
-- Real Estate Agency (template_id: 5)
(5, 'property.ng', 'available'),
(5, 'realestate.ng', 'available'),
(5, 'homes.ng', 'available'),
(5, 'propertypro.ng', 'available'),
-- Fitness & Gym (template_id: 6)
(6, 'fitlife.ng', 'available'),
(6, 'gympro.ng', 'available'),
(6, 'fitness.ng', 'available'),
(6, 'workout.ng', 'available'),
-- Beauty Salon (template_id: 7)
(7, 'beautysalon.ng', 'available'),
(7, 'spa.ng', 'available'),
(7, 'beautypro.ng', 'available'),
(7, 'salon.ng', 'available'),
-- Blog & Magazine (template_id: 8)
(8, 'blog.ng', 'available'),
(8, 'magazine.ng', 'available'),
(8, 'news.ng', 'available'),
(8, 'articles.ng', 'available'),
-- Photography Studio (template_id: 9)
(9, 'photostudio.ng', 'available'),
(9, 'camerawork.ng', 'available'),
(9, 'photography.ng', 'available'),
(9, 'lens.ng', 'available'),
-- Consulting Firm (template_id: 10)
(10, 'consulting.ng', 'available'),
(10, 'advisors.ng', 'available'),
(10, 'consultpro.ng', 'available'),
(10, 'expert.ng', 'available'),
-- Law Firm (template_id: 11)
(11, 'lawfirm.ng', 'available'),
(11, 'legal.ng', 'available'),
(11, 'attorneys.ng', 'available'),
(11, 'lawpro.ng', 'available');
