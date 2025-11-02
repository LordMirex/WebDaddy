-- Template Marketplace Database Schema - PostgreSQL Version
-- Compatible with Replit PostgreSQL
-- This application now runs on PostgreSQL only

-- Drop existing types and tables (in reverse dependency order)
DROP TABLE IF EXISTS activity_logs CASCADE;
DROP TABLE IF EXISTS withdrawal_requests CASCADE;
DROP TABLE IF EXISTS sales CASCADE;
DROP TABLE IF EXISTS pending_orders CASCADE;
DROP TABLE IF EXISTS domains CASCADE;
DROP TABLE IF EXISTS affiliates CASCADE;
DROP TABLE IF EXISTS templates CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS settings CASCADE;

-- Drop existing types
DROP TYPE IF EXISTS withdrawal_status_enum CASCADE;
DROP TYPE IF EXISTS order_status_enum CASCADE;
DROP TYPE IF EXISTS domain_status_enum CASCADE;
DROP TYPE IF EXISTS status_enum CASCADE;
DROP TYPE IF EXISTS role_enum CASCADE;

-- Settings Table
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_settings_key ON settings(setting_key);

-- Users Table (Admin and Affiliates)
CREATE TYPE role_enum AS ENUM ('admin', 'affiliate');
CREATE TYPE status_enum AS ENUM ('active', 'inactive', 'suspended');

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role role_enum NOT NULL,
    bank_details TEXT,
    status status_enum DEFAULT 'active',
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
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_templates_slug ON templates(slug);
CREATE INDEX idx_templates_active ON templates(active);
CREATE INDEX idx_templates_category ON templates(category);

-- Affiliates Table
CREATE TABLE affiliates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code VARCHAR(50) NOT NULL UNIQUE,
    total_clicks INTEGER DEFAULT 0,
    total_sales INTEGER DEFAULT 0,
    commission_earned DECIMAL(10,2) DEFAULT 0.00,
    commission_pending DECIMAL(10,2) DEFAULT 0.00,
    commission_paid DECIMAL(10,2) DEFAULT 0.00,
    custom_commission_rate DECIMAL(5,4) DEFAULT NULL,
    status status_enum DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_affiliates_code ON affiliates(code);
CREATE INDEX idx_affiliates_user_id ON affiliates(user_id);
CREATE INDEX idx_affiliates_status ON affiliates(status);

-- Domains Table (without foreign key to pending_orders - will be added later)
CREATE TYPE domain_status_enum AS ENUM ('available', 'in_use', 'suspended');

CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE CASCADE,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status domain_status_enum DEFAULT 'available',
    assigned_customer_id INTEGER,
    assigned_order_id INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_domains_template_id ON domains(template_id);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_domain_name ON domains(domain_name);

-- Pending Orders Table
CREATE TYPE order_status_enum AS ENUM ('pending', 'paid', 'cancelled');

CREATE TABLE pending_orders (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE RESTRICT,
    chosen_domain_id INTEGER REFERENCES domains(id) ON DELETE SET NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    business_name VARCHAR(255),
    custom_fields TEXT,
    affiliate_code VARCHAR(50) REFERENCES affiliates(code) ON DELETE SET NULL,
    session_id VARCHAR(255),
    message_text TEXT,
    status order_status_enum DEFAULT 'pending',
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pending_orders_template_id ON pending_orders(template_id);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_affiliate_code ON pending_orders(affiliate_code);
CREATE INDEX idx_pending_orders_customer_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_created_at ON pending_orders(created_at);

-- Sales Table
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE RESTRICT,
    admin_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    amount_paid DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    affiliate_id INTEGER REFERENCES affiliates(id) ON DELETE SET NULL,
    payment_method VARCHAR(100) DEFAULT 'WhatsApp',
    payment_notes TEXT,
    payment_confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sales_pending_order_id ON sales(pending_order_id);
CREATE INDEX idx_sales_admin_id ON sales(admin_id);
CREATE INDEX idx_sales_affiliate_id ON sales(affiliate_id);
CREATE INDEX idx_sales_payment_confirmed_at ON sales(payment_confirmed_at);

-- Withdrawal Requests Table
CREATE TYPE withdrawal_status_enum AS ENUM ('pending', 'approved', 'rejected', 'paid');

CREATE TABLE withdrawal_requests (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    amount DECIMAL(10,2) NOT NULL,
    bank_details_json TEXT NOT NULL,
    status withdrawal_status_enum DEFAULT 'pending',
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
    type VARCHAR(50) DEFAULT 'info',
    is_active BOOLEAN DEFAULT true,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_announcements_is_active ON announcements(is_active);
CREATE INDEX idx_announcements_created_at ON announcements(created_at);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_number', '+2349132672126'),
('site_name', 'WebDaddy Empire'),
('commission_rate', '0.30'),
('affiliate_cookie_days', '30');

-- Insert Default Admin User (password: admin123)
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin User', 'admin@example.com', '08012345678', 'admin123', 'admin');

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

-- Insert Sample Domains
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

-- Add foreign key constraint for domains.assigned_order_id (after pending_orders table exists)
ALTER TABLE domains 
ADD CONSTRAINT fk_domains_pending_orders 
FOREIGN KEY (assigned_order_id) REFERENCES pending_orders(id) ON DELETE SET NULL;
