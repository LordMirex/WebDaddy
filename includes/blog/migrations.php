<?php
/**
 * Blog Database Migrations
 * Run once during setup, then safe to delete
 */

if (!function_exists('runBlogMigrations')) {
    function runBlogMigrations() {
        $db = getDb();
        $executed = 0;
        $errors = [];

        // Migration 1: Create blog_analytics table
        $sql1 = "CREATE TABLE IF NOT EXISTS blog_analytics (
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
        )";

        if ($db->query($sql1)) {
            $executed++;
        } else {
            $errors[] = $db->error;
        }

        // Migration 2: Add view_count to blog_posts
        $checkViewCount = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME='blog_posts' AND COLUMN_NAME='view_count'");
        
        if ($checkViewCount->num_rows == 0) {
            if ($db->query("ALTER TABLE blog_posts ADD COLUMN view_count INT DEFAULT 0")) {
                $executed++;
            } else {
                $errors[] = $db->error;
            }
        }

        // Migration 3: Add share_count to blog_posts
        $checkShareCount = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME='blog_posts' AND COLUMN_NAME='share_count'");
        
        if ($checkShareCount->num_rows == 0) {
            if ($db->query("ALTER TABLE blog_posts ADD COLUMN share_count INT DEFAULT 0")) {
                $executed++;
            } else {
                $errors[] = $db->error;
            }
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
            'success' => count($errors) === 0
        ];
    }
}

// Auto-run migrations if called directly
if (basename($_SERVER['PHP_SELF']) === 'migrations.php') {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/db.php';
    
    $result = runBlogMigrations();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
