-- WebDaddy Empire - Template Marketplace Database Schema
-- SQLite Version - Current Production Schema
-- Generated from live database: database/webdaddy.db
-- All migrations have been applied and consolidated into this file
-- Last Updated: November 26, 2025

PRAGMA foreign_keys = ON;

-- Settings Table
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_settings_key ON settings(setting_key);

-- Customers Table (User Accounts)
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    username TEXT UNIQUE,
    full_name TEXT,
    whatsapp_number TEXT,
    password_hash TEXT,
    password_changed_at TIMESTAMP,
    last_login_at TIMESTAMP,
    status TEXT DEFAULT 'pending_setup' CHECK(status IN ('pending_setup', 'active', 'inactive', 'suspended')),
    email_verified INTEGER DEFAULT 0,
    registration_step INTEGER DEFAULT 0,
    account_complete INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_customers_username ON customers(username);
CREATE INDEX idx_customers_status ON customers(status);

-- Customer OTP Codes Table
CREATE TABLE customer_otp_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    email TEXT NOT NULL,
    otp_code TEXT NOT NULL,
    otp_type TEXT DEFAULT 'email_verify' CHECK(otp_type IN ('email_verify', 'password_reset')),
    delivery_method TEXT DEFAULT 'email',
    is_used INTEGER DEFAULT 0,
    used_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    email_sent INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_customer_otp_codes_customer ON customer_otp_codes(customer_id);
CREATE INDEX idx_customer_otp_codes_email ON customer_otp_codes(email);
CREATE INDEX idx_customer_otp_codes_code ON customer_otp_codes(otp_code);
CREATE INDEX idx_customer_otp_codes_type ON customer_otp_codes(otp_type);

-- Customer Sessions Table
CREATE TABLE customer_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    session_token TEXT NOT NULL UNIQUE,
    ip_address TEXT,
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_customer_sessions_customer ON customer_sessions(customer_id);
CREATE INDEX idx_customer_sessions_token ON customer_sessions(session_token);

-- Customer Activity Log Table
CREATE TABLE customer_activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_customer_activity_log_customer ON customer_activity_log(customer_id);
CREATE INDEX idx_customer_activity_log_action ON customer_activity_log(action);

-- Users Table (Admin and Affiliates)
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
    slug TEXT NOT NULL UNIQUE,
    price REAL NOT NULL DEFAULT 0.00,
    category TEXT,
    description TEXT,
    features TEXT,
    thumbnail_url TEXT,
    active INTEGER DEFAULT 1,
    delivery_note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_tools_slug ON tools(slug);
CREATE INDEX idx_tools_active ON tools(active);
CREATE INDEX idx_tools_category ON tools(category);

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

-- Pending Orders Table (Main Order Table - Phase 1)
CREATE TABLE pending_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER REFERENCES templates(id) ON DELETE SET NULL,
    tool_id INTEGER REFERENCES tools(id) ON DELETE SET NULL,
    order_type TEXT DEFAULT 'template' CHECK(order_type IN ('template', 'tool', 'mixed')),
    chosen_domain_id INTEGER REFERENCES domains(id) ON DELETE SET NULL,
    customer_name TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    customer_phone TEXT NOT NULL,
    business_name TEXT,
    custom_fields TEXT,
    affiliate_code TEXT REFERENCES affiliates(code) ON DELETE SET NULL,
    session_id TEXT,
    message_text TEXT,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'paid', 'cancelled', 'failed')),
    ip_address TEXT,
    original_price REAL,
    discount_amount REAL DEFAULT 0,
    final_amount REAL,
    quantity INTEGER DEFAULT 1,
    cart_snapshot TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    payment_notes TEXT,
    payment_method TEXT DEFAULT 'manual',
    payment_verified_at TIMESTAMP NULL,
    delivery_status TEXT DEFAULT 'pending',
    email_verified INTEGER DEFAULT 0,
    paystack_payment_id TEXT
);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_affiliate ON pending_orders(affiliate_code);

-- Order Items Table (Phase 2 - Mixed Orders Support)
CREATE TABLE order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    product_type TEXT NOT NULL CHECK(product_type IN ('template', 'tool')),
    product_id INTEGER NOT NULL,
    template_name TEXT,
    tool_name TEXT,
    price REAL NOT NULL,
    quantity INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_order_items_pending_order ON order_items(pending_order_id);
