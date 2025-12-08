<?php
$pageTitle = 'Templates Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url_utils.php';
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
            
            $active = isset($_POST['active']) ? 1 : 0;
            $priorityOrder = isset($_POST['priority_order']) ? intval($_POST['priority_order']) : null;
            $priorityOrder = ($priorityOrder > 0 && $priorityOrder <= 10) ? $priorityOrder : null;
            
            if (empty($name) || empty($slug)) {
                $errorMessage = 'Template name and slug are required.';
            } elseif ($videoType === 'youtube' && empty($previewYoutube)) {
                $errorMessage = 'Invalid YouTube URL or video ID. Please provide a valid YouTube link.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $db->prepare("
                            INSERT INTO templates (name, slug, price, category, description, features, seo_keywords, media_type, demo_url, demo_video_url, preview_youtube, thumbnail_url, active, priority_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if ($stmt === false) {
                            throw new PDOException('Failed to prepare statement');
                        }
                        $result = $stmt->execute([$name, $slug, $price, $category, $description, $features, $seoKeywords, $mediaType, $demoUrl, $demoVideoUrl, $previewYoutube, $thumbnailUrl, $active, $priorityOrder]);
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
                            SET name = ?, slug = ?, price = ?, category = ?, description = ?, features = ?, seo_keywords = ?, media_type = ?, demo_url = ?, demo_video_url = ?, preview_youtube = ?, thumbnail_url = ?, active = ?, priority_order = ?
                            WHERE id = ?
                        ");
                        if ($stmt === false) {
                            throw new PDOException('Failed to prepare statement');
                        }
                        $result = $stmt->execute([$name, $slug, $price, $category, $description, $features, $seoKeywords, $mediaType, $demoUrl, $demoVideoUrl, $previewYoutube, $thumbnailUrl, $active, $priorityOrder, $id]);
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

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Get total count for pagination
$countSql = preg_replace('/SELECT .* FROM/is', 'SELECT COUNT(*) FROM', $sql);
$countSql = preg_replace('/ORDER BY .*/is', '', $countSql);
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalTemplates = $countStmt->fetchColumn();
$totalPages = ceil($totalTemplates / $perPage);

// Add pagination to query
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT DISTINCT category FROM templates WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Fetch all used priorities for templates (excluding current template if editing)
$usedTemplatePriorities = [];
$priorityQuery = "SELECT id, priority_order, name FROM templates WHERE priority_order IS NOT NULL AND priority_order > 0";
$priorityStmt = $db->query($priorityQuery);
while ($row = $priorityStmt->fetch(PDO::FETCH_ASSOC)) {
    $usedTemplatePriorities[$row['priority_order']] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

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
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Priority</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
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
                            <?php if (!empty($template['priority_order']) && $template['priority_order'] > 0): ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                <i class="bi bi-star-fill"></i> #<?php echo $template['priority_order']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">-</span>
                            <?php endif; ?>
                        </td>
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
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <nav class="flex items-center justify-center gap-2 flex-wrap">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus !== '' ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus !== '' ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 border rounded-lg font-medium transition-colors <?php echo $i === $page ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterCategory ? '&category=' . urlencode($filterCategory) : ''; ?><?php echo $filterStatus !== '' ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
            <div class="text-center mt-3 text-sm text-gray-600">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalTemplates; ?> total templates)
            </div>
        </div>
        <?php endif; ?>
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
                        
                        <!-- Banner Section (Always Present) -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Template Banner <span class="text-red-600">*</span></label>
                            <p class="text-xs text-gray-500 mb-3">Required. This image represents your template on the marketplace.</p>
                            <div class="flex gap-2 mb-3">
                                <button type="button" onclick="toggleBannerMode('url')" id="banner-url-btn" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Paste URL</button>
                                <button type="button" onclick="toggleBannerMode('upload')" id="banner-upload-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Upload & Crop</button>
                            </div>
                            <div id="banner-url-mode">
                                <input type="text" name="thumbnail_url" id="banner-url-input" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($editTemplate['thumbnail_url'] ?? ''); ?>" <?php echo !$editTemplate ? 'required' : ''; ?> placeholder="https://example.com/banner.jpg or /uploads/...">
                            </div>
                            <div id="banner-upload-mode" style="display: none;">
                                <input type="file" id="banner-file-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                                <div id="banner-cropper-container" style="margin-top: 15px; display: none;"></div>
                                <input type="hidden" name="thumbnail_cropped_data" id="banner-cropped-data">
                            </div>
                            <small class="text-gray-500 text-xs mt-1 block">Upload or paste banner image URL (recommended: 1280x720px)</small>
                        </div>
                        
                        <!-- Video/Demo Section (Optional) -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Preview/Demo (Optional)</label>
                            <p class="text-xs text-gray-500 mb-3">Add a video preview or demo website link for customers to see your template in action.</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-primary-400" id="video-type-none-label">
                                    <input type="radio" name="video_type" value="none" onchange="handleVideoTypeChange()" class="w-4 h-4 text-primary-600" <?php echo (!$editTemplate || ($editTemplate['media_type'] ?? 'banner') === 'banner') ? 'checked' : ''; ?>>
                                    <span class="font-medium text-sm">🚫 None</span>
                                </label>
                                <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-primary-400" id="video-type-video-label">
                                    <input type="radio" name="video_type" value="video" onchange="handleVideoTypeChange()" class="w-4 h-4 text-primary-600" <?php echo ($editTemplate && ($editTemplate['media_type'] ?? '') === 'video') ? 'checked' : ''; ?>>
                                    <span class="font-medium text-sm">🎥 Video</span>
                                </label>
                                <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-primary-400" id="video-type-youtube-label">
                                    <input type="radio" name="video_type" value="youtube" onchange="handleVideoTypeChange()" class="w-4 h-4 text-primary-600" <?php echo ($editTemplate && ($editTemplate['media_type'] ?? '') === 'youtube') ? 'checked' : ''; ?>>
                                    <span class="font-medium text-sm">📺 YouTube</span>
                                </label>
                                <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-all hover:border-primary-400" id="video-type-demo-url-label">
                                    <input type="radio" name="video_type" value="demo_url" onchange="handleVideoTypeChange()" class="w-4 h-4 text-primary-600" <?php echo ($editTemplate && ($editTemplate['media_type'] ?? '') === 'demo_url') ? 'checked' : ''; ?>>
                                    <span class="font-medium text-sm">🌐 Demo URL</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="video-upload-section" class="md:col-span-2" style="display: none;">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Demo Video</label>
                            <input type="file" id="video-file-input" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm">
                            <div id="video-upload-progress" style="margin-top: 10px; display: none;">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                                        <div id="video-progress-bar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                                    </div>
                                    <span id="video-progress-text" class="text-sm text-gray-600 flex items-center gap-1">
                                        <span id="video-progress-percentage">0%</span>
                                        <svg id="video-upload-check" class="w-4 h-4 text-green-600" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <input type="hidden" name="demo_video_uploaded_url" id="video-uploaded-url" value="<?php echo htmlspecialchars($editTemplate['demo_video_url'] ?? ''); ?>">
                            <small class="text-gray-500 text-xs mt-1 block">Upload demo video (MP4, WebM recommended, max 500MB)</small>
                        </div>
                        
                        <div id="youtube-section" class="md:col-span-2" style="display: none;">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube Video URL or ID</label>
                            <input type="text" name="youtube_url_input" id="youtube-url-input" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($editTemplate['preview_youtube'] ?? ''); ?>" placeholder="https://youtube.com/watch?v=... or just the video ID">
                            <small class="text-gray-500 text-xs mt-1 block">Paste any YouTube URL or video ID. Unlisted videos work too! (Fastest loading option)</small>
                        </div>
                        
                        <div id="demo-url-section" class="md:col-span-2" style="display: none;">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Demo Website URL</label>
                            <input type="url" name="demo_url_input" id="demo-url-input" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" value="<?php echo htmlspecialchars($editTemplate['demo_url'] ?? ''); ?>" placeholder="https://example.com/demo">
                            <small class="text-gray-500 text-xs mt-1 block">Enter the URL of a live website for interactive preview (shown in iframe)</small>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Priority (Top 10 Featured)</label>
                            <select name="priority_order" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                                <option value="">None (Regular Listing)</option>
                                <?php
                                $priorityLabels = [
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
                                foreach ($priorityLabels as $num => $label):
                                    $isSelected = ($editTemplate && $editTemplate['priority_order'] == $num);
                                    $isUsed = isset($usedTemplatePriorities[$num]);
                                    $usedByCurrentItem = $isUsed && $editTemplate && $usedTemplatePriorities[$num]['id'] == $editTemplate['id'];
                                    $isDisabled = $isUsed && !$usedByCurrentItem;
                                    $usedByName = $isUsed && !$usedByCurrentItem ? ' (Used by: ' . htmlspecialchars(substr($usedTemplatePriorities[$num]['name'], 0, 20)) . ')' : '';
                                ?>
                                <option value="<?php echo $num; ?>" <?php echo $isSelected ? 'selected' : ''; ?> <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                    <?php echo $label . $usedByName; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-gray-500 text-xs mt-1 block">Select to feature this template in the top 10 displayed first. Disabled options are already in use by other templates.</small>
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
let bannerCropper = null;
let videoUploadInProgress = false;

function handleVideoTypeChange() {
    const selectedType = document.querySelector('input[name="video_type"]:checked').value;
    
    const demoUrlSection = document.getElementById('demo-url-section');
    const videoUploadSection = document.getElementById('video-upload-section');
    const youtubeSection = document.getElementById('youtube-section');
    
    const noneLabel = document.getElementById('video-type-none-label');
    const videoLabel = document.getElementById('video-type-video-label');
    const youtubeLabel = document.getElementById('video-type-youtube-label');
    const demoUrlLabel = document.getElementById('video-type-demo-url-label');
    
    demoUrlSection.style.display = 'none';
    videoUploadSection.style.display = 'none';
    youtubeSection.style.display = 'none';
    
    noneLabel.classList.remove('border-primary-600', 'bg-primary-50');
    videoLabel.classList.remove('border-primary-600', 'bg-primary-50');
    youtubeLabel.classList.remove('border-primary-600', 'bg-primary-50');
    demoUrlLabel.classList.remove('border-primary-600', 'bg-primary-50');
    
    if (selectedType === 'demo_url') {
        demoUrlSection.style.display = 'block';
        demoUrlLabel.classList.add('border-primary-600', 'bg-primary-50');
    } else if (selectedType === 'video') {
        videoUploadSection.style.display = 'block';
        videoLabel.classList.add('border-primary-600', 'bg-primary-50');
    } else if (selectedType === 'youtube') {
        youtubeSection.style.display = 'block';
        youtubeLabel.classList.add('border-primary-600', 'bg-primary-50');
    } else {
        noneLabel.classList.add('border-primary-600', 'bg-primary-50');
    }
}

// Initialize video type on page load
document.addEventListener('DOMContentLoaded', function() {
    handleVideoTypeChange();
});

function toggleBannerMode(mode) {
    const urlMode = document.getElementById('banner-url-mode');
    const uploadMode = document.getElementById('banner-upload-mode');
    const urlBtn = document.getElementById('banner-url-btn');
    const uploadBtn = document.getElementById('banner-upload-btn');
    const urlInput = document.getElementById('banner-url-input');
    const croppedDataInput = document.getElementById('banner-cropped-data');
    
    if (mode === 'url') {
        urlMode.style.display = 'block';
        uploadMode.style.display = 'none';
        urlBtn.classList.add('bg-primary-600', 'text-white');
        urlBtn.classList.remove('bg-gray-200', 'text-gray-700');
        uploadBtn.classList.remove('bg-primary-600', 'text-white');
        uploadBtn.classList.add('bg-gray-200', 'text-gray-700');
        if (!urlInput.value || urlInput.value.trim() === '') {
            urlInput.required = true;
        }
        croppedDataInput.value = '';
        
        if (bannerCropper) {
            bannerCropper.destroy();
            bannerCropper = null;
        }
    } else {
        urlMode.style.display = 'none';
        uploadMode.style.display = 'block';
        uploadBtn.classList.add('bg-primary-600', 'text-white');
        uploadBtn.classList.remove('bg-gray-200', 'text-gray-700');
        urlBtn.classList.remove('bg-primary-600', 'text-white');
        urlBtn.classList.add('bg-gray-200', 'text-gray-700');
        urlInput.required = false;
    }
}

document.getElementById('video-file-input')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const hasVideoType = file.type && file.type.match('video.*');
    const hasVideoExtension = file.name && file.name.match(/\.(mp4|webm|mov|avi)$/i);
    if (!hasVideoType && !hasVideoExtension) {
        alert('Please select a video file (MP4, WebM, MOV, or AVI)');
        e.target.value = '';
        return;
    }
    
    const maxSize = 500 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('Video file is too large. Maximum size is 500MB.');
        e.target.value = '';
        return;
    }
    
    const progressDiv = document.getElementById('video-upload-progress');
    const progressBar = document.getElementById('video-progress-bar');
    const progressText = document.getElementById('video-progress-text');
    const uploadedUrlInput = document.getElementById('video-uploaded-url');
    
    if (!progressDiv || !progressBar || !progressText || !uploadedUrlInput) {
        alert('Upload interface not found. Please refresh the page and try again.');
        return;
    }
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.classList.remove('bg-green-600');
    const progressPercentageElem = document.getElementById('video-progress-percentage');
    const progressCheckElem = document.getElementById('video-upload-check');
    if (progressPercentageElem) {
        progressPercentageElem.textContent = '0%';
    }
    if (progressCheckElem) {
        progressCheckElem.style.display = 'none';
    }
    videoUploadInProgress = true;
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', 'video');
    formData.append('category', 'templates');
    
    try {
        const xhr = new XMLHttpRequest();
        
        const progressPercentage = document.getElementById('video-progress-percentage');
        const progressCheck = document.getElementById('video-upload-check');
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                if (progressPercentage) {
                    if (percentComplete === 100) {
                        progressPercentage.textContent = 'Processing...';
                    } else {
                        progressPercentage.textContent = percentComplete + '%';
                    }
                }
            }
        });
        
        xhr.addEventListener('load', () => {
            console.log('XHR load event - Status:', xhr.status, 'Response:', xhr.responseText.substring(0, 200));
            
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    console.log('Parsed result:', result);
                    
                    if (result.success && result.url) {
                        uploadedUrlInput.value = result.url;
                        progressBar.classList.remove('bg-primary-600');
                        progressBar.classList.add('bg-green-600');
                        progressBar.style.width = '100%';
                        if (progressPercentage) {
                            progressPercentage.textContent = 'Complete!';
                            progressPercentage.classList.add('text-green-600', 'font-semibold');
                        }
                        if (progressCheck) {
                            progressCheck.style.display = 'inline-block';
                        }
                        videoUploadInProgress = false;
                        console.log('✅ Video uploaded successfully:', result.url);
                        
                        const fileInput = document.getElementById('video-file-input');
                        if (fileInput) {
                            fileInput.disabled = true;
                        }
                        
                        if (result.video_data && result.video_data.processing_warning) {
                            console.warn('⚠️ ' + result.video_data.processing_warning);
                        }
                    } else {
                        videoUploadInProgress = false;
                        console.error('Upload failed - result:', result);
                        throw new Error(result.error || 'Upload failed - no URL returned');
                    }
                } catch (error) {
                    videoUploadInProgress = false;
                    console.error('❌ Video upload error:', error, 'Response:', xhr.responseText);
                    alert('Failed to upload video: ' + error.message);
                    progressDiv.style.display = 'none';
                    progressBar.style.width = '0%';
                    progressBar.classList.remove('bg-green-600');
                    progressBar.classList.add('bg-primary-600');
                    if (progressPercentage) {
                        progressPercentage.textContent = '0%';
                        progressPercentage.classList.remove('text-green-600', 'font-semibold');
                    }
                    if (progressCheck) {
                        progressCheck.style.display = 'none';
                    }
                    e.target.value = '';
                }
            } else {
                videoUploadInProgress = false;
                console.error('❌ Upload failed with status:', xhr.status, 'Response:', xhr.responseText);
                let errorMsg = 'Upload failed with status ' + xhr.status;
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.error) {
                        errorMsg = result.error;
                    }
                } catch (parseError) {
                    console.error('Failed to parse error response:', parseError);
                }
                alert('Upload failed: ' + errorMsg);
                progressDiv.style.display = 'none';
                progressBar.style.width = '0%';
                progressBar.classList.remove('bg-green-600');
                progressBar.classList.add('bg-primary-600');
                e.target.value = '';
            }
        });
        
        xhr.addEventListener('error', () => {
            videoUploadInProgress = false;
            alert('Network error occurred. Please check your connection and try again.');
            progressDiv.style.display = 'none';
            progressBar.style.width = '0%';
            e.target.value = '';
        });
        
        xhr.addEventListener('abort', () => {
            videoUploadInProgress = false;
            progressDiv.style.display = 'none';
            progressBar.style.width = '0%';
        });
        
        xhr.open('POST', '/api/upload.php');
        xhr.send(formData);
        
    } catch (error) {
        videoUploadInProgress = false;
        console.error('Video upload error:', error);
        alert('Failed to upload video: ' + error.message);
        progressDiv.style.display = 'none';
        progressBar.style.width = '0%';
        e.target.value = '';
    }
});

