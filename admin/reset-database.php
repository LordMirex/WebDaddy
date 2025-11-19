<?php
/**
 * Database Reset Tool - Pre-Launch Cleanup
 * Resets all data except admin accounts and sets IDs back to 1
 * DANGER: This action cannot be undone!
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';
$resetComplete = false;

// Handle reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_database') {
        // Verify confirmation code
        $confirmCode = $_POST['confirm_code'] ?? '';
        
        if ($confirmCode !== 'RESET-ALL-DATA') {
            $errorMessage = 'Invalid confirmation code. Please type exactly: RESET-ALL-DATA';
        } else {
            try {
                $db->beginTransaction();
                
                // STEP 1: Get admin user IDs to preserve them (use LOWER for case-insensitive comparison)
                // Also trim role to handle whitespace
                $stmt = $db->query("SELECT id, email, name, role FROM users WHERE LOWER(TRIM(role)) = 'admin'");
                $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($adminUsers)) {
                    throw new Exception('No admin users found! Aborting to prevent lockout.');
                }
                
                // Build list of admin IDs for safe deletion
                $adminIds = array_column($adminUsers, 'id');
                
                // Validate admin IDs are valid integers
                $adminIds = array_filter(array_map('intval', $adminIds), function($id) {
                    return $id > 0;
                });
                
                if (empty($adminIds)) {
                    throw new Exception('No valid admin IDs found! Aborting to prevent lockout.');
                }
                
                // Create safe placeholders for parameterized query
                $placeholders = implode(',', array_fill(0, count($adminIds), '?'));
                
                // STEP 2: Delete all data except admins and settings (in correct order to respect foreign keys)
                // Complete list of all tables to clear
                // NOTE: 'settings' is preserved because it contains critical WhatsApp, SMTP, and feature flag config
                $tablesToClear = [
                    'ticket_replies',
                    'support_tickets',
                    'announcement_emails',
                    'announcements',
                    'activity_logs',
                    'withdrawal_requests',
                    'sales',
                    'cart_items',
                    'order_items',
                    'pending_orders',
                    'affiliate_actions',
                    'affiliates',
                    'domains',
                    'media_files',
                    'tools',
                    'templates',
                    'page_interactions',
                    'page_visits',
                    'session_summary'
                ];
                
                foreach ($tablesToClear as $table) {
                    $db->exec("DELETE FROM {$table}");
                }
                
                // STEP 3: Delete non-admin users using parameterized query (prevents SQL injection and empty list errors)
                $stmt = $db->prepare("DELETE FROM users WHERE id NOT IN ({$placeholders})");
                $stmt->execute($adminIds);
                
                // STEP 4: Reset SQLite auto-increment sequences for ALL tables
                foreach ($tablesToClear as $table) {
                    $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                }
                
                // STEP 5: Reset users sequence to the max admin ID (don't renumber admins - keeps sessions valid)
                $maxAdminId = max($adminIds);
                $stmt = $db->prepare("DELETE FROM sqlite_sequence WHERE name = 'users'");
                $stmt->execute();
                $stmt = $db->prepare("INSERT INTO sqlite_sequence (name, seq) VALUES ('users', ?)");
                $stmt->execute([$maxAdminId]);
                
                $db->commit();
                
                $resetComplete = true;
                $successMessage = "Database reset complete! All data cleared except " . count($adminUsers) . " admin account(s) and system settings (WhatsApp, SMTP, etc). All IDs reset to start from " . ($maxAdminId + 1) . ".";
                
                // Log this critical action (before logging, current session is still valid)
                logActivity('database_reset', "Complete database reset performed. Preserved admins: " . implode(', ', array_column($adminUsers, 'email')), getAdminId());
                
            } catch (Exception $e) {
                $db->rollBack();
                $errorMessage = 'Database reset failed: ' . $e->getMessage();
                error_log('Database reset error: ' . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-red-600 flex items-center gap-3">
        <i class="bi bi-exclamation-triangle"></i> Database Reset Tool
    </h1>
    <p class="text-gray-600 mt-2">Pre-launch cleanup - Reset all data except admin accounts</p>
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

<?php if (!$resetComplete): ?>
<!-- Warning Section -->
<div class="bg-red-50 border-2 border-red-500 rounded-xl p-6 mb-6">
    <div class="flex items-start gap-4">
        <i class="bi bi-exclamation-triangle-fill text-red-600 text-4xl"></i>
        <div>
            <h2 class="text-2xl font-bold text-red-900 mb-3">⚠️ DANGER: Irreversible Action!</h2>
            <p class="text-red-800 mb-4 text-lg">This will <strong>permanently delete</strong> all data from your database, including:</p>
            <ul class="list-disc list-inside text-red-800 space-y-2 mb-4">
                <li>All templates and tools</li>
                <li>All domains</li>
                <li>All orders (pending and completed)</li>
                <li>All sales records</li>
                <li>All affiliates and their earnings</li>
                <li>All withdrawal requests</li>
                <li>All activity logs</li>
                <li>All support tickets</li>
                <li>All analytics data</li>
                <li>All cart items</li>
            </ul>
            <p class="text-red-900 font-bold text-lg">✅ PRESERVED: Admin accounts only</p>
            <p class="text-red-900 font-bold text-lg">📊 RESET: All IDs will start from 1</p>
        </div>
    </div>
</div>

<!-- Current Database Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
    $stats = [
        ['table' => 'templates', 'label' => 'Templates', 'icon' => 'file-earmark-text'],
        ['table' => 'tools', 'label' => 'Tools', 'icon' => 'tools'],
        ['table' => 'domains', 'label' => 'Domains', 'icon' => 'globe'],
        ['table' => 'pending_orders', 'label' => 'Orders', 'icon' => 'cart'],
        ['table' => 'sales', 'label' => 'Sales', 'icon' => 'currency-dollar'],
        ['table' => 'affiliates', 'label' => 'Affiliates', 'icon' => 'people'],
        ['table' => 'users', 'label' => 'Total Users', 'icon' => 'person'],
        ['table' => 'page_visits', 'label' => 'Page Visits', 'icon' => 'bar-chart'],
        ['table' => 'activity_logs', 'label' => 'Activity Logs', 'icon' => 'list-check']
    ];
    
    foreach ($stats as $stat) {
        $count = $db->query("SELECT COUNT(*) FROM {$stat['table']}")->fetchColumn();
        $adminCount = ($stat['table'] === 'users') ? $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() : 0;
        ?>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <i class="bi bi-<?php echo $stat['icon']; ?> text-2xl text-primary-600"></i>
                <div>
                    <p class="text-sm text-gray-600"><?php echo $stat['label']; ?></p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($count); ?></p>
                    <?php if ($stat['table'] === 'users'): ?>
                        <p class="text-xs text-green-600 font-semibold"><?php echo $adminCount; ?> admin(s) will be kept</p>
                    <?php else: ?>
                        <p class="text-xs text-red-600 font-semibold">Will be deleted</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<!-- Reset Form -->
<div class="bg-white rounded-xl shadow-md border border-gray-200 p-8">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Database Reset</h3>
    <p class="text-gray-700 mb-6">To proceed with the database reset, type the confirmation code exactly as shown:</p>
    
    <form method="POST" action="" x-data="{ confirmCode: '', isValid: false }" @submit="if (!isValid) { alert('Please enter the correct confirmation code'); return false; }">
        <input type="hidden" name="action" value="reset_database">
        
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Confirmation Code <span class="text-red-600">*</span>
            </label>
            <div class="bg-gray-100 border-2 border-gray-300 rounded-lg p-4 mb-3">
                <code class="text-lg font-mono font-bold text-red-600">RESET-ALL-DATA</code>
            </div>
            <input 
                type="text" 
                name="confirm_code" 
                x-model="confirmCode"
                @input="isValid = (confirmCode === 'RESET-ALL-DATA')"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all font-mono"
                placeholder="Type: RESET-ALL-DATA"
                required
                autocomplete="off">
            <p class="text-sm text-gray-500 mt-2">Type exactly as shown above (case-sensitive)</p>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <i class="bi bi-info-circle-fill text-yellow-600 text-xl"></i>
                <div class="text-sm text-yellow-800">
                    <p class="font-semibold mb-2">Before you proceed:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Make sure you have a database backup (if needed)</li>
                        <li>This action cannot be undone</li>
                        <li>Admin accounts will be preserved</li>
                        <li>All IDs will restart from 1</li>
                        <li>This is logged in activity logs</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="flex gap-4">
            <button 
                type="submit" 
                :disabled="!isValid"
                :class="isValid ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-400 cursor-not-allowed'"
                class="flex-1 px-6 py-4 text-white font-bold rounded-lg transition-all shadow-lg text-lg">
                <i class="bi bi-trash mr-2"></i> Reset Database Now
            </button>
            <a href="/admin/" class="flex-1 px-6 py-4 bg-gray-600 hover:bg-gray-700 text-white font-bold rounded-lg transition-all shadow-lg text-center text-lg">
                <i class="bi bi-x-circle mr-2"></i> Cancel
            </a>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Success - Show next steps -->
<div class="bg-white rounded-xl shadow-md border border-gray-200 p-8">
    <div class="text-center">
        <i class="bi bi-check-circle-fill text-green-600 text-6xl mb-4"></i>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Database Reset Complete!</h2>
        <p class="text-gray-700 mb-6">Your database has been cleaned and is ready for launch.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-green-50 rounded-lg p-4">
                <i class="bi bi-people-fill text-green-600 text-3xl mb-2"></i>
                <p class="text-sm text-gray-600">Admin Accounts</p>
                <p class="text-2xl font-bold text-green-700">Preserved</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <i class="bi bi-arrow-counterclockwise text-blue-600 text-3xl mb-2"></i>
                <p class="text-sm text-gray-600">Auto-Increment IDs</p>
                <p class="text-2xl font-bold text-blue-700">Reset to 1</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
                <i class="bi bi-database text-purple-600 text-3xl mb-2"></i>
                <p class="text-sm text-gray-600">Database Structure</p>
                <p class="text-2xl font-bold text-purple-700">Intact</p>
            </div>
        </div>
        
        <div class="flex gap-4 justify-center">
            <a href="/admin/" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-all">
                <i class="bi bi-house mr-2"></i> Go to Dashboard
            </a>
            <a href="/admin/templates.php" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-all">
                <i class="bi bi-plus-circle mr-2"></i> Add Templates
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
