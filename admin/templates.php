<?php
$pageTitle = 'Templates Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $slug = sanitizeInput($_POST['slug'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $category = sanitizeInput($_POST['category'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $features = sanitizeInput($_POST['features'] ?? '');
            
            $uploadedVideoUrl = sanitizeInput($_POST['demo_video_uploaded_url'] ?? '');
            $demoUrlInput = sanitizeInput($_POST['demo_url'] ?? '');
            $demoUrl = !empty($uploadedVideoUrl) ? $uploadedVideoUrl : $demoUrlInput;
            
            $croppedThumbnailData = sanitizeInput($_POST['thumbnail_cropped_data'] ?? '');
            $thumbnailUrlInput = sanitizeInput($_POST['thumbnail_url'] ?? '');
            $thumbnailUrl = !empty($croppedThumbnailData) ? $croppedThumbnailData : $thumbnailUrlInput;
            
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name) || empty($slug)) {
                $errorMessage = 'Template name and slug are required.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $db->prepare("
                            INSERT INTO templates (name, slug, price, category, description, features, demo_url, thumbnail_url, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if ($stmt === false) {
                            throw new PDOException('Failed to prepare statement');
                        }
                        $result = $stmt->execute([$name, $slug, $price, $category, $description, $features, $demoUrl, $thumbnailUrl, $active]);
                        if ($result === false) {
                            throw new PDOException('Failed to execute statement');
                        }
                        $_SESSION['success_message'] = 'Template added successfully!';
                        logActivity('template_created', "Template created: $name", getAdminId());
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $id = intval($_POST['id']);
                        $stmt = $db->prepare("
                            UPDATE templates 
                            SET name = ?, slug = ?, price = ?, category = ?, description = ?, features = ?, demo_url = ?, thumbnail_url = ?, active = ?
                            WHERE id = ?
                        ");
                        if ($stmt === false) {
                            throw new PDOException('Failed to prepare statement');
                        }
                        $result = $stmt->execute([$name, $slug, $price, $category, $description, $features, $demoUrl, $thumbnailUrl, $active, $id]);
                        if ($result === false) {
                            throw new PDOException('Failed to execute statement');
                        }
                        $_SESSION['success_message'] = 'Template updated successfully!';
                        logActivity('template_updated', "Template updated: $name", getAdminId());
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log('Template add/edit error: ' . $e->getMessage());
                    $errorMessage = 'Database error occurred. Please try again.';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $template = getTemplateById($id);
                $stmt = $db->prepare("DELETE FROM templates WHERE id = ?");
                if ($stmt === false) {
                    throw new PDOException('Failed to prepare statement');
                }
                $result = $stmt->execute([$id]);
                if ($result === false) {
                    throw new PDOException('Failed to execute statement');
                }
                $_SESSION['success_message'] = 'Template deleted successfully!';
                logActivity('template_deleted', "Template deleted: " . ($template['name'] ?? $id), getAdminId());
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                error_log('Template delete error: ' . $e->getMessage());
                $errorMessage = 'Cannot delete template. Please try again.';
            }
        } elseif ($action === 'toggle_active') {
            $id = intval($_POST['id']);
            try {
                $stmt = $db->prepare("UPDATE templates SET active = CASE WHEN active = true THEN false ELSE true END WHERE id = ?");
                if ($stmt === false) {
                    throw new PDOException('Failed to prepare statement');
                }
                $result = $stmt->execute([$id]);
                if ($result === false) {
                    throw new PDOException('Failed to execute statement');
                }
                $_SESSION['success_message'] = 'Template status updated!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                error_log('Template toggle error: ' . $e->getMessage());
                $errorMessage = 'Database error occurred. Please try again.';
            }
        }
    }
}

