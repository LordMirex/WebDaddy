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
                'affiliate_cookie_days' => (int)$_POST['affiliate_cookie_days'],
                'site_account_number' => sanitizeInput($_POST['site_account_number'] ?? ''),
                'site_bank_name' => sanitizeInput($_POST['site_bank_name'] ?? ''),
                'site_bank_number' => sanitizeInput($_POST['site_bank_number'] ?? '')
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

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-gear text-primary-600"></i> Site Settings
    </h1>
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
        <i class="bi bi-exclamation-triangle text-xl"></i>
        <span><?php echo htmlspecialchars($errorMessage); ?></span>
    </div>
    <button @click="show = false" class="text-red-700 hover:text-red-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-sliders text-primary-600"></i>General Settings
                </h5>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-building text-primary-600 mr-1"></i>Site Name
                            </label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="site_name"
                                   value="<?php echo htmlspecialchars($currentSettings['site_name'] ?? 'WebDaddy Empire'); ?>" required>
                            <small class="text-gray-500 text-sm">The name displayed throughout the website</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-whatsapp text-green-600 mr-1"></i>WhatsApp Number
                            </label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="whatsapp_number"
                                   value="<?php echo htmlspecialchars($currentSettings['whatsapp_number'] ?? '+2349132672126'); ?>"
                                   placeholder="+2349132672126" required>
                            <small class="text-gray-500 text-sm">Your business WhatsApp number with country code</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-percent text-yellow-600 mr-1"></i>Affiliate Commission Rate
                            </label>
                            <div class="flex">
                                <input type="number" class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="commission_rate" step="0.01" min="0" max="1"
                                       value="<?php echo htmlspecialchars($currentSettings['commission_rate'] ?? '0.30'); ?>" required>
                                <span class="px-4 py-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-700 font-medium">%</span>
                            </div>
                            <small class="text-gray-500 text-sm">Commission percentage (0.30 = 30%)</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-clock text-blue-600 mr-1"></i>Affiliate Cookie Duration
                            </label>
                            <div class="flex">
                                <input type="number" class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="affiliate_cookie_days" min="1" max="365"
                                       value="<?php echo htmlspecialchars($currentSettings['affiliate_cookie_days'] ?? '30'); ?>" required>
                                <span class="px-4 py-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-700 font-medium">days</span>
                            </div>
                            <small class="text-gray-500 text-sm">How long affiliate tracking cookies last</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-bank text-indigo-600 mr-1"></i>Bank Account Number
                            </label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="site_account_number"
                                   value="<?php echo htmlspecialchars($currentSettings['site_account_number'] ?? ''); ?>" 
                                   placeholder="e.g., 1234567890">
                            <small class="text-gray-500 text-sm">Your business bank account number for customer transfers</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-building text-indigo-600 mr-1"></i>Bank Name
                            </label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="site_bank_name"
                                   value="<?php echo htmlspecialchars($currentSettings['site_bank_name'] ?? ''); ?>" 
                                   placeholder="e.g., Access Bank, GTBank">
                            <small class="text-gray-500 text-sm">Name of your bank</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-person-circle text-indigo-600 mr-1"></i>Account Name
                            </label>
                            <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="site_bank_number"
                                   value="<?php echo htmlspecialchars($currentSettings['site_bank_number'] ?? ''); ?>" 
                                   placeholder="e.g., Business Account">
                            <small class="text-gray-500 text-sm">Name associated with your bank account</small>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] shadow-lg">
                            <i class="bi bi-save mr-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-info-circle text-primary-600"></i>Current Settings
                </h5>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="text-sm text-gray-600 mb-1">Site Name</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['site_name'] ?? 'WebDaddy Empire'); ?></div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="text-sm text-gray-600 mb-1">WhatsApp Number</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['whatsapp_number'] ?? '+2349132672126'); ?></div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="text-sm text-gray-600 mb-1">Commission Rate</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['commission_rate'] ?? '0.30'); ?> (<?php echo (float)($currentSettings['commission_rate'] ?? '0.30') * 100; ?>%)</div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="text-sm text-gray-600 mb-1">Cookie Duration</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['affiliate_cookie_days'] ?? '30'); ?> days</div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-blue-50 border-blue-200">
                        <div class="text-sm text-gray-600 mb-1">🏦 Bank Account Number</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['site_account_number'] ?? 'Not set'); ?></div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-blue-50 border-blue-200">
                        <div class="text-sm text-gray-600 mb-1">🏢 Bank Name</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['site_bank_name'] ?? 'Not set'); ?></div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4 bg-blue-50 border-blue-200">
                        <div class="text-sm text-gray-600 mb-1">👤 Account Name</div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($currentSettings['site_bank_number'] ?? 'Not set'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-lightbulb text-yellow-500"></i>Tips
                </h5>
            </div>
            <div class="p-6">
                <ul class="text-sm text-gray-700 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Changes take effect immediately across the website</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>WhatsApp number is used for all contact links</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Commission rate affects new affiliate earnings</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                        <span>Cookie duration affects referral tracking</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