document.getElementById('banner-file-input')?.addEventListener('change', async function(e) {
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
    
    const container = document.getElementById('banner-cropper-container');
    container.style.display = 'block';
    container.innerHTML = '';
    
    if (bannerCropper) {
        bannerCropper.destroy();
    }
    
    bannerCropper = new ImageCropper({
        aspectRatio: 16 / 9,
        minCropSize: 100,
        maxZoom: 3,
        onCropChange: (cropData) => {
            console.log('Crop changed:', cropData);
        }
    });
    
    container.appendChild(bannerCropper.getElement());
    
    try {
        await bannerCropper.loadImage(file);
    } catch (error) {
        console.error('Error loading image:', error);
        alert('Failed to load image. Please try again.');
    }
});

const bannerUploadMode = document.getElementById('banner-upload-mode');
const templateForm = bannerUploadMode?.closest('form[method="POST"]');
if (templateForm) {
    templateForm.addEventListener('submit', async function(e) {
        const croppedDataInput = document.getElementById('banner-cropped-data');
        
        if (videoUploadInProgress) {
            e.preventDefault();
            alert('Please wait for the video upload to complete before submitting.');
            return false;
        }
        
        if (bannerUploadMode && bannerUploadMode.style.display !== 'none' && bannerCropper) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i> Uploading...';
            
            try {
                const croppedBlob = await bannerCropper.getCroppedBlob({
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
                    throw new Error(`Upload failed with status ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    croppedDataInput.value = result.url;
                    bannerCropper.destroy();
                    bannerCropper = null;
                    
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
        
        return true;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    handleVideoTypeChange();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