CREATE INDEX idx_order_items_product_type ON order_items(product_type);

-- Deliveries Table (Phase 3-5 Delivery System)
CREATE TABLE deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    product_type TEXT NOT NULL CHECK(product_type IN ('template', 'tool')),
    product_id INTEGER NOT NULL,
    product_name TEXT,
    delivery_status TEXT DEFAULT 'pending' CHECK(delivery_status IN ('pending', 'ready', 'sent', 'delivered', 'failed', 'retrying')),
    
    -- Template Credentials (Phase 2)
    hosted_domain TEXT,
    domain_login_url TEXT,
    template_admin_username TEXT,
    template_admin_password_encrypted TEXT,
    template_admin_password_iv TEXT,
    hosting_type TEXT CHECK(hosting_type IN ('wordpress', 'cpanel', 'custom', 'static')),
    credentials_sent_at TIMESTAMP,
    
    -- Tool Delivery (Phase 3)
    download_link TEXT,
    download_count INTEGER DEFAULT 0,
    download_limit INTEGER DEFAULT 10,
    download_expires_at TIMESTAMP,
    
    -- Email Tracking (Phase 4-5)
    email_sent_at TIMESTAMP,
    email_status TEXT DEFAULT 'pending',
    email_attempts INTEGER DEFAULT 0,
    
    -- Retry Mechanism (Phase 3)
    retry_count INTEGER DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    delivered_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_deliveries_order ON deliveries(pending_order_id);
CREATE INDEX idx_deliveries_status ON deliveries(delivery_status);
CREATE INDEX idx_deliveries_credentials ON deliveries(template_admin_username, credentials_sent_at);
CREATE INDEX idx_deliveries_retry ON deliveries(delivery_status, next_retry_at);

-- Tool Files Table (Phase 3)
CREATE TABLE tool_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tool_id INTEGER NOT NULL REFERENCES tools(id) ON DELETE CASCADE,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    file_type TEXT,
    upload_date TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_tool_files_tool_id ON tool_files(tool_id);

-- Download Tokens Table (Phase 3)
CREATE TABLE download_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL REFERENCES tool_files(id) ON DELETE CASCADE,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    token TEXT NOT NULL UNIQUE,
    download_count INTEGER DEFAULT 0,
    max_downloads INTEGER DEFAULT 10,
    expires_at TEXT NOT NULL,
    is_bundle INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_download_tokens_token ON download_tokens(token);
CREATE INDEX idx_download_tokens_order ON download_tokens(pending_order_id);
CREATE INDEX idx_download_tokens_file ON download_tokens(file_id);

