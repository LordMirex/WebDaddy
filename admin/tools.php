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
        $previewYoutube = null;
        $mediaType = 'banner';
        
        if ($videoType === 'demo_url') {
            $demoUrl = sanitizeInput($_POST['demo_url_input'] ?? '');
            $mediaType = 'demo_url';
        } elseif ($videoType === 'video') {
            $uploadedVideoUrl = sanitizeInput($_POST['demo_video_uploaded_url'] ?? '');
            $demoVideoUrl = UrlUtils::normalizeUploadUrl($uploadedVideoUrl);
            $mediaType = 'video';
        } elseif ($videoType === 'youtube') {
            $youtubeInput = sanitizeInput($_POST['youtube_url_input'] ?? '');
            $previewYoutube = extractYoutubeVideoId($youtubeInput);
            $mediaType = 'youtube';
        }
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        $priorityOrder = isset($_POST['priority_order']) ? intval($_POST['priority_order']) : null;
        $priorityOrder = ($priorityOrder > 0 && $priorityOrder <= 10) ? $priorityOrder : null;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
        } elseif (empty($slug)) {
            $errorMessage = 'Slug is required.';
        } elseif (empty($thumbnailUrl)) {
            $errorMessage = 'Product banner image is required.';
        } elseif ($videoType === 'youtube' && empty($previewYoutube)) {
            $errorMessage = 'Invalid YouTube URL or video ID. Please provide a valid YouTube link.';
        } else {
            try {
                
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
                    'preview_youtube' => $previewYoutube,
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
        $slug = sanitizeInput($_POST['slug'] ?? '');
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
        $previewYoutube = null;
        $mediaType = 'banner';
        
        if ($videoType === 'demo_url') {
            $demoUrl = sanitizeInput($_POST['demo_url_input'] ?? '');
            $mediaType = 'demo_url';
        } elseif ($videoType === 'video') {
            $uploadedVideoUrl = sanitizeInput($_POST['demo_video_uploaded_url'] ?? '');
            $demoVideoUrl = UrlUtils::normalizeUploadUrl($uploadedVideoUrl);
            $mediaType = 'video';
        } elseif ($videoType === 'youtube') {
            $youtubeInput = sanitizeInput($_POST['youtube_url_input'] ?? '');
            $previewYoutube = extractYoutubeVideoId($youtubeInput);
            $mediaType = 'youtube';
        }
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        $priorityOrder = isset($_POST['priority_order']) ? intval($_POST['priority_order']) : null;
        $priorityOrder = ($priorityOrder > 0 && $priorityOrder <= 10) ? $priorityOrder : null;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
        } elseif (empty($slug)) {
            $errorMessage = 'Slug is required.';
        } elseif (empty($thumbnailUrl)) {
            $errorMessage = 'Product banner image is required.';
        } elseif ($videoType === 'youtube' && empty($previewYoutube)) {
            $errorMessage = 'Invalid YouTube URL or video ID. Please provide a valid YouTube link.';
        } else {
            try {
                
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
                    'preview_youtube' => $previewYoutube,
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
            require_once __DIR__ . '/../includes/tool_files.php';
            require_once __DIR__ . '/../includes/delivery.php';
            require_once __DIR__ . '/../includes/email_queue.php';
            
            $tool = getToolById($toolId);
            if (!$tool) {
                throw new Exception('Tool not found.');
            }
            
            // VALIDATION: Cannot mark complete if no files exist
            $toolFiles = getToolFiles($toolId);
            if ($uploadComplete && empty($toolFiles)) {
                throw new Exception('Cannot mark tool as complete without any files. Please upload at least one file or add a link first.');
            }
            
            $stmt = $db->prepare("UPDATE tools SET upload_complete = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$uploadComplete, $toolId]);
            
            if ($uploadComplete) {
                // Process pending deliveries (new customers waiting for files)
                $pendingResult = processPendingToolDeliveries($toolId);
                $pendingEmails = $pendingResult['sent'] ?? 0;
                $pendingQueued = $pendingResult['queued'] ?? 0;
                
                // Send update emails to existing customers (uses queue for large batches)
                $updateResult = sendToolVersionUpdateEmails($toolId);
                $updateEmails = $updateResult['sent'] ?? 0;
                $updateQueued = $updateResult['queued'] ?? 0;
                $skippedNoChanges = $updateResult['skipped'] ?? 0;
                
                $totalSent = $pendingEmails + $updateEmails;
                $totalQueued = $pendingQueued + $updateQueued;
                $successMessage = "Tool marked as complete!";
                
                $parts = [];
                if ($pendingEmails > 0 || $pendingQueued > 0) {
                    $pendingCount = $pendingEmails + $pendingQueued;
                    $parts[] = "{$pendingCount} new customer(s) notified";
                }
                if ($updateEmails > 0 || $updateQueued > 0) {
                    $updateCount = $updateEmails + $updateQueued;
                    $parts[] = "{$updateCount} existing customer(s) received update notifications";
                }
                if ($skippedNoChanges > 0) {
                    $parts[] = "{$skippedNoChanges} customer(s) skipped (no file changes)";
                }
                if (!empty($parts)) {
                    $successMessage .= " " . implode(', ', $parts) . ".";
                }
                if ($totalQueued > 0) {
                    $successMessage .= " Emails are being processed in the background.";
                }
                
                logActivity('tool_upload_complete', "Tool files marked complete: {$tool['name']}, {$pendingEmails} pending + {$updateEmails} update emails sent, {$skippedNoChanges} skipped", getAdminId());
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
        
        try {
            // CSRF protection
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Security validation failed. Please refresh and try again.');
            }
            
            // ORDER COMPLETION LOCKING: Check if modifications are allowed
            $modifyCheck = canModifyToolFiles($toolId);
            if (!$modifyCheck['allowed']) {
                throw new Exception($modifyCheck['reason']);
            }
            
            $isLink = ($_POST['upload_mode'] ?? '') === 'link';
            
            if ($isLink) {
                $externalLink = sanitizeInput($_POST['external_link'] ?? '');
                if (empty($externalLink)) {
                    throw new Exception('Please provide a link URL');
                }
                if (!filter_var($externalLink, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL format');
                }
                addToolLink($toolId, $externalLink, $description);
                $successMessage = 'Link added successfully!';
            } else {
                if (!isset($_FILES['tool_file'])) {
                    throw new Exception('No file selected. Please choose a file to upload.');
                }
                if ($_FILES['tool_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMap = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'File upload incomplete',
                        UPLOAD_ERR_NO_FILE => 'No file selected',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                        UPLOAD_ERR_EXTENSION => 'Extension blocked'
                    ];
                    throw new Exception($errorMap[$_FILES['tool_file']['error']] ?? 'Unknown upload error');
                }
                uploadToolFile($toolId, $_FILES['tool_file'], $fileType, $description);
                $successMessage = 'File uploaded successfully!';
            }
            
            $tool = getToolById($toolId);
            
            // CRITICAL FIX: Send update emails if tool is already marked as complete
            // This notifies customers who already received the tool about new files
            if (!empty($tool['upload_complete'])) {
                require_once __DIR__ . '/../includes/delivery.php';
                $updateResult = sendToolUpdateEmails($toolId);
                if ($updateResult['sent'] > 0) {
                    $successMessage = ($isLink ? 'Link' : 'File') . " added! Update emails sent to {$updateResult['sent']} customer(s).";
                    logActivity('tool_file_update', "New " . ($isLink ? 'link' : 'file') . " added to completed tool (Tool ID: $toolId), {$updateResult['sent']} update emails sent", getAdminId());
                } else {
                    logActivity('tool_file_uploaded', ($isLink ? 'Link' : 'File') . " added to tool: {$tool['name']}", getAdminId());
                }
            } else {
                logActivity('tool_file_uploaded', ($isLink ? 'Link' : 'File') . " added to tool: {$tool['name']}", getAdminId());
            }
            
            header('Location: /admin/tools.php?edit=' . $toolId . '&tab=files&success=1');
            exit;
        } catch (Exception $e) {
            error_log('Tool file upload error: ' . $e->getMessage());
            $errorMessage = 'Error uploading file: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_tool_file') {
        $fileId = intval($_POST['file_id']);
        $toolId = intval($_POST['tool_id']);
        $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] === '1';
        
        try {
            // CSRF protection
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Security validation failed. Please refresh and try again.');
            }
            
            $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ? AND tool_id = ?");
            $stmt->execute([$fileId, $toolId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new Exception('File not found or does not belong to this tool');
            }
            
            // Check if tool is marked as complete - if complete, block deletion
            $toolStmt = $db->prepare("SELECT upload_complete, name FROM tools WHERE id = ?");
            $toolStmt->execute([$toolId]);
            $toolData = $toolStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($toolData && $toolData['upload_complete'] && !$forceDelete) {
                throw new Exception('Cannot delete files when tool is marked as complete. Unmark the tool first to make changes.');
            }
            
            // Log file deletion for version control tracking (before deleting)
            $logStmt = $db->prepare("
                INSERT INTO tool_file_deletion_log 
                (tool_id, file_id, file_name, file_type, file_description, deleted_by)
                VALUES (?, ?, ?, ?, ?, 'admin')
            ");
            $logStmt->execute([
                $toolId,
                $fileId,
                $file['file_name'],
                $file['file_type'] ?? 'unknown',
                $file['file_description'] ?? ''
            ]);
            
            // Delete the file from disk (if it's a local file, not an external link)
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath) && !preg_match('/^https?:\/\//i', $file['file_path'])) {
                unlink($filePath);
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM tool_files WHERE id = ?");
            $stmt->execute([$fileId]);
            
            // Clean up any download tokens for this file
            $stmt = $db->prepare("DELETE FROM download_tokens WHERE file_id = ?");
            $stmt->execute([$fileId]);
            
            // Update tool file count
            $db->exec("UPDATE tools SET total_files = (SELECT COUNT(*) FROM tool_files WHERE tool_id = $toolId) WHERE id = $toolId");
            
            $successMessage = 'File deleted successfully!';
            logActivity('tool_file_deleted', "File removed from tool: {$toolData['name']}" . ($forceDelete ? ' (force delete)' : ''), getAdminId());
            
            header('Location: /admin/tools.php?edit=' . $toolId . '&tab=files');
            exit;
        } catch (Exception $e) {
            error_log('Tool file delete error: ' . $e->getMessage());
            $errorMessage = 'Error deleting file: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$searchTerm = $_GET['search'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterStatus = $_GET['status'] ?? 'active';

// Get all tools (including out-of-stock) for admin view
// Use $inStockOnly = false to show ALL tools regardless of stock
$allTools = getTools(false, $filterCategory ? $filterCategory : null, null, null, false);

// Apply search filter
if (!empty($searchTerm)) {
    $searchLower = strtolower($searchTerm);
    $allTools = array_filter($allTools, function($t) use ($searchLower) {
        return (
            stripos($t['name'] ?? '', $searchLower) !== false ||
            stripos($t['slug'] ?? '', $searchLower) !== false ||
            stripos($t['description'] ?? '', $searchLower) !== false ||
            stripos($t['short_description'] ?? '', $searchLower) !== false ||
            stripos($t['category'] ?? '', $searchLower) !== false
        );
    });
}

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

// Fetch all used priorities for tools (excluding current tool if editing)
$usedToolPriorities = [];
$priorityQuery = $db->query("SELECT id, priority_order, name FROM tools WHERE priority_order IS NOT NULL AND priority_order > 0");
while ($row = $priorityQuery->fetch(PDO::FETCH_ASSOC)) {
    $usedToolPriorities[$row['priority_order']] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

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
    <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search tools by name, description...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
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
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-search mr-2"></i> Filter
                    </button>
                    <?php if (!empty($searchTerm) || !empty($filterCategory) || $filterStatus !== 'active'): ?>
                    <a href="?" class="px-4 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors" title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tools Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b-2 border-gray-200">
                        <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tool</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Files Ready</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($tools)): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                            <i class="bi bi-inbox text-4xl mb-2"></i>
                            <p>No tools found. Click "Add New Tool" to get started.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tools as $tool): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 font-bold text-gray-900">#<?php echo $tool['id']; ?></td>
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
                                <?php if (!empty($tool['priority_order']) && $tool['priority_order'] > 0): ?>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                    <i class="bi bi-star-fill"></i> #<?php echo $tool['priority_order']; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
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
                <a href="?page=<?php echo $page - 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 border rounded-lg font-medium transition-colors <?php echo $i === $page ? 'bg-purple-600 border-purple-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
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
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Slug <span class="text-red-600">*</span></label>
                            <input type="text" name="slug" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="e.g., premium-api-key">
                            <small class="text-gray-500 text-xs">URL-friendly identifier (lowercase, hyphens only)</small>
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
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Features</label>
                        <textarea name="features" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm" placeholder="Feature 1, Feature 2, Feature 3"></textarea>
                        <small class="text-gray-500 text-xs">Enter features separated by commas</small>
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
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-none-label-create">
                                <input type="radio" name="video_type" value="none" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600" checked>
                                <span class="font-medium text-sm">🚫 None</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-video-label-create">
                                <input type="radio" name="video_type" value="video" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600">
                                <span class="font-medium text-sm">🎥 Video</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-youtube-label-create">
                                <input type="radio" name="video_type" value="youtube" onchange="handleToolVideoTypeChange('create')" class="w-4 h-4 text-purple-600">
                                <span class="font-medium text-sm">📺 YouTube</span>
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
                        <small class="text-gray-500 text-xs mt-1 block">Upload demo video (MP4, WebM recommended, max 500MB)</small>
                    </div>
                    
                    <div id="tool-youtube-section-create" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube Video URL or ID</label>
                        <input type="text" name="youtube_url_input" id="tool-youtube-url-input-create" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" placeholder="https://youtube.com/watch?v=... or just the video ID">
                        <small class="text-gray-500 text-xs mt-1 block">Paste any YouTube URL or video ID. Unlisted videos work too! (Fastest loading option)</small>
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Priority (Top 10 Featured)</label>
                        <select name="priority_order" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">None (Regular Listing)</option>
                            <?php
                            $createPriorityLabels = [
                                1 => '⭐ #1 - Top Priority',
                                2 => '#2 - Second Priority',
                                3 => '#3 - Third Priority',
                                4 => '#4 - Fourth Priority',
                                5 => '#5 - Fifth Priority',
                                6 => '#6 - Sixth Priority',
                                7 => '#7 - Seventh Priority',
                                8 => '#8 - Eighth Priority',
                                9 => '#9 - Ninth Priority',
                                10 => '#10 - Tenth Priority'
                            ];
                            foreach ($createPriorityLabels as $num => $label):
                                $isUsed = isset($usedToolPriorities[$num]);
                                $usedByName = $isUsed ? ' (Used by: ' . htmlspecialchars(substr($usedToolPriorities[$num]['name'], 0, 20)) . ')' : '';
                            ?>
                            <option value="<?php echo $num; ?>" <?php echo $isUsed ? 'disabled' : ''; ?>>
                                <?php echo $label . $usedByName; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-gray-500 text-xs mt-1 block">Select to feature this tool in the top 10 displayed first. Disabled options are already in use.</small>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Slug <span class="text-red-600">*</span></label>
                            <input type="text" name="slug" required value="<?php echo htmlspecialchars($editTool['slug'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="e.g., premium-api-key">
                            <small class="text-gray-500 text-xs">URL-friendly identifier (lowercase, hyphens only)</small>
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
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Features</label>
                        <textarea name="features" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm" placeholder="Feature 1, Feature 2, Feature 3"><?php echo htmlspecialchars($editTool['features'] ?? ''); ?></textarea>
                        <small class="text-gray-500 text-xs">Enter features separated by commas</small>
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
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-none-label-edit">
                                <input type="radio" name="video_type" value="none" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo (!$editTool || ($editTool['media_type'] ?? 'banner') === 'banner') ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">🚫 None</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-video-label-edit">
                                <input type="radio" name="video_type" value="video" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo ($editTool && ($editTool['media_type'] ?? '') === 'video') ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">🎥 Video</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-youtube-label-edit">
                                <input type="radio" name="video_type" value="youtube" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo ($editTool && ($editTool['media_type'] ?? '') === 'youtube') ? 'checked' : ''; ?>>
                                <span class="font-medium text-sm">📺 YouTube</span>
                            </label>
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-purple-400" id="tool-video-type-demo-url-label-edit">
                                <input type="radio" name="video_type" value="demo_url" onchange="handleToolVideoTypeChange('edit')" class="w-4 h-4 text-purple-600" <?php echo ($editTool && ($editTool['media_type'] ?? '') === 'demo_url') ? 'checked' : ''; ?>>
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
                        <small class="text-gray-500 text-xs mt-1 block">Upload demo video (MP4, WebM recommended, max 500MB)</small>
                    </div>
                    
                    <div id="tool-youtube-section-edit" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube Video URL or ID</label>
                        <input type="text" name="youtube_url_input" id="tool-youtube-url-input-edit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($editTool['preview_youtube'] ?? ''); ?>" placeholder="https://youtube.com/watch?v=... or just the video ID">
                        <small class="text-gray-500 text-xs mt-1 block">Paste any YouTube URL or video ID. Unlisted videos work too! (Fastest loading option)</small>
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Priority (Top 10 Featured)</label>
                        <select name="priority_order" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">None (Regular Listing)</option>
                            <?php
                            $editPriorityLabels = [
                                1 => '⭐ #1 - Top Priority',
                                2 => '#2 - Second Priority',
                                3 => '#3 - Third Priority',
                                4 => '#4 - Fourth Priority',
                                5 => '#5 - Fifth Priority',
                                6 => '#6 - Sixth Priority',
                                7 => '#7 - Seventh Priority',
                                8 => '#8 - Eighth Priority',
                                9 => '#9 - Ninth Priority',
                                10 => '#10 - Tenth Priority'
                            ];
                            foreach ($editPriorityLabels as $num => $label):
                                $isSelected = ($editTool && $editTool['priority_order'] == $num);
                                $isUsed = isset($usedToolPriorities[$num]);
                                $usedByCurrentItem = $isUsed && $editTool && $usedToolPriorities[$num]['id'] == $editTool['id'];
                                $isDisabled = $isUsed && !$usedByCurrentItem;
                                $usedByName = $isUsed && !$usedByCurrentItem ? ' (Used by: ' . htmlspecialchars(substr($usedToolPriorities[$num]['name'], 0, 20)) . ')' : '';
                            ?>
                            <option value="<?php echo $num; ?>" <?php echo $isSelected ? 'selected' : ''; ?> <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                <?php echo $label . $usedByName; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-gray-500 text-xs mt-1 block">Select to feature this tool in the top 10 displayed first. Disabled options are already in use.</small>
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
            require_once __DIR__ . '/../includes/tool_files.php';
            $modifyCheck = canModifyToolFiles($editTool['id']);
            $canModify = $modifyCheck['allowed'];
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
                                            <?php if (!empty($file['file_description'])): ?>
                                             · <?php echo htmlspecialchars(substr($file['file_description'], 0, 30)); ?><?php echo strlen($file['file_description']) > 30 ? '...' : ''; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!$editUploadComplete): ?>
                                <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Delete this file? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_tool_file">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                                    <?php echo csrfTokenField(); ?>
                                    <button type="submit" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete file">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="p-2 text-gray-400 cursor-not-allowed" title="Unmark as complete to delete">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add New File Form -->
                    <?php if ($editUploadComplete): ?>
                    <!-- LOCKED: Tool is marked as complete - no uploads allowed -->
                    <div class="bg-green-50 border-2 border-green-200 rounded-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center bg-green-500 flex-shrink-0">
                                <i class="bi bi-check-lg text-white text-xl"></i>
                            </div>
                            <div>
                                <h5 class="font-bold text-gray-900 mb-2">Files Ready for Delivery</h5>
                                <p class="text-sm text-gray-600 mb-3">This tool is marked as complete. Uploads and deletions are locked.</p>
                                <p class="text-xs text-gray-500">To make changes: Toggle "Files Ready for Delivery" to OFF above, make your changes, then toggle it back ON. Customers will receive update notifications.</p>
                            </div>
                        </div>
                    </div>
                    <?php elseif (!$canModify): ?>
                    <!-- LOCKED: Has completed orders - extra warning -->
                    <div class="bg-amber-50 border-2 border-amber-200 rounded-xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-400 flex-shrink-0">
                                <i class="bi bi-lock-fill text-white text-xl"></i>
                            </div>
                            <div>
                                <h5 class="font-bold text-gray-900 mb-2">Uploads Locked</h5>
                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($modifyCheck['reason']); ?></p>
                                <p class="text-xs text-gray-500">To add new files: First toggle "Files Ready for Delivery" to OFF above, upload the files, then toggle it back ON to notify customers.</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h5 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-cloud-upload text-purple-600"></i> Upload New File
                        </h5>
                        <?php if (isset($_GET['success'])): ?>
                        <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-lg flex items-start gap-3">
                            <i class="bi bi-check-circle text-xl mt-0.5"></i>
                            <div>
                                <strong>Success!</strong>
                                <p class="text-sm mt-1">File uploaded successfully!</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <form id="uploadForm" class="space-y-5">
                            <input type="hidden" name="tool_id" value="<?php echo $editTool['id']; ?>">
                            <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo getCsrfToken(); ?>">
                            
                            <!-- File Input (hidden when External Link selected) -->
                            <div id="fileInputDiv">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">📎 Select File</label>
                                <div class="relative">
                                    <input type="file" id="toolFile" name="tool_file" 
                                           class="w-full px-4 py-3 bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all cursor-pointer file:cursor-pointer file:bg-purple-600 file:border-0 file:rounded file:px-3 file:py-1 file:text-white file:text-sm file:font-medium hover:border-purple-500">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">💡 Max size: 2GB (chunked upload - 20MB chunks, 6 concurrent = 3x faster!)</p>
                            </div>
                            
                            <!-- Link Input (shown only when External Link selected) -->
                            <div id="linkInputDiv" class="hidden">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">🔗 External Link URL</label>
                                <input type="url" id="externalLink" name="external_link" 
                                       class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                       placeholder="https://example.com/resource or https://docs.yourservice.com">
                                <p class="text-xs text-gray-500 mt-2">💡 Paste the full URL to the external service or resource.</p>
                            </div>
                            
                            <!-- File Type -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">📁 File Type</label>
                                <select id="fileType" name="file_type" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>
                                    <option value="zip_archive">📦 ZIP Archive</option>
                                    <option value="attachment">📎 General Attachment</option>
                                    <option value="text_instructions">📝 Instructions/Documentation</option>
                                    <option value="code">💻 Code/Script</option>
                                    <option value="access_key">🔑 Access Key/Credentials</option>
                                    <option value="image">🖼️ Image</option>
                                    <option value="video">🎬 Video</option>
                                    <option value="link">🔗 External Link/URL</option>
                                </select>
                            </div>
                            
                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">💬 Description (Optional)</label>
                                <textarea id="description" name="description" 
                                          class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-none"
                                          rows="3"
                                          placeholder="e.g., Main tool files, Updated version 2.0, Installation guide..."></textarea>
                            </div>
                            
                            <!-- Progress Bar (hidden initially) -->
                            <div id="uploadProgress" class="hidden space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700">Uploading: <span id="fileName">...</span></span>
                                    <span id="progressPercent" class="text-purple-600 font-bold">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                    <div id="progressBar" class="bg-gradient-to-r from-purple-600 to-purple-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <!-- Status Message -->
                            <div id="uploadStatus"></div>
                            
                            <!-- Submit Button -->
                            <div>
                                <button type="submit" id="uploadBtn" class="w-full px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition-colors shadow-lg hover:shadow-xl flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="bi bi-cloud-upload"></i> <span id="uploadBtnText">Upload File</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
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
let toolVideoTypeInitialized = { create: false, edit: false };

function handleToolVideoTypeChange(formType) {
    const noneLabel = document.getElementById(`tool-video-type-none-label-${formType}`);
    const videoLabel = document.getElementById(`tool-video-type-video-label-${formType}`);
    const youtubeLabel = document.getElementById(`tool-video-type-youtube-label-${formType}`);
    const demoUrlLabel = document.getElementById(`tool-video-type-demo-url-label-${formType}`);
    
    if (!noneLabel || !videoLabel || !demoUrlLabel) {
        console.error('Video type labels not found for formType:', formType);
        return;
    }
    
    const noneRadio = noneLabel.querySelector('input[type="radio"]');
    const videoRadio = videoLabel.querySelector('input[type="radio"]');
    const youtubeRadio = youtubeLabel ? youtubeLabel.querySelector('input[type="radio"]') : null;
    const demoUrlRadio = demoUrlLabel.querySelector('input[type="radio"]');
    
    let selectedType = 'none';
    if (videoRadio && videoRadio.checked) {
        selectedType = 'video';
    } else if (youtubeRadio && youtubeRadio.checked) {
        selectedType = 'youtube';
    } else if (demoUrlRadio && demoUrlRadio.checked) {
        selectedType = 'demo_url';
    }
    
    const demoUrlSection = document.getElementById(`tool-demo-url-section-${formType}`);
    const videoUploadSection = document.getElementById(`tool-video-upload-section-${formType}`);
    const youtubeSection = document.getElementById(`tool-youtube-section-${formType}`);
    
    demoUrlSection.style.display = 'none';
    videoUploadSection.style.display = 'none';
    if (youtubeSection) youtubeSection.style.display = 'none';
    
    noneLabel.classList.remove('border-purple-600', 'bg-purple-50');
    videoLabel.classList.remove('border-purple-600', 'bg-purple-50');
    if (youtubeLabel) youtubeLabel.classList.remove('border-purple-600', 'bg-purple-50');
    demoUrlLabel.classList.remove('border-purple-600', 'bg-purple-50');
    
    // Only clear fields when user actively switches types (not on initial page load)
    const videoUrlField = document.getElementById(`tool-video-uploaded-url-${formType}`);
    const youtubeUrlField = document.getElementById(`tool-youtube-url-input-${formType}`);
    const demoUrlField = document.getElementById(`tool-demo-url-input-${formType}`);
    const shouldClear = toolVideoTypeInitialized[formType];
    
    if (selectedType === 'demo_url') {
        demoUrlSection.style.display = 'block';
        demoUrlLabel.classList.add('border-purple-600', 'bg-purple-50');
        if (shouldClear) {
            if (videoUrlField) videoUrlField.value = '';
            if (youtubeUrlField) youtubeUrlField.value = '';
        }
    } else if (selectedType === 'video') {
        videoUploadSection.style.display = 'block';
        videoLabel.classList.add('border-purple-600', 'bg-purple-50');
        if (shouldClear) {
            if (youtubeUrlField) youtubeUrlField.value = '';
            if (demoUrlField) demoUrlField.value = '';
        }
    } else if (selectedType === 'youtube') {
        if (youtubeSection) youtubeSection.style.display = 'block';
        if (youtubeLabel) youtubeLabel.classList.add('border-purple-600', 'bg-purple-50');
        if (shouldClear) {
            if (videoUrlField) videoUrlField.value = '';
            if (demoUrlField) demoUrlField.value = '';
        }
    } else {
        noneLabel.classList.add('border-purple-600', 'bg-purple-50');
        if (shouldClear) {
            if (videoUrlField) videoUrlField.value = '';
            if (youtubeUrlField) youtubeUrlField.value = '';
            if (demoUrlField) demoUrlField.value = '';
        }
    }
    
    toolVideoTypeInitialized[formType] = true;
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

// Video upload handler function - reusable for both create and edit forms
function initVideoUploadHandler(formType) {
    const fileInput = document.getElementById('tool-video-file-input-' + formType);
    if (!fileInput) return;
    
    // Remove any existing listeners by cloning the element
    const newInput = fileInput.cloneNode(true);
    fileInput.parentNode.replaceChild(newInput, fileInput);
    
    newInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const progressDiv = document.getElementById('tool-video-upload-progress-' + formType);
        const progressBar = document.getElementById('tool-video-progress-bar-' + formType);
        const progressText = document.getElementById('tool-video-progress-percentage-' + formType);
        const checkIcon = document.getElementById('tool-video-upload-check-' + formType);
        const urlInput = document.getElementById('tool-video-uploaded-url-' + formType);
        
        if (!progressDiv || !progressBar || !progressText || !urlInput) {
            console.error('Video upload elements not found for form type:', formType);
            alert('Video upload error: UI elements not found. Please refresh and try again.');
            return;
        }
        
        progressDiv.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        if (checkIcon) checkIcon.style.display = 'none';
        
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
                    try {
                        const result = JSON.parse(xhr.responseText);
                        if (result.success) {
                            urlInput.value = result.url;
                            if (checkIcon) checkIcon.style.display = 'inline';
                            console.log('Video uploaded successfully (' + formType + '):', result.url);
                        } else {
                            alert('Video upload failed: ' + (result.error || 'Unknown error'));
                            progressDiv.style.display = 'none';
                        }
                    } catch (parseError) {
                        console.error('Failed to parse upload response:', parseError);
                        console.error('Server response was:', xhr.responseText.substring(0, 500));
                        alert('Video upload failed: Invalid server response. Check console for details.');
                        progressDiv.style.display = 'none';
                    }
                } else {
                    alert('Video upload failed (HTTP ' + xhr.status + ')');
                    progressDiv.style.display = 'none';
                }
            });
            
            xhr.addEventListener('error', function() {
                alert('Video upload failed - network error');
                progressDiv.style.display = 'none';
            });
            
            xhr.open('POST', '/api/upload.php');
            xhr.withCredentials = true;
            xhr.send(formData);
        } catch (error) {
            console.error('Video upload error (' + formType + '):', error);
            alert('Failed to upload video: ' + error.message);
            progressDiv.style.display = 'none';
        }
    });
}

