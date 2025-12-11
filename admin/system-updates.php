<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

// Initialize table if it doesn't exist
$db = getDb();
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
            } else {
                $error = 'Failed to save update.';
            }
        } else {
            // Create
            $stmt = $db->prepare('INSERT INTO system_updates (title, description, display_date) VALUES (?, ?, ?)');
            if ($stmt->execute([$title, $description, $display_date])) {
                $message = 'Update created successfully.';
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

$siteName = SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Updates - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
            background-color: #0f172a;
        }
        .sidebar {
            background-color: #1e293b;
            border-right: 1px solid rgba(148, 163, 184, 0.1);
        }
        .sidebar a {
            display: block;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .sidebar a:hover {
            background-color: #334155;
            color: #f1f5f9;
        }
        .sidebar a.active {
            border-left-color: #3b82f6;
            background-color: #1e3a8a;
            color: #60a5fa;
        }
    </style>
</head>
<body class="bg-slate-950 text-white">
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar p-4">
            <a href="/admin/index.php" class="font-bold mb-4">Admin Dashboard</a>
            <nav class="space-y-2">
                <a href="/admin/index.php">Dashboard</a>
                <a href="/admin/templates.php">Templates</a>
                <a href="/admin/tools.php">Tools</a>
                <a href="/admin/orders.php">Orders</a>
                <a href="/admin/affiliates.php">Affiliates</a>
                <a href="/admin/settings.php">Settings</a>
                <a href="/admin/system-updates.php" class="active">System Updates</a>
                <a href="/admin/logout.php">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <main class="p-8">
            <div class="max-w-4xl">
                <h1 class="text-3xl font-bold mb-8">System Updates</h1>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-900/30 border border-green-500/50 rounded-lg p-4 mb-6">
                        <p class="text-green-400"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-900/30 border border-red-500/50 rounded-lg p-4 mb-6">
                        <p class="text-red-400"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Form -->
                <div class="bg-slate-800 rounded-lg p-6 mb-8 border border-slate-700">
                    <h2 class="text-xl font-bold mb-4"><?php echo $editId ? 'Edit Update' : 'Add New Update'; ?></h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <?php if ($editId): ?>
                            <input type="hidden" name="id" value="<?php echo $editId; ?>">
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Title</label>
                            <input type="text" name="title" required 
                                   value="<?php echo $editData ? htmlspecialchars($editData['title']) : ''; ?>"
                                   class="w-full px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                                   placeholder="e.g., System Maintenance Complete">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Description</label>
                            <textarea name="description" required rows="3"
                                      class="w-full px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                                      placeholder="e.g., Routine maintenance completed successfully. All services running smoothly."><?php echo $editData ? htmlspecialchars($editData['description']) : ''; ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Display Date</label>
                            <input type="text" name="display_date"
                                   value="<?php echo $editData ? htmlspecialchars($editData['display_date']) : date('M j, Y'); ?>"
                                   class="w-full px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                                   placeholder="Dec 11, 2025">
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition-colors">
                                <?php echo $editId ? 'Update' : 'Add'; ?> Update
                            </button>
                            <?php if ($editId): ?>
                                <a href="/admin/system-updates.php" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition-colors">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Updates List -->
                <div>
                    <h2 class="text-xl font-bold mb-4">Recent Updates</h2>
                    <?php if (empty($updates)): ?>
                        <p class="text-gray-400">No updates yet. Create one to get started.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($updates as $update): ?>
                                <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($update['title']); ?></h3>
                                        <p class="text-gray-400 text-sm mt-1"><?php echo htmlspecialchars($update['description']); ?></p>
                                        <p class="text-gray-500 text-xs mt-2">
                                            Date: <?php echo htmlspecialchars($update['display_date']); ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="?edit=<?php echo $update['id']; ?>" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-sm transition-colors">
                                            Edit
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this update?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $update['id']; ?>">
                                            <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-sm transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
