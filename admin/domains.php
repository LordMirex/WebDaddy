<?php
$pageTitle = 'Domains Management';

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
        
        if ($action === 'add') {
            $templateId = intval($_POST['template_id']);
            $domainName = sanitizeInput($_POST['domain_name']);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if (empty($domainName) || $templateId <= 0) {
                $errorMessage = 'Domain name and template are required.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO domains (template_id, domain_name, status, notes) VALUES (?, ?, 'available', ?)");
                    $stmt->execute([$templateId, $domainName, $notes]);
                    $_SESSION['success_message'] = 'Domain added successfully!';
                    logActivity('domain_created', "Domain created: $domainName", getAdminId());
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $templateId = intval($_POST['template_id']);
            $domainName = sanitizeInput($_POST['domain_name']);
            $status = sanitizeInput($_POST['status']);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if (empty($domainName) || $templateId <= 0) {
                $errorMessage = 'Domain name and template are required.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE domains SET template_id = ?, domain_name = ?, status = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$templateId, $domainName, $status, $notes, $id]);
                    $_SESSION['success_message'] = 'Domain updated successfully!';
                    logActivity('domain_updated', "Domain updated: $domainName", getAdminId());
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $db->prepare("SELECT domain_name FROM domains WHERE id = ?");
                $stmt->execute([$id]);
                $domain = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("DELETE FROM domains WHERE id = ? AND status = 'available'");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Domain deleted successfully!';
                    logActivity('domain_deleted', "Domain deleted: " . ($domain['domain_name'] ?? $id), getAdminId());
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $errorMessage = 'Cannot delete domain. It may be in use or already assigned.';
                }
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
            }
        } elseif ($action === 'bulk_add') {
            $templateId = intval($_POST['template_id']);
            $domainList = $_POST['domain_list'] ?? '';
            
            if ($templateId <= 0 || empty($domainList)) {
                $errorMessage = 'Template and domain list are required.';
            } else {
                $domains = array_filter(array_map('trim', explode("\n", $domainList)));
                $addedCount = 0;
                $errors = [];
                
                foreach ($domains as $domainName) {
                    $domainName = sanitizeInput($domainName);
                    if (!empty($domainName)) {
                        try {
                            $stmt = $db->prepare("INSERT INTO domains (template_id, domain_name, status) VALUES (?, ?, 'available')");
                            $stmt->execute([$templateId, $domainName]);
                            $addedCount++;
                        } catch (PDOException $e) {
                            $errors[] = "$domainName: " . $e->getMessage();
                        }
                    }
                }
                
                if ($addedCount > 0) {
                    $_SESSION['success_message'] = "Successfully added $addedCount domain(s)!";
                    logActivity('domains_bulk_created', "Bulk added $addedCount domains", getAdminId());
                    if (!empty($errors)) {
                        $_SESSION['error_message'] = 'Some domains could not be added: ' . implode(', ', $errors);
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } elseif (!empty($errors)) {
                    $errorMessage = 'Some domains could not be added: ' . implode(', ', $errors);
                }
            }
        }
    }
}

// Get success/error messages from session
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$searchTerm = $_GET['search'] ?? '';
$filterTemplate = $_GET['template'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT d.*, t.name as template_name 
        FROM domains d 
        LEFT JOIN templates t ON d.template_id = t.id 
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND d.domain_name LIKE ?";
    $params[] = '%' . $searchTerm . '%';
}

if (!empty($filterTemplate)) {
    $sql .= " AND d.template_id = ?";
    $params[] = intval($filterTemplate);
}

if (!empty($filterStatus)) {
    $sql .= " AND d.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = getTemplates(false);

$editDomain = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM domains WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $editDomain = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-globe"></i> Domains Management</h1>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkAddModal">
            <i class="bi bi-plus-circle-dotted"></i> Bulk Add Domains
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#domainModal">
            <i class="bi bi-plus-circle"></i> Add Domain
        </button>
    </div>
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
                <label class="form-label">Search Domain</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by domain name...">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Template</label>
                <select class="form-select" name="template">
                    <option value="">All Templates</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?php echo $tpl['id']; ?>" <?php echo $filterTemplate == $tpl['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tpl['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="in_use" <?php echo $filterStatus === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                    <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
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
                        <th>Domain Name</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th>Order ID</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($domains)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-2">No domains found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($domains as $domain): ?>
                    <tr>
                        <td><strong>#<?php echo $domain['id']; ?></strong></td>
                        <td>
                            <i class="bi bi-globe"></i> <strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($domain['template_name']); ?></td>
                        <td>
                            <?php
                            $statusClass = [
                                'available' => 'success',
                                'in_use' => 'primary',
                                'suspended' => 'danger'
                            ];
                            $statusIcon = [
                                'available' => 'check-circle',
                                'in_use' => 'hourglass-split',
                                'suspended' => 'x-circle'
                            ];
                            $class = $statusClass[$domain['status']] ?? 'secondary';
                            $icon = $statusIcon[$domain['status']] ?? 'circle';
                            ?>
                            <span class="badge bg-<?php echo $class; ?>">
                                <i class="bi bi-<?php echo $icon; ?>"></i> <?php echo ucfirst($domain['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($domain['assigned_order_id']): ?>
                            <a href="/admin/orders.php?view=<?php echo $domain['assigned_order_id']; ?>">
                                #<?php echo $domain['assigned_order_id']; ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($domain['notes'])): ?>
                            <small><?php echo htmlspecialchars(substr($domain['notes'], 0, 50)); ?><?php echo strlen($domain['notes']) > 50 ? '...' : ''; ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?edit=<?php echo $domain['id']; ?>" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($domain['status'] === 'available'): ?>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $domain['id']; ?>, '<?php echo htmlspecialchars(addslashes($domain['domain_name'])); ?>')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
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

<div class="modal fade" id="domainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editDomain ? 'Edit Domain' : 'Add New Domain'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editDomain ? 'edit' : 'add'; ?>">
                    <?php if ($editDomain): ?>
                    <input type="hidden" name="id" value="<?php echo $editDomain['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Domain Name *</label>
                        <input type="text" class="form-control" name="domain_name" value="<?php echo htmlspecialchars($editDomain['domain_name'] ?? ''); ?>" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template *</label>
                        <select class="form-select" name="template_id" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>" <?php echo ($editDomain && $editDomain['template_id'] == $tpl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tpl['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($editDomain): ?>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="available" <?php echo $editDomain['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="in_use" <?php echo $editDomain['status'] === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="suspended" <?php echo $editDomain['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($editDomain['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?php echo $editDomain ? 'Update' : 'Add'; ?> Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Add Domains</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_add">
                    
                    <div class="mb-3">
                        <label class="form-label">Template *</label>
                        <select class="form-select" name="template_id" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>">
                                <?php echo htmlspecialchars($tpl['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain List *</label>
                        <textarea class="form-control" name="domain_list" rows="8" placeholder="example1.com&#10;example2.ng&#10;example3.com.ng" required></textarea>
                        <small class="text-muted">Enter one domain per line</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle-dotted"></i> Add Domains
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
    if (confirm('Are you sure you want to delete the domain "' + name + '"?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

<?php if ($editDomain): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('domainModal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
