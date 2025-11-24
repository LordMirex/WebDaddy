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

if ($selectedToolId) {
    $toolFiles = getToolFiles($selectedToolId);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    try {
        $toolId = $_POST['tool_id'];
        $fileType = $_POST['file_type'];
        $description = $_POST['description'] ?? '';
        
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
        $fileId = $_POST['file_id'];
        $toolId = $_POST['tool_id'];
        
        // Get file info
        $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // Delete physical file
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM tool_files WHERE id = ?");
            $stmt->execute([$fileId]);
            
            $success = 'File deleted successfully!';
        }
        
        header('Location: /admin/tool-files.php?tool_id=' . $toolId . '&success=1');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">📁 Tool Files Management</h1>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Operation completed successfully!</div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Tool Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Select Tool</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-6">
                        <select name="tool_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Select a Tool --</option>
                            <?php foreach ($tools as $tool): ?>
                            <option value="<?php echo $tool['id']; ?>" 
                                    <?php echo $selectedToolId == $tool['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tool['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($selectedToolId): ?>
    
    <!-- Upload New File -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Upload New File</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Select File</label>
                    <input type="file" name="tool_file" class="form-control" required>
                    <small class="text-muted">All file types accepted. Recommended: ZIP files for complete tool packages.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">File Type</label>
                    <select name="file_type" class="form-select" required>
                        <option value="zip_archive">ZIP Archive</option>
                        <option value="attachment">General Attachment</option>
                        <option value="text_instructions">Instructions/Documentation</option>
                        <option value="code">Code/Script</option>
                        <option value="access_key">Access Key/Credentials</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                        <option value="link">External Link/URL</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="Optional description or instructions for this file"></textarea>
                </div>
                
                <button type="submit" name="upload_file" class="btn btn-primary">
                    📤 Upload File
                </button>
            </form>
        </div>
    </div>
    
    <!-- Existing Files -->
    <div class="card">
        <div class="card-header">
            <h5>Existing Files (<?php echo count($toolFiles); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($toolFiles)): ?>
            <div class="alert alert-info">
                <strong>No files uploaded yet.</strong><br>
                Upload files above to make them available for automatic delivery when customers purchase this tool.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Downloads</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($toolFiles as $file): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($file['file_name']); ?></strong>
                                <?php if ($file['file_description']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($file['file_description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($file['file_type']); ?></span></td>
                            <td><?php echo number_format($file['file_size'] / 1024, 2); ?> KB</td>
                            <td><?php echo $file['download_count']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($file['created_at'])); ?></td>
                            <td>
                                <a href="/<?php echo htmlspecialchars($file['file_path']); ?>" 
                                   class="btn btn-sm btn-primary" target="_blank" download>
                                    📥 Download
                                </a>
                                <form method="POST" style="display:inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this file? This cannot be undone.');">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
                                    <button type="submit" name="delete_file" class="btn btn-sm btn-danger">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
