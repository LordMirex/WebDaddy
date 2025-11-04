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
                    VALUES (?, ?, 'available', CURRENT_TIMESTAMP)
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

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-upload text-primary-600"></i> Bulk Import Domains
    </h1>
    <p class="text-gray-600 mt-2">Import multiple domains at once</p>
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
        <i class="bi bi-exclamation-circle text-xl"></i>
        <span><?php echo htmlspecialchars($errorMessage); ?></span>
    </div>
    <button @click="show = false" class="text-red-700 hover:text-red-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-list text-primary-600"></i> Import Domains
                </h5>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select Template <span class="text-red-600">*</span></label>
                        <select name="template_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" required>
                            <option value="">Choose template...</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-gray-500 text-sm">Domains will be linked to this template</small>
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Domains <span class="text-red-600">*</span></label>
                        <textarea 
                            name="domains_text" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all font-mono text-sm" 
                            rows="15" 
                            placeholder="Enter one domain per line:&#10;example1.com&#10;example2.com&#10;example3.com&#10;subdomain.example4.com"
                            required
                        ><?php echo isset($_POST['domains_text']) ? htmlspecialchars($_POST['domains_text']) : ''; ?></textarea>
                        <small class="text-gray-500 text-sm">Enter one domain per line. URLs will be automatically cleaned.</small>
                    </div>
                    
                    <div class="space-y-3">
                        <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                            <i class="bi bi-upload"></i> Import Domains
                        </button>
                        <a href="/admin/domains.php" class="block w-full text-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                            <i class="bi bi-arrow-left"></i> Back to Domains
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h6 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-info-circle text-blue-600"></i> Import Tips
                </h6>
            </div>
            <div class="p-6">
                <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Enter one domain per line</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Domains can include or exclude http/https</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Duplicate domains will be automatically skipped</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Invalid domains will be reported in results</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>All imported domains will be set to "available" status</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div>
        <?php if (!empty($importResults)): ?>
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-list-check text-primary-600"></i> Import Results
                </h5>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 px-3 font-semibold text-gray-700">Domain</th>
                                <th class="text-left py-2 px-3 font-semibold text-gray-700">Status</th>
                                <th class="text-left py-2 px-3 font-semibold text-gray-700">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($importResults as $result): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3"><code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($result['domain']); ?></code></td>
                                <td class="py-2 px-3">
                                    <?php if ($result['status'] === 'success'): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Success</span>
                                    <?php elseif ($result['status'] === 'duplicate'): ?>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">Duplicate</span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-gray-600 text-xs"><?php echo htmlspecialchars($result['message']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="p-12 text-center">
                <i class="bi bi-cloud-upload text-8xl text-gray-300"></i>
                <p class="text-gray-500 mt-4">Import results will appear here after you submit domains</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
