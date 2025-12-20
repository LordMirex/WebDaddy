<?php
/**
 * Newsletter Signup System
 * Handles email collection and lead magnet delivery
 */

class NewsletterSignup {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->initTable();
    }
    
    private function initTable() {
        try {
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT UNIQUE NOT NULL,
                    name TEXT,
                    interest_topic TEXT,
                    signup_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    status TEXT DEFAULT "active",
                    confirmation_token TEXT,
                    confirmed_at DATETIME,
                    unsubscribe_token TEXT
                )
            ');
        } catch (Exception $e) {
            error_log('Newsletter table error: ' . $e->getMessage());
        }
    }
    
    public function subscribe($email, $name = '', $topic = '') {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            $stmt = $this->db->prepare('
                INSERT INTO newsletter_subscribers (email, name, interest_topic) 
                VALUES (?, ?, ?)
            ');
            
            $result = $stmt->execute([$email, $name, $topic]);
            
            if ($result) {
                // Log conversion event
                $this->logConversion('newsletter_signup', $email, ['topic' => $topic]);
                return ['success' => true, 'message' => 'Successfully subscribed!'];
            }
            
            return ['success' => false, 'message' => 'Already subscribed'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Subscription error'];
        }
    }
    
    public function getSubscriberCount() {
        try {
            $stmt = $this->db->query('SELECT COUNT(*) as count FROM newsletter_subscribers WHERE status = "active"');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function logConversion($type, $identifier, $data = []) {
        try {
            $this->db->prepare('
                INSERT INTO conversion_events (type, identifier, data, timestamp) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ')->execute([$type, $identifier, json_encode($data)]);
        } catch (Exception $e) {
            error_log('Conversion log error: ' . $e->getMessage());
        }
    }
}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'newsletter_subscribe') {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db.php';
    
    $db = getDb();
    $newsletter = new NewsletterSignup($db);
    
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $topic = $_POST['topic'] ?? '';
    
    echo json_encode($newsletter->subscribe($email, $name, $topic));
    exit;
}
?>
