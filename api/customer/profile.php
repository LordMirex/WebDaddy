<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';

header('Content-Type: application/json');

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$customerId = $customer['customer_id'];
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT id, email, full_name, phone, username, created_at
        FROM customers WHERE id = ?
    ");
    $stmt->execute([$customerId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'customer' => $profile
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $fullName = isset($input['full_name']) ? trim($input['full_name']) : null;
    $phone = isset($input['phone']) ? trim($input['phone']) : null;
    $username = isset($input['username']) ? trim($input['username']) : null;
    
    if ($username !== null) {
        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
            exit;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
        $stmt->execute([$username, $customerId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }
    }
    
    $updates = [];
    $params = [];
    
    if ($fullName !== null) {
        $updates[] = "full_name = ?";
        $params[] = $fullName;
    }
    if ($phone !== null) {
        $updates[] = "phone = ?";
        $params[] = preg_replace('/[^0-9+]/', '', $phone);
    }
    if ($username !== null) {
        $updates[] = "username = ?";
        $params[] = $username;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    $updates[] = "updated_at = datetime('now')";
    $params[] = $customerId;
    
    $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    logCustomerActivity($customerId, 'profile_updated', 'Profile information updated');
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
