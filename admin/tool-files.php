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

// Calculate stats
$totalFiles = count($toolFiles);
$totalDownloads = 0;
$totalSize = 0;
foreach ($toolFiles as $file) {
    $totalDownloads += $file['download_count'];
    $totalSize += $file['file_size'];
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
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
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

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
    <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg p-6 border border-blue-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-blue-100 uppercase tracking-wide">Total Files</h6>
            <i class="bi bi-file-earmark text-2xl text-blue-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo $totalFiles; ?></div>
        <p class="text-sm text-blue-100 mt-1">Uploaded for this tool</p>
    </div>
    
    <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl shadow-lg p-6 border border-purple-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-purple-100 uppercase tracking-wide">Total Downloads</h6>
            <i class="bi bi-download text-2xl text-purple-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalDownloads); ?></div>
        <p class="text-sm text-purple-100 mt-1">By customers</p>
    </div>
    
    <div class="bg-gradient-to-br from-orange-600 to-orange-700 rounded-xl shadow-lg p-6 border border-orange-500/20">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-orange-100 uppercase tracking-wide">Total Size</h6>
            <i class="bi bi-hdd text-2xl text-orange-200"></i>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalSize / (1024 * 1024), 1); ?> MB</div>
        <p class="text-sm text-orange-100 mt-1">Combined file size</p>
    </div>
</div>

<!-- Tool Info Banner -->
<div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl shadow-lg p-6 mb-8 border border-primary-500/20">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-white">🔧 <?php echo htmlspecialchars($selectedTool['name']); ?></h3>
            <p class="text-primary-100 mt-1">Managing downloadable files for automatic delivery to customers</p>
        </div>
        <i class="bi bi-file-earmark-check text-4xl text-primary-200 opacity-30"></i>
    </div>
</div>

<!-- Upload Form -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 mb-8">
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-cloud-upload text-primary-400"></i> Upload New File
        </h2>
    </div>
    <div class="p-6">
        <form id="uploadForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
            
            <!-- File Input -->
            <div>
                <label class="block text-sm font-semibold text-gray-200 mb-2">📎 Select File</label>
                <div class="relative">
                    <input type="file" id="toolFile" name="tool_file" 
                           class="w-full px-4 py-3 bg-gray-700 border-2 border-dashed border-gray-600 rounded-lg text-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all cursor-pointer file:cursor-pointer file:bg-primary-600 file:border-0 file:rounded file:px-3 file:py-1 file:text-white file:text-sm file:font-medium hover:border-primary-500"
                           required>
                </div>
                <p class="text-xs text-gray-400 mt-2">💡 Recommended: ZIP files for complete tool packages. Max size: 100MB</p>
            </div>
            
            <!-- File Type -->
            <div>
                <label class="block text-sm font-semibold text-gray-200 mb-2">📁 File Type</label>
                <select id="fileType" name="file_type" class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" required>
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
                <textarea id="description" name="description" 
                          class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                          rows="3"
                          placeholder="e.g., Main tool files, Updated version 2.0, Installation guide..."></textarea>
            </div>
            
            <!-- Progress Bar (hidden initially) -->
            <div id="uploadProgress" class="hidden space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-300">Uploading: <span id="fileName">...</span></span>
                    <span id="progressPercent" class="text-primary-400 font-bold">0%</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="bg-gradient-to-r from-primary-500 to-primary-400 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
            
            <!-- Status Message -->
            <div id="uploadStatus"></div>
            
            <!-- Submit Button -->
            <div>
                <button type="submit" id="uploadBtn" class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors shadow-lg hover:shadow-xl flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="bi bi-cloud-upload"></i> <span id="uploadBtnText">Upload File</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const file = document.getElementById('toolFile').files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('tool_file', file);
    formData.append('tool_id', document.querySelector('input[name="tool_id"]').value);
    formData.append('file_type', document.getElementById('fileType').value);
    formData.append('description', document.getElementById('description').value);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    formData.append('upload_file', '1');
    
    const btn = document.getElementById('uploadBtn');
    const progressDiv = document.getElementById('uploadProgress');
    const statusDiv = document.getElementById('uploadStatus');
    
    btn.disabled = true;
    statusDiv.innerHTML = '';
    progressDiv.classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    
    try {
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        });
        
        if (response.status === 200 && response.url.includes('success=1')) {
            statusDiv.innerHTML = '<div class="p-4 bg-green-900/30 border-l-4 border-green-400 text-green-200 rounded-lg">✅ File uploaded successfully!</div>';
            setTimeout(() => window.location.reload(), 1500);
        } else if (response.ok) {
            const text = await response.text();
            if (text.includes('success')) {
                statusDiv.innerHTML = '<div class="p-4 bg-green-900/30 border-l-4 border-green-400 text-green-200 rounded-lg">✅ File uploaded successfully!</div>';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error('Upload failed');
            }
        } else {
            throw new Error('Upload failed with status ' + response.status);
        }
    } catch (err) {
        statusDiv.innerHTML = '<div class="p-4 bg-red-900/30 border-l-4 border-red-400 text-red-200 rounded-lg">❌ ' + err.message + '</div>';
        btn.disabled = false;
        progressDiv.classList.add('hidden');
    }
});

