<?php
$pageTitle = 'System Updates';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Initialize table if it doesn't exist
try {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_updates'");
    if (!$result->fetch()) {
        $sql = "
        CREATE TABLE IF NOT EXISTS system_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            display_date TEXT
        )
        ";
        $db->exec($sql);
    }
} catch (Exception $e) {
    error_log('Error initializing system_updates table: ' . $e->getMessage());
}

$message = '';
$error = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['id']);
    $stmt = $db->prepare('DELETE FROM system_updates WHERE id = ?');
    if ($stmt->execute([$id])) {
        $message = 'Update deleted successfully.';
        logActivity('system_update_deleted', 'System update #' . $id . ' deleted', getAdminId());
    } else {
        $error = 'Failed to delete update.';
    }
}

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = isset($_POST['id']) && $_POST['id'] ? intval($_POST['id']) : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $display_date = trim($_POST['display_date'] ?? date('M j, Y'));

    if (empty($title)) {
        $error = 'Title is required.';
    } elseif (empty($description)) {
        $error = 'Description is required.';
    } else {
        if ($id) {
            // Update
            $stmt = $db->prepare('UPDATE system_updates SET title = ?, description = ?, display_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            if ($stmt->execute([$title, $description, $display_date, $id])) {
                $message = 'Update saved successfully.';
                logActivity('system_update_edited', 'System update #' . $id . ' updated', getAdminId());
            } else {
                $error = 'Failed to save update.';
            }
        } else {
            // Create
            $stmt = $db->prepare('INSERT INTO system_updates (title, description, display_date) VALUES (?, ?, ?)');
            if ($stmt->execute([$title, $description, $display_date])) {
                $message = 'Update created successfully.';
                logActivity('system_update_created', 'New system update created', getAdminId());
            } else {
                $error = 'Failed to create update.';
            }
        }
    }
}

// Fetch all updates
$updates = [];
try {
    $stmt = $db->query('SELECT * FROM system_updates ORDER BY created_at DESC');
    $updates = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching updates: ' . $e->getMessage());
}

// Get edit data if editing
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$editData = null;
if ($editId) {
    $stmt = $db->prepare('SELECT * FROM system_updates WHERE id = ?');
    $stmt->execute([$editId]);
    $editData = $stmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-megaphone text-primary-600"></i> System Updates
    </h1>
    <p class="text-gray-600 mt-2">Manage status page announcements</p>
</div>

<?php if ($message): ?>
<div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-check-circle text-xl"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <button @click="show = false" class="text-green-600 hover:text-green-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-exclamation-circle text-xl"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <button @click="show = false" class="text-red-600 hover:text-red-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Section -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i class="bi bi-plus-circle text-primary-600"></i>
                <?php echo $editId ? 'Edit Update' : 'Add New Update'; ?>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save">
                <?php if ($editId): ?>
                    <input type="hidden" name="id" value="<?php echo $editId; ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                    <input type="text" name="title" required 
                           value="<?php echo $editData ? htmlspecialchars($editData['title']) : ''; ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="System Maintenance Complete">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" required rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                              placeholder="Describe the update..."><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Display Date</label>
                    <input type="text" name="display_date"
                           value="<?php echo $editData ? htmlspecialchars($editData['display_date']) : date('M j, Y'); ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="Dec 11, 2025">
                    <small class="text-gray-500 mt-1 block">Format: M d, Y (e.g., Dec 11, 2025)</small>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg font-semibold hover:shadow-lg transition-all">
                        <i class="bi bi-check-circle mr-2"></i>
                        <?php echo $editId ? 'Update' : 'Create'; ?> Update
                    </button>
                    <?php if ($editId): ?>
                        <a href="/admin/system-updates.php" class="flex-1 px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-all text-center">
                            <i class="bi bi-x-circle mr-2"></i>
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Updates List Section -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i class="bi bi-list-check text-primary-600"></i>
                Recent Updates <?php echo count($updates) > 0 ? '(' . count($updates) . ')' : ''; ?>
            </h2>

            <?php if (empty($updates)): ?>
                <div class="text-center py-12">
                    <i class="bi bi-inbox text-4xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500 font-medium">No updates yet</p>
                    <p class="text-gray-400 text-sm">Create your first system update to get started</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-[700px] overflow-y-auto">
                    <?php foreach ($updates as $update): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 hover:bg-primary-50 transition-all">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($update['title']); ?></h3>
                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($update['description']); ?></p>
                                    <div class="flex gap-4 mt-2 text-xs text-gray-500">
                                        <span><i class="bi bi-calendar mr-1"></i><?php echo htmlspecialchars($update['display_date']); ?></span>
                                        <span><i class="bi bi-clock mr-1"></i><?php echo date('M j, Y', strtotime($update['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <a href="?edit=<?php echo $update['id']; ?>" class="flex-1 px-3 py-1.5 bg-blue-100 text-blue-700 rounded text-xs font-medium hover:bg-blue-200 transition-colors text-center">
                                    <i class="bi bi-pencil mr-1"></i>Edit
                                </a>
                                <form method="POST" class="flex-1" onsubmit="return confirm('Delete this update? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $update['id']; ?>">
                                    <button type="submit" class="w-full px-3 py-1.5 bg-red-100 text-red-700 rounded text-xs font-medium hover:bg-red-200 transition-colors">
                                        <i class="bi bi-trash mr-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
