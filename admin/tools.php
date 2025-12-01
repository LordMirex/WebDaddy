<?php
$pageTitle = 'Working Tools Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url_utils.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/tool_files.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_tool') {
        $name = sanitizeInput($_POST['name']);
        $category = sanitizeInput($_POST['category']);
        $toolType = sanitizeInput($_POST['tool_type']);
        $price = floatval($_POST['price']);
        $shortDescription = sanitizeInput($_POST['short_description']);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $seoKeywords = sanitizeInput($_POST['seo_keywords'] ?? '');
        
        $croppedThumbnailData = sanitizeInput($_POST['thumbnail_cropped_data'] ?? '');
        $thumbnailUrlInput = sanitizeInput($_POST['thumbnail_url'] ?? '');
        $thumbnailUrl = !empty($croppedThumbnailData) ? $croppedThumbnailData : $thumbnailUrlInput;
        $thumbnailUrl = UrlUtils::normalizeUploadUrl($thumbnailUrl);
        
        $videoType = sanitizeInput($_POST['video_type'] ?? 'none');
        $demoUrl = null;
        $demoVideoUrl = null;
        $mediaType = 'banner';
        
        if ($videoType === 'demo_url') {
            $demoUrl = sanitizeInput($_POST['demo_url_input'] ?? '');
            $mediaType = 'demo_url';
        } elseif ($videoType === 'video') {
            $uploadedVideoUrl = sanitizeInput($_POST['demo_video_uploaded_url'] ?? '');
            $demoVideoUrl = UrlUtils::normalizeUploadUrl($uploadedVideoUrl);
            $mediaType = 'video';
        }
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        $priorityOrder = isset($_POST['priority_order']) ? intval($_POST['priority_order']) : null;
        $priorityOrder = ($priorityOrder > 0 && $priorityOrder <= 3) ? $priorityOrder : null;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
        } elseif (empty($thumbnailUrl)) {
            $errorMessage = 'Product banner image is required.';
        } else {
            try {
                $slug = generateToolSlug($name);
                
                $toolId = createTool([
                    'name' => $name,
                    'slug' => $slug,
                    'category' => $category,
                    'tool_type' => $toolType,
                    'short_description' => $shortDescription,
                    'description' => $description,
                    'features' => $features,
                    'seo_keywords' => $seoKeywords,
                    'price' => $price,
                    'thumbnail_url' => $thumbnailUrl,
                    'media_type' => $mediaType,
                    'demo_url' => $demoUrl,
                    'demo_video_url' => $demoVideoUrl,
                    'delivery_instructions' => $deliveryInstructions,
                    'stock_unlimited' => $stockUnlimited,
                    'stock_quantity' => $stockQuantity,
                    'low_stock_threshold' => $lowStockThreshold,
                    'active' => $active,
                    'priority_order' => $priorityOrder
                ]);
                
                if ($toolId) {
                    $successMessage = 'Tool created successfully!';
                    logActivity('tool_created', "Tool created: $name", getAdminId());
                } else {
                    $errorMessage = 'Failed to create tool.';
                }
            } catch (Exception $e) {
                error_log('Tool creation error: ' . $e->getMessage());
                $errorMessage = 'Error creating tool: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_tool') {
        $toolId = intval($_POST['tool_id']);
        $name = sanitizeInput($_POST['name']);
        $category = sanitizeInput($_POST['category']);
        $toolType = sanitizeInput($_POST['tool_type']);
        $price = floatval($_POST['price']);
        $shortDescription = sanitizeInput($_POST['short_description']);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $seoKeywords = sanitizeInput($_POST['seo_keywords'] ?? '');
        
        $croppedThumbnailData = sanitizeInput($_POST['thumbnail_cropped_data'] ?? '');
        $thumbnailUrlInput = sanitizeInput($_POST['thumbnail_url'] ?? '');
        $thumbnailUrl = !empty($croppedThumbnailData) ? $croppedThumbnailData : $thumbnailUrlInput;
        $thumbnailUrl = UrlUtils::normalizeUploadUrl($thumbnailUrl);
        
        $videoType = sanitizeInput($_POST['video_type'] ?? 'none');
        $demoUrl = null;
        $demoVideoUrl = null;
        $mediaType = 'banner';
        
        if ($videoType === 'demo_url') {
            $demoUrl = sanitizeInput($_POST['demo_url_input'] ?? '');
            $mediaType = 'demo_url';
        } elseif ($videoType === 'video') {
            $uploadedVideoUrl = sanitizeInput($_POST['demo_video_uploaded_url'] ?? '');
            $demoVideoUrl = UrlUtils::normalizeUploadUrl($uploadedVideoUrl);
            $mediaType = 'video';
        }
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        $priorityOrder = isset($_POST['priority_order']) ? intval($_POST['priority_order']) : null;
        $priorityOrder = ($priorityOrder > 0 && $priorityOrder <= 3) ? $priorityOrder : null;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
        } elseif (empty($thumbnailUrl)) {
            $errorMessage = 'Product banner image is required.';
        } else {
            try {
                $slug = generateToolSlug($name, $toolId);
                
                $result = updateTool($toolId, [
                    'name' => $name,
                    'slug' => $slug,
                    'category' => $category,
                    'tool_type' => $toolType,
                    'short_description' => $shortDescription,
                    'description' => $description,
                    'features' => $features,
                    'seo_keywords' => $seoKeywords,
                    'price' => $price,
                    'thumbnail_url' => $thumbnailUrl,
                    'media_type' => $mediaType,
                    'demo_url' => $demoUrl,
                    'demo_video_url' => $demoVideoUrl,
                    'delivery_instructions' => $deliveryInstructions,
                    'stock_unlimited' => $stockUnlimited,
                    'stock_quantity' => $stockQuantity,
                    'low_stock_threshold' => $lowStockThreshold,
                    'active' => $active,
                    'priority_order' => $priorityOrder
                ]);
                
                if ($result) {
                    $successMessage = 'Tool updated successfully!';
                    logActivity('tool_updated', "Tool updated: $name", getAdminId());
                } else {
                    $errorMessage = 'Failed to update tool.';
                }
            } catch (Exception $e) {
                error_log('Tool update error: ' . $e->getMessage());
                $errorMessage = 'Error updating tool: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_tool') {
        $toolId = intval($_POST['tool_id']);
        
        try {
            $tool = getToolById($toolId);
            if ($tool) {
                $result = deleteTool($toolId);
                if ($result) {
                    $successMessage = 'Tool deleted successfully!';
                    logActivity('tool_deleted', "Tool deleted: {$tool['name']}", getAdminId());
                } else {
                    $errorMessage = 'Failed to delete tool.';
                }
            } else {
                $errorMessage = 'Tool not found.';
            }
        } catch (Exception $e) {
            error_log('Tool deletion error: ' . $e->getMessage());
            $errorMessage = 'Error deleting tool: ' . $e->getMessage();
        }
    } elseif ($action === 'adjust_stock') {
        $toolId = intval($_POST['tool_id']);
        $adjustment = intval($_POST['adjustment']);
        
        try {
            $tool = getToolById($toolId);
            if (!$tool) {
                $errorMessage = 'Tool not found.';
            } elseif ($tool['stock_unlimited'] == 1) {
                $errorMessage = 'Cannot adjust stock for unlimited inventory tool.';
            } else {
                if ($adjustment > 0) {
                    $result = incrementToolStock($toolId, $adjustment);
                    if ($result) {
                        $successMessage = "Added $adjustment units to stock.";
                        logActivity('tool_stock_adjusted', "Stock adjusted for {$tool['name']}: +$adjustment", getAdminId());
                    } else {
                        $errorMessage = 'Failed to add stock.';
                    }
                } else {
                    $result = decrementToolStock($toolId, abs($adjustment));
                    if ($result) {
                        $successMessage = "Removed " . abs($adjustment) . " units from stock.";
                        logActivity('tool_stock_adjusted', "Stock adjusted for {$tool['name']}: $adjustment", getAdminId());
                    } else {
                        $errorMessage = 'Failed to remove stock. Insufficient quantity?';
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Stock adjustment error: ' . $e->getMessage());
            $errorMessage = 'Error adjusting stock: ' . $e->getMessage();
        }
    } elseif ($action === 'toggle_upload_complete') {
        $toolId = intval($_POST['tool_id']);
        $uploadComplete = isset($_POST['upload_complete']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE tools SET upload_complete = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$uploadComplete, $toolId]);
            
            $tool = getToolById($toolId);
            
            if ($uploadComplete) {
                require_once __DIR__ . '/../includes/delivery.php';
                $deliveryResult = processPendingToolDeliveries($toolId);
                
                $successMessage = "Tool marked as complete!";
                if ($deliveryResult['sent'] > 0) {
                    $successMessage .= " {$deliveryResult['sent']} customer(s) notified with download links.";
                }
                logActivity('tool_upload_complete', "Tool files marked complete: {$tool['name']}, {$deliveryResult['sent']} emails sent", getAdminId());
            } else {
                $successMessage = "Tool marked as incomplete (uploads in progress).";
                logActivity('tool_upload_incomplete', "Tool files marked incomplete: {$tool['name']}", getAdminId());
            }
        } catch (Exception $e) {
            error_log('Toggle upload complete error: ' . $e->getMessage());
            $errorMessage = 'Error updating upload status: ' . $e->getMessage();
        }
    } elseif ($action === 'upload_tool_file') {
        $toolId = intval($_POST['tool_id']);
        $fileType = sanitizeInput($_POST['file_type'] ?? 'attachment');
        $description = sanitizeInput($_POST['file_description'] ?? '');
        
        $uploadSuccess = false;
        try {
            if ($_POST['upload_mode'] === 'link') {
                $externalLink = sanitizeInput($_POST['external_link'] ?? '');
                if (empty($externalLink)) {
                    throw new Exception('Please provide a link URL');
                }
                if (!filter_var($externalLink, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL format');
                }
                addToolLink($toolId, $externalLink, $description);
                $successMessage = 'Link added successfully!';
                $uploadSuccess = true;
            } else {
                if (!isset($_FILES['tool_file'])) {
                    throw new Exception('No file selected. Please choose a file to upload.');
                }
                if ($_FILES['tool_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMap = [
                        UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds server limit)',
                        UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                        UPLOAD_ERR_PARTIAL => 'File upload incomplete',
                        UPLOAD_ERR_NO_FILE => 'No file selected',
                        UPLOAD_ERR_NO_TMP_DIR => 'Server error: no temp directory',
                        UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write file'
                    ];
                    throw new Exception($errorMap[$_FILES['tool_file']['error']] ?? 'File upload failed');
                }
                uploadToolFile($toolId, $_FILES['tool_file'], $fileType, $description);
                $successMessage = 'File uploaded successfully!';
                $uploadSuccess = true;
            }
            $tool = getToolById($toolId);
            logActivity('tool_file_uploaded', "File added to tool: {$tool['name']}", getAdminId());
            
            if ($uploadSuccess) {
                header('Location: /admin/tools.php?edit=' . $toolId . '&tab=files&success=file_uploaded');
                exit;
            }
        } catch (Exception $e) {
            error_log('Tool file upload error: ' . $e->getMessage());
            $errorMessage = 'Error uploading file: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_tool_file') {
        $fileId = intval($_POST['file_id']);
        $toolId = intval($_POST['tool_id']);
        
        try {
            $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ? AND tool_id = ?");
            $stmt->execute([$fileId, $toolId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new Exception('File not found');
            }
            
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath) && !preg_match('/^https?:\/\//i', $file['file_path'])) {
                unlink($filePath);
            }
            
            $stmt = $db->prepare("DELETE FROM tool_files WHERE id = ?");
            $stmt->execute([$fileId]);
            
            $successMessage = 'File deleted successfully!';
            $tool = getToolById($toolId);
            logActivity('tool_file_deleted', "File removed from tool: {$tool['name']}", getAdminId());
            
            header('Location: /admin/tools.php?edit=' . $toolId . '&tab=files');
            exit;
        } catch (Exception $e) {
            error_log('Tool file delete error: ' . $e->getMessage());
            $errorMessage = 'Error deleting file: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$filterCategory = $_GET['category'] ?? '';
$filterStatus = $_GET['status'] ?? 'active';

// Get all tools (including out-of-stock) for admin view
// Use $inStockOnly = false to show ALL tools regardless of stock
$allTools = getTools(false, $filterCategory ? $filterCategory : null, null, null, false);

// Apply status filter
if ($filterStatus === 'active') {
    $tools = array_filter($allTools, fn($t) => $t['active'] == 1);
} elseif ($filterStatus === 'inactive') {
    $tools = array_filter($allTools, fn($t) => $t['active'] == 0);
} else {
    $tools = $allTools;
}

// Get categories for filter dropdown
$categories = getToolCategories();

// Get low stock tools for alerts
$lowStockTools = getLowStockTools();
$outOfStockTools = getOutOfStockTools();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalTools = count($tools);
$totalPages = ceil($totalTools / $perPage);
$offset = ($page - 1) * $perPage;
$tools = array_slice($tools, $offset, $perPage);

// Get tool for editing
$editTool = null;
if (isset($_GET['edit'])) {
    $editTool = getToolById(intval($_GET['edit']));
}

require_once __DIR__ . '/includes/header.php';
?>

<div x-data="{ 
    showCreateModal: <?php echo isset($_GET['create']) ? 'true' : 'false'; ?>,
    showEditModal: <?php echo $editTool ? 'true' : 'false'; ?>,
    showStockModal: false,
    stockToolId: null,
    stockToolName: '',
    currentStock: 0
}">

    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
        <div>
            <h1 class="text-4xl font-bold text-gray-900 flex items-center gap-3">
                <i class="bi bi-tools text-purple-600"></i> Working Tools Management
            </h1>
            <p class="text-gray-600 mt-2">Manage digital products and their inventory</p>
        </div>
        <button @click="showCreateModal = true" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg shadow-lg transition-all flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> Add New Tool
        </button>
    </div>

    <!-- Alerts -->
    <?php if (!empty($lowStockTools) || !empty($outOfStockTools)): ?>
    <div class="mb-6 space-y-3">
        <?php if (!empty($outOfStockTools)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
            <div class="flex items-center gap-2 font-semibold mb-2">
                <i class="bi bi-exclamation-triangle-fill"></i> Out of Stock (<?php echo count($outOfStockTools); ?>)
            </div>
            <div class="text-sm">
                <?php foreach ($outOfStockTools as $tool): ?>
                    <span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded mr-2 mb-1"><?php echo htmlspecialchars($tool['name']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($lowStockTools)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">
            <div class="flex items-center gap-2 font-semibold mb-2">
                <i class="bi bi-exclamation-circle-fill"></i> Low Stock Alert (<?php echo count($lowStockTools); ?>)
            </div>
            <div class="text-sm">
                <?php foreach ($lowStockTools as $tool): ?>
                    <span class="inline-block bg-yellow-100 text-yellow-800 px-2 py-1 rounded mr-2 mb-1">
                        <?php echo htmlspecialchars($tool['name']); ?> (<?php echo $tool['stock_quantity']; ?> left)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Success/Error Messages -->
    <?php if ($successMessage): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6">
        <i class="bi bi-check-circle mr-2"></i> <?php echo htmlspecialchars($successMessage); ?>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
        <i class="bi bi-x-circle mr-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition-colors">
                    <i class="bi bi-funnel mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Tools Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b-2 border-gray-200">
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tool</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Files Ready</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($tools)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="bi bi-inbox text-4xl mb-2"></i>
                            <p>No tools found. Click "Add New Tool" to get started.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tools as $tool): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <?php if (!empty($tool['thumbnail_url']) && trim($tool['thumbnail_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($tool['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>" class="w-20 h-20 object-cover rounded-lg shadow-sm" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="w-20 h-20 bg-purple-100 rounded-lg flex items-center justify-center shadow-sm hidden" style="display:none;">
                                        <i class="bi bi-tools text-purple-600 text-3xl"></i>
                                    </div>
                                    <?php else: ?>
                                    <div class="w-20 h-20 bg-purple-100 rounded-lg flex items-center justify-center shadow-sm">
                                        <i class="bi bi-tools text-purple-600 text-3xl"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tool['name']); ?></div>
                                        <?php if ($tool['short_description']): ?>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($tool['short_description'], 0, 50)); ?><?php echo strlen($tool['short_description']) > 50 ? '...' : ''; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($tool['category']): ?>
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded">
                                    <?php echo htmlspecialchars($tool['category']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-gray-700"><?php echo htmlspecialchars(ucfirst($tool['tool_type'])); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-gray-900"><?php echo formatCurrency($tool['price']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $fileCount = $db->prepare("SELECT COUNT(*) FROM tool_files WHERE tool_id = ?");
                                $fileCount->execute([$tool['id']]);
                                $toolFileCount = $fileCount->fetchColumn();
                                $uploadComplete = $tool['upload_complete'] ?? 0;
                                ?>
                                <?php if ($uploadComplete && $toolFileCount > 0): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded flex items-center gap-1 w-fit">
                                    <i class="bi bi-check-circle-fill"></i> <?php echo $toolFileCount; ?> file<?php echo $toolFileCount > 1 ? 's' : ''; ?>
                                </span>
                                <?php elseif ($toolFileCount > 0): ?>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded flex items-center gap-1 w-fit">
                                    <i class="bi bi-hourglass-split"></i> <?php echo $toolFileCount; ?> uploading...
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded flex items-center gap-1 w-fit">
                                    <i class="bi bi-dash-circle"></i> No files
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($tool['stock_unlimited'] == 1): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded flex items-center gap-1 w-fit">
                                    <i class="bi bi-infinity"></i> Unlimited
                                </span>
                                <?php else: ?>
                                    <?php
                                    $stockClass = 'bg-gray-100 text-gray-800';
                                    if ($tool['stock_quantity'] == 0) {
                                        $stockClass = 'bg-red-100 text-red-800';
                                    } elseif ($tool['stock_quantity'] <= $tool['low_stock_threshold']) {
                                        $stockClass = 'bg-yellow-100 text-yellow-800';
                                    }
                                    ?>
                                    <button 
                                        @click="stockToolId = <?php echo $tool['id']; ?>; stockToolName = '<?php echo htmlspecialchars($tool['name']); ?>'; currentStock = <?php echo $tool['stock_quantity']; ?>; showStockModal = true"
                                        class="px-2 py-1 <?php echo $stockClass; ?> text-xs font-semibold rounded hover:opacity-80 transition-opacity cursor-pointer"
                                    >
                                        <?php echo number_format($tool['stock_quantity']); ?> units
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($tool['active'] == 1): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">Active</span>
                                <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="?edit=<?php echo $tool['id']; ?>" class="px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-semibold rounded transition-colors">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this tool?');">
                                        <input type="hidden" name="action" value="delete_tool">
                                        <input type="hidden" name="tool_id" value="<?php echo $tool['id']; ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold rounded transition-colors">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <nav class="flex items-center justify-center gap-2 flex-wrap">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 border rounded-lg font-medium transition-colors <?php echo $i === $page ? 'bg-purple-600 border-purple-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
            <div class="text-center mt-3 text-sm text-gray-600">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalTools; ?> total tools)
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Tool Modal -->
    <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <input type="hidden" name="action" value="create_tool">
                
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-plus-circle text-purple-600"></i> Add New Working Tool
                    </h3>
                    <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Basic Info -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tool Name <span class="text-red-600">*</span></label>
                            <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="e.g., Premium API Key">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                            <input type="text" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="e.g., API Keys">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Price (₦) <span class="text-red-600">*</span></label>
                            <input type="number" name="price" step="0.01" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tool Type <span class="text-red-600">*</span></label>
                            <select name="tool_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="software">Software/License</option>
                                <option value="api_key">API Key</option>
                                <option value="subscription">Subscription</option>
                                <option value="digital_asset">Digital Asset</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Short Description</label>
                            <input type="text" name="short_description" maxlength="200" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Brief description">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Full Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Detailed description"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Features (JSON)</label>
                        <textarea name="features" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm" placeholder='["Feature 1", "Feature 2"]'></textarea>
                        <small class="text-gray-500 text-xs">JSON array format</small>
                    </div>
                    
                    <!-- Product Banner Image -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Banner Image <span class="text-red-600">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">Required. This image represents your tool on the marketplace.</p>
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="toggleToolThumbnailMode('url', 'create')" id="tool-thumbnail-url-btn-create" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">URL</button>
                            <button type="button" onclick="toggleToolThumbnailMode('upload', 'create')" id="tool-thumbnail-upload-btn-create" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                        </div>
                        <div id="tool-thumbnail-url-mode-create">
                            <input type="text" id="tool-thumbnail-url-input-create" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="thumbnail_url" required placeholder="https://example.com/image.jpg or /uploads/image.jpg">
                        </div>
                        <div id="tool-thumbnail-upload-mode-create" style="display: none;">
                            <input type="file" id="tool-thumbnail-file-input-create" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                            <div id="tool-thumbnail-cropper-container-create" style="margin-top: 15px; display: none;"></div>
                            <input type="hidden" id="tool-thumbnail-cropped-data-create" name="thumbnail_cropped_data">
                        </div>
                    </div>
                    
                    <!-- Video/Demo Section (Optional) -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preview/Demo (Optional)</label>
                        <p class="text-xs text-gray-500 mb-3">Add a video preview or demo link for customers to see your tool in action.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-none-label-create">
                                <input type="radio" name="video_type" value="none" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600" checked>
                                <span class="font-medium text-sm">🚫 None</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-video-label-create">
                                <input type="radio" name="video_type" value="video" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600">
                                <span class="font-medium text-sm">🎥 Video</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-demo-url-label-create">
                                <input type="radio" name="video_type" value="demo_url" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600">
                                <span class="font-medium text-sm">🌐 Demo URL</span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="tool-video-upload-section-create" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Demo Video</label>
                        <input type="file" id="tool-video-file-input-create" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                        <div id="tool-video-upload-progress-create" style="margin-top: 10px; display: none;">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div id="tool-video-progress-bar-create" class="bg-purple-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                                </div>
                                <span id="tool-video-progress-text-create" class="text-sm text-gray-600 flex items-center gap-1">
                                    <span id="tool-video-progress-percentage-create">0%</span>
                                    <svg id="tool-video-upload-check-create" class="w-4 h-4 text-green-600" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <input type="hidden" name="demo_video_uploaded_url" id="tool-video-uploaded-url-create" value="">
                        <small class="text-gray-500 text-xs mt-1 block">Upload demo video (MP4, WebM recommended, max 100MB)</small>
                    </div>
                    
                    <div id="tool-demo-url-section-create" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Demo URL</label>
                        <input type="text" name="demo_url_input" id="tool-demo-url-input-create" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" placeholder="https://example.com/demo or /path/to/demo">
                        <small class="text-gray-500 text-xs mt-1 block">Enter a URL for interactive preview or demo</small>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Delivery Instructions</label>
                        <textarea name="delivery_instructions" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Instructions for delivering this tool to customers"></textarea>
                    </div>
                    
                    <!-- Stock Management -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3" x-data="{ unlimited: true }">
                        <h4 class="font-bold text-gray-900 mb-2 flex items-center gap-2 text-sm">
                            <i class="bi bi-box-seam text-purple-600"></i> Inventory Management
                        </h4>
                        
                        <div class="mb-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="stock_unlimited" value="1" checked x-model="unlimited" class="w-4 h-4 text-purple-600 rounded">
                                <span class="font-semibold text-gray-700 text-sm">Unlimited Stock</span>
                            </label>
                        </div>
                        
                        <div x-show="!unlimited" x-transition class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Initial Stock Quantity</label>
                                <input type="number" name="stock_quantity" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" min="0" value="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Priority & Status -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Priority (Top 3 Featured)</label>
                        <select name="priority_order" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">None (Regular Listing)</option>
                            <option value="1">⭐ #1 - Top Priority</option>
                            <option value="2">⭐⭐ #2 - Second Priority</option>
                            <option value="3">⭐⭐⭐ #3 - Third Priority</option>
                        </select>
                        <small class="text-gray-500 text-xs mt-1 block">Select to feature this tool in the top 3 displayed first</small>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="active" value="1" checked class="w-4 h-4 text-purple-600 rounded">
                            <span class="font-semibold text-gray-700 text-sm">Active (visible to customers)</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showCreateModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        <i class="bi bi-x-circle mr-2"></i> Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-colors shadow-lg">
                        <i class="bi bi-plus-lg mr-2"></i> Create Tool
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tool Modal -->
    <?php if ($editTool): ?>
    <div x-show="showEditModal" x-transition class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <input type="hidden" name="action" value="update_tool">
                <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                    <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-pencil text-purple-600"></i> Edit Tool: <?php echo htmlspecialchars($editTool['name']); ?>
                    </h3>
                    <a href="/admin/tools.php" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Same form fields as create, but with values -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tool Name <span class="text-red-600">*</span></label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($editTool['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                            <input type="text" name="category" value="<?php echo htmlspecialchars($editTool['category'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Price (₦) <span class="text-red-600">*</span></label>
                            <input type="number" name="price" step="0.01" min="0" required value="<?php echo $editTool['price']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tool Type <span class="text-red-600">*</span></label>
                            <select name="tool_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="software" <?php echo $editTool['tool_type'] === 'software' ? 'selected' : ''; ?>>Software/License</option>
                                <option value="api_key" <?php echo $editTool['tool_type'] === 'api_key' ? 'selected' : ''; ?>>API Key</option>
                                <option value="subscription" <?php echo $editTool['tool_type'] === 'subscription' ? 'selected' : ''; ?>>Subscription</option>
                                <option value="digital_asset" <?php echo $editTool['tool_type'] === 'digital_asset' ? 'selected' : ''; ?>>Digital Asset</option>
                                <option value="other" <?php echo $editTool['tool_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Short Description</label>
                            <input type="text" name="short_description" maxlength="200" value="<?php echo htmlspecialchars($editTool['short_description'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Full Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo htmlspecialchars($editTool['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Features (JSON)</label>
                        <textarea name="features" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm"><?php echo htmlspecialchars($editTool['features'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Product Banner Image -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Banner Image <span class="text-red-600">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">Required. This image represents your tool on the marketplace.</p>
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="toggleToolThumbnailMode('url', 'edit')" id="tool-thumbnail-url-btn-edit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">URL</button>
                            <button type="button" onclick="toggleToolThumbnailMode('upload', 'edit')" id="tool-thumbnail-upload-btn-edit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                        </div>
                        <div id="tool-thumbnail-url-mode-edit">
                            <input type="text" id="tool-thumbnail-url-input-edit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="thumbnail_url" required value="<?php echo htmlspecialchars($editTool['thumbnail_url'] ?? ''); ?>" placeholder="https://example.com/image.jpg or /uploads/image.jpg">
                        </div>
                        <div id="tool-thumbnail-upload-mode-edit" style="display: none;">
                            <input type="file" id="tool-thumbnail-file-input-edit" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                            <div id="tool-thumbnail-cropper-container-edit" style="margin-top: 15px; display: none;"></div>
                            <input type="hidden" id="tool-thumbnail-cropped-data-edit" name="thumbnail_cropped_data">
                        </div>
                    </div>
                    
                    <!-- Video/Demo Section (Optional) -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preview/Demo (Optional)</label>
                        <p class="text-xs text-gray-500 mb-3">Add a video preview or demo link for customers to see your tool in action.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-none-label-edit">
                                <input type="radio" name="video_type" value="none" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo (!$editTool || (!$editTool['demo_url'] && !$editTool['demo_video_url'])) ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">🚫 None</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-video-label-edit">
                                <input type="radio" name="video_type" value="video" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo ($editTool && $editTool['demo_video_url']) ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">🎥 Video</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-demo-url-label-edit">
                                <input type="radio" name="video_type" value="demo_url" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo ($editTool && $editTool['demo_url']) ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">🌐 Demo URL</span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="tool-video-upload-section-edit" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Demo Video</label>
                        <input type="file" id="tool-video-file-input-edit" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                        <div id="tool-video-upload-progress-edit" style="margin-top: 10px; display: none;">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div id="tool-video-progress-bar-edit" class="bg-purple-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                                </div>
                                <span id="tool-video-progress-text-edit" class="text-sm text-gray-600 flex items-center gap-1">
                                    <span id="tool-video-progress-percentage-edit">0%</span>
                                    <svg id="tool-video-upload-check-edit" class="w-4 h-4 text-green-600" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <input type="hidden" name="demo_video_uploaded_url" id="tool-video-uploaded-url-edit" value="<?php echo htmlspecialchars($editTool['demo_video_url'] ?? ''); ?>">
                        <small class="text-gray-500 text-xs mt-1 block">Upload demo video (MP4, WebM recommended, max 100MB)</small>
                    </div>
                    
                    <div id="tool-demo-url-section-edit" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Demo URL</label>
                        <input type="text" name="demo_url_input" id="tool-demo-url-input-edit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($editTool['demo_url'] ?? ''); ?>" placeholder="https://example.com/demo or /path/to/demo">
                        <small class="text-gray-500 text-xs mt-1 block">Enter a URL for interactive preview or demo</small>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Delivery Instructions</label>
                        <textarea name="delivery_instructions" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?php echo htmlspecialchars($editTool['delivery_instructions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3" x-data="{ unlimited: <?php echo $editTool['stock_unlimited'] ? 'true' : 'false'; ?> }">
                        <h4 class="font-bold text-gray-900 mb-2 flex items-center gap-2 text-sm">
                            <i class="bi bi-box-seam text-purple-600"></i> Inventory Management
                        </h4>
                        
                        <div class="mb-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="stock_unlimited" value="1" <?php echo $editTool['stock_unlimited'] ? 'checked' : ''; ?> x-model="unlimited" class="w-4 h-4 text-purple-600 rounded">
                                <span class="font-semibold text-gray-700 text-sm">Unlimited Stock</span>
                            </label>
                        </div>
                        
                        <div x-show="!unlimited" x-transition class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Stock Quantity</label>
                                <input type="number" name="stock_quantity" min="0" value="<?php echo $editTool['stock_quantity']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" min="0" value="<?php echo $editTool['low_stock_threshold']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Priority (Top 3 Featured)</label>
                        <select name="priority_order" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">None (Regular Listing)</option>
                            <option value="1" <?php echo ($editTool && $editTool['priority_order'] == 1) ? 'selected' : ''; ?>>⭐ #1 - Top Priority</option>
                            <option value="2" <?php echo ($editTool && $editTool['priority_order'] == 2) ? 'selected' : ''; ?>>⭐⭐ #2 - Second Priority</option>
                            <option value="3" <?php echo ($editTool && $editTool['priority_order'] == 3) ? 'selected' : ''; ?>>⭐⭐⭐ #3 - Third Priority</option>
                        </select>
                        <small class="text-gray-500 text-xs mt-1 block">Select to feature this tool in the top 3 displayed first</small>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="active" value="1" <?php echo $editTool['active'] ? 'checked' : ''; ?> class="w-4 h-4 text-purple-600 rounded">
                            <span class="font-semibold text-gray-700 text-sm">Active (visible to customers)</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <a href="/admin/tools.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        <i class="bi bi-x-circle mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-colors shadow-lg">
                        <i class="bi bi-save mr-2"></i> Update Tool
                    </button>
                </div>
            </form>
            
            <!-- File Management Section -->
            <?php 
            $editToolFiles = getToolFiles($editTool['id']);
            $editUploadComplete = $editTool['upload_complete'] ?? 0;
            ?>
            <div class="border-t-4 border-purple-600">
                <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-200">
                    <h4 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-file-earmark-zip text-purple-600"></i> Downloadable Files
                        <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-800 text-xs font-semibold rounded"><?php echo count($editToolFiles); ?> file<?php echo count($editToolFiles) != 1 ? 's' : ''; ?></span>
                    </h4>
                    <p class="text-sm text-gray-600 mt-1">Upload files that customers will receive after purchase</p>
                </div>
                
                <div class="p-6 space-y-5">
                    <!-- Upload Complete Toggle -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4">
                        <form method="POST" class="flex items-center justify-between flex-wrap gap-4">
                            <input type="hidden" name="action" value="toggle_upload_complete">
                            <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $editUploadComplete ? 'bg-green-500' : 'bg-gray-300'; ?>">
                                    <i class="bi <?php echo $editUploadComplete ? 'bi-check-lg text-white text-2xl' : 'bi-hourglass-split text-gray-600 text-xl'; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="font-bold text-gray-900"><?php echo $editUploadComplete ? 'Files Ready for Delivery' : 'Upload In Progress'; ?></h5>
                                    <p class="text-sm text-gray-600"><?php echo $editUploadComplete 
                                        ? 'Customers waiting for files will receive download emails' 
                                        : 'Mark complete when all files are uploaded'; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="upload_complete" value="1" <?php echo $editUploadComplete ? 'checked' : ''; ?> class="sr-only peer" onchange="this.form.submit()">
                                    <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-500"></div>
                                    <span class="ml-3 text-sm font-semibold text-gray-700"><?php echo $editUploadComplete ? 'Complete' : 'In Progress'; ?></span>
                                </label>
                            </div>
                        </form>
                        <?php if (!$editUploadComplete && count($editToolFiles) > 0): ?>
                        <p class="mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                            <i class="bi bi-info-circle mr-1"></i> <strong>Note:</strong> Customers won't receive download emails until you toggle this to "Complete"
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Current Files List -->
                    <?php if (!empty($editToolFiles)): ?>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                            <span class="font-semibold text-gray-700 text-sm">Current Files</span>
                            <span class="text-xs text-gray-500"><?php echo count($editToolFiles); ?> file<?php echo count($editToolFiles) != 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($editToolFiles as $file): ?>
                            <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <?php 
                                    $isLink = preg_match('/^https?:\/\//i', $file['file_path']);
                                    $fileIcon = $isLink ? 'bi-link-45deg text-blue-600' : 'bi-file-earmark-zip text-purple-600';
                                    ?>
                                    <i class="bi <?php echo $fileIcon; ?> text-xl flex-shrink-0"></i>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $isLink ? 'External Link' : number_format($file['file_size'] / 1024, 1) . ' KB'; ?>
                                            <?php if ($file['description']): ?>
                                             · <?php echo htmlspecialchars(substr($file['description'], 0, 30)); ?><?php echo strlen($file['description']) > 30 ? '...' : ''; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Delete this file?');">
                                    <input type="hidden" name="action" value="delete_tool_file">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                                    <button type="submit" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete file">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add New File Form -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                        <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="bi bi-cloud-upload text-purple-600"></i> Add New File
                        </h5>
                        <?php if (isset($_GET['success']) && $_GET['success'] === 'file_uploaded'): ?>
                        <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800 flex items-center gap-2">
                                <i class="bi bi-check-circle-fill"></i> File uploaded successfully!
                            </p>
                        </div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4" id="toolFileUploadForm">
                            <input type="hidden" name="action" value="upload_tool_file">
                            <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                            
                            <div class="flex gap-2 mb-3">
                                <button type="button" onclick="toggleToolFileMode('file')" id="tool-file-mode-file" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">Upload File</button>
                                <button type="button" onclick="toggleToolFileMode('link')" id="tool-file-mode-link" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Add Link</button>
                            </div>
                            <input type="hidden" name="upload_mode" id="upload_mode" value="file">
                            
                            <div id="file-upload-section">
                                <input type="file" name="tool_file" class="w-full px-3 py-2 bg-white border-2 border-dashed border-gray-300 rounded-lg text-sm cursor-pointer hover:border-purple-400 transition-colors">
                                <p class="text-xs text-gray-500 mt-1">Max 50MB per file</p>
                            </div>
                            
                            <div id="link-upload-section" style="display: none;">
                                <input type="url" name="external_link" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm" placeholder="https://example.com/resource">
                                <p class="text-xs text-gray-500 mt-1">Paste full URL to external resource</p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">📁 File Type</label>
                                    <select name="file_type" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <option value="zip_archive">📦 ZIP Archive</option>
                                        <option value="attachment">📎 Attachment</option>
                                        <option value="text_instructions">📝 Instructions</option>
                                        <option value="code">💻 Code/Script</option>
                                        <option value="access_key">🔑 Access Key</option>
                                        <option value="link">🔗 External Link</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">📝 Description</label>
                                    <input type="text" name="file_description" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Optional (max 100 chars)">
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2 shadow-md">
                                <i class="bi bi-cloud-upload"></i> Upload File
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stock Adjustment Modal -->
    <div x-show="showStockModal" x-transition class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="tool_id" x-bind:value="stockToolId">
                
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-box-seam text-purple-600"></i> Adjust Stock
                    </h3>
                    <button type="button" @click="showStockModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="text-sm text-gray-600 mb-1">Tool:</div>
                        <div class="font-bold text-gray-900" x-text="stockToolName"></div>
                        <div class="text-sm text-gray-600 mt-2">Current Stock:</div>
                        <div class="font-bold text-purple-600 text-lg" x-text="currentStock + ' units'"></div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Adjustment</label>
                        <input type="number" name="adjustment" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="e.g., +10 or -5">
                        <small class="text-gray-500 text-xs mt-1 block">
                            Use positive numbers to add stock, negative to remove
                        </small>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showStockModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-check-lg mr-2"></i> Adjust Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="/assets/js/image-cropper.js"></script>
<script>
let toolThumbnailCropperCreate = null;
let toolThumbnailCropperEdit = null;

function toggleToolThumbnailMode(mode, formType) {
    const urlMode = document.getElementById(`tool-thumbnail-url-mode-${formType}`);
    const uploadMode = document.getElementById(`tool-thumbnail-upload-mode-${formType}`);
    const urlBtn = document.getElementById(`tool-thumbnail-url-btn-${formType}`);
    const uploadBtn = document.getElementById(`tool-thumbnail-upload-btn-${formType}`);
    const urlInput = document.getElementById(`tool-thumbnail-url-input-${formType}`);
    const croppedDataInput = document.getElementById(`tool-thumbnail-cropped-data-${formType}`);
    
    if (mode === 'url') {
        urlMode.style.display = 'block';
        uploadMode.style.display = 'none';
        urlBtn.classList.add('bg-purple-600', 'text-white');
        urlBtn.classList.remove('bg-gray-200', 'text-gray-700');
        uploadBtn.classList.remove('bg-purple-600', 'text-white');
        uploadBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.required = true;
        croppedDataInput.value = '';
        
        const cropper = formType === 'create' ? toolThumbnailCropperCreate : toolThumbnailCropperEdit;
        if (cropper) {
            cropper.destroy();
            if (formType === 'create') {
                toolThumbnailCropperCreate = null;
            } else {
                toolThumbnailCropperEdit = null;
            }
        }
    } else {
        urlMode.style.display = 'none';
        uploadMode.style.display = 'block';
        uploadBtn.classList.add('bg-purple-600', 'text-white');
        uploadBtn.classList.remove('bg-gray-200', 'text-gray-700');
        urlBtn.classList.remove('bg-purple-600', 'text-white');
        urlBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.required = false;
        urlInput.value = '';
    }
}

document.getElementById('tool-thumbnail-file-input-create')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const container = document.getElementById('tool-thumbnail-cropper-container-create');
    container.style.display = 'block';
    container.innerHTML = '';
    
    if (toolThumbnailCropperCreate) {
        toolThumbnailCropperCreate.destroy();
    }
    
    toolThumbnailCropperCreate = new ImageCropper({
        aspectRatio: 16 / 9,
        minCropSize: 100,
        maxZoom: 3,
        onCropChange: (cropData) => {
            console.log('Crop changed:', cropData);
        }
    });
    
    container.appendChild(toolThumbnailCropperCreate.getElement());
    
    try {
        await toolThumbnailCropperCreate.loadImage(file);
    } catch (error) {
        console.error('Error loading image:', error);
        alert('Failed to load image. Please try again.');
    }
});

document.getElementById('tool-thumbnail-file-input-edit')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const container = document.getElementById('tool-thumbnail-cropper-container-edit');
    container.style.display = 'block';
    container.innerHTML = '';
    
    if (toolThumbnailCropperEdit) {
        toolThumbnailCropperEdit.destroy();
    }
    
    toolThumbnailCropperEdit = new ImageCropper({
        aspectRatio: 16 / 9,
        minCropSize: 100,
        maxZoom: 3,
        onCropChange: (cropData) => {
            console.log('Crop changed:', cropData);
        }
    });
    
    container.appendChild(toolThumbnailCropperEdit.getElement());
    
    try {
        await toolThumbnailCropperEdit.loadImage(file);
    } catch (error) {
        console.error('Error loading image:', error);
        alert('Failed to load image. Please try again.');
    }
});

const createForm = document.querySelector('form[method="POST"] input[name="action"][value="create_tool"]')?.closest('form');
if (createForm) {
    createForm.addEventListener('submit', async function(e) {
        const uploadMode = document.getElementById('tool-thumbnail-upload-mode-create');
        const croppedDataInput = document.getElementById('tool-thumbnail-cropped-data-create');
        
        if (uploadMode && uploadMode.style.display !== 'none' && toolThumbnailCropperCreate) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i> Uploading...';
            
            try {
                const croppedBlob = await toolThumbnailCropperCreate.getCroppedBlob({
                    width: 1280,
                    height: 720,
                    type: 'image/jpeg',
                    quality: 0.9
                });
                
                if (!croppedBlob) {
                    throw new Error('Failed to crop image');
                }
                
                const formData = new FormData();
                formData.append('file', croppedBlob, 'thumbnail.jpg');
                formData.append('upload_type', 'image');
                formData.append('category', 'tools');
                
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Upload failed');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    croppedDataInput.value = result.url;
                    toolThumbnailCropperCreate.destroy();
                    toolThumbnailCropperCreate = null;
                    
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    e.target.submit();
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Failed to upload image: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            
            return false;
        }
    });
}

const editForm = document.querySelector('form[method="POST"] input[name="action"][value="update_tool"]')?.closest('form');
if (editForm) {
    editForm.addEventListener('submit', async function(e) {
        const uploadMode = document.getElementById('tool-thumbnail-upload-mode-edit');
        const croppedDataInput = document.getElementById('tool-thumbnail-cropped-data-edit');
        
        if (uploadMode && uploadMode.style.display !== 'none' && toolThumbnailCropperEdit) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i> Uploading...';
            
            try {
                const croppedBlob = await toolThumbnailCropperEdit.getCroppedBlob({
                    width: 1280,
                    height: 720,
                    type: 'image/jpeg',
                    quality: 0.9
                });
                
                if (!croppedBlob) {
                    throw new Error('Failed to crop image');
                }
                
                const formData = new FormData();
                formData.append('file', croppedBlob, 'thumbnail.jpg');
                formData.append('upload_type', 'image');
                formData.append('category', 'tools');
                
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Upload failed');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    croppedDataInput.value = result.url;
                    toolThumbnailCropperEdit.destroy();
                    toolThumbnailCropperEdit = null;
                    
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    e.target.submit();
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Failed to upload image: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            
            return false;
        }
    });
}

// Tool Video Type Handler
function handleToolVideoTypeChange(formType) {
    const noneLabel = document.getElementById(`tool-video-type-none-label-${formType}`);
    const videoLabel = document.getElementById(`tool-video-type-video-label-${formType}`);
    const demoUrlLabel = document.getElementById(`tool-video-type-demo-url-label-${formType}`);
    
    if (!noneLabel || !videoLabel || !demoUrlLabel) {
        console.error('Video type labels not found for formType:', formType);
        return;
    }
    
    const noneRadio = noneLabel.querySelector('input[type="radio"]');
    const videoRadio = videoLabel.querySelector('input[type="radio"]');
    const demoUrlRadio = demoUrlLabel.querySelector('input[type="radio"]');
    
    let selectedType = 'none';
    if (videoRadio && videoRadio.checked) {
        selectedType = 'video';
    } else if (demoUrlRadio && demoUrlRadio.checked) {
        selectedType = 'demo_url';
    }
    
    const demoUrlSection = document.getElementById(`tool-demo-url-section-${formType}`);
    const videoUploadSection = document.getElementById(`tool-video-upload-section-${formType}`);
    
    demoUrlSection.style.display = 'none';
    videoUploadSection.style.display = 'none';
    
    noneLabel.classList.remove('border-purple-600', 'bg-purple-50');
    videoLabel.classList.remove('border-purple-600', 'bg-purple-50');
    demoUrlLabel.classList.remove('border-purple-600', 'bg-purple-50');
    
    if (selectedType === 'demo_url') {
        demoUrlSection.style.display = 'block';
        demoUrlLabel.classList.add('border-purple-600', 'bg-purple-50');
    } else if (selectedType === 'video') {
        videoUploadSection.style.display = 'block';
        videoLabel.classList.add('border-purple-600', 'bg-purple-50');
    } else {
        noneLabel.classList.add('border-purple-600', 'bg-purple-50');
    }
}

// Initialize video type on page load for edit form
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('tool-video-type-video-label-edit')) {
        handleToolVideoTypeChange('edit');
    }
});

// Toggle between file upload and link mode for tool files
function toggleToolFileMode(mode) {
    const fileBtn = document.getElementById('tool-file-mode-file');
    const linkBtn = document.getElementById('tool-file-mode-link');
    const fileSection = document.getElementById('file-upload-section');
    const linkSection = document.getElementById('link-upload-section');
    const uploadModeInput = document.getElementById('upload_mode');
    
    if (mode === 'file') {
        fileBtn.classList.add('bg-purple-600', 'text-white');
        fileBtn.classList.remove('bg-gray-200', 'text-gray-700');
        linkBtn.classList.remove('bg-purple-600', 'text-white');
        linkBtn.classList.add('bg-gray-200', 'text-gray-700');
        fileSection.style.display = 'block';
        linkSection.style.display = 'none';
        uploadModeInput.value = 'file';
    } else {
        linkBtn.classList.add('bg-purple-600', 'text-white');
        linkBtn.classList.remove('bg-gray-200', 'text-gray-700');
        fileBtn.classList.remove('bg-purple-600', 'text-white');
        fileBtn.classList.add('bg-gray-200', 'text-gray-700');
        linkSection.style.display = 'block';
        fileSection.style.display = 'none';
        uploadModeInput.value = 'link';
    }
}

// Tool Video Upload Handler - Create
document.getElementById('tool-video-file-input-create')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const progressDiv = document.getElementById('tool-video-upload-progress-create');
    const progressBar = document.getElementById('tool-video-progress-bar-create');
    const progressText = document.getElementById('tool-video-progress-percentage-create');
    const checkIcon = document.getElementById('tool-video-upload-check-create');
    const urlInput = document.getElementById('tool-video-uploaded-url-create');
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    checkIcon.style.display = 'none';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', 'video');
    formData.append('category', 'tools');
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });
        
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                const result = JSON.parse(xhr.responseText);
                if (result.success) {
                    urlInput.value = result.url;
                    checkIcon.style.display = 'inline';
                    console.log('Video uploaded successfully:', result.url);
                } else {
                    alert('Video upload failed: ' + (result.error || 'Unknown error'));
                    progressDiv.style.display = 'none';
                }
            } else {
                alert('Video upload failed');
                progressDiv.style.display = 'none';
            }
        });
        
        xhr.addEventListener('error', function() {
            alert('Video upload failed');
            progressDiv.style.display = 'none';
        });
        
        xhr.open('POST', '/api/upload.php');
        xhr.send(formData);
    } catch (error) {
        console.error('Video upload error:', error);
        alert('Failed to upload video: ' + error.message);
        progressDiv.style.display = 'none';
    }
});

