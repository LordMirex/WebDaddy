<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

$period = $_GET['period'] ?? '7days';
$page = max(1, (int)($_GET['page'] ?? 1));
$ipFilter = trim($_GET['ip'] ?? '');
$perPage = 2;

// Date filters
$dateFilter = '';
switch ($period) {
    case '24hours':
        $dateFilter = " AND visit_date = DATE('now')";
        break;
    case '7days':
        $dateFilter = " AND visit_date >= DATE('now', '-7 days')";
        break;
    case '30days':
        $dateFilter = " AND visit_date >= DATE('now', '-30 days')";
        break;
    case '90days':
        $dateFilter = " AND visit_date >= DATE('now', '-90 days')";
        break;
    case 'all':
        $dateFilter = '';
        break;
}

$ipFilterQuery = '';
if (!empty($ipFilter)) {
    $ipFilterQuery = " AND ip_address LIKE " . $db->quote('%' . $ipFilter . '%');
}

try {
    // Get total count
    $totalCount = $db->query("SELECT COUNT(*) FROM page_visits WHERE 1=1 $dateFilter $ipFilterQuery")->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);
    
    // Ensure page is within bounds
    $page = min($page, max(1, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    // Get visits
    $visits = $db->query("
        SELECT 
            page_url,
            page_title,
            visit_date,
            visit_time,
            referrer,
            ip_address,
            device_type
        FROM page_visits
        WHERE 1=1 $dateFilter $ipFilterQuery
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedVisits = [];
    foreach ($visits as $visit) {
        $device = $visit['device_type'] ?? 'Desktop';
        $deviceIcons = ['Mobile' => 'phone', 'Tablet' => 'tablet', 'Desktop' => 'laptop'];
        $deviceColors = ['Mobile' => 'text-green-600', 'Tablet' => 'text-purple-600', 'Desktop' => 'text-blue-600'];
        
        $formattedVisits[] = [
            'page_url' => htmlspecialchars($visit['page_url']),
            'page_title' => htmlspecialchars($visit['page_title'] ?: 'Untitled'),
            'visit_date_time' => htmlspecialchars($visit['visit_date'] . ' ' . $visit['visit_time']),
            'referrer' => htmlspecialchars($visit['referrer'] ?: 'Direct'),
            'ip_address' => htmlspecialchars($visit['ip_address']),
            'device_type' => $device,
            'device_icon' => $deviceIcons[$device] ?? 'laptop',
            'device_color' => $deviceColors[$device] ?? 'text-gray-600'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'page' => $page,
        'total_pages' => max(1, $totalPages),
        'total_count' => (int)$totalCount,
        'per_page' => $perPage,
        'offset' => $offset,
        'visits' => $formattedVisits
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
