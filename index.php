<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/cart.php';

// Set cache headers for static content - works with .htaccess on main server
header('Cache-Control: public, max-age=31536000, immutable', false);

startSecureSession();
handleAffiliateTracking();

// AUTO-RESTORE SAVED CART FROM PREVIOUS VISIT (if not already loaded)
if (empty(getCart())) {
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare('
        SELECT id, cart_snapshot FROM draft_orders 
        WHERE ip_address = ? AND created_at > datetime("now", "-7 days")
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$ip]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($draft) {
        $draftData = json_decode($draft['cart_snapshot'], true);
        if (!empty($draftData['cart_items'])) {
            $_SESSION['cart'] = $draftData['cart_items'];
            if (!empty($draftData['affiliate_code'])) {
                $_SESSION['affiliate_code'] = $draftData['affiliate_code'];
            }
        }
    }
}

// Determine current view: 'templates' or 'tools'
$currentView = $_GET['view'] ?? 'templates';
$currentView = in_array($currentView, ['templates', 'tools']) ? $currentView : 'templates';

// Track page visit
trackPageVisit($_SERVER['REQUEST_URI'], 'Home - ' . ucfirst($currentView));

// Get database connection
$db = getDb();

// Get affiliate code
$affiliateCode = getAffiliateCode();

// Get cart count for badge
$cartCount = getCartCount();

// Get total active templates and tools (unfiltered by category)
$totalActiveTemplates = count(getTemplates(true));
$totalActiveTools = getToolsCount(true, null, true);

// Initialize variables
$templates = [];
$tools = [];
$totalTemplates = 0;
$totalTools = 0;
$totalPages = 1;
$page = max(1, (int)($_GET['page'] ?? 1));

// Auto-open tool from share link
$autoOpenTool = null;
$autoOpenToolSlug = isset($_GET['tool']) ? sanitizeInput($_GET['tool']) : null;
if ($autoOpenToolSlug) {
    $autoOpenTool = getToolBySlug($autoOpenToolSlug);
    if ($autoOpenTool) {
        $currentView = 'tools'; // Force tools view if opening a tool from share link
        // Track tool view when opened from share link
