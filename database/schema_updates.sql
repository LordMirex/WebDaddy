CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE sqlite_sequence(name,seq);
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
CREATE TABLE pending_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL REFERENCES templates(id) ON DELETE RESTRICT,
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
, original_price REAL, discount_amount REAL DEFAULT 0, final_amount REAL);
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
, original_price REAL, discount_amount REAL DEFAULT 0, final_amount REAL);
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
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    is_active INTEGER DEFAULT 1,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
, affiliate_id INTEGER DEFAULT NULL REFERENCES affiliates(id) ON DELETE CASCADE, expires_at TEXT DEFAULT NULL);
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
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE page_interactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        page_url TEXT NOT NULL,
        action_type TEXT NOT NULL,
        action_target TEXT,
        template_id INTEGER,
        time_spent INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
    );
CREATE TABLE session_summary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT UNIQUE NOT NULL,
        first_visit TEXT NOT NULL,
        last_visit TEXT NOT NULL,
        total_pages INTEGER DEFAULT 1,
        total_time_seconds INTEGER DEFAULT 0,
        is_bounce INTEGER DEFAULT 0,
        converted INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE support_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT 'open',
        priority TEXT DEFAULT 'normal',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    );
CREATE TABLE ticket_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        admin_id INTEGER DEFAULT NULL,
        affiliate_id INTEGER DEFAULT NULL,
        message TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL
    );
CREATE TABLE sqlite_stat1(tbl,idx,stat);
CREATE INDEX idx_settings_key ON settings(setting_key);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_templates_slug ON templates(slug);
CREATE INDEX idx_templates_active ON templates(active);
CREATE INDEX idx_templates_category ON templates(category);
CREATE INDEX idx_affiliates_code ON affiliates(code);
CREATE INDEX idx_affiliates_user_id ON affiliates(user_id);
CREATE INDEX idx_affiliates_status ON affiliates(status);
CREATE INDEX idx_domains_template_id ON domains(template_id);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_domain_name ON domains(domain_name);
CREATE INDEX idx_pending_orders_template_id ON pending_orders(template_id);
CREATE INDEX idx_pending_orders_status ON pending_orders(status);
CREATE INDEX idx_pending_orders_affiliate_code ON pending_orders(affiliate_code);
CREATE INDEX idx_pending_orders_customer_email ON pending_orders(customer_email);
CREATE INDEX idx_pending_orders_created_at ON pending_orders(created_at);
CREATE INDEX idx_sales_pending_order_id ON sales(pending_order_id);
CREATE INDEX idx_sales_admin_id ON sales(admin_id);
CREATE INDEX idx_sales_affiliate_id ON sales(affiliate_id);
CREATE INDEX idx_sales_payment_confirmed_at ON sales(payment_confirmed_at);
CREATE INDEX idx_withdrawal_requests_affiliate_id ON withdrawal_requests(affiliate_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);
CREATE INDEX idx_withdrawal_requests_requested_at ON withdrawal_requests(requested_at);
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX idx_announcements_is_active ON announcements(is_active);
CREATE INDEX idx_announcements_created_at ON announcements(created_at);
CREATE INDEX idx_announcements_affiliate_id ON announcements(affiliate_id);
CREATE INDEX idx_announcements_expires_at ON announcements(expires_at);
CREATE INDEX idx_page_visits_session ON page_visits(session_id);
CREATE INDEX idx_page_visits_date ON page_visits(visit_date);
CREATE INDEX idx_page_visits_url ON page_visits(page_url);
CREATE INDEX idx_interactions_session ON page_interactions(session_id);
CREATE INDEX idx_interactions_template ON page_interactions(template_id);
CREATE INDEX idx_interactions_action ON page_interactions(action_type);
CREATE INDEX idx_session_summary_date ON session_summary(first_visit);
CREATE INDEX idx_tickets_affiliate ON support_tickets(affiliate_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
CREATE INDEX idx_replies_ticket ON ticket_replies(ticket_id);
CREATE INDEX idx_replies_admin ON ticket_replies(admin_id);
CREATE INDEX idx_replies_affiliate ON ticket_replies(affiliate_id);
