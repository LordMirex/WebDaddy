<?php
/**
 * Conversion & Revenue Tracking
 * Tracks clicks, signups, purchases, and affiliate conversions
 */

class ConversionTracking {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->initTables();
    }
    
    private function initTables() {
        try {
            // Conversion events table
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS conversion_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    identifier TEXT,
                    data TEXT,
                    ip_address TEXT,
                    user_agent TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type (type),
                    INDEX idx_timestamp (timestamp)
                )
            ');
            
            // Click tracking
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS link_clicks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    link_type TEXT NOT NULL,
                    link_id TEXT,
                    post_id INTEGER,
                    click_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ip_address TEXT,
                    referer TEXT,
                    INDEX idx_type (link_type),
                    INDEX idx_date (click_date)
                )
            ');
            
            // Revenue tracking
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS revenue_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source TEXT NOT NULL,
                    amount DECIMAL(10,2),
                    currency TEXT DEFAULT "NGN",
                    description TEXT,
                    reference_id TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_source (source),
                    INDEX idx_timestamp (timestamp)
                )
            ');
        } catch (Exception $e) {
            error_log('Tracking table error: ' . $e->getMessage());
        }
    }
    
    public function trackClick($linkType, $linkId, $postId = null) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO link_clicks (link_type, link_id, post_id, ip_address, referer)
                VALUES (?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $linkType,
                $linkId,
                $postId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_REFERER'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log('Click tracking error: ' . $e->getMessage());
        }
    }
    
    public function trackConversion($type, $identifier, $data = []) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO conversion_events (type, identifier, data, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $type,
                $identifier,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log('Conversion tracking error: ' . $e->getMessage());
        }
    }
    
    public function trackRevenue($source, $amount, $reference, $description = '') {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO revenue_events (source, amount, reference_id, description)
                VALUES (?, ?, ?, ?)
            ');
            
            $stmt->execute([$source, $amount, $reference, $description]);
        } catch (Exception $e) {
            error_log('Revenue tracking error: ' . $e->getMessage());
        }
    }
    
    public function getMetrics($type = null, $days = 30) {
        try {
            $where = $type ? "WHERE type = ?" : "";
            $params = $type ? [$type] : [];
            
            $stmt = $this->db->prepare("
                SELECT 
                    type,
                    COUNT(*) as count,
                    DATE(timestamp) as date
                FROM conversion_events
                WHERE timestamp > datetime('now', '-$days days') $where
                GROUP BY type, DATE(timestamp)
                ORDER BY timestamp DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getRevenueMetrics($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    source,
                    SUM(amount) as total,
                    COUNT(*) as transactions,
                    AVG(amount) as avg_amount
                FROM revenue_events
                WHERE timestamp > datetime('now', '-$days days')
                GROUP BY source
                ORDER BY total DESC
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

// AJAX Handler for tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'track_click') {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db.php';
    
    $db = getDb();
    $tracking = new ConversionTracking($db);
    
    $tracking->trackClick(
        $_POST['link_type'] ?? 'unknown',
        $_POST['link_id'] ?? '',
        $_POST['post_id'] ?? null
    );
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
?>