// Initialize video upload handlers on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize for create form
    initVideoUploadHandler('create');
    // Initialize for edit form (if present)
    initVideoUploadHandler('edit');
});

// ADVANCED FILE UPLOAD SYSTEM - Chunked Upload with Sequential Processing
const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB chunks - more reliable
const MAX_RETRIES = 3;
const CHUNK_TIMEOUT = 60000; // 60 seconds per chunk

function generateUploadId() {
    return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

async function uploadChunkWithRetry(formData, retries = 0) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.timeout = CHUNK_TIMEOUT;
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    reject(new Error('Invalid JSON response'));
                }
            } else {
                const errorMsg = xhr.responseText || `HTTP ${xhr.status}`;
                if (retries < MAX_RETRIES) {
                    console.log(`Retry ${retries + 1}/${MAX_RETRIES} for chunk`);
                    setTimeout(() => {
                        uploadChunkWithRetry(formData, retries + 1).then(resolve).catch(reject);
                    }, 1000 * (retries + 1));
                } else {
                    reject(new Error(`Server error: ${errorMsg}`));
                }
            }
        });
        
        xhr.addEventListener('error', () => {
            if (retries < MAX_RETRIES) {
                console.log(`Retry ${retries + 1}/${MAX_RETRIES} after network error`);
                setTimeout(() => {
                    uploadChunkWithRetry(formData, retries + 1).then(resolve).catch(reject);
                }, 1000 * (retries + 1));
            } else {
                reject(new Error('Network error after retries'));
            }
        });
        
        xhr.addEventListener('timeout', () => {
            if (retries < MAX_RETRIES) {
                console.log(`Retry ${retries + 1}/${MAX_RETRIES} after timeout`);
                setTimeout(() => {
                    uploadChunkWithRetry(formData, retries + 1).then(resolve).catch(reject);
                }, 1000 * (retries + 1));
            } else {
                reject(new Error('Upload timeout after retries'));
            }
        });
        
        xhr.open('POST', '/api/upload-chunk.php');
        xhr.send(formData);
    });
}