// Tool Video Upload Handler - Edit
document.getElementById('tool-video-file-input-edit')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const progressDiv = document.getElementById('tool-video-upload-progress-edit');
    const progressBar = document.getElementById('tool-video-progress-bar-edit');
    const progressText = document.getElementById('tool-video-progress-percentage-edit');
    const checkIcon = document.getElementById('tool-video-upload-check-edit');
    const urlInput = document.getElementById('tool-video-uploaded-url-edit');
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    checkIcon.style.display = 'none';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', 'video');
    formData.append('category', 'tools');
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });
        
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                const result = JSON.parse(xhr.responseText);
                if (result.success) {
                    urlInput.value = result.url;
                    checkIcon.style.display = 'inline';
                    console.log('Video uploaded successfully:', result.url);
                } else {
                    alert('Video upload failed: ' + (result.error || 'Unknown error'));
                    progressDiv.style.display = 'none';
                }
            } else {
                alert('Video upload failed');
                progressDiv.style.display = 'none';
            }
        });
        
        xhr.addEventListener('error', function() {
            alert('Video upload failed');
            progressDiv.style.display = 'none';
        });
        
        xhr.open('POST', '/api/upload.php');
        xhr.send(formData);
    } catch (error) {
        console.error('Video upload error:', error);
        alert('Failed to upload video: ' + error.message);
        progressDiv.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
