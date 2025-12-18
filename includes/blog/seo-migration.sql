-- Blog Analytics Table
CREATE TABLE IF NOT EXISTS blog_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    session_id VARCHAR(255),
    referrer TEXT,
    affiliate_code VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_id (post_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);

-- Add analytics columns to blog_posts if not exists
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS share_count INT DEFAULT 0;

-- Blog SEO fields
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS focus_keyword VARCHAR(255);
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS seo_score INT DEFAULT 0;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS reading_time_minutes INT;