async function uploadFileInChunks(file, toolId, fileType, description) {
    const btn = document.getElementById('uploadBtn');
    const progressDiv = document.getElementById('uploadProgress');
    const statusDiv = document.getElementById('uploadStatus');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    
    btn.disabled = true;
    progressDiv.classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId = generateUploadId();
    
    statusDiv.innerHTML = '<div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-lg">⬆️ Starting upload: ' + totalChunks + ' chunks...</div>';
    
    // Process chunks sequentially for reliability
    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);
        
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('tool_id', toolId);
        formData.append('file_name', file.name);
        
        const percent = Math.round(((i + 1) / totalChunks) * 100);
        progressBar.style.width = percent + '%';
        progressPercent.textContent = percent + '%';
        statusDiv.innerHTML = '<div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-lg animate-pulse">⬆️ Uploading chunk ' + (i + 1) + ' of ' + totalChunks + '...</div>';
        
        try {
            const response = await uploadChunkWithRetry(formData);
            
            if (response.error) {
                throw new Error(response.error);
            }
            
            if (response.completed) {
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                
                if (response.replaced) {
                    statusDiv.innerHTML = '<div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-lg">🔄 ' + (response.message || 'File has been updated with the new version.') + ' Reloading...</div>';
                    // Increased delay and cache-bust to ensure database commit is visible
                    setTimeout(() => {
                        const cacheBust = '?t=' + Date.now() + '&edit=' + toolId + '&tab=files';
                        window.location.href = window.location.pathname + cacheBust;
                    }, 2000);
                    return;
                }
                
                statusDiv.innerHTML = '<div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-lg">✅ File uploaded successfully! Reloading...</div>';
                // Increased delay and cache-bust to ensure database commit is visible
                setTimeout(() => {
                    const cacheBust = '?t=' + Date.now() + '&edit=' + toolId + '&tab=files';
                    window.location.href = window.location.pathname + cacheBust;
                }, 2000);
                return;
            }
        } catch (error) {
            statusDiv.innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ Upload failed at chunk ' + (i + 1) + ': ' + error.message + '</div>';
            btn.disabled = false;
            progressDiv.classList.add('hidden');
            throw error;
        }
    }
}

