<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $cartItems = getCart();
            $affiliateCode = getAffiliateCode();
            
            if (empty($cartItems)) {
                throw new Exception('Cart is empty');
            }
            
            $draftData = [
                'cart_items' => $cartItems,
                'affiliate_code' => $affiliateCode,
                'saved_at' => date('Y-m-d H:i:s'),
                'session_id' => session_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ];
            
            $db = getDb();
            $stmt = $db->prepare('
                INSERT INTO draft_orders (cart_snapshot, session_id, ip_address, created_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ');
            
            $result = $stmt->execute([
                json_encode($draftData),
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            if (!$result) {
                throw new Exception('Failed to save draft order');
            }
            
            $draftId = $db->lastInsertId();
            $response['success'] = true;
            $response['draft_id'] = $draftId;
            $response['message'] = 'Draft order auto-saved: ' . count($cartItems) . ' items';
            break;
            
        case 'load':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Invalid request method');
            }
            
            $draftId = (int)($_GET['draft_id'] ?? 0);
            if (!$draftId) {
                throw new Exception('Draft ID required');
            }
            
            $db = getDb();
            $stmt = $db->prepare('
                SELECT cart_snapshot FROM draft_orders 
                WHERE id = ? AND session_id = ?
                LIMIT 1
            ');
            
            $stmt->execute([$draftId, session_id()]);
            $draft = $stmt->fetch();
            
            if (!$draft) {
                throw new Exception('Draft order not found');
            }
            
            $draftData = json_decode($draft['cart_snapshot'], true);
            
            // Restore cart
            $_SESSION['cart'] = $draftData['cart_items'] ?? [];
            $_SESSION['affiliate_code'] = $draftData['affiliate_code'] ?? null;
            
            $response['success'] = true;
            $response['message'] = 'Draft order loaded successfully';
            break;
            
        case 'list':
            $db = getDb();
            $stmt = $db->prepare('
                SELECT id, customer_email, created_at 
                FROM draft_orders 
                WHERE session_id = ? AND created_at > datetime(\'now\', \'-30 days\')
                ORDER BY created_at DESC
                LIMIT 10
            ');
            
            $stmt->execute([session_id()]);
            $drafts = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['drafts'] = $drafts;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