// Get success message from session
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$searchTerm = $_GET['search'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT * FROM templates WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (name LIKE ? OR slug LIKE ? OR description LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($filterCategory)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}

if ($filterStatus !== '') {
    $sql .= " AND active = ?";
    $params[] = ($filterStatus === '1' || $filterStatus === 'true') ? true : false;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT DISTINCT category FROM templates WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = getTemplateById(intval($_GET['edit']));
}

require_once __DIR__ . '/includes/header.php';
?>

<div x-data="{ showModal: <?php echo $editTemplate ? 'true' : 'false'; ?> }">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 gap-3 sm:gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="bi bi-grid text-primary-600"></i> Templates Management
            </h1>
        </div>
        <button @click="showModal = true" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
            <i class="bi bi-plus-circle mr-2"></i> Add New Template
        </button>
    </div>

    <?php if ($successMessage): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
        <div class="flex items-center gap-3">
            <i class="bi bi-check-circle text-xl"></i>
            <span><?php echo htmlspecialchars($successMessage); ?></span>
        </div>
        <button @click="show = false" class="text-green-700 hover:text-green-900">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
        <div class="flex items-center gap-3">
            <i class="bi bi-exclamation-triangle text-xl"></i>
            <span><?php echo htmlspecialchars($errorMessage); ?></span>
        </div>
        <button @click="show = false" class="text-red-700 hover:text-red-900">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search templates...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="category">
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
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-search mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Slug</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Category</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Price</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-12">
                            <i class="bi bi-inbox text-6xl text-gray-300"></i>
                            <p class="text-gray-500 mt-4">No templates found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-bold text-gray-900">#<?php echo $template['id']; ?></td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($template['name']); ?></div>
                            <?php if (!empty($template['thumbnail_url'])): ?>
                            <div class="text-xs text-gray-500 mt-1"><i class="bi bi-image"></i> Has thumbnail</div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <code class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs"><?php echo htmlspecialchars($template['slug']); ?></code>
                        </td>
                        <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($template['category'] ?? '-'); ?></td>
                        <td class="py-3 px-4 font-bold text-gray-900"><?php echo formatCurrency($template['price']); ?></td>
                        <td class="py-3 px-4">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                <button type="submit" class="px-3 py-1 <?php echo $template['active'] ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?> rounded-full text-xs font-semibold transition-colors">
                                    <i class="bi bi-<?php echo $template['active'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                    <?php echo $template['active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex gap-2">
                                <?php if (!empty($template['demo_url'])): ?>
                                <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" target="_blank" class="px-3 py-1 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition-colors text-sm" title="View Demo">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?edit=<?php echo $template['id']; ?>" class="px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg transition-colors text-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template? This action cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg transition-colors text-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
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

    <!-- Alpine.js Modal -->
    <div x-show="showModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showModal = false" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white rounded-t-2xl">
                    <h3 class="text-2xl font-bold text-gray-900">
                        <?php echo $editTemplate ? 'Edit Template' : 'Add New Template'; ?>
                    </h3>
                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6">
                    <input type="hidden" name="action" value="<?php echo $editTemplate ? 'edit' : 'add'; ?>">
                    <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?php echo $editTemplate['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Template Name <span class="text-red-600">*</span></label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="name" value="<?php echo htmlspecialchars($editTemplate['name'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Price (₦) <span class="text-red-600">*</span></label>
                            <input type="number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="price" value="<?php echo $editTemplate['price'] ?? '0'; ?>" step="0.01" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Slug <span class="text-red-600">*</span></label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="slug" value="<?php echo htmlspecialchars($editTemplate['slug'] ?? ''); ?>" required>
                            <small class="text-gray-500 text-xs">URL-friendly name (e.g., ecommerce-store)</small>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="category" value="<?php echo htmlspecialchars($editTemplate['category'] ?? ''); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="description" rows="3"><?php echo htmlspecialchars($editTemplate['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Features</label>
                            <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="features" rows="3"><?php echo htmlspecialchars($editTemplate['features'] ?? ''); ?></textarea>
                            <small class="text-gray-500 text-xs">Comma-separated list of features</small>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Demo Video</label>
                            <div class="flex gap-2 mb-3">
                                <button type="button" onclick="toggleDemoVideoMode('url')" id="demo-video-url-btn" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">URL</button>
                                <button type="button" onclick="toggleDemoVideoMode('upload')" id="demo-video-upload-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload Video</button>
                            </div>
                            <div id="demo-video-url-mode">
                                <input type="url" id="demo-video-url-input" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="demo_url" value="<?php echo htmlspecialchars($editTemplate['demo_url'] ?? ''); ?>" placeholder="https://example.com/video.mp4">
                            </div>
                            <div id="demo-video-upload-mode" style="display: none;">
                                <input type="file" id="demo-video-file-input" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                                <div id="demo-video-upload-progress" style="margin-top: 10px; display: none;">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                            <div id="demo-video-progress-bar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                                        </div>
                                        <span id="demo-video-progress-text" class="text-sm text-gray-600">0%</span>
                                    </div>
                                </div>
                                <input type="hidden" id="demo-video-uploaded-url" name="demo_video_uploaded_url">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Thumbnail Image</label>
                            <div class="flex gap-2 mb-3">
                                <button type="button" onclick="toggleThumbnailMode('url')" id="thumbnail-url-btn" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">URL</button>
                                <button type="button" onclick="toggleThumbnailMode('upload')" id="thumbnail-upload-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                            </div>
                            <div id="thumbnail-url-mode">
                                <input type="url" id="thumbnail-url-input" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="thumbnail_url" value="<?php echo htmlspecialchars($editTemplate['thumbnail_url'] ?? ''); ?>" placeholder="https://example.com/image.jpg">
                            </div>
                            <div id="thumbnail-upload-mode" style="display: none;">
                                <input type="file" id="thumbnail-file-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                                <div id="thumbnail-cropper-container" style="margin-top: 15px; display: none;"></div>
                                <input type="hidden" id="thumbnail-cropped-data" name="thumbnail_cropped_data">
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" name="active" id="active" <?php echo (!$editTemplate || $editTemplate['active']) ? 'checked' : ''; ?>>
                                <span class="text-sm font-medium text-gray-700">Active (visible to customers)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-save mr-2"></i> <?php echo $editTemplate ? 'Update' : 'Create'; ?> Template
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="/assets/js/image-cropper.js"></script>
<script>
let thumbnailCropper = null;
let videoUploadInProgress = false;

function toggleDemoVideoMode(mode) {
    const urlMode = document.getElementById('demo-video-url-mode');
    const uploadMode = document.getElementById('demo-video-upload-mode');
    const urlBtn = document.getElementById('demo-video-url-btn');
    const uploadBtn = document.getElementById('demo-video-upload-btn');
    const urlInput = document.getElementById('demo-video-url-input');
    const uploadedUrlInput = document.getElementById('demo-video-uploaded-url');
    const fileInput = document.getElementById('demo-video-file-input');
    const progressDiv = document.getElementById('demo-video-upload-progress');
    
    if (mode === 'url') {
        urlMode.style.display = 'block';
        uploadMode.style.display = 'none';
        urlBtn.classList.add('bg-primary-600', 'text-white');
        urlBtn.classList.remove('bg-gray-200', 'text-gray-700');
        uploadBtn.classList.remove('bg-primary-600', 'text-white');
        uploadBtn.classList.add('bg-gray-200', 'text-gray-700');
        uploadedUrlInput.value = '';
        fileInput.value = '';
        progressDiv.style.display = 'none';
    } else {
        urlMode.style.display = 'none';
        uploadMode.style.display = 'block';
        uploadBtn.classList.add('bg-primary-600', 'text-white');
        uploadBtn.classList.remove('bg-gray-200', 'text-gray-700');
        urlBtn.classList.remove('bg-primary-600', 'text-white');
        urlBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.value = '';
    }
}

document.getElementById('demo-video-file-input')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const maxSize = 100 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('Video file is too large. Maximum size is 100MB.');
        e.target.value = '';
        return;
    }
    
    const progressDiv = document.getElementById('demo-video-upload-progress');
    const progressBar = document.getElementById('demo-video-progress-bar');
    const progressText = document.getElementById('demo-video-progress-text');
    const uploadedUrlInput = document.getElementById('demo-video-uploaded-url');
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressText.textContent = '0%';
    videoUploadInProgress = true;
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', 'video');
    formData.append('category', 'templates');
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });
        
        xhr.addEventListener('load', () => {
            videoUploadInProgress = false;
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        uploadedUrlInput.value = result.url;
                        document.getElementById('demo-video-url-input').value = result.url;
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
            videoUploadInProgress = false;
            alert('Upload failed. Please check your connection and try again.');
            progressDiv.style.display = 'none';
            e.target.value = '';
        });
        
        xhr.open('POST', '/api/upload.php');
        xhr.send(formData);
        
    } catch (error) {
        videoUploadInProgress = false;
        console.error('Upload error:', error);
        alert('Failed to upload video: ' + error.message);
        progressDiv.style.display = 'none';
        e.target.value = '';
    }
});