// Show/hide file or link input based on file type selection
document.getElementById('fileType').addEventListener('change', (e) => {
    const isLink = e.target.value === 'link';
    document.getElementById('fileInputDiv').classList.toggle('hidden', isLink);
    document.getElementById('linkInputDiv').classList.toggle('hidden', !isLink);
    document.getElementById('toolFile').required = !isLink;
    document.getElementById('externalLink').required = isLink;
    document.getElementById('uploadStatus').innerHTML = '';
});

document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = document.getElementById('uploadForm');
    const fileType = document.getElementById('fileType').value;
    // FIX: Use form-scoped selector to get correct tool_id from upload form (not other forms)
    const toolId = form.querySelector('input[name="tool_id"]').value;
    const description = document.getElementById('description').value;
    const statusDiv = document.getElementById('uploadStatus');
    
    // Handle external link submission
    if (fileType === 'link') {
        const externalLink = document.getElementById('externalLink').value.trim();
        if (!externalLink) {
            statusDiv.innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ Please enter a URL</div>';
            return;
        }
        
        const csrfToken = document.getElementById('csrfToken').value;
        const formData = new FormData();
        formData.append('action', 'upload_tool_file');
        formData.append('tool_id', toolId);
        formData.append('file_type', fileType);
        formData.append('description', description);
        formData.append('external_link', externalLink);
        formData.append('upload_mode', 'link');
        formData.append('csrf_token', csrfToken);
        
        statusDiv.innerHTML = '<div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded-lg animate-pulse">🔄 Adding link...</div>';
        
        try {
            const response = await fetch('/admin/tools.php?edit=' + toolId + '&tab=files', {
                method: 'POST',
                body: formData
            });
            if (response.ok) {
                statusDiv.innerHTML = '<div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-lg">✅ Link added! Reloading...</div>';
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            statusDiv.innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ Error: ' + error.message + '</div>';
        }
        return;
    }
    
    // Handle file upload
    const file = document.getElementById('toolFile').files[0];
    if (!file) return;
    
    statusDiv.innerHTML = '';
    
    // IMMEDIATE FEEDBACK - show upload is starting
    statusDiv.innerHTML = '<div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded-lg animate-pulse">🔄 Upload starting... sending chunks to server</div>';
    
    try {
        await uploadFileInChunks(file, toolId, fileType, description);
    } catch (error) {
        statusDiv.innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ Upload failed: ' + error.message + '</div>';
        document.getElementById('uploadBtn').disabled = false;
        document.getElementById('uploadProgress').classList.add('hidden');
    }
});

