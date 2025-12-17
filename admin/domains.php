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

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Get total count for pagination
$countSql = preg_replace('/SELECT .* FROM/is', 'SELECT COUNT(*) FROM', $sql);
$countSql = preg_replace('/ORDER BY .*/is', '', $countSql);
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalDomains = $countStmt->fetchColumn();
$totalPages = ceil($totalDomains / $perPage);

// Add pagination to query
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

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

<?php
// Build base URL preserving query params except 'edit'
$closeParams = $_GET;
unset($closeParams['edit']);
$closeUrl = $_SERVER['PHP_SELF'] . ($closeParams ? '?' . http_build_query($closeParams) : '');
?>
<div x-data="{ 
    showCreateModal: false, 
    showEditModal: <?php echo $editDomain ? 'true' : 'false'; ?>, 
    showBulkModal: false,
    resetCreateForm() {
        const form = this.$el.querySelector('#create-domain-form');
        if (form) form.reset();
    },
    resetBulkForm() {
        const form = this.$el.querySelector('#bulk-domain-form');
        if (form) form.reset();
    }
}">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 gap-3 sm:gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="bi bi-globe text-primary-600"></i> Domains Management
            </h1>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full sm:w-auto">
            <button @click="showBulkModal = true" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg transition-all shadow-lg whitespace-nowrap">
                <i class="bi bi-plus-circle-dotted mr-2"></i> Bulk Add Domains
            </button>
            <button @click="showCreateModal = true" class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg whitespace-nowrap">
                <i class="bi bi-plus-circle mr-2"></i> Add Domain
            </button>
        </div>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search Domain</label>
                    <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by domain name...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Template</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template">
                        <option value="">All Templates</option>
                        <?php foreach ($templates as $tpl): ?>
                        <option value="<?php echo $tpl['id']; ?>" <?php echo $filterTemplate == $tpl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tpl['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="in_use" <?php echo $filterStatus === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                        <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
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
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Domain Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Template</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Order ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Notes</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($domains)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-12">
                            <i class="bi bi-inbox text-6xl text-gray-300"></i>
                            <p class="text-gray-500 mt-4">No domains found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($domains as $domain): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-bold text-gray-900">#<?php echo $domain['id']; ?></td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2 text-gray-900 font-medium">
                                <i class="bi bi-globe text-primary-600"></i>
                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($domain['template_name']); ?></td>
                        <td class="py-3 px-4">
                            <?php
                            $statusColors = [
                                'available' => 'bg-green-100 text-green-800',
                                'in_use' => 'bg-blue-100 text-blue-800',
                                'suspended' => 'bg-red-100 text-red-800'
                            ];
                            $statusIcons = [
                                'available' => 'check-circle',
                                'in_use' => 'hourglass-split',
                                'suspended' => 'x-circle'
                            ];
                            $color = $statusColors[$domain['status']] ?? 'bg-gray-100 text-gray-800';
                            $icon = $statusIcons[$domain['status']] ?? 'circle';
                            ?>
                            <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold whitespace-nowrap">
                                <i class="bi bi-<?php echo $icon; ?>"></i>
                                <span class="hidden sm:inline sm:ml-1"><?php echo ucfirst($domain['status']); ?></span>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($domain['assigned_order_id']): ?>
                            <a href="/admin/orders.php?view=<?php echo $domain['assigned_order_id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                                #<?php echo $domain['assigned_order_id']; ?>
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if (!empty($domain['notes'])): ?>
                            <span class="text-xs text-gray-600"><?php echo htmlspecialchars(substr($domain['notes'], 0, 50)); ?><?php echo strlen($domain['notes']) > 50 ? '...' : ''; ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex gap-2">
                                <a href="?edit=<?php echo $domain['id']; ?>" class="px-3 py-1 bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg transition-colors text-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($domain['status'] === 'available'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this domain? This action cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $domain['id']; ?>">
                                    <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg transition-colors text-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
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
                <a href="?page=<?php echo $page - 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterTemplate ? '&template=' . urlencode($filterTemplate) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    <i class="bi bi-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterTemplate ? '&template=' . urlencode($filterTemplate) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 border rounded-lg font-medium transition-colors <?php echo $i === $page ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterTemplate ? '&template=' . urlencode($filterTemplate) : ''; ?><?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
            <div class="text-center mt-3 text-sm text-gray-600">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalDomains; ?> total domains)
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Domain Modal -->
    <div x-show="showCreateModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showCreateModal = false; resetCreateForm()" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <form method="POST" id="create-domain-form">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900">Add New Domain</h3>
                    <button type="button" @click="showCreateModal = false; resetCreateForm()" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Domain Name <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="domain_name" placeholder="example.com" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Template <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template_id" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>">
                                <?php echo htmlspecialchars($tpl['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showCreateModal = false; resetCreateForm()" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-save mr-2"></i> Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Domain Modal -->
    <?php if ($editDomain): ?>
    <div x-show="showEditModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="window.location.href = '<?php echo htmlspecialchars($closeUrl); ?>'" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <form method="POST">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900">Edit Domain</h3>
                    <a href="<?php echo htmlspecialchars($closeUrl); ?>" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $editDomain['id']; ?>">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Domain Name <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="domain_name" value="<?php echo htmlspecialchars($editDomain['domain_name'] ?? ''); ?>" placeholder="example.com" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Template <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template_id" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>" <?php echo ($editDomain['template_id'] == $tpl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tpl['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                            <option value="available" <?php echo $editDomain['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="in_use" <?php echo $editDomain['status'] === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                            <option value="suspended" <?php echo $editDomain['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="notes" rows="3"><?php echo htmlspecialchars($editDomain['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <a href="<?php echo htmlspecialchars($closeUrl); ?>" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-save mr-2"></i> Update Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bulk Add Modal -->
    <div x-show="showBulkModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showBulkModal = false; resetBulkForm()" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <form method="POST" id="bulk-domain-form">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900">Bulk Add Domains</h3>
                    <button type="button" @click="showBulkModal = false; resetBulkForm()" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="bulk_add">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Template <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template_id" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>">
                                <?php echo htmlspecialchars($tpl['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Domain List <span class="text-red-600">*</span></label>
                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all font-mono text-sm" name="domain_list" rows="8" placeholder="example1.com&#10;example2.ng&#10;example3.com.ng" required></textarea>
                        <small class="text-gray-500 text-xs">Enter one domain per line</small>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showBulkModal = false; resetBulkForm()" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-plus-circle-dotted mr-2"></i> Add Domains
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
