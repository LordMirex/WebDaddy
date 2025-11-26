<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Validate CSRF
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

$affiliateCode = strtoupper(trim($_POST['affiliate_code'] ?? ''));

if (empty($affiliateCode)) {
    echo json_encode(['success' => false, 'message' => 'Please enter an affiliate code']);
    exit;
}

// Lookup affiliate
$affiliate = getAffiliateByCode($affiliateCode);

if (!$affiliate || $affiliate['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive affiliate code']);
    exit;
}

// Valid affiliate - save to session
$_SESSION['affiliate_code'] = $affiliateCode;
setcookie(
    'affiliate_code',
    $affiliateCode,
    time() + (defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400,
    '/',
    '',
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    true
);

// Increment affiliate clicks
if (function_exists('incrementAffiliateClick')) {
    incrementAffiliateClick($affiliateCode);
}

// Calculate new totals
$totals = getCartTotal(null, $affiliateCode);

echo json_encode([
    'success' => true,
    'message' => '✅ 20% discount applied!',
    'discount' => $totals['discount'],
    'total' => $totals['total'],
    'affiliate_code' => $affiliateCode
]);
