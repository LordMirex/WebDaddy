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
    $action = $input['action'] ?? null;
    
    // Handle setup wizard step 1 - username and password
    if ($action === 'complete_registration_step1') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        
        if (empty($username) || strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
            exit;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores']);
            exit;
        }
        
        // Check username uniqueness
        $stmt = $db->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
        $stmt->execute([$username, $customerId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username already taken']);
            exit;
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            exit;
        }
        
        if ($password !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if WhatsApp number is provided in this request (from skip scenario)
        $providedWhatsApp = trim($input['whatsapp_number'] ?? '');
        $cleanProvidedWhatsApp = !empty($providedWhatsApp) ? preg_replace('/[^0-9+]/', '', $providedWhatsApp) : '';
        
        // Check if user already has WhatsApp number in DB
        $stmt = $db->prepare("SELECT whatsapp_number FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        $existingWhatsApp = $existingCustomer['whatsapp_number'] ?? '';
        
        // Use provided WhatsApp or existing one
        $finalWhatsApp = '';
        if (!empty($cleanProvidedWhatsApp) && strlen($cleanProvidedWhatsApp) >= 10) {
            $finalWhatsApp = $cleanProvidedWhatsApp;
        } elseif (!empty($existingWhatsApp) && strlen(preg_replace('/[^0-9]/', '', $existingWhatsApp)) >= 10) {
            $finalWhatsApp = $existingWhatsApp;
        }
        
        $hasWhatsApp = !empty($finalWhatsApp);
        
        if ($hasWhatsApp) {
            // Complete account fully with WhatsApp
            $stmt = $db->prepare("
                UPDATE customers 
                SET username = ?, 
                    password_hash = ?, 
                    password_changed_at = datetime('now'),
                    whatsapp_number = ?,
                    registration_step = 0,
                    status = 'active',
                    account_complete = 1,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$username, $hash, $finalWhatsApp, $customerId]);
        } else {
            // Just set credentials, need WhatsApp step
            $stmt = $db->prepare("
                UPDATE customers 
                SET username = ?, 
                    password_hash = ?, 
                    password_changed_at = datetime('now'),
                    registration_step = 2,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$username, $hash, $customerId]);
        }
        
        logCustomerActivity($customerId, 'registration_step1', 'Username and password set');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Credentials saved',
            'account_complete' => $hasWhatsApp
        ]);
        exit;
    }
    
    // Handle setup wizard step 2 - WhatsApp number
    if ($action === 'complete_registration_whatsapp') {
        $whatsappNumber = trim($input['whatsapp_number'] ?? '');
        
        if (empty($whatsappNumber) || strlen($whatsappNumber) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please enter a valid WhatsApp number']);
            exit;
        }
        
        $cleanWhatsapp = preg_replace('/[^0-9+]/', '', $whatsappNumber);
        
        $stmt = $db->prepare("
            UPDATE customers 
            SET whatsapp_number = ?,
                registration_step = 0,
                status = 'active',
                account_complete = 1,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$cleanWhatsapp, $customerId]);
        
        logCustomerActivity($customerId, 'registration_complete', 'Registration completed with WhatsApp number');
        
        echo json_encode([
            'success' => true,
            'message' => 'Account setup complete!'
        ]);
        exit;
    }
    
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