document.getElementById('toolFile').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const sizeInGB = file.size / (1024 * 1024 * 1024);
        const sizeMB = file.size / (1024 * 1024);
        if (sizeInGB > 2) {
            document.getElementById('uploadStatus').innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ File too large (max 2GB). Your file: ' + sizeInGB.toFixed(2) + 'GB</div>';
            e.target.value = '';
        } else {
            const chunks = Math.ceil(file.size / CHUNK_SIZE);
            const displaySize = sizeMB > 1024 ? (sizeInGB).toFixed(2) + 'GB' : sizeMB.toFixed(1) + 'MB';
            document.getElementById('uploadStatus').innerHTML = '<div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-lg">⚡ Ready to upload: ' + displaySize + ' (' + chunks + ' chunk' + (chunks > 1 ? 's' : '') + ')</div>';
        }
    }
});

// AJAX Tool Search
let searchTimeout;
const toolSearch = document.getElementById('toolSearch');
const searchDropdown = document.getElementById('searchDropdown');

if (toolSearch) {
    toolSearch.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            searchDropdown.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performToolSearch(query, 1);
        }, 300);
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (toolSearch && !e.target.closest('#toolSearch') && !e.target.closest('#searchDropdown')) {
        searchDropdown.style.display = 'none';
    }
});

