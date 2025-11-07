<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics.php';

header('Content-Type: application/json');

startSecureSession();
handleAffiliateTracking();

$searchTerm = trim($_GET['q'] ?? '');

if (empty($searchTerm)) {
    echo json_encode(['success' => false, 'error' => 'Search term is required']);
    exit;
}

try {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT id, name, category, description, price, thumbnail_url, demo_url 
        FROM templates 
        WHERE is_active = 1 
        AND (name LIKE ? OR category LIKE ? OR description LIKE ?)
        ORDER BY name ASC
    ");
    
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    trackSearch($searchTerm, count($results));
    
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'results' => $results
    ]);
} catch (Exception $e) {
    error_log('Search API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed. Please try again.'
    ]);
}
