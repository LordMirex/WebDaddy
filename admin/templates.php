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
            $demoUrl = sanitizeInput($_POST['demo_url'] ?? '');
            $thumbnailUrl = sanitizeInput($_POST['thumbnail_url'] ?? '');
            $videoLinks = sanitizeInput($_POST['video_links'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name) || empty($slug)) {
                $errorMessage = 'Template name and slug are required.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $db->prepare("
                            INSERT INTO templates (name, slug, price, category, description, features, demo_url, thumbnail_url, video_links, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $slug, $price, $category, $description, $features, $demoUrl, $thumbnailUrl, $videoLinks, $active]);
                        $_SESSION['success_message'] = 'Template added successfully!';
                        logActivity('template_created', "Template created: $name", getAdminId());
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $id = intval($_POST['id']);
                        $stmt = $db->prepare("
                            UPDATE templates 
                            SET name = ?, slug = ?, price = ?, category = ?, description = ?, features = ?, demo_url = ?, thumbnail_url = ?, video_links = ?, active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $slug, $price, $category, $description, $features, $demoUrl, $thumbnailUrl, $videoLinks, $active, $id]);
                        $_SESSION['success_message'] = 'Template updated successfully!';
                        logActivity('template_updated', "Template updated: $name", getAdminId());
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $template = getTemplateById($id);
                $stmt = $db->prepare("DELETE FROM templates WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = 'Template deleted successfully!';
                logActivity('template_deleted', "Template deleted: " . ($template['name'] ?? $id), getAdminId());
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $errorMessage = 'Cannot delete template: ' . $e->getMessage();
            }
        } elseif ($action === 'toggle_active') {
            $id = intval($_POST['id']);
            try {
                $stmt = $db->prepare("UPDATE templates SET active = CASE WHEN active = true THEN false ELSE true END WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = 'Template status updated!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-grid"></i> Templates Management</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
        <i class="bi bi-plus-circle"></i> Add New Template
    </button>
</div>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search templates...">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-2">No templates found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><strong>#<?php echo $template['id']; ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($template['name']); ?>
                            <?php if (!empty($template['thumbnail_url'])): ?>
                            <br><small class="text-muted"><i class="bi bi-image"></i> Has thumbnail</small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($template['slug']); ?></code></td>
                        <td><?php echo htmlspecialchars($template['category'] ?? '-'); ?></td>
                        <td><strong><?php echo formatCurrency($template['price']); ?></strong></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $template['active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                    <i class="bi bi-<?php echo $template['active'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                    <?php echo $template['active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if (!empty($template['demo_url'])): ?>
                                <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" target="_blank" class="btn btn-info" title="View Demo">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?edit=<?php echo $template['id']; ?>" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars(addslashes($template['name'])); ?>')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editTemplate ? 'Edit Template' : 'Add New Template'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editTemplate ? 'edit' : 'add'; ?>">
                    <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?php echo $editTemplate['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Template Name *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($editTemplate['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price (₦) *</label>
                            <input type="number" class="form-control" name="price" value="<?php echo $editTemplate['price'] ?? '0'; ?>" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug *</label>
                            <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($editTemplate['slug'] ?? ''); ?>" required>
                            <small class="text-muted">URL-friendly name (e.g., ecommerce-store)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($editTemplate['category'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($editTemplate['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Features</label>
                            <textarea class="form-control" name="features" rows="3"><?php echo htmlspecialchars($editTemplate['features'] ?? ''); ?></textarea>
                            <small class="text-muted">Comma-separated list of features</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Demo URL</label>
                            <input type="url" class="form-control" name="demo_url" value="<?php echo htmlspecialchars($editTemplate['demo_url'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thumbnail URL</label>
                            <input type="url" class="form-control" name="thumbnail_url" value="<?php echo htmlspecialchars($editTemplate['thumbnail_url'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Video Links</label>
                            <textarea class="form-control" name="video_links" rows="2"><?php echo htmlspecialchars($editTemplate['video_links'] ?? ''); ?></textarea>
                            <small class="text-muted">One URL per line</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="active" <?php echo (!$editTemplate || $editTemplate['active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">
                                    Active (visible to customers)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?php echo $editTemplate ? 'Update' : 'Create'; ?> Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, name) {
    if (confirm('Are you sure you want to delete the template "' + name + '"? This action cannot be undone.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

<?php if ($editTemplate): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('templateModal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