document.getElementById('toolFile').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 100 * 1024 * 1024) {
            document.getElementById('uploadStatus').innerHTML = '<div class="p-4 bg-red-900/30 border-l-4 border-red-400 text-red-200 rounded-lg">❌ File is too large (max 100MB)</div>';
            e.target.value = '';
        }
    }
});
</script>

<!-- Existing Files Table -->
<div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700">
    <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="bi bi-folder text-primary-400"></i> Uploaded Files (<?php echo count($toolFiles); ?>)
        </h2>
    </div>
    <div class="p-6">
        <?php if (empty($toolFiles)): ?>
        <div class="bg-blue-900/30 border-l-4 border-blue-400 text-blue-200 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No files uploaded yet. Upload files above to make them available for automatic delivery when customers purchase this tool.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700 bg-gray-750">
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden sm:table-cell">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm">File Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden md:table-cell">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden lg:table-cell">Size</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden xl:table-cell">Downloads</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm hidden lg:table-cell">Uploaded</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-200 text-sm text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
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
                    <tr class="border-b border-gray-700 hover:bg-gray-750/50 transition-colors">
                        <td class="py-3 px-4 text-sm font-medium text-gray-100 hidden sm:table-cell">#<?php echo $file['id']; ?></td>
                        <td class="py-3 px-4 text-sm text-gray-200">
                            <div class="flex items-center gap-2">
                                <span class="text-lg"><?php echo $icon; ?></span>
                                <div>
                                    <p class="font-medium text-gray-100"><?php echo htmlspecialchars($file['file_name']); ?></p>
                                    <?php if ($file['file_description']): ?>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($file['file_description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm hidden md:table-cell">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-900/40 text-blue-300 border border-blue-700">
                                <?php echo ucfirst(str_replace('_', ' ', $file['file_type'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-300 hidden lg:table-cell">
                            <?php echo number_format($file['file_size'] / 1024, 1); ?> KB
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-300 hidden xl:table-cell">
                            <div class="flex items-center gap-1">
                                <i class="bi bi-download text-primary-400"></i>
                                <?php echo $file['download_count']; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-400 hidden lg:table-cell">
                            <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($file['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="/<?php echo htmlspecialchars($file['file_path']); ?>" 
                                   class="text-primary-400 hover:text-primary-300 transition-colors" 
                                   target="_blank" 
                                   download
                                   title="Download file">
                                    <i class="bi bi-download text-lg"></i>
                                </a>
                                <form method="POST" style="display:inline;" 
                                      onsubmit="return confirm('⚠️ Delete this file permanently? This cannot be undone.');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
                                    <button type="submit" name="delete_file" class="text-red-400 hover:text-red-300 transition-colors" title="Delete file">
                                        <i class="bi bi-trash text-lg"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