function performToolSearch(query, page = 1) {
    const url = `/api/admin-search-tools.php?q=${encodeURIComponent(query)}&page=${page}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Search result:', data);
            const toolsList = document.getElementById('toolsList');
            
            if (!data.success || data.tools.length === 0) {
                toolsList.innerHTML = '<div class="p-4 text-gray-600 text-sm">No tools found</div>';
                searchDropdown.style.display = 'block';
                document.getElementById('searchPagination').innerHTML = '';
                return;
            }
            
            // Build tools list
            let html = '';
            data.tools.forEach(tool => {
                const status = tool.upload_complete ? '✅ Ready' : '⏳ Pending';
                html += `
                    <a href="/admin/tools.php?edit=${tool.id}" class="block p-3 hover:bg-purple-50 border-b transition-colors cursor-pointer">
                        <div class="font-semibold text-gray-900 text-sm">${tool.name}</div>
                        <div class="text-xs text-gray-500 mt-1">${tool.file_count} files • ${status}</div>
                    </a>
                `;
            });
            toolsList.innerHTML = html;
            
            // Build pagination
            let paginationHtml = '';
            if (data.totalPages > 1) {
                for (let i = 1; i <= data.totalPages; i++) {
                    if (i === page) {
                        paginationHtml += `<span class="px-2 py-1 bg-purple-600 text-white text-xs rounded font-bold">${i}</span>`;
                    } else {
                        paginationHtml += `<button class="px-2 py-1 bg-gray-200 hover:bg-gray-300 text-xs rounded" onclick="performToolSearch('${query.replace(/'/g, "\\'")}', ${i}); return false;">${i}</button>`;
                    }
                }
            }
            document.getElementById('searchPagination').innerHTML = paginationHtml;
            searchDropdown.style.display = 'block';
        })
        .catch(error => {
            console.error('Search error:', error);
            document.getElementById('toolsList').innerHTML = '<div class="p-4 text-red-600 text-sm">❌ Error connecting to search</div>';
            searchDropdown.style.display = 'block';
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
