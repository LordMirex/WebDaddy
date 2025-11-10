<?php
/**
 * Tools API Endpoint
 * 
 * Provides REST API for fetching tools with pagination, filtering, and search
 * 
 * Supported operations:
 * - GET: Fetch tools (with optional filters)
 * - Query parameters: page, limit, category, search
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tools.php';

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $action = $_GET['action'] ?? 'list';
    
    // Handle get single tool action
    if ($action === 'get_tool') {
        $toolId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($toolId > 0) {
            $tool = getToolById($toolId);
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
    
    // Get query parameters for list action
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 18;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    $offset = ($page - 1) * $limit;
    
    // Handle search
    if ($searchQuery) {
        $tools = searchTools($searchQuery, $limit);
        $totalTools = count($tools); // For search, we limit results already
        $totalPages = 1; // Search doesn't paginate
    } else {
        // Get tools with filters
        $tools = getTools(true, $category, $limit, $offset);
        $totalTools = getToolsCount(true, $category);
        $totalPages = ceil($totalTools / $limit);
    }
    
    // Format response
    $response = [
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total_tools' => $totalTools,
        'category' => $category,
        'search_query' => $searchQuery,
        'tools' => $tools
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Tools API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
