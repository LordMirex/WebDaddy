<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/access_log.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');

$startTime = microtime(true);
if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

startSecureSession();
handleAffiliateTracking();

$searchTerm = trim($_GET['q'] ?? '');
$searchType = trim($_GET['type'] ?? 'all');

if (!in_array($searchType, ['template', 'tool', 'all'])) {
    $searchType = 'all';
}

try {
    $db = getDb();
    $results = [];
    
    if (empty($searchTerm)) {
        // Try cache for recent items
        $cacheKey = 'search_recent_' . $searchType;
        $cachedResults = ProductCache::get($cacheKey);
        if ($cachedResults !== null) {
            echo json_encode($cachedResults);
            $duration = (microtime(true) - $startTime) * 1000;
            logApiAccess('/api/search.php?type=' . $searchType, 'GET', 200, $duration);
            exit;
        }
        
        // Return recent items when no search term (minimal fields for fast response)
        if ($searchType === 'template' || $searchType === 'all') {
            $stmt = $db->prepare("
                SELECT id, name, category, price, thumbnail_url, demo_url 
                FROM templates 
                WHERE active = 1 
                ORDER BY created_at DESC
                LIMIT 9
            ");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as $template) {
                $template['type'] = 'template';
                $template['product_type'] = 'Website Template';
                $results[] = $template;
            }
        }
        
        if ($searchType === 'tool' || $searchType === 'all') {
            $stmt = $db->prepare("
                SELECT id, name, category, price, thumbnail_url, demo_url 
                FROM tools 
                WHERE active = 1 
                AND (stock_unlimited = 1 OR stock_quantity > 0)
                ORDER BY created_at DESC
                LIMIT 9
            ");
            $stmt->execute();
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tools as $tool) {
                $tool['type'] = 'tool';
                $tool['product_type'] = 'Working Tool';
                $results[] = $tool;
            }
        }
    } else {
        // Search templates
        if ($searchType === 'template' || $searchType === 'all') {
            $stmt = $db->prepare("
                SELECT id, name, category, price, thumbnail_url, demo_url 
                FROM templates 
                WHERE active = 1 
                AND (name LIKE ? OR category LIKE ? OR description LIKE ?)
                ORDER BY name ASC
                LIMIT 20
            ");
            
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as $template) {
                $template['type'] = 'template';
                $template['product_type'] = 'Website Template';
                $results[] = $template;
            }
        }
        
        // Search tools
        if ($searchType === 'tool' || $searchType === 'all') {
            $toolResults = searchTools($searchTerm);
            foreach ($toolResults as $tool) {
                $tool['type'] = 'tool';
                $tool['product_type'] = 'Working Tool';
                $results[] = $tool;
            }
        }
        
        trackSearch($searchTerm, count($results));
    } else {
        // Cache search results
        $cacheKey = 'search_' . md5($searchTerm . $searchType);
        ProductCache::set($cacheKey, [
            'success' => true,
            'count' => count($results),
            'search_term' => $searchTerm,
            'search_type' => $searchType,
            'results' => $results
        ]);
    }
    
    $response = [
        'success' => true,
        'count' => count($results),
        'search_term' => $searchTerm,
        'search_type' => $searchType,
        'results' => $results
    ];
    
    // Cache recent items
    if (empty($searchTerm)) {
        ProductCache::set('search_recent_' . $searchType, $response);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Search API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed. Please try again.'
    ]);
}

// Log API access
$duration = (microtime(true) - $startTime) * 1000;
$searchQuery = $_GET['q'] ?? '';
logApiAccess('/api/search.php?q=' . substr($searchQuery, 0, 20), 'GET', 200, $duration);
rotateAccessLogs();
?>
