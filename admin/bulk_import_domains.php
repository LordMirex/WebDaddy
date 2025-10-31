<?php
$pageTitle = 'Bulk Import Domains';

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
$importResults = [];

// Get templates for dropdown
$templates = $db->query("SELECT id, name FROM templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = intval($_POST['template_id'] ?? 0);
    $domainsText = trim($_POST['domains_text'] ?? '');
    
    if ($templateId <= 0) {
        $errorMessage = 'Please select a template.';
    } elseif (empty($domainsText)) {
        $errorMessage = 'Please enter at least one domain.';
    } else {
        // Split domains by newline
        $domains = array_filter(array_map('trim', explode("\n", $domainsText)));
        
        $successCount = 0;
        $failCount = 0;
        $duplicateCount = 0;
        
        foreach ($domains as $domainName) {
            // Basic validation
            if (empty($domainName)) continue;
            
            // Remove http/https if present
            $domainName = preg_replace('/^https?:\/\//', '', $domainName);
            $domainName = preg_replace('/\/.*$/', '', $domainName); // Remove path
            
            try {
                // Check if domain already exists
                $stmt = $db->prepare("SELECT id FROM domains WHERE domain_name = ?");
                $stmt->execute([$domainName]);
                
                if ($stmt->fetch()) {
                    $duplicateCount++;
                    $importResults[] = [
                        'domain' => $domainName,
                        'status' => 'duplicate',
                        'message' => 'Already exists'
                    ];
                    continue;
                }
                
                // Insert domain
                $stmt = $db->prepare("
                    INSERT INTO domains (domain_name, template_id, status, created_at)
                    VALUES (?, ?, 'available', NOW())
                ");
                $stmt->execute([$domainName, $templateId]);
                
                $successCount++;
                $importResults[] = [
                    'domain' => $domainName,
                    'status' => 'success',
                    'message' => 'Imported successfully'
                ];
            } catch (PDOException $e) {
                $failCount++;
                $importResults[] = [
                    'domain' => $domainName,
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }
        
        if ($successCount > 0) {
            $successMessage = "Successfully imported {$successCount} domain(s)";
            if ($duplicateCount > 0) {
                $successMessage .= ". Skipped {$duplicateCount} duplicate(s)";
            }
            if ($failCount > 0) {
                $successMessage .= ". {$failCount} failed";
            }
            logActivity('domains_bulk_imported', "Imported {$successCount} domains", getAdminId());
        } else {
            $errorMessage = 'No domains were imported.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-upload"></i> Bulk Import Domains</h1>
    <p class="text-muted">Import multiple domains at once</p>
</div>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Import Domains</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Select Template <span class="text-danger">*</span></label>
                        <select name="template_id" class="form-select" required>
                            <option value="">Choose template...</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Domains will be linked to this template</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Domains <span class="text-danger">*</span></label>
                        <textarea 
                            name="domains_text" 
                            class="form-control font-monospace" 
                            rows="15" 
                            placeholder="Enter one domain per line:&#10;example1.com&#10;example2.com&#10;example3.com&#10;subdomain.example4.com"
                            required
                        ><?php echo isset($_POST['domains_text']) ? htmlspecialchars($_POST['domains_text']) : ''; ?></textarea>
                        <small class="text-muted">Enter one domain per line. URLs will be automatically cleaned.</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import Domains
                        </button>
                        <a href="/admin/domains.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Domains
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Import Tips</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Enter one domain per line</li>
                    <li>Domains can include or exclude http/https</li>
                    <li>Duplicate domains will be automatically skipped</li>
                    <li>Invalid domains will be reported in results</li>
                    <li>All imported domains will be set to "available" status</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <?php if (!empty($importResults)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Import Results</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importResults as $result): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($result['domain']); ?></code></td>
                                <td>
                                    <?php if ($result['status'] === 'success'): ?>
                                    <span class="badge bg-success">Success</span>
                                    <?php elseif ($result['status'] === 'duplicate'): ?>
                                    <span class="badge bg-warning">Duplicate</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($result['message']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-cloud-upload" style="font-size: 4rem; opacity: 0.2;"></i>
                <p class="text-muted mt-3">Import results will appear here after you submit domains</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