function toggleThumbnailMode(mode) {
    const urlMode = document.getElementById('thumbnail-url-mode');
    const uploadMode = document.getElementById('thumbnail-upload-mode');
    const urlBtn = document.getElementById('thumbnail-url-btn');
    const uploadBtn = document.getElementById('thumbnail-upload-btn');
    const urlInput = document.getElementById('thumbnail-url-input');
    const croppedDataInput = document.getElementById('thumbnail-cropped-data');
    
    if (mode === 'url') {
        urlMode.style.display = 'block';
        uploadMode.style.display = 'none';
        urlBtn.classList.add('bg-primary-600', 'text-white');
        urlBtn.classList.remove('bg-gray-200', 'text-gray-700');
        uploadBtn.classList.remove('bg-primary-600', 'text-white');
        uploadBtn.classList.add('bg-gray-200', 'text-gray-700');
        croppedDataInput.value = '';
        
        if (thumbnailCropper) {
            thumbnailCropper.destroy();
            thumbnailCropper = null;
        }
    } else {
        urlMode.style.display = 'none';
        uploadMode.style.display = 'block';
        uploadBtn.classList.add('bg-primary-600', 'text-white');
        uploadBtn.classList.remove('bg-gray-200', 'text-gray-700');
        urlBtn.classList.remove('bg-primary-600', 'text-white');
        urlBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.value = '';
    }
}

