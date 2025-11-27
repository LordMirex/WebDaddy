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
        
        if (!isset($_FILES['tool_file'])) {
            throw new Exception('No file provided');
        }
        
        if ($_FILES['tool_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMap = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File upload incomplete',
                UPLOAD_ERR_NO_FILE => 'No file selected',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION => 'Extension blocked'
            ];
            throw new Exception($errorMap[$_FILES['tool_file']['error']] ?? 'Unknown upload error');
        }
        
        $fileId = uploadToolFile($toolId, $_FILES['tool_file'], $fileType, $description);
        error_log("✅ File uploaded successfully: Tool ID=$toolId, File ID=$fileId");
        $success = 'File uploaded successfully!';
        header('Location: /admin/tool-files.php?tool_id=' . $toolId . '&success=1');
        exit;
    } catch (Exception $e) {
        error_log("❌ Upload error: " . $e->getMessage());
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
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-file-earmark-arrow-up text-primary-600"></i> Tool Files Management
    </h1>
    <p class="text-gray-600 mt-2">Upload and manage downloadable files for your tools</p>
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
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-tools text-primary-600"></i> Select Tool
        </h2>
    </div>
    <div class="p-6">
        <form method="GET" class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Tool Name</label>
                <select name="tool_id" onchange="this.form.submit()" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all cursor-pointer">
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
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 hover:shadow-lg transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Files</h6>
            <i class="bi bi-file-earmark text-2xl text-blue-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo $totalFiles; ?></div>
        <p class="text-xs sm:text-sm text-gray-500 mt-1">Uploaded for this tool</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 hover:shadow-lg transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Downloads</h6>
            <i class="bi bi-download text-2xl text-purple-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo number_format($totalDownloads); ?></div>
        <p class="text-xs sm:text-sm text-gray-500 mt-1">By customers</p>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 sm:p-6 hover:shadow-lg transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-xs sm:text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Size</h6>
            <i class="bi bi-hdd text-2xl text-green-600 flex-shrink-0"></i>
        </div>
        <div class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo number_format($totalSize / (1024 * 1024), 1); ?> MB</div>
        <p class="text-xs sm:text-sm text-gray-500 mt-1">Combined file size</p>
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
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-cloud-upload text-primary-600"></i> Upload New File
        </h2>
    </div>
    <div class="p-6">
        <form id="uploadForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
            
            <!-- File Input -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">📎 Select File</label>
                <div class="relative">
                    <input type="file" id="toolFile" name="tool_file" 
                           class="w-full px-4 py-3 bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all cursor-pointer file:cursor-pointer file:bg-primary-600 file:border-0 file:rounded file:px-3 file:py-1 file:text-white file:text-sm file:font-medium hover:border-primary-500"
                           required>
                </div>
                <p class="text-xs text-gray-500 mt-2">💡 Recommended: ZIP files for complete tool packages. Max size: 2GB (intelligent chunked upload - 20MB chunks, 3 concurrent)</p>
            </div>
            
            <!-- File Type -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">📁 File Type</label>
                <select id="fileType" name="file_type" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" required>
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
                <label class="block text-sm font-semibold text-gray-700 mb-2">💬 Description (Optional)</label>
                <textarea id="description" name="description" 
                          class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all resize-none"
                          rows="3"
                          placeholder="e.g., Main tool files, Updated version 2.0, Installation guide..."></textarea>
            </div>
            
            <!-- Progress Bar (hidden initially) -->
            <div id="uploadProgress" class="hidden space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-700">Uploading: <span id="fileName">...</span></span>
                    <span id="progressPercent" class="text-primary-600 font-bold">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="bg-gradient-to-r from-primary-600 to-primary-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
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
const CHUNK_SIZE = 20 * 1024 * 1024; // 20MB chunks for optimal speed/reliability balance
const MAX_CONCURRENT = 3; // 3 concurrent uploads = best throughput without server stress
const UPLOAD_ID = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

class UploadQueue {
    constructor(maxConcurrent) {
        this.maxConcurrent = maxConcurrent;
        this.running = 0;
        this.queue = [];
        this.results = [];
    }
    
    async add(task) {
        return new Promise((resolve) => {
            this.queue.push({ task, resolve });
            this.process();
        });
    }
    
    async process() {
        while (this.running < this.maxConcurrent && this.queue.length > 0) {
            this.running++;
            const { task, resolve } = this.queue.shift();
            
            try {
                const result = await task();
                this.results.push(result);
                resolve(result);
            } catch (err) {
                resolve({ error: err.message });
            }
            
            this.running--;
            this.process();
        }
    }
}

async function uploadFileInChunks(file, toolId, fileType, description) {
    const btn = document.getElementById('uploadBtn');
    const progressDiv = document.getElementById('uploadProgress');
    const statusDiv = document.getElementById('uploadStatus');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    
    btn.disabled = true;
    progressDiv.classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const queue = new UploadQueue(MAX_CONCURRENT);
    let uploadedChunks = 0;
    
    // Queue all chunks for upload
    for (let i = 0; i < totalChunks; i++) {
        const chunkIndex = i;
        queue.add(async () => {
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);
            
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('upload_id', UPLOAD_ID);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('tool_id', toolId);
                formData.append('file_name', file.name);
                
                const xhr = new XMLHttpRequest();
                
                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            uploadedChunks++;
                            
                            const percent = Math.round((uploadedChunks / totalChunks) * 100);
                            progressBar.style.width = percent + '%';
                            progressPercent.textContent = percent + '%';
                            
                            if (response.completed) {
                                statusDiv.innerHTML = '<div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-lg">✅ File uploaded successfully! Reloading...</div>';
                                progressBar.style.width = '100%';
                                progressPercent.textContent = '100%';
                                setTimeout(() => window.location.reload(), 1500);
                            }
                            
                            resolve(response);
                        } catch (e) {
                            reject(new Error('Invalid response'));
                        }
                    } else {
                        reject(new Error('Chunk failed: ' + xhr.status));
                    }
                });
                
                xhr.addEventListener('error', () => {
                    reject(new Error('Network error'));
                });
                
                xhr.addEventListener('abort', () => {
                    reject(new Error('Upload cancelled'));
                });
                
                xhr.open('POST', '/api/upload-chunk.php');
                xhr.send(formData);
            });
        });
    }
}

