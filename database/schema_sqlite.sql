-- Template Marketplace Database Schema - SQLite Version
-- Portable single-file database for WebDaddy Empire
-- Compatible with any PHP environment

-- Enable foreign key support (must be set per connection in SQLite)
PRAGMA foreign_keys = ON;

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS announcement_emails;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS withdrawal_requests;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS draft_orders;
DROP TABLE IF EXISTS pending_orders;
DROP TABLE IF EXISTS domains;
DROP TABLE IF EXISTS affiliates;
DROP TABLE IF EXISTS tools;
DROP TABLE IF EXISTS templates;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- Settings Table
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_settings_key ON settings(setting_key);

-- Users Table (Admin and Affiliates)
-- Using CHECK constraints instead of ENUMs
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin', 'affiliate')),
    bank_details TEXT,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- Templates Table
CREATE TABLE templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    price REAL NOT NULL DEFAULT 0.00,
    category TEXT,
    description TEXT,
    features TEXT,
    demo_url TEXT,
    thumbnail_url TEXT,
    video_links TEXT,
    active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_templates_slug ON templates(slug);
CREATE INDEX idx_templates_active ON templates(active);
CREATE INDEX idx_templates_category ON templates(category);

-- Tools Table (Digital Products)
CREATE TABLE tools (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    category TEXT,
    tool_type TEXT DEFAULT 'software',
    short_description TEXT,
    description TEXT,
    features TEXT,
    price REAL NOT NULL DEFAULT 0,
    thumbnail_url TEXT,
    demo_url TEXT,
    download_url TEXT,
    delivery_instructions TEXT,
    stock_unlimited INTEGER DEFAULT 1,
    stock_quantity INTEGER DEFAULT 0,
    low_stock_threshold INTEGER DEFAULT 5,
    active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tools_slug ON tools(slug);
CREATE INDEX idx_tools_active ON tools(active);
CREATE INDEX idx_tools_category ON tools(category);
CREATE INDEX idx_tools_stock ON tools(stock_unlimited, stock_quantity);

-- Affiliates Table
CREATE TABLE affiliates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code TEXT NOT NULL UNIQUE,
    total_clicks INTEGER DEFAULT 0,
    total_sales INTEGER DEFAULT 0,
    commission_earned REAL DEFAULT 0.00,
    commission_pending REAL DEFAULT 0.00,
    commission_paid REAL DEFAULT 0.00,
    custom_commission_rate REAL DEFAULT NULL,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_affiliates_code ON affiliates(code);
CREATE INDEX idx_affiliates_user_id ON affiliates(user_id);
CREATE INDEX idx_affiliates_status ON affiliates(status);

-- Domains Table
CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE CASCADE,
    domain_name TEXT NOT NULL UNIQUE,
    status TEXT DEFAULT 'available' CHECK(status IN ('available', 'in_use', 'suspended')),
    assigned_customer_id INTEGER,
    assigned_order_id INTEGER,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_domains_template_id ON domains(template_id);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_domain_name ON domains(domain_name);

-- Draft Orders Table (Cart Abandonment Recovery)
CREATE TABLE draft_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cart_snapshot TEXT NOT NULL,
    customer_email TEXT,
    session_id TEXT NOT NULL,
    ip_address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_draft_orders_session_id ON draft_orders(session_id);
CREATE INDEX idx_draft_orders_customer_email ON draft_orders(customer_email);
CREATE INDEX idx_draft_orders_created_at ON draft_orders(created_at);

-- Pending Orders Table (Order Header)
-- Updated to support both templates and tools, plus multi-item orders
CREATE TABLE pending_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER REFERENCES templates(id) ON DELETE RESTRICT,
    tool_id INTEGER REFERENCES tools(id) ON DELETE RESTRICT,
    order_type TEXT DEFAULT 'template' CHECK(order_type IN ('template', 'tools', 'mixed')),
    chosen_domain_id INTEGER REFERENCES domains(id) ON DELETE SET NULL,
    customer_name TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    customer_phone TEXT NOT NULL,
    business_name TEXT,
    custom_fields TEXT,
    affiliate_code TEXT REFERENCES affiliates(code) ON DELETE SET NULL,
    session_id TEXT,
    message_text TEXT,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'paid', 'cancelled')),
    ip_address TEXT,
    original_price REAL,
    discount_amount REAL DEFAULT 0.00,
    final_amount REAL,
    quantity INTEGER DEFAULT 1,
    cart_snapshot TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pending_orders_template_id ON pending_orders(template_id);