document.getElementById('thumbnail-file-input').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    if (!file.type.match('image.*')) {
        alert('Please select an image file');
        return;
    }
    
    const maxSize = 20 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('Image must be less than 20MB');
        return;
    }
    
    const container = document.getElementById('thumbnail-cropper-container');
    container.style.display = 'block';
    container.innerHTML = '';
    
    if (thumbnailCropper) {
        thumbnailCropper.destroy();
    }
    
    thumbnailCropper = new ImageCropper({
        aspectRatio: 16 / 9,
        minCropSize: 100,
        maxZoom: 3,
        onCropChange: (cropData) => {
            console.log('Crop changed:', cropData);
        }
    });
    
    container.appendChild(thumbnailCropper.getElement());
    
    try {
        await thumbnailCropper.loadImage(file);
    } catch (error) {
        console.error('Error loading image:', error);
        alert('Failed to load image. Please try again.');
    }
});

const originalFormSubmit = document.querySelector('form[method="POST"]').onsubmit;
document.querySelector('form[method="POST"]').onsubmit = async function(e) {
    const croppedDataInput = document.getElementById('thumbnail-cropped-data');
    const uploadMode = document.getElementById('thumbnail-upload-mode');
    const videoUploadMode = document.getElementById('demo-video-upload-mode');
    const demoVideoUrlInput = document.getElementById('demo-video-url-input');
    
    if (videoUploadInProgress) {
        e.preventDefault();
        alert('Please wait for the video upload to complete before submitting.');
        return false;
    }
    
    if (videoUploadMode && videoUploadMode.style.display !== 'none') {
        const fileInput = document.getElementById('demo-video-file-input');
        if (fileInput && fileInput.files.length > 0 && !demoVideoUrlInput.value) {
            e.preventDefault();
            alert('Please wait for the video to finish uploading, or switch to URL mode.');
            return false;
        }
    }
    
    if (uploadMode.style.display !== 'none' && thumbnailCropper) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i> Uploading...';
        
        try {
            const croppedBlob = await thumbnailCropper.getCroppedBlob({
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
            formData.append('category', 'templates');
            
            const response = await fetch('/api/upload.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Upload failed');
            }
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('thumbnail-url-input').value = result.url;
                croppedDataInput.value = result.url;
                
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
    
    if (originalFormSubmit) {
        return originalFormSubmit.call(this, e);
    }
    
    return true;
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
