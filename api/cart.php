<?php
/**
 * Cart API Endpoint
 * 
 * Provides REST API for shopping cart operations
 * 
 * Supported operations:
 * - POST action=add: Add item to cart
 * - POST action=update: Update item quantity
 * - POST action=remove: Remove item from cart
 * - POST action=clear: Clear entire cart
 * - GET action=get: Get cart contents
 * - GET action=count: Get cart item count
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/analytics.php';

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
startSecureSession();

// Track affiliate if present
handleAffiliateTracking();

// Get affiliate code from session for discount calculation
$affiliateCode = $_SESSION['affiliate_code'] ?? null;

try {
    // Get action from GET parameter for GET requests
    $action = $_GET['action'] ?? '';
    
    // GET requests - add caching headers
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Cache GET responses for 5 minutes
        header('Cache-Control: public, max-age=300');
        header('Vary: Accept-Encoding');
        if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
        
        switch ($action) {
            case 'get':
                // Get cart contents
                $cartItems = getCart();
                $totals = getCartTotal(null, $affiliateCode);
                
                echo json_encode([
                    'success' => true,
                    'items' => $cartItems,
                    'count' => $totals['item_count'],
                    'total' => $totals['total'],
                    'totals' => $totals,
                    'affiliate_code' => $affiliateCode
                ]);
                break;
                
            case 'count':
                // Get cart count
                $count = getCartCount();
                echo json_encode([
                    'success' => true,
                    'count' => $count
                ]);
                break;
                
            case 'validate':
                // Validate cart
                $validation = validateCart();
                echo json_encode([
                    'success' => true,
                    'validation' => $validation
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action for GET request'
                ]);
        }
        exit;
    }
    
    // POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get input from either JSON body or URL-encoded POST data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // If JSON decode failed, try URL-encoded POST data
        if (json_last_error() !== JSON_ERROR_NONE) {
            $input = $_POST;
        }
        
        // Get action from JSON body, POST parameter, or GET parameter
        $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Add to cart (supports both tools and templates)
                $productType = $input['product_type'] ?? 'tool';
                $productId = $input['product_id'] ?? $input['tool_id'] ?? null;
                $quantity = $input['quantity'] ?? 1;
                
                if (!$productId || $quantity < 1) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid product_id or quantity'
                    ]);
                    exit;
                }
                
                // Use unified cart function
                if (isset($input['product_type'])) {
                    $result = addProductToCart($productType, $productId, $quantity);
                } else {
                    // Backward compatibility for tool_id parameter
                    $result = addToCart($productId, $quantity);
                }
                
                if ($result['success']) {
                    // Get updated cart count
                    $count = getCartCount();
                    echo json_encode([
                        'success' => true,
                        'message' => $result['message'],
                        'action' => $result['action'],
                        'cart_count' => $count
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
                break;
                
            case 'update':
                // Update quantity
                $cartItemId = $input['cart_item_id'] ?? $input['cart_id'] ?? null;
                $quantity = $input['quantity'] ?? null;
                
                if (!$cartItemId || $quantity === null) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Missing cart_item_id or quantity'
                    ]);
                    exit;
                }
                
                $result = updateCartQuantity($cartItemId, $quantity);
                
                if ($result['success']) {
                    // Get updated totals
                    $totals = getCartTotal(null, $affiliateCode);
                    echo json_encode([
                        'success' => true,
                        'message' => $result['message'],
                        'totals' => $totals
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
                break;
                
            case 'remove':
                // Remove item
                $cartItemId = $input['cart_item_id'] ?? $input['cart_id'] ?? null;
                
                if (!$cartItemId) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Missing cart_item_id'
                    ]);
                    exit;
                }
                
                $result = removeFromCart($cartItemId);
                
                if ($result['success']) {
                    $count = getCartCount();
                    $totals = getCartTotal(null, $affiliateCode);
                    echo json_encode([
                        'success' => true,
                        'message' => $result['message'],
                        'cart_count' => $count,
                        'totals' => $totals
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode($result);
                }
                break;
                
            case 'clear':
                // Clear cart
                $success = clearCart();
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Cart cleared' : 'Failed to clear cart',
                    'cart_count' => 0
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action for POST request'
                ]);
        }
        exit;
    }
    
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Cart API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
