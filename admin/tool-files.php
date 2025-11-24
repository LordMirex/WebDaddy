<?php
$pageTitle = 'Tool Files Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/tool_files.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get all tools
$tools = $db->query("SELECT id, name FROM tools WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedToolId = $_GET['tool_id'] ?? null;
$toolFiles = [];
$selectedTool = null;

if ($selectedToolId) {
    $toolFiles = getToolFiles($selectedToolId);
    $stmt = $db->prepare("SELECT * FROM tools WHERE id = ? AND active = 1");
    $stmt->execute([$selectedToolId]);
    $selectedTool = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    try {
        // CSRF protection
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Security validation failed');
        }
        
        $toolId = $_POST['tool_id'];
        $fileType = $_POST['file_type'];
        $description = $_POST['description'] ?? '';
        
        // Validate tool exists and is active
        $stmt = $db->prepare("SELECT id FROM tools WHERE id = ? AND active = 1");
        $stmt->execute([$toolId]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid tool selected');
        }
        
        if (isset($_FILES['tool_file']) && $_FILES['tool_file']['error'] === UPLOAD_ERR_OK) {
            uploadToolFile($toolId, $_FILES['tool_file'], $fileType, $description);
            $success = 'File uploaded successfully!';
            header('Location: /admin/tool-files.php?tool_id=' . $toolId . '&success=1');
            exit;
        } else {
            throw new Exception('File upload failed');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    try {
        // CSRF protection
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Security validation failed');
        }
        
        $fileId = $_POST['file_id'];
        $toolId = $_POST['tool_id'];
        
        // Get file info and verify ownership
        $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ? AND tool_id = ?");
        $stmt->execute([$fileId, $toolId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            throw new Exception('File not found or does not belong to this tool');
        }
        
        // Delete physical file
        $filePath = __DIR__ . '/../' . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM tool_files WHERE id = ? AND tool_id = ?");
        $stmt->execute([$fileId, $toolId]);
        
        $success = 'File deleted successfully!';
        
        header('Location: /admin/tool-files.php?tool_id=' . $toolId . '&success=1');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-white flex items-center gap-3">
        <i class="bi bi-file-earmark-arrow-up text-primary-400"></i> Tool Files Management
    </h1>
    <p class="text-gray-300 mt-2">Upload and manage downloadable files for your tools</p>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="mb-6 p-4 bg-green-900/30 border-l-4 border-green-400 text-green-200 rounded-lg flex items-start gap-3">
    <i class="bi bi-check-circle text-xl mt-0.5"></i>
    <div>
        <strong>Success!</strong>
        <p class="text-sm mt-1">Operation completed successfully.</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-6 p-4 bg-red-900/30 border-l-4 border-red-400 text-red-200 rounded-lg flex items-start gap-3">
    <i class="bi bi-exclamation-circle text-xl mt-0.5"></i>
    <div>
        <strong>Error!</strong>
        <p class="text-sm mt-1"><?php echo htmlspecialchars($error); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Tool Selection Card -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 mb-8">
    <div class="px-6 py-4 border-b border-gray-700">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-tools text-primary-400"></i> Select Tool
        </h2>
    </div>
    <div class="p-6">
        <form method="GET" class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-200 mb-2">Tool Name</label>
                <select name="tool_id" onchange="this.form.submit()" class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all cursor-pointer">
                    <option value="">-- Select a Tool --</option>
                    <?php foreach ($tools as $tool): ?>
                    <option value="<?php echo $tool['id']; ?>" 
                            <?php echo $selectedToolId == $tool['id'] ? 'selected' : ''; ?>>
                        🔧 <?php echo htmlspecialchars($tool['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedToolId && $selectedTool): ?>

<!-- Tool Info -->
<div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl shadow-lg p-6 mb-8 border border-primary-500/20">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-white">🔧 <?php echo htmlspecialchars($selectedTool['name']); ?></h3>
            <p class="text-primary-100 mt-1">Total files: <span class="font-bold"><?php echo count($toolFiles); ?></span></p>
        </div>
        <i class="bi bi-file-earmark-check text-4xl text-primary-200 opacity-30"></i>
    </div>
</div>

<!-- Upload Form -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 mb-8">
    <div class="px-6 py-4 border-b border-gray-700">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-cloud-upload text-primary-400"></i> Upload New File
        </h2>
    </div>
    <div class="p-6">
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
            
            <!-- File Input -->
            <div>
                <label class="block text-sm font-semibold text-gray-200 mb-2">📎 Select File</label>
                <div class="relative">
                    <input type="file" name="tool_file" 
                           class="w-full px-4 py-3 bg-gray-700 border-2 border-dashed border-gray-600 rounded-lg text-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all cursor-pointer file:cursor-pointer file:bg-primary-600 file:border-0 file:rounded file:px-3 file:py-1 file:text-white file:text-sm file:font-medium hover:border-primary-500"
                           required>
                </div>
                <p class="text-xs text-gray-400 mt-2">💡 Recommended: ZIP files for complete tool packages. Max size: 100MB</p>
            </div>
            
            <!-- File Type -->
            <div>
                <label class="block text-sm font-semibold text-gray-200 mb-2">📁 File Type</label>
                <select name="file_type" class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" required>
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
                <label class="block text-sm font-semibold text-gray-200 mb-2">💬 Description (Optional)</label>
                <textarea name="description" 
                          class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                          rows="3"
                          placeholder="e.g., Main tool files, Updated version 2.0, Installation guide..."></textarea>
            </div>
            
            <!-- Submit Button -->
            <div>
                <button type="submit" name="upload_file" class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                    <i class="bi bi-cloud-upload"></i> Upload File
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Existing Files -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
    <div class="px-6 py-4 border-b border-gray-700">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-folder text-primary-400"></i> Uploaded Files (<?php echo count($toolFiles); ?>)
        </h2>
    </div>
    <div class="p-6">
        <?php if (empty($toolFiles)): ?>
        <div class="text-center py-12">
            <i class="bi bi-inbox text-4xl text-gray-600 mb-3 block"></i>
            <p class="text-gray-300 font-medium">No files uploaded yet</p>
            <p class="text-gray-400 text-sm mt-1">Upload files above to make them available for automatic delivery when customers purchase this tool.</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($toolFiles as $file): 
                $fileTypeIcons = [
                    'zip_archive' => '📦',
                    'attachment' => '📎',
                    'text_instructions' => '📝',
                    'code' => '💻',
                    'access_key' => '🔑',
                    'image' => '🖼️',
                    'video' => '🎬',
                    'link' => '🔗'
                ];
                $icon = $fileTypeIcons[$file['file_type']] ?? '📄';
            ?>
            <div class="bg-gray-750 border border-gray-700 rounded-lg p-4 hover:bg-gray-700 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-2xl"><?php echo $icon; ?></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-white truncate"><?php echo htmlspecialchars($file['file_name']); ?></p>
                                <?php if ($file['file_description']): ?>
                                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($file['file_description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-medium bg-blue-900/40 text-blue-300 border border-blue-700">
                                <?php echo ucfirst(str_replace('_', ' ', $file['file_type'])); ?>
                            </span>
                            <span class="text-gray-400">📊 <?php echo number_format($file['file_size'] / 1024, 1); ?> KB</span>
                            <span class="text-gray-400">📥 <?php echo $file['download_count']; ?> downloads</span>
                            <span class="text-gray-500">📅 <?php echo date('M d, Y', strtotime($file['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <a href="/<?php echo htmlspecialchars($file['file_path']); ?>" 
                           class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                           target="_blank" download>
                            <i class="bi bi-download"></i>
                            <span>Download</span>
                        </a>
                        <form method="POST" style="display:inline;" 
                              onsubmit="return confirm('⚠️ Delete this file? This cannot be undone.');">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                            <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
                            <button type="submit" name="delete_file" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                                <i class="bi bi-trash"></i>
                                <span>Delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- Empty State -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-12 text-center">
    <i class="bi bi-inbox text-6xl text-gray-600 block mb-4"></i>
    <h3 class="text-xl font-bold text-gray-200 mb-2">No Tool Selected</h3>
    <p class="text-gray-400">Select a tool from above to view and manage its files.</p>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
