<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$cartItems = getCart();
$totals = getCartTotal();

$items = [];
foreach ($cartItems as $item) {
    $items[] = [
        'id' => $item['id'],
        'name' => $item['name'],
        'price' => $item['price_at_add'],
        'thumbnail' => $item['thumbnail_url'] ?? '/assets/images/placeholder.png',
        'type' => $item['product_type'] ?? 'tool',
        'quantity' => $item['quantity']
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($cartItems),
    'total' => $totals['total'] ?? 0,
    'items' => $items
]);
