<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();
requireAdmin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$search = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

if ($action === 'search') {
    $db = getDb();
    
    // Build search query
    $sqlWhere = "WHERE active = 1";
    $params = [];
    
    if (!empty($search)) {
        $sqlWhere .= " AND (name LIKE ? OR description LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM tools $sqlWhere");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Calculate pagination
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    // Fetch tools
    $stmt = $db->prepare("SELECT id, name, description, price, stock, active, upload_complete FROM tools $sqlWhere ORDER BY name ASC LIMIT ? OFFSET ?");
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get file counts for each tool
    foreach ($tools as &$tool) {
        $fileStmt = $db->prepare("SELECT COUNT(*) as count FROM tool_files WHERE tool_id = ?");
        $fileStmt->execute([$tool['id']]);
        $tool['file_count'] = (int)$fileStmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    echo json_encode([
        'success' => true,
        'tools' => $tools,
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
        'perPage' => $perPage
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
