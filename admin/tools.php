<?php
$pageTitle = 'Working Tools Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
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
        
        $croppedThumbnailData = sanitizeInput($_POST['thumbnail_cropped_data'] ?? '');
        $thumbnailUrlInput = sanitizeInput($_POST['thumbnail_url'] ?? '');
        $thumbnailUrl = !empty($croppedThumbnailData) ? $croppedThumbnailData : $thumbnailUrlInput;
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
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
                    'price' => $price,
                    'thumbnail_url' => $thumbnailUrl,
                    'delivery_instructions' => $deliveryInstructions,
                    'stock_unlimited' => $stockUnlimited,
                    'stock_quantity' => $stockQuantity,
                    'low_stock_threshold' => $lowStockThreshold,
                    'active' => $active
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
        
        $croppedThumbnailData = sanitizeInput($_POST['thumbnail_cropped_data'] ?? '');
        $thumbnailUrlInput = sanitizeInput($_POST['thumbnail_url'] ?? '');
        $thumbnailUrl = !empty($croppedThumbnailData) ? $croppedThumbnailData : $thumbnailUrlInput;
        
        $deliveryInstructions = trim($_POST['delivery_instructions'] ?? '');
        $stockUnlimited = isset($_POST['stock_unlimited']) ? 1 : 0;
        $stockQuantity = $stockUnlimited ? 0 : intval($_POST['stock_quantity'] ?? 0);
        $lowStockThreshold = intval($_POST['low_stock_threshold'] ?? 5);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name) || empty($price)) {
            $errorMessage = 'Name and price are required.';
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
                    'price' => $price,
                    'thumbnail_url' => $thumbnailUrl,
                    'delivery_instructions' => $deliveryInstructions,
                    'stock_unlimited' => $stockUnlimited,
                    'stock_quantity' => $stockQuantity,
                    'low_stock_threshold' => $lowStockThreshold,
                    'active' => $active
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
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($tools)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="bi bi-inbox text-4xl mb-2"></i>
                            <p>No tools found. Click "Add New Tool" to get started.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tools as $tool): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($tool['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($tool['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>" class="w-12 h-12 object-cover rounded-lg">
                                    <?php else: ?>
                                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <i class="bi bi-tools text-purple-600 text-xl"></i>
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
                    
                    <!-- Thumbnail Image -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Thumbnail Image</label>
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="toggleToolThumbnailMode('url', 'create')" id="tool-thumbnail-url-btn-create" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">URL</button>
                            <button type="button" onclick="toggleToolThumbnailMode('upload', 'create')" id="tool-thumbnail-upload-btn-create" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                        </div>
                        <div id="tool-thumbnail-url-mode-create">
                            <input type="url" id="tool-thumbnail-url-input-create" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="thumbnail_url" placeholder="https://example.com/image.jpg">
                        </div>
                        <div id="tool-thumbnail-upload-mode-create" style="display: none;">
                            <input type="file" id="tool-thumbnail-file-input-create" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                            <div id="tool-thumbnail-cropper-container-create" style="margin-top: 15px; display: none;"></div>
                            <input type="hidden" id="tool-thumbnail-cropped-data-create" name="thumbnail_cropped_data">
                        </div>
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
                    
                    <!-- Status -->
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
                    
                    <!-- Thumbnail Image -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Thumbnail Image</label>
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="toggleToolThumbnailMode('url', 'edit')" id="tool-thumbnail-url-btn-edit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">URL</button>
                            <button type="button" onclick="toggleToolThumbnailMode('upload', 'edit')" id="tool-thumbnail-upload-btn-edit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                        </div>
                        <div id="tool-thumbnail-url-mode-edit">
                            <input type="url" id="tool-thumbnail-url-input-edit" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="thumbnail_url" value="<?php echo htmlspecialchars($editTool['thumbnail_url'] ?? ''); ?>" placeholder="https://example.com/image.jpg">
                        </div>
                        <div id="tool-thumbnail-upload-mode-edit" style="display: none;">
                            <input type="file" id="tool-thumbnail-file-input-edit" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                            <div id="tool-thumbnail-cropper-container-edit" style="margin-top: 15px; display: none;"></div>
                            <input type="hidden" id="tool-thumbnail-cropped-data-edit" name="thumbnail_cropped_data">
                        </div>
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

function toggleToolDemoVideoMode(mode, formType) {
    const urlMode = document.getElementById(`tool-demo-video-url-mode-${formType}`);
    const uploadMode = document.getElementById(`tool-demo-video-upload-mode-${formType}`);
    const urlBtn = document.getElementById(`tool-demo-video-url-btn-${formType}`);
    const uploadBtn = document.getElementById(`tool-demo-video-upload-btn-${formType}`);
    const urlInput = document.getElementById(`tool-demo-video-url-input-${formType}`);
    const fileInput = document.getElementById(`tool-demo-video-file-input-${formType}`);
    const progressDiv = document.getElementById(`tool-demo-video-upload-progress-${formType}`);
    
    if (mode === 'url') {
        urlMode.style.display = 'block';
        uploadMode.style.display = 'none';
        urlBtn.classList.add('bg-purple-600', 'text-white');
        urlBtn.classList.remove('bg-gray-200', 'text-gray-700');
        uploadBtn.classList.remove('bg-purple-600', 'text-white');
        uploadBtn.classList.add('bg-gray-200', 'text-gray-700');
        fileInput.value = '';
        progressDiv.style.display = 'none';
    } else {
        urlMode.style.display = 'none';
        uploadMode.style.display = 'block';
        uploadBtn.classList.add('bg-purple-600', 'text-white');
        uploadBtn.classList.remove('bg-gray-200', 'text-gray-700');
        urlBtn.classList.remove('bg-purple-600', 'text-white');
        urlBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.value = '';
    }
}

document.getElementById('tool-demo-video-file-input-create')?.addEventListener('change', async function(e) {
    handleToolVideoUpload(e, 'create');
});

document.getElementById('tool-demo-video-file-input-edit')?.addEventListener('change', async function(e) {
    handleToolVideoUpload(e, 'edit');
});

function handleToolVideoUpload(e, formType) {
    const file = e.target.files[0];
    if (!file) return;
    
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('Video file is too large. Maximum size is 10MB.');
        e.target.value = '';
        return;
    }
    
    const progressDiv = document.getElementById(`tool-demo-video-upload-progress-${formType}`);
    const progressBar = document.getElementById(`tool-demo-video-progress-bar-${formType}`);
    const progressText = document.getElementById(`tool-demo-video-progress-text-${formType}`);
    const urlInput = document.getElementById(`tool-demo-video-url-input-${formType}`);
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', 'video');
    formData.append('category', 'tools');
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        urlInput.value = result.url;
                        progressText.textContent = 'Upload complete!';
                        progressBar.classList.add('bg-green-600');
                    } else {
                        throw new Error(result.error || 'Upload failed');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Failed to upload video: ' + error.message);
                    progressDiv.style.display = 'none';
                    e.target.value = '';
                }
            } else {
                alert('Upload failed. Please try again.');
                progressDiv.style.display = 'none';
                e.target.value = '';
            }
        });
        
        xhr.addEventListener('error', () => {
            alert('Upload failed. Please check your connection and try again.');
            progressDiv.style.display = 'none';
            e.target.value = '';
        });
        
        xhr.open('POST', '/api/upload.php');
        xhr.send(formData);
        
    } catch (error) {
        console.error('Upload error:', error);
        alert('Failed to upload video: ' + error.message);
        progressDiv.style.display = 'none';
        e.target.value = '';
    }
}

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
                    document.getElementById('tool-thumbnail-url-input-create').value = result.url;
                    
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
                    document.getElementById('tool-thumbnail-url-input-edit').value = result.url;
                    
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
