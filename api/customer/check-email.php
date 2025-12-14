<?php
/**
 * Check if email exists in customer database
 * Returns whether user has password (for login) or needs OTP
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limit: 10 requests per minute per IP
$clientIP = getClientIP();
enforceRateLimit($clientIP, 'check_email', 10, 60, 'Too many email checks. Please wait a moment.');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email is required']);
    exit;
}

$result = checkCustomerEmail($email);

echo json_encode([
    'exists' => $result['exists'],
    'has_password' => $result['has_password'] ?? false,
    'full_name' => $result['full_name'] ?? null,
    'status' => $result['status'] ?? null,
    'needs_setup' => ($result['status'] ?? '') === 'pending_setup'
]);
