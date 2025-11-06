<?php
$pageTitle = 'Announcement Management';

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
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_announcement') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? 'info');
        $affiliateId = isset($_POST['affiliate_id']) && $_POST['affiliate_id'] !== '' ? intval($_POST['affiliate_id']) : null;
        $durationType = sanitizeInput($_POST['duration_type'] ?? 'permanent');
        $durationHours = intval($_POST['duration_hours'] ?? 0);
        $durationMinutes = intval($_POST['duration_minutes'] ?? 0);
        
        if (empty($title) || empty($message)) {
            $errorMessage = 'Title and message are required.';
        } else {
            $expiresAt = null;
            if ($durationType === 'timed' && ($durationHours > 0 || $durationMinutes > 0)) {
                $totalMinutes = ($durationHours * 60) + $durationMinutes;
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$totalMinutes} minutes"));
            }
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO announcements (title, message, type, is_active, created_by, affiliate_id, expires_at)
                    VALUES (?, ?, ?, 1, ?, ?, ?)
                ");
                $stmt->execute([$title, $message, $type, getAdminId(), $affiliateId, $expiresAt]);
                
                $target = $affiliateId ? "specific affiliate" : "all affiliates";
                $expiryInfo = $expiresAt ? " (expires: $expiresAt)" : " (permanent)";
                $successMessage = "Announcement created successfully for {$target}{$expiryInfo}!";
                logActivity('announcement_created', "Created: $title", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'update_announcement') {
        $id = intval($_POST['announcement_id'] ?? 0);
        $title = sanitizeInput($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? 'info');
        $durationType = sanitizeInput($_POST['duration_type'] ?? 'permanent');
        $durationHours = intval($_POST['duration_hours'] ?? 0);
        $durationMinutes = intval($_POST['duration_minutes'] ?? 0);
        
        if ($id && !empty($title) && !empty($message)) {
            $expiresAt = null;
            if ($durationType === 'timed' && ($durationHours > 0 || $durationMinutes > 0)) {
                $totalMinutes = ($durationHours * 60) + $durationMinutes;
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$totalMinutes} minutes"));
            }
            
            try {
                $stmt = $db->prepare("
                    UPDATE announcements 
                    SET title = ?, message = ?, type = ?, expires_at = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$title, $message, $type, $expiresAt, $id]);
                $successMessage = "Announcement updated successfully!";
                logActivity('announcement_updated', "Updated: $title", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Update failed: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'toggle_announcement') {
        $id = intval($_POST['announcement_id'] ?? 0);
        if ($id) {
            try {
                $stmt = $db->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = "Announcement status toggled!";
                logActivity('announcement_toggled', "Toggled announcement #$id", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Toggle failed: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_announcement') {
        $id = intval($_POST['announcement_id'] ?? 0);
        if ($id) {
            try {
                $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = "Announcement deleted successfully!";
                logActivity('announcement_deleted', "Deleted announcement #$id", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'extend_announcement') {
        $id = intval($_POST['announcement_id'] ?? 0);
        $extendHours = intval($_POST['extend_hours'] ?? 0);
        $extendMinutes = intval($_POST['extend_minutes'] ?? 0);
        
        if ($id && ($extendHours > 0 || $extendMinutes > 0)) {
            try {
                $stmt = $db->prepare("SELECT expires_at FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetchColumn();
                
                $totalMinutes = ($extendHours * 60) + $extendMinutes;
                $baseTime = $current && strtotime($current) > time() ? $current : 'now';
                $newExpiresAt = date('Y-m-d H:i:s', strtotime("$baseTime +{$totalMinutes} minutes"));
                
                $stmt = $db->prepare("UPDATE announcements SET expires_at = ? WHERE id = ?");
                $stmt->execute([$newExpiresAt, $id]);
                
                $successMessage = "Announcement extended to $newExpiresAt!";
                logActivity('announcement_extended', "Extended announcement #$id", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Extension failed: ' . $e->getMessage();
            }
        }
    }
}

$affiliates = $db->query("
    SELECT a.id, u.name, u.email
    FROM affiliates a
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'active'
    ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$announcements = $db->query("
    SELECT 
        a.*,
        u.name as affiliate_name,
        creator.name as creator_name,
        CASE 
            WHEN a.expires_at IS NULL THEN 'permanent'
            WHEN datetime(a.expires_at) > datetime('now') THEN 'active'
            ELSE 'expired'
        END as status,
        CASE
            WHEN a.expires_at IS NOT NULL AND datetime(a.expires_at) > datetime('now') THEN 
                CAST((julianday(a.expires_at) - julianday('now')) * 24 * 60 AS INTEGER)
            ELSE NULL
        END as minutes_remaining
    FROM announcements a
    LEFT JOIN affiliates af ON a.affiliate_id = af.id
    LEFT JOIN users u ON af.user_id = u.id
    LEFT JOIN users creator ON a.created_by = creator.id
    ORDER BY 
        CASE 
            WHEN a.expires_at IS NULL THEN 0
            WHEN datetime(a.expires_at) > datetime('now') THEN 1
            ELSE 2
        END,
        a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-megaphone text-primary-600"></i> Announcement Management
    </h1>
    <p class="text-gray-600 mt-2">Create, manage, and schedule announcements for affiliates</p>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg flex items-center gap-3" x-data="{ show: true }" x-show="show">
    <i class="bi bi-check-circle text-xl"></i>
    <span class="flex-1"><?php echo htmlspecialchars($successMessage); ?></span>
    <button @click="show = false" class="text-green-600 hover:text-green-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg flex items-center gap-3" x-data="{ show: true }" x-show="show">
    <i class="bi bi-exclamation-triangle text-xl"></i>
    <span class="flex-1"><?php echo htmlspecialchars($errorMessage); ?></span>
    <button @click="show = false" class="text-red-600 hover:text-red-800">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6" x-data="{ 
    showForm: false, 
    editMode: false,
    durationType: 'permanent',
    durationHours: 0,
    durationMinutes: 30,
    quillEditor: null
}">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-plus-circle text-primary-600"></i> Create New Announcement
        </h5>
        <button @click="showForm = !showForm; if (!showForm) editMode = false" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            <span x-text="showForm ? 'Cancel' : 'New Announcement'"></span>
        </button>
    </div>
    
    <div x-show="showForm" x-collapse>
        <form method="POST" class="p-6 space-y-4" @submit="if (!quillEditor) return false">
            <input type="hidden" name="action" value="create_announcement">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
                    <input type="text" name="title" required maxlength="200"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Important Update">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="info">Info (Blue)</option>
                        <option value="success">Success (Green)</option>
                        <option value="warning">Warning (Yellow)</option>
                        <option value="danger">Danger (Red)</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience</label>
                <select name="affiliate_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">All Affiliates (Global)</option>
                    <?php foreach ($affiliates as $affiliate): ?>
                        <option value="<?php echo $affiliate['id']; ?>">
                            <?php echo htmlspecialchars($affiliate['name']) . ' (' . htmlspecialchars($affiliate['email']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Message *</label>
                <div id="editor" style="height: 200px;"></div>
                <textarea name="message" id="message-content" class="hidden" required></textarea>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <label class="block text-sm font-semibold text-gray-700 mb-3">Duration</label>
                
                <div class="flex items-center gap-4 mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="duration_type" value="permanent" x-model="durationType" class="text-primary-600">
                        <span>Permanent (No Expiry)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="duration_type" value="timed" x-model="durationType" class="text-primary-600">
                        <span>Timed (Auto-Remove)</span>
                    </label>
                </div>
                
                <div x-show="durationType === 'timed'" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Hours</label>
                        <input type="number" name="duration_hours" min="0" max="720" x-model="durationHours"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Minutes</label>
                        <input type="number" name="duration_minutes" min="0" max="59" x-model="durationMinutes"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
                
                <div x-show="durationType === 'timed'" class="mt-3 text-sm text-gray-600">
                    <i class="bi bi-info-circle"></i>
                    <span>Announcement will automatically disappear after <strong x-text="durationHours"></strong> hours and <strong x-text="durationMinutes"></strong> minutes</span>
                </div>
            </div>
            
            <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class="bi bi-send-fill"></i> Post Announcement
            </button>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-list-task text-primary-600"></i> All Announcements (<?php echo count($announcements); ?>)
        </h5>
    </div>
    
    <div class="p-6">
        <?php if (empty($announcements)): ?>
            <div class="text-center py-12">
                <i class="bi bi-megaphone text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No announcements yet. Create your first one above!</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($announcements as $announcement): ?>
                    <?php
                    $typeColors = [
                        'info' => 'blue',
                        'success' => 'green',
                        'warning' => 'yellow',
                        'danger' => 'red'
                    ];
                    $color = $typeColors[$announcement['type']] ?? 'gray';
                    $statusColor = $announcement['status'] === 'active' ? 'green' : ($announcement['status'] === 'expired' ? 'red' : 'gray');
                    ?>
                    <div class="border border-<?php echo $color; ?>-200 rounded-lg p-4 bg-<?php echo $color; ?>-50" x-data="{ showEdit: false, showExtend: false }">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h6 class="font-bold text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <span class="px-2 py-1 bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800 rounded text-xs font-semibold uppercase">
                                        <?php echo $announcement['status']; ?>
                                    </span>
                                    <span class="px-2 py-1 bg-<?php echo $color; ?>-200 text-<?php echo $color; ?>-900 rounded text-xs font-semibold uppercase">
                                        <?php echo $announcement['type']; ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-700 prose prose-sm max-w-none">
                                    <?php echo $announcement['message']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 mb-3">
                            <span><i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($announcement['creator_name'] ?? 'Unknown'); ?></span>
                            <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?></span>
                            <span><i class="bi bi-bullseye"></i> <?php echo $announcement['affiliate_name'] ? htmlspecialchars($announcement['affiliate_name']) : 'All Affiliates'; ?></span>
                            <?php if ($announcement['expires_at']): ?>
                                <span><i class="bi bi-clock"></i> Expires: <?php echo date('M d, Y H:i', strtotime($announcement['expires_at'])); ?></span>
                                <?php if ($announcement['minutes_remaining'] !== null && $announcement['minutes_remaining'] > 0): ?>
                                    <span class="text-<?php echo $statusColor; ?>-600 font-semibold">
                                        <i class="bi bi-hourglass-split"></i> 
                                        <?php 
                                        $hours = floor($announcement['minutes_remaining'] / 60);
                                        $mins = $announcement['minutes_remaining'] % 60;
                                        echo $hours > 0 ? "{$hours}h " : "";
                                        echo "{$mins}m remaining";
                                        ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-500"><i class="bi bi-infinity"></i> Permanent</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-wrap gap-2">
                            <button @click="showExtend = !showExtend" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs font-medium transition-colors">
                                <i class="bi bi-clock-history"></i> Extend
                            </button>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_announcement">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium transition-colors">
                                    <i class="bi bi-toggle-<?php echo $announcement['is_active'] ? 'on' : 'off'; ?>"></i> 
                                    <?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this announcement?');">
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition-colors">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                        
                        <div x-show="showExtend" x-collapse class="mt-4 pt-4 border-t border-<?php echo $color; ?>-200">
                            <form method="POST" class="flex items-end gap-3">
                                <input type="hidden" name="action" value="extend_announcement">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Add Hours</label>
                                    <input type="number" name="extend_hours" min="0" max="720" value="1"
                                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Add Minutes</label>
                                    <input type="number" name="extend_minutes" min="0" max="59" value="0"
                                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">
                                    <i class="bi bi-plus-circle"></i> Extend Now
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                ['link'],
                ['clean']
            ]
        },
        placeholder: 'Write your announcement message here...'
    });
    
    const form = quill.root.closest('form');
    form.addEventListener('submit', function() {
        document.getElementById('message-content').value = quill.root.innerHTML;
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