document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const file = document.getElementById('toolFile').files[0];
    if (!file) return;
    
    document.getElementById('uploadStatus').innerHTML = '';
    
    const toolId = document.querySelector('input[name="tool_id"]').value;
    const fileType = document.getElementById('fileType').value;
    const description = document.getElementById('description').value;
    
    try {
        await uploadFileInChunks(file, toolId, fileType, description);
    } catch (error) {
        const statusDiv = document.getElementById('uploadStatus');
        statusDiv.innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ Upload failed: ' + error.message + '</div>';
        document.getElementById('uploadBtn').disabled = false;
        document.getElementById('uploadProgress').classList.add('hidden');
    }
});

document.getElementById('toolFile').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const sizeInGB = file.size / (1024 * 1024 * 1024);
        if (sizeInGB > 2) {
            document.getElementById('uploadStatus').innerHTML = '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">❌ File too large (max 2GB). Your file: ' + sizeInGB.toFixed(2) + 'GB</div>';
            e.target.value = '';
        } else {
            const chunks = Math.ceil(file.size / CHUNK_SIZE);
            document.getElementById('uploadStatus').innerHTML = '<div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-lg">💡 File will upload in ' + chunks + ' chunks (20MB each) with 3 concurrent streams</div>';
        }
    }
});
</script>

<!-- Existing Files Table -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-folder text-primary-600"></i> Uploaded Files (<?php echo count($toolFiles); ?>)
        </h2>
    </div>
    <div class="p-6">
        <?php if (empty($toolFiles)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
            <i class="bi bi-info-circle text-xl"></i>
            <span>No files uploaded yet. Upload files above to make them available for automatic delivery when customers purchase this tool.</span>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden sm:table-cell">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">File Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden md:table-cell">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden lg:table-cell">Size</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden xl:table-cell">Downloads</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm hidden lg:table-cell">Uploaded</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm text-center">Action</th>
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
                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4 text-sm font-medium text-gray-900 hidden sm:table-cell">#<?php echo $file['id']; ?></td>
                        <td class="py-3 px-4 text-sm text-gray-700">
                            <div class="flex items-center gap-2">
                                <span class="text-lg"><?php echo $icon; ?></span>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($file['file_name']); ?></p>
                                    <?php if ($file['file_description']): ?>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($file['file_description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm hidden md:table-cell">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-300">
                                <?php echo ucfirst(str_replace('_', ' ', $file['file_type'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600 hidden lg:table-cell">
                            <?php echo number_format($file['file_size'] / 1024, 1); ?> KB
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600 hidden xl:table-cell">
                            <div class="flex items-center gap-1">
                                <i class="bi bi-download text-primary-600"></i>
                                <?php echo $file['download_count']; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500 hidden lg:table-cell">
                            <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                            <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($file['created_at'])); ?></div>
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
<div class="bg-white rounded-xl shadow-md border border-gray-100 p-12 text-center">
    <i class="bi bi-inbox text-6xl text-gray-300 block mb-4"></i>
    <h3 class="text-xl font-bold text-gray-900 mb-2">No Tool Selected</h3>
    <p class="text-gray-600">Select a tool from above to view and manage its files.</p>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
