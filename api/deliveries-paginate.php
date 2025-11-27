<?php
/**
 * AJAX Pagination for Deliveries
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../admin/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$page = (int)($_GET['page'] ?? 1);
$perPage = 15;
$offset = ($page - 1) * $perPage;

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDays = $_GET['days'] ?? '';

$sql = "
    SELECT d.*, 
           po.customer_name, 
           po.customer_email,
           po.id as order_id
    FROM deliveries d
    INNER JOIN pending_orders po ON d.pending_order_id = po.id
    WHERE 1=1
";
$params = [];

if (!empty($filterType) && in_array($filterType, ['template', 'tool'])) {
    $sql .= " AND d.product_type = ?";
    $params[] = $filterType;
}

if (!empty($filterStatus)) {
    $sql .= " AND d.delivery_status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterDays) && is_numeric($filterDays)) {
    $sql .= " AND d.created_at >= datetime('now', '-{$filterDays} days')";
}

$countSql = "SELECT COUNT(*) as total FROM deliveries d INNER JOIN pending_orders po ON d.pending_order_id = po.id WHERE 1=1";
if (!empty($filterType) && in_array($filterType, ['template', 'tool'])) {
    $countSql .= " AND d.product_type = ?";
}
if (!empty($filterStatus)) {
    $countSql .= " AND d.delivery_status = ?";
}
if (!empty($filterDays) && is_numeric($filterDays)) {
    $countSql .= " AND d.created_at >= datetime('now', '-{$filterDays} days')";
}

$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCount / $perPage);

$sql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusBadges = [
    'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'border' => 'border-yellow-300', 'icon' => 'hourglass-split'],
    'pending_retry' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-orange-300', 'icon' => 'arrow-repeat'],
    'delivered' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-300', 'icon' => 'check-circle-fill'],
    'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-300', 'icon' => 'x-circle']
];

$html = '';
foreach ($deliveries as $d) {
    $badge = $statusBadges[$d['delivery_status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-gray-300', 'icon' => 'question-circle'];
    $productType = $d['product_type'] === 'tool' ? 'Tool' : 'Template';
    $productBg = $d['product_type'] === 'tool' ? 'bg-blue-100 text-blue-700 border-blue-300' : 'bg-purple-100 text-purple-700 border-purple-300';
    
    $html .= '<tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
        <td class="py-3 px-4 text-sm font-medium text-gray-600 hidden sm:table-cell">#' . htmlspecialchars($d['id']) . '</td>
        <td class="py-3 px-4 text-sm text-gray-900">
            <a href="/admin/orders.php?view=' . $d['order_id'] . '" class="text-primary-600 hover:text-primary-700 font-medium">
                #' . $d['order_id'] . '
            </a>
        </td>
        <td class="py-3 px-4 text-sm hidden md:table-cell">
            <div class="font-medium text-gray-900">' . htmlspecialchars($d['customer_name']) . '</div>
            <div class="text-xs text-gray-600">' . htmlspecialchars($d['customer_email']) . '</div>
        </td>
        <td class="py-3 px-4 text-sm text-gray-900 hidden lg:table-cell">' . htmlspecialchars($d['product_name'] ?? '') . '</td>
        <td class="py-3 px-4 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' . $productBg . ' border">
                <i class="bi bi-' . ($d['product_type'] === 'tool' ? 'tools' : 'palette') . ' mr-1"></i> ' . $productType . '
            </span>
        </td>
        <td class="py-3 px-4 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' . $badge['bg'] . ' ' . $badge['text'] . ' border ' . $badge['border'] . '">
                <i class="bi bi-' . $badge['icon'] . ' mr-1"></i>
                <span class="hidden sm:inline">' . ucfirst(str_replace('_', ' ', $d['delivery_status'])) . '</span>
                <span class="sm:hidden">' . substr(ucfirst(str_replace('_', ' ', $d['delivery_status'])), 0, 3) . '</span>
            </span>
        </td>
        <td class="py-3 px-4 text-sm text-gray-700 hidden lg:table-cell">
            ' . date('M d, Y', strtotime($d['created_at'])) . '
            <div class="text-xs text-gray-600">' . date('H:i', strtotime($d['created_at'])) . '</div>
        </td>
        <td class="py-3 px-4 text-sm text-center">
            <a href="/admin/orders.php?view=' . $d['order_id'] . '" class="inline-flex items-center px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-lg transition-colors">
                <i class="bi bi-eye mr-1"></i> View
            </a>
        </td>
    </tr>';
}

$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml .= '<div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200"><div class="text-sm text-gray-600">Showing ' . (($page - 1) * $perPage + 1) . '-' . min($page * $perPage, $totalCount) . ' of ' . $totalCount . ' deliveries</div><div class="flex gap-2">';
    
    if ($page > 1) {
        $paginationHtml .= '<button onclick="loadDeliveriesPage(' . ($page - 1) . ')" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-900 text-sm font-semibold rounded-lg">← Prev</button>';
    }
    
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    
    if ($startPage > 1) {
        $paginationHtml .= '<button onclick="loadDeliveriesPage(1)" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-900 text-sm font-semibold rounded-lg">1</button>';
        if ($startPage > 2) $paginationHtml .= '<span class="px-2 py-1 text-gray-600">...</span>';
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $page) {
            $paginationHtml .= '<button class="px-3 py-1 bg-primary-600 text-white text-sm font-semibold rounded-lg">' . $i . '</button>';
        } else {
            $paginationHtml .= '<button onclick="loadDeliveriesPage(' . $i . ')" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-900 text-sm font-semibold rounded-lg">' . $i . '</button>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) $paginationHtml .= '<span class="px-2 py-1 text-gray-600">...</span>';
        $paginationHtml .= '<button onclick="loadDeliveriesPage(' . $totalPages . ')" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-900 text-sm font-semibold rounded-lg">' . $totalPages . '</button>';
    }
    
    if ($page < $totalPages) {
        $paginationHtml .= '<button onclick="loadDeliveriesPage(' . ($page + 1) . ')" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-900 text-sm font-semibold rounded-lg">Next →</button>';
    }
    
    $paginationHtml .= '</div></div>';
}

header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'pagination' => $paginationHtml,
    'page' => $page,
    'totalPages' => $totalPages,
    'total' => $totalCount
]);
