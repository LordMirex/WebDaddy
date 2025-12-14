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
$currentToken = $_COOKIE[CUSTOMER_SESSION_COOKIE] ?? null;
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessions = getCustomerActiveSessions($customerId);
    
    $stmt = $db->prepare("
        SELECT id, session_token FROM customer_sessions 
        WHERE session_token = ? AND is_active = 1
    ");
    $stmt->execute([$currentToken]);
    $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentSessionId = $currentSession ? $currentSession['id'] : null;
    
    $formattedSessions = array_map(function($session) use ($currentSessionId) {
        $deviceName = 'Unknown Device';
        $userAgent = $session['user_agent'] ?? '';
        
        if (stripos($userAgent, 'chrome') !== false) {
            $deviceName = 'Chrome';
        } elseif (stripos($userAgent, 'firefox') !== false) {
            $deviceName = 'Firefox';
        } elseif (stripos($userAgent, 'safari') !== false) {
            $deviceName = 'Safari';
        } elseif (stripos($userAgent, 'edge') !== false) {
            $deviceName = 'Edge';
        }
        
        if (stripos($userAgent, 'windows') !== false) {
            $deviceName .= ' on Windows';
        } elseif (stripos($userAgent, 'macintosh') !== false || stripos($userAgent, 'mac os') !== false) {
            $deviceName .= ' on Mac';
        } elseif (stripos($userAgent, 'iphone') !== false) {
            $deviceName .= ' on iPhone';
        } elseif (stripos($userAgent, 'android') !== false) {
            $deviceName .= ' on Android';
        } elseif (stripos($userAgent, 'linux') !== false) {
            $deviceName .= ' on Linux';
        }
        
        if (!empty($session['device_name'])) {
            $deviceName = $session['device_name'];
        }
        
        $ipAddress = $session['ip_address'] ?? '';
        if ($ipAddress) {
            $parts = explode('.', $ipAddress);
            if (count($parts) === 4) {
                $ipAddress = $parts[0] . '.' . $parts[1] . '.xxx.xxx';
            }
        }
        
        return [
            'id' => (int)$session['id'],
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'last_activity' => $session['last_activity_at'],
            'created_at' => $session['created_at'],
            'is_current' => ($session['id'] == $currentSessionId)
        ];
    }, $sessions);
    
    echo json_encode([
        'success' => true,
        'sessions' => $formattedSessions
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $sessionId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session ID is required']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT id, session_token FROM customer_sessions 
        WHERE id = ? AND customer_id = ? AND is_active = 1
    ");
    $stmt->execute([$sessionId, $customerId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        exit;
    }
    
    if ($session['session_token'] === $currentToken) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot revoke current session. Use logout instead.']);
        exit;
    }
    
    $stmt = $db->prepare("
        UPDATE customer_sessions 
        SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'user_revoked'
        WHERE id = ?
    ");
    $stmt->execute([$sessionId]);
    
    logCustomerActivity($customerId, 'session_revoked', "Revoked session #$sessionId");
    
    echo json_encode([
        'success' => true,
        'message' => 'Session revoked successfully'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
