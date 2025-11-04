<?php
$pageTitle = 'Site Settings';

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

        if ($action === 'update_settings') {
            $settings = [
                'whatsapp_number' => sanitizeInput($_POST['whatsapp_number']),
                'site_name' => sanitizeInput($_POST['site_name']),
                'commission_rate' => (float)$_POST['commission_rate'],
                'affiliate_cookie_days' => (int)$_POST['affiliate_cookie_days']
            ];

            try {
                $db->beginTransaction();

                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value, updated_at)
                        VALUES (?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT(setting_key)
                        DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$key, $value]);
                }

                $db->commit();
                $successMessage = 'Settings updated successfully!';
                logActivity('settings_updated', 'Site settings updated', getAdminId());

            } catch (PDOException $e) {
                $db->rollBack();
                $errorMessage = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load current settings
$currentSettings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-gear"></i> Site Settings</h1>
</div>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>General Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-600">
                                <i class="bi bi-building text-primary me-1"></i>Site Name
                            </label>
                            <input type="text" class="form-control" name="site_name"
                                   value="<?php echo htmlspecialchars($currentSettings['site_name'] ?? 'WebDaddy Empire'); ?>" required>
                            <small class="text-muted">The name displayed throughout the website</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-600">
                                <i class="bi bi-whatsapp text-success me-1"></i>WhatsApp Number
                            </label>
                            <input type="text" class="form-control" name="whatsapp_number"
                                   value="<?php echo htmlspecialchars($currentSettings['whatsapp_number'] ?? '+2349132672126'); ?>"
                                   placeholder="+2349132672126" required>
                            <small class="text-muted">Your business WhatsApp number with country code</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-600">
                                <i class="bi bi-percent text-warning me-1"></i>Affiliate Commission Rate
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="commission_rate" step="0.01" min="0" max="1"
                                       value="<?php echo htmlspecialchars($currentSettings['commission_rate'] ?? '0.30'); ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Commission percentage (0.30 = 30%)</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-600">
                                <i class="bi bi-clock text-info me-1"></i>Affiliate Cookie Duration
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="affiliate_cookie_days" min="1" max="365"
                                       value="<?php echo htmlspecialchars($currentSettings['affiliate_cookie_days'] ?? '30'); ?>" required>
                                <span class="input-group-text">days</span>
                            </div>
                            <small class="text-muted">How long affiliate tracking cookies last</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Current Settings</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="small text-muted mb-1">Site Name</div>
                            <div class="fw-600"><?php echo htmlspecialchars($currentSettings['site_name'] ?? 'WebDaddy Empire'); ?></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="small text-muted mb-1">WhatsApp Number</div>
                            <div class="fw-600"><?php echo htmlspecialchars($currentSettings['whatsapp_number'] ?? '+2349132672126'); ?></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="small text-muted mb-1">Commission Rate</div>
                            <div class="fw-600"><?php echo htmlspecialchars($currentSettings['commission_rate'] ?? '0.30'); ?> (<?php echo (float)($currentSettings['commission_rate'] ?? '0.30') * 100; ?>%)</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="small text-muted mb-1">Cookie Duration</div>
                            <div class="fw-600"><?php echo htmlspecialchars($currentSettings['affiliate_cookie_days'] ?? '30'); ?> days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips</h5>
            </div>
            <div class="card-body">
                <ul class="small mb-0">
                    <li class="mb-2">Changes take effect immediately across the website</li>
                    <li class="mb-2">WhatsApp number is used for all contact links</li>
                    <li class="mb-2">Commission rate affects new affiliate earnings</li>
                    <li>Cookie duration affects referral tracking</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
