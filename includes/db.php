<?php

require_once __DIR__ . '/config.php';

class Database
{
    private static $instance = null;
    private $connection;
    private $dbType = 'sqlite';

    private function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            // SQLite DSN - single file database
            $dbPath = __DIR__ . '/../database/webdaddy.db';
            $dsn = 'sqlite:' . $dbPath;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->connection = new PDO($dsn, null, null, $options);
            
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign key constraints (critical for SQLite)
            $this->connection->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            
            if (DISPLAY_ERRORS) {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                die('A system error occurred. Please contact support.');
            }
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql)
    {
        try {
            return $this->connection->query($sql);
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    public function prepare($sql)
    {
        try {
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            error_log('Prepare Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    public function escapeString($string)
    {
        return substr($this->connection->quote($string), 1, -1);
    }

    public function getLastInsertId($sequenceName = null)
    {
        return $this->connection->lastInsertId($sequenceName);
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollBack();
    }

    
    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

function getDb()
{
    static $migrationsApplied = false;
    $db = Database::getInstance()->getConnection();
    
    // Apply pending schema migrations once per request
    if (!$migrationsApplied) {
        $migrationsApplied = true;
        applyPendingMigrations($db);
    }
    
    return $db;
}

function getDbType()
{
    return 'sqlite';
}

/**
 * Apply pending schema migrations for SQLite
 * Checks if columns exist and adds them if missing
 */
function applyPendingMigrations($db) {
    try {
        // Check for payment_notified columns in pending_orders
        $result = $db->query("PRAGMA table_info(pending_orders)")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($result, 'name');
        
        if (!in_array('payment_notified', $columns)) {
            $db->exec("ALTER TABLE pending_orders ADD COLUMN payment_notified INTEGER DEFAULT 0");
            error_log("Migration: Added payment_notified column to pending_orders");
        }
        
        if (!in_array('payment_notified_at', $columns)) {
            $db->exec("ALTER TABLE pending_orders ADD COLUMN payment_notified_at TEXT");
            error_log("Migration: Added payment_notified_at column to pending_orders");
        }
        
        // Create user_announcements table if not exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_announcements'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE user_announcements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    message TEXT NOT NULL,
                    type TEXT DEFAULT 'info',
                    is_active INTEGER DEFAULT 1,
                    customer_id INTEGER DEFAULT NULL,
                    created_by INTEGER,
                    expires_at TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $db->exec("CREATE INDEX idx_user_announcements_active ON user_announcements(is_active)");
            $db->exec("CREATE INDEX idx_user_announcements_customer ON user_announcements(customer_id)");
            error_log("Migration: Created user_announcements table");
        }
        
        // Create user_announcement_emails table if not exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_announcement_emails'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE user_announcement_emails (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    announcement_id INTEGER NOT NULL,
                    customer_id INTEGER NOT NULL,
                    email_address TEXT NOT NULL,
                    failed INTEGER DEFAULT 0,
                    error_message TEXT,
                    sent_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $db->exec("CREATE INDEX idx_user_announcement_emails_announcement ON user_announcement_emails(announcement_id)");
            error_log("Migration: Created user_announcement_emails table");
        }
        
        // ============================================
        // BLOG SYSTEM TABLES
        // ============================================
        
        // Create blog_categories table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_categories'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    description TEXT,
                    meta_title TEXT,
                    meta_description TEXT,
                    parent_id INTEGER DEFAULT NULL,
                    display_order INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (parent_id) REFERENCES blog_categories(id) ON DELETE SET NULL
                )
            ");
            error_log("Migration: Created blog_categories table");
        }
        
        // Create blog_posts table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_posts'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    excerpt TEXT,
                    featured_image TEXT,
                    featured_image_alt TEXT,
                    category_id INTEGER,
                    author_name TEXT DEFAULT 'WebDaddy Team',
                    author_avatar TEXT,
                    status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'published', 'scheduled', 'archived')),
                    publish_date DATETIME,
                    reading_time_minutes INTEGER DEFAULT 5,
                    meta_title TEXT,
                    meta_description TEXT,
                    canonical_url TEXT,
                    focus_keyword TEXT,
                    seo_score INTEGER DEFAULT 0,
                    og_title TEXT,
                    og_description TEXT,
                    og_image TEXT,
                    twitter_title TEXT,
                    twitter_description TEXT,
                    twitter_image TEXT,
                    view_count INTEGER DEFAULT 0,
                    share_count INTEGER DEFAULT 0,
                    primary_template_id INTEGER,
                    show_affiliate_ctas INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
                )
            ");
            $db->exec("CREATE INDEX idx_blog_posts_status ON blog_posts(status)");
            $db->exec("CREATE INDEX idx_blog_posts_category ON blog_posts(category_id)");
            $db->exec("CREATE INDEX idx_blog_posts_publish_date ON blog_posts(publish_date)");
            $db->exec("CREATE INDEX idx_blog_posts_slug ON blog_posts(slug)");
            error_log("Migration: Created blog_posts table");
        }
        
        // Create blog_blocks table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_blocks'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_blocks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    block_type TEXT NOT NULL,
                    display_order INTEGER NOT NULL,
                    semantic_role TEXT DEFAULT 'primary_content' CHECK(semantic_role IN (
                        'primary_content', 'supporting_content', 'conversion_content', 'authority_content'
                    )),
                    layout_variant TEXT DEFAULT 'default',
                    data_payload TEXT NOT NULL,
                    behavior_config TEXT,
                    is_visible INTEGER DEFAULT 1,
                    visibility_conditions TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
                )
            ");
            $db->exec("CREATE INDEX idx_blog_blocks_post ON blog_blocks(post_id)");
            $db->exec("CREATE INDEX idx_blog_blocks_order ON blog_blocks(post_id, display_order)");
            error_log("Migration: Created blog_blocks table");
        }
        
        // Create blog_tags table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_tags'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_tags (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            error_log("Migration: Created blog_tags table");
        }
        
        // Create blog_post_tags junction table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_post_tags'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_post_tags (
                    post_id INTEGER NOT NULL,
                    tag_id INTEGER NOT NULL,
                    PRIMARY KEY (post_id, tag_id),
                    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
                )
            ");
            error_log("Migration: Created blog_post_tags table");
        }
        
        // Create blog_internal_links table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_internal_links'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_internal_links (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source_post_id INTEGER NOT NULL,
                    target_post_id INTEGER NOT NULL,
                    anchor_text TEXT,
                    link_type TEXT DEFAULT 'related' CHECK(link_type IN ('related', 'series', 'prerequisite', 'followup')),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (source_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                    FOREIGN KEY (target_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
                )
            ");
            error_log("Migration: Created blog_internal_links table");
        }
        
        // Create blog_analytics table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_analytics'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_analytics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL CHECK(event_type IN ('view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100', 'cta_click', 'share', 'template_click')),
                    session_id TEXT,
                    referrer TEXT,
                    affiliate_code TEXT,
                    user_agent TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
                )
            ");
            $db->exec("CREATE INDEX idx_blog_analytics_post ON blog_analytics(post_id)");
            $db->exec("CREATE INDEX idx_blog_analytics_date ON blog_analytics(created_at)");
            error_log("Migration: Created blog_analytics table");
        }
        
        // Create blog_comments table
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_comments'")->fetch();
        if (!$tableCheck) {
            $db->exec("
                CREATE TABLE blog_comments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    customer_id INTEGER,
                    author_name TEXT,
                    author_email TEXT,
                    content TEXT NOT NULL,
                    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'spam', 'deleted')),
                    parent_id INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
                )
            ");
            error_log("Migration: Created blog_comments table");
        }
        
    } catch (Exception $e) {
        // Log but don't fail - migrations are optional enhancements
        error_log("Migration check error: " . $e->getMessage());
    }
}