-- Bundle Downloads Table (Phase 3.3)
CREATE TABLE bundle_downloads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id INTEGER NOT NULL REFERENCES download_tokens(id) ON DELETE CASCADE,
    tool_id INTEGER NOT NULL REFERENCES tools(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    zip_path TEXT NOT NULL,
    zip_name TEXT NOT NULL,
    file_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_bundle_downloads_token ON bundle_downloads(token_id);
CREATE INDEX idx_bundle_downloads_order ON bundle_downloads(order_id);

-- Email Events Table (Phase 5.4 & 5.8)
CREATE TABLE email_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    event_type TEXT NOT NULL,
    recipient_email TEXT,
    subject TEXT,
    status TEXT DEFAULT 'sent',
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_email_events_order ON email_events(pending_order_id);
CREATE INDEX idx_email_events_type ON email_events(event_type);

-- Page Visits Table (Analytics)
CREATE TABLE page_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    page_url TEXT NOT NULL,
    page_title TEXT,
    referrer TEXT,
    user_agent TEXT,
    ip_address TEXT,
    country_code TEXT,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    device_type TEXT DEFAULT 'Desktop',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_page_visits_session ON page_visits(session_id);
CREATE INDEX idx_page_visits_date ON page_visits(visit_date);

-- Page Interactions Table (Analytics)
CREATE TABLE page_interactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    page_url TEXT NOT NULL,
    action_type TEXT NOT NULL,
    action_target TEXT,
    template_id INTEGER REFERENCES templates(id) ON DELETE SET NULL,
    tool_id INTEGER REFERENCES tools(id) ON DELETE SET NULL,
    time_spent INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_page_interactions_session ON page_interactions(session_id);
CREATE INDEX idx_page_interactions_action ON page_interactions(action_type);

-- Session Summary Table (Analytics)
CREATE TABLE session_summary (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT UNIQUE NOT NULL,
    first_visit TEXT NOT NULL,
    last_visit TEXT NOT NULL,
    total_pages INTEGER DEFAULT 0,
    total_time INTEGER DEFAULT 0,
    interactions INTEGER DEFAULT 0,
    referrer TEXT,
    device_type TEXT DEFAULT 'Desktop',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_session_summary_session ON session_summary(session_id);

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
CREATE INDEX idx_withdrawal_requests_affiliate ON withdrawal_requests(affiliate_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);

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
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created ON activity_logs(created_at);

-- Announcements Table
CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    is_active INTEGER DEFAULT 1,
    affiliate_id INTEGER DEFAULT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    expires_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_announcements_active ON announcements(is_active);

-- Announcement Emails Table
CREATE TABLE announcement_emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    announcement_id INTEGER NOT NULL REFERENCES announcements(id) ON DELETE CASCADE,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    sent_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_announcement_emails_announcement ON announcement_emails(announcement_id);

-- Draft Orders Table
CREATE TABLE draft_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    cart_content TEXT NOT NULL,
    total_price REAL NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_draft_orders_session ON draft_orders(session_id);

-- Media Files Table
CREATE TABLE media_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_type TEXT,
    file_size INTEGER,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_media_files_uploaded_by ON media_files(uploaded_by);

-- Support Tickets Table
CREATE TABLE support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT DEFAULT 'open' CHECK(status IN ('open', 'in_progress', 'resolved', 'closed')),
    priority TEXT DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high', 'urgent')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_support_tickets_status ON support_tickets(status);
CREATE INDEX idx_support_tickets_user ON support_tickets(user_id);

-- Ticket Replies Table
CREATE TABLE ticket_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ticket_replies_ticket ON ticket_replies(ticket_id);

-- Affiliate Actions Table (Phase 5.1 Analytics)
CREATE TABLE affiliate_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    action_type TEXT NOT NULL,
    details TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_affiliate_actions_affiliate ON affiliate_actions(affiliate_id);

-- Commission Log Table (Phase 6 - Audit Trail)
CREATE TABLE commission_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    affiliate_id INTEGER REFERENCES affiliates(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    amount REAL DEFAULT 0.00,
    details TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_commission_log_order_id ON commission_log(order_id);
CREATE INDEX idx_commission_log_affiliate_id ON commission_log(affiliate_id);
CREATE UNIQUE INDEX idx_commission_log_unique ON commission_log(order_id, action);

-- Sales Table (Source of Truth for Revenue)
CREATE TABLE sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    amount_paid REAL NOT NULL,
    commission_amount REAL DEFAULT 0.00,
    affiliate_id INTEGER REFERENCES affiliates(id) ON DELETE SET NULL,
    payment_confirmed_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sales_order ON sales(pending_order_id);
CREATE INDEX idx_sales_affiliate ON sales(affiliate_id);
CREATE UNIQUE INDEX idx_sales_unique_order ON sales(pending_order_id);

-- Payments Table (Payment Processing)
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pending_order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    payment_method TEXT DEFAULT 'paystack' CHECK(payment_method IN ('paystack', 'manual', 'bank_transfer')),
    amount_requested REAL NOT NULL,
    amount_paid REAL,
    currency TEXT DEFAULT 'NGN',
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'processing', 'completed', 'failed', 'abandoned')),
    paystack_reference TEXT UNIQUE,
    paystack_access_code TEXT,
    paystack_authorization_url TEXT,
    paystack_response TEXT,
    payment_verified_at TIMESTAMP,
    failure_reason TEXT,
    retry_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_payments_order ON payments(pending_order_id);
CREATE INDEX idx_payments_reference ON payments(paystack_reference);
CREATE INDEX idx_payments_status ON payments(status);
