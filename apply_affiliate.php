<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');
startSecureSession();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $affiliateCode = trim($_POST['affiliate_code'] ?? '');
    if (empty($affiliateCode)) {
        throw new Exception('Affiliate code is required');
    }

    // Convert to uppercase
    $affiliateCode = strtoupper($affiliateCode);

    // Check if affiliate code exists and is active
    $affiliateData = getAffiliateByCode($affiliateCode);
    if (!$affiliateData) {
        throw new Exception('Affiliate code not found');
    }

    // Apply the affiliate code to session
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

    // Increment affiliate click
    incrementAffiliateClick($affiliateCode);

    echo json_encode([
        'success' => true,
        'message' => 'Affiliate code applied successfully!',
        'affiliate_code' => $affiliateCode
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
