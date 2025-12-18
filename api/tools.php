<?php
/**
 * Tools API Endpoint
 * 
 * Provides REST API for fetching tools with pagination, filtering, and search
 * Implements response limiting and caching to reduce database queries
 */

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/access_log.php';

$startTime = microtime(true);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Limit response fields to reduce payload size
function limitToolFields($tool) {
    return [
        'id' => $tool['id'],
        'name' => $tool['name'] ?? '',
        'slug' => $tool['slug'] ?? '',
        'category' => $tool['category'] ?? '',
        'price' => $tool['price'] ?? 0,
        'thumbnail_url' => $tool['thumbnail_url'] ?? ''
    ];
}

try {
    $action = $_GET['action'] ?? 'list';
    
    // Handle get single tool action
    if ($action === 'get_tool') {
        $toolId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($toolId > 0) {
            $cacheKey = 'tool_' . $toolId;
            $tool = ProductCache::get($cacheKey);
            
            if ($tool === null) {
                $tool = getToolById($toolId, true);
                if ($tool) {
                    ProductCache::set($cacheKey, $tool);
                }
            }
            
            if ($tool) {
                echo json_encode(['success' => true, 'tool' => $tool]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tool not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid tool ID']);
        }
        exit;
    }
    
    // Get query parameters with response limiting
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(25, max(1, (int)$_GET['limit'])) : 18;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    $offset = ($page - 1) * $limit;
    
    // Try cache for list
    if (!$searchQuery) {
        $cacheKey = 'tools_list_' . $page . '_' . $limit . '_' . ($category ?? 'all');
        $cachedResponse = ProductCache::get($cacheKey);
        if ($cachedResponse !== null) {
            echo json_encode($cachedResponse);
            exit;
        }
    }
    
    // Handle search
    if ($searchQuery) {
        $tools = searchTools($searchQuery, $limit);
        $totalTools = count($tools);
        $totalPages = 1;
    } else {
        // Get tools with filters
        $tools = getTools(true, $category, $limit, $offset);
        $totalTools = getToolsCount(true, $category);
        $totalPages = ceil($totalTools / $limit);
    }
    
    // Limit response fields
    $limitedTools = array_map('limitToolFields', $tools);
    
    // Format response
    $response = [
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total_tools' => $totalTools,
        'category' => $category,
        'tools' => $limitedTools
    ];
    
    // Cache list results
    if (!$searchQuery) {
        ProductCache::set('tools_list_' . $page . '_' . $limit . '_' . ($category ?? 'all'), $response);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Tools API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request. Please try again.'
    ]);
}

// Log API access
$duration = (microtime(true) - $startTime) * 1000;
$endpoint = $_GET['action'] ?? 'list';
logApiAccess('/api/tools.php?action=' . $endpoint, 'GET', http_response_code(), $duration);
rotateAccessLogs();
?>