CREATE INDEX idx_pending_orders_tool_id ON pending_orders(tool_id);
CREATE INDEX idx_pending_orders_order_type ON pending_orders(order_type);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_affiliate_code ON pending_orders(affiliate_code);
CREATE INDEX idx_pending_orders_customer_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_created_at ON pending_orders(created_at);

-- Order Items Table (Normalized Line Items for Multi-Product Orders)
-- Supports granular tracking of each product in an order
CREATE TABLE order_items (
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

CREATE INDEX idx_order_items_pending_order_id ON order_items(pending_order_id);
CREATE INDEX idx_order_items_product_type_id ON order_items(product_type, product_id);
CREATE INDEX idx_order_items_created_at ON order_items(created_at);

-- Sales Table
CREATE TABLE sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE RESTRICT,
    admin_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    amount_paid REAL NOT NULL,
    commission_amount REAL DEFAULT 0.00,
    affiliate_id INTEGER REFERENCES affiliates(id) ON DELETE SET NULL,
    payment_method TEXT DEFAULT 'WhatsApp',
    payment_notes TEXT,
    payment_confirmed_at TEXT DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sales_pending_order_id ON sales(pending_order_id);
CREATE INDEX idx_sales_admin_id ON sales(admin_id);
CREATE INDEX idx_sales_affiliate_id ON sales(affiliate_id);
CREATE INDEX idx_sales_payment_confirmed_at ON sales(payment_confirmed_at);

-- Withdrawal Requests Table
CREATE TABLE withdrawal_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    amount REAL NOT NULL,
    bank_details_json TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected', 'paid')),
    admin_notes TEXT,
    requested_at TEXT DEFAULT CURRENT_TIMESTAMP,
    processed_at TEXT,
    processed_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_withdrawal_requests_affiliate_id ON withdrawal_requests(affiliate_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);
CREATE INDEX idx_withdrawal_requests_requested_at ON withdrawal_requests(requested_at);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Announcements Table (for affiliate dashboard)
CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    is_active INTEGER DEFAULT 1,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    affiliate_id INTEGER DEFAULT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    expires_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_announcements_is_active ON announcements(is_active);
CREATE INDEX idx_announcements_created_at ON announcements(created_at);
CREATE INDEX idx_announcements_affiliate_id ON announcements(affiliate_id);
CREATE INDEX idx_announcements_expires_at ON announcements(expires_at);

-- Announcement Email Delivery Tracking
CREATE TABLE announcement_emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    announcement_id INTEGER NOT NULL,
    affiliate_id INTEGER NOT NULL,
    email_address TEXT NOT NULL,
    sent_at TEXT DEFAULT CURRENT_TIMESTAMP,
    failed INTEGER DEFAULT 0,
    error_message TEXT,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
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
-- Password hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin User', 'admin@example.com', '08012345678', '$2y$10$.c8E/ZBHQ9cVaJWxo6G9EekJyqPAZ8C2LbC8CptJ2OAYCnvKs0dwC', 'admin');

-- Insert Sample Templates
INSERT INTO templates (name, slug, price, category, description, features, demo_url, thumbnail_url, active) VALUES
('E-commerce Store', 'ecommerce-store', 200000.00, 'Business', 'Complete online store with product catalog, cart, and checkout', 'Product Management, Shopping Cart, Order Tracking, Payment Integration', 'https://startbootstrap.com/previews/shop-homepage', 'https://via.placeholder.com/400x300/28a745/ffffff?text=E-commerce', 1),
('Portfolio Website', 'portfolio-site', 150000.00, 'Personal', 'Professional portfolio to showcase your work and skills', 'Gallery, About Section, Contact Form, Responsive Design', 'https://startbootstrap.com/previews/freelancer', 'https://via.placeholder.com/400x300/007bff/ffffff?text=Portfolio', 1),
('Business Website', 'business-site', 180000.00, 'Business', 'Corporate website for businesses and startups', 'About Us, Services, Team, Contact Form, Blog', 'https://startbootstrap.com/previews/creative', 'https://via.placeholder.com/400x300/6c757d/ffffff?text=Business', 1),
('Restaurant & Cafe', 'restaurant-cafe', 160000.00, 'Food & Beverage', 'Beautiful restaurant website with menu display, reservations, and gallery', 'Online Menu, Reservation System, Photo Gallery, Location Map, Customer Reviews', 'https://startbootstrap.com/previews/grayscale', 'https://via.placeholder.com/400x300/DC3545/ffffff?text=Restaurant', 1),
('Real Estate Agency', 'real-estate-agency', 220000.00, 'Real Estate', 'Professional real estate website with property listings and agent profiles', 'Property Listings, Search Filters, Agent Profiles, Mortgage Calculator, Property Details', 'https://startbootstrap.com/previews/heroic-features', 'https://via.placeholder.com/400x300/17A2B8/ffffff?text=Real+Estate', 1),
('Fitness & Gym', 'fitness-gym', 140000.00, 'Health & Fitness', 'Modern gym website with class schedules, trainer profiles, and membership options', 'Class Schedule, Trainer Profiles, Membership Plans, BMI Calculator, Gallery', 'https://startbootstrap.com/previews/bare', 'https://via.placeholder.com/400x300/28A745/ffffff?text=Fitness+Gym', 1),
('Beauty Salon', 'beauty-salon', 130000.00, 'Beauty & Spa', 'Elegant beauty salon website with service booking and stylist profiles', 'Service Booking, Stylist Profiles, Price List, Appointment System, Gallery', 'https://startbootstrap.com/previews/stylish-portfolio', 'https://via.placeholder.com/400x300/E83E8C/ffffff?text=Beauty+Salon', 1),
('Blog & Magazine', 'blog-magazine', 120000.00, 'Media', 'Modern blog and magazine website with multiple layouts and categories', 'Multiple Layouts, Categories, Author Profiles, Social Sharing, Newsletter', 'https://startbootstrap.com/previews/clean-blog', 'https://via.placeholder.com/400x300/6F42C1/ffffff?text=Blog+Magazine', 1),
('Photography Studio', 'photography-studio', 170000.00, 'Creative', 'Stunning photography portfolio with gallery and booking system', 'Photo Gallery, Portfolio, Client Showcase, Booking System, About Page', 'https://startbootstrap.com/previews/photography', 'https://via.placeholder.com/400x300/20C997/ffffff?text=Photography', 1),
('Consulting Firm', 'consulting-firm', 190000.00, 'Business', 'Professional consulting website with services, case studies, and team profiles', 'Service Pages, Case Studies, Team Profiles, Testimonials, Contact Forms', 'https://startbootstrap.com/previews/business-frontpage', 'https://via.placeholder.com/400x300/FD7E14/ffffff?text=Consulting', 1),
('Law Firm', 'law-firm', 210000.00, 'Legal', 'Authoritative law firm website with practice areas and attorney profiles', 'Practice Areas, Attorney Profiles, Case Results, Contact Forms, Blog', 'https://startbootstrap.com/previews/modern-business', 'https://via.placeholder.com/400x300/6C757D/ffffff?text=Law+Firm', 1);

-- Insert Sample Domains (44 total)
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
