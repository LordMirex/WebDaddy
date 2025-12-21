<?php
$pageTitle = 'User Campaign';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'email_all_users') {
            $subject = sanitizeInput($_POST['email_subject']);
            $message = trim($_POST['email_message'] ?? '');
            
            if (empty($subject) || empty($message)) {
                $errorMessage = 'Subject and message are required.';
            } else {
                try {
                    $stmt = $db->query("
                        SELECT id, email, username 
                        FROM customers 
                        WHERE status = 'active'
                    ");
                    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $sentCount = 0;
                    $failedCount = 0;
                    
                    foreach ($customers as $customer) {
                        try {
                            sendCustomEmailToCustomer(
                                $customer['username'] ?? 'Customer',
                                $customer['email'],
                                $subject,
                                $message
                            );
                            $sentCount++;
                        } catch (Exception $e) {
                            $failedCount++;
                            error_log('Failed to send email to ' . $customer['email'] . ': ' . $e->getMessage());
                        }
                    }
                    
                    $successMessage = "Email sent to $sentCount user(s). Failed: $failedCount";
                    logActivity('email_all_users', "Sent bulk email: $subject", getAdminId());
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'email_single_user') {
            $customerId = intval($_POST['customer_id'] ?? 0);
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (empty($subject) || empty($message) || !$customerId) {
                $errorMessage = 'User, subject, and message are required.';
            } else {
                try {
                    $stmt = $db->prepare("SELECT id, email, username FROM customers WHERE id = ?");
                    $stmt->execute([$customerId]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($customer) {
                        sendCustomEmailToCustomer(
                            $customer['username'] ?? 'Customer',
                            $customer['email'],
                            $subject,
                            $message
                        );
                        $successMessage = 'Email sent successfully to ' . htmlspecialchars($customer['email']);
                        logActivity('email_single_user', "Sent email to user #{$customerId}: $subject", getAdminId());
                    } else {
                        $errorMessage = 'User not found.';
                    }
                } catch (Exception $e) {
                    error_log('Email sending error: ' . $e->getMessage());
                    $errorMessage = 'Failed to send email.';
                }
            }
        } elseif ($action === 'create_announcement') {
            $title = sanitizeInput($_POST['announcement_title'] ?? '');
            $rawMessage = trim($_POST['announcement_message'] ?? '');
            $type = sanitizeInput($_POST['announcement_type'] ?? 'info');
            $target = sanitizeInput($_POST['announcement_target'] ?? 'all');
            $targetCustomerId = isset($_POST['target_customer_id']) ? intval($_POST['target_customer_id']) : null;
            $duration = sanitizeInput($_POST['announcement_duration'] ?? 'permanent');
            $expiryDays = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : null;
            
            if (empty($title) || empty($rawMessage)) {
                $errorMessage = 'Title and message are required.';
            } elseif (!in_array($type, ['info', 'success', 'warning', 'danger'])) {
                $errorMessage = 'Invalid announcement type.';
            } elseif (!in_array($target, ['all', 'specific'])) {
                $errorMessage = 'Invalid target selection.';
            } elseif ($target === 'specific' && empty($targetCustomerId)) {
                $errorMessage = 'Please select a specific user.';
            } elseif ($duration === 'timed' && (!$expiryDays || $expiryDays < 1)) {
                $errorMessage = 'Please specify a valid expiry duration (minimum 1 day) for timed announcements.';
            } else {
                $message = sanitizeEmailHtml($rawMessage);
                $db->beginTransaction();
                try {
                    $expiresAt = null;
                    if ($duration === 'timed' && $expiryDays > 0) {
                        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
                    }
                    
                    $customerIdForDb = ($target === 'specific') ? $targetCustomerId : null;
                    
                    $stmt = $db->prepare("
                        INSERT INTO user_announcements (title, message, type, is_active, created_by, customer_id, expires_at)
                        VALUES (?, ?, ?, 1, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $message,
                        $type,
                        getAdminId(),
                        $customerIdForDb,
                        $expiresAt
                    ]);
                    
                    $announcementId = $db->lastInsertId();
                    $db->commit();
                    
                    if ($target === 'specific' && $targetCustomerId) {
                        $stmt = $db->prepare("
                            SELECT id, username, email 
                            FROM customers 
                            WHERE id = ? AND status = 'active'
                        ");
                        $stmt->execute([$targetCustomerId]);
                        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $db->query("
                            SELECT id, username, email 
                            FROM customers 
                            WHERE status = 'active'
                        ");
                        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    $emailStats = sendUserAnnouncementEmails(
                        $announcementId,
                        $title,
                        $message,
                        $type,
                        $customers,
                        $db
                    );
                    
                    $successMessage = "Announcement created and sent to {$emailStats['sent']} user(s)! " .
                                    ($emailStats['failed'] > 0 ? "Failed: {$emailStats['failed']}" : "");
                    
                    logActivity(
                        'user_announcement_created',
                        "Created user announcement: {$title} (Sent: {$emailStats['sent']}, Failed: {$emailStats['failed']})",
                        getAdminId()
                    );
                    
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('Announcement creation error: ' . $e->getMessage());
                    $errorMessage = 'Failed to create announcement: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle_announcement') {
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            
            try {
                $stmt = $db->prepare("UPDATE user_announcements SET is_active = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$isActive, $announcementId]);
                
                $status = $isActive ? 'activated' : 'deactivated';
                $successMessage = "Announcement {$status} successfully!";
                logActivity('user_announcement_toggled', "User announcement #{$announcementId} {$status}", getAdminId());
            } catch (Exception $e) {
                error_log('Toggle announcement error: ' . $e->getMessage());
                $errorMessage = 'Failed to update announcement status.';
            }
        } elseif ($action === 'delete_announcement') {
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            
            try {
                $stmt = $db->prepare("SELECT title FROM user_announcements WHERE id = ?");
                $stmt->execute([$announcementId]);
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($announcement) {
                    $stmt = $db->prepare("DELETE FROM user_announcements WHERE id = ?");
                    $stmt->execute([$announcementId]);
                    
                    $successMessage = 'Announcement deleted successfully!';
                    logActivity('user_announcement_deleted', "Deleted user announcement: {$announcement['title']}", getAdminId());
                } else {
                    $errorMessage = 'Announcement not found.';
                }
            } catch (Exception $e) {
                error_log('Delete announcement error: ' . $e->getMessage());
                $errorMessage = 'Failed to delete announcement.';
            }
        }
    }
}

$customers = $db->query("
    SELECT id, email, username 
    FROM customers 
    WHERE status = 'active' 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$announcements = $db->query("
    SELECT ua.*, 
           c.username as target_customer_name,
           c.email as target_customer_email,
           (SELECT COUNT(*) FROM user_announcement_emails WHERE announcement_id = ua.id AND failed = 0) as emails_sent,
           (SELECT COUNT(*) FROM user_announcement_emails WHERE announcement_id = ua.id AND failed = 1) as emails_failed
    FROM user_announcements ua
    LEFT JOIN customers c ON ua.customer_id = c.id
    ORDER BY ua.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stats = $db->query("
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers
    FROM customers
")->fetch(PDO::FETCH_ASSOC);

$announcementStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
    FROM user_announcements
")->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div x-data="{ 
    activeTab: 'announcements', 
    showEmailModal: false, 
    showAnnouncementModal: false,
    emailAudience: 'all',
    announcementTarget: 'all',
    announcementDuration: 'permanent'
}">
    <div class="mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-megaphone text-primary-600"></i> User Campaign
                </h1>
                <p class="text-gray-600 mt-1">Send announcements and emails to your customers</p>
            </div>
            <div class="flex gap-3">
                <button @click="showEmailModal = true" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors flex items-center gap-2">
                    <i class="bi bi-envelope"></i> Email Users
                </button>
                <button @click="showAnnouncementModal = true" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-colors flex items-center gap-2">
                    <i class="bi bi-megaphone"></i> Post Announcement
                </button>
            </div>
        </div>
    </div>

    <?php if ($successMessage): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
        <i class="bi bi-check-circle mr-2"></i><?php echo $successMessage; ?>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
        <i class="bi bi-exclamation-circle mr-2"></i><?php echo $errorMessage; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="bi bi-people text-blue-600 text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_customers']); ?></div>
                    <div class="text-sm text-gray-500">Total Users</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="bi bi-person-check text-green-600 text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_customers']); ?></div>
                    <div class="text-sm text-gray-500">Active Users</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="bi bi-megaphone text-purple-600 text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($announcementStats['total']); ?></div>
                    <div class="text-sm text-gray-500">Total Announcements</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="bi bi-broadcast text-amber-600 text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($announcementStats['active']); ?></div>
                    <div class="text-sm text-gray-500">Active Announcements</div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-megaphone text-purple-600"></i> Announcements
            </h2>
            <p class="text-gray-600 text-sm mt-1">Manage announcements shown to users on their dashboard</p>
        </div>
        <div class="p-6">
            <?php if (empty($announcements)): ?>
            <div class="text-center py-8">
                <i class="bi bi-megaphone text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-500">No announcements yet</p>
                <button @click="showAnnouncementModal = true" class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                    <i class="bi bi-plus-lg mr-2"></i> Create Your First Announcement
                </button>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Title</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Type</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Target</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Emails</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Status</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Expires</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Created</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $ann): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-2">
                                <div class="font-medium text-gray-900 max-w-[200px] truncate"><?php echo htmlspecialchars($ann['title']); ?></div>
                            </td>
                            <td class="py-3 px-2">
                                <?php
                                $typeColors = [
                                    'info' => 'bg-blue-100 text-blue-700',
                                    'success' => 'bg-green-100 text-green-700',
                                    'warning' => 'bg-yellow-100 text-yellow-700',
                                    'danger' => 'bg-red-100 text-red-700'
                                ];
                                $color = $typeColors[$ann['type']] ?? $typeColors['info'];
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $color; ?>">
                                    <?php echo ucfirst($ann['type']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                <?php if ($ann['customer_id']): ?>
                                    <span class="text-purple-600"><?php echo htmlspecialchars($ann['target_customer_email'] ?? 'User #' . $ann['customer_id']); ?></span>
                                <?php else: ?>
                                    <span class="text-green-600">All Users</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-sm">
                                <span class="text-green-600"><?php echo $ann['emails_sent']; ?> sent</span>
                                <?php if ($ann['emails_failed'] > 0): ?>
                                <span class="text-red-600 ml-1">(<?php echo $ann['emails_failed']; ?> failed)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2">
                                <?php if ($ann['is_active']): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>
                                <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                <?php if ($ann['expires_at']): ?>
                                    <?php echo date('M j, Y', strtotime($ann['expires_at'])); ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-600">
                                <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
                            </td>
                            <td class="py-3 px-2">
                                <div class="flex items-center gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $ann['is_active'] ? 0 : 1; ?>">
                                        <button type="submit" class="p-2 rounded-lg transition-colors <?php echo $ann['is_active'] ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50'; ?>" title="<?php echo $ann['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="bi <?php echo $ann['is_active'] ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                            <i class="bi bi-trash"></i>
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

    <div x-show="showEmailModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showEmailModal = false" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-envelope text-green-600"></i> Email Users
                    </h3>
                    <button type="button" @click="showEmailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" x-bind:value="emailAudience === 'all' ? 'email_all_users' : 'email_single_user'">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Audience <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" x-model="emailAudience">
                            <option value="all">All Active Users</option>
                            <option value="single">Single User</option>
                        </select>
                    </div>
                    
                    <div x-show="emailAudience === 'single'" x-transition>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select User <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="customer_id" :required="emailAudience === 'single'">
                            <option value="">-- Select User --</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['email']); ?> (<?php echo htmlspecialchars($customer['username'] ?? 'N/A'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div x-show="emailAudience === 'all'" x-transition class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                        <i class="bi bi-info-circle mr-2"></i> This will send an email to all active users (<?php echo number_format($stats['active_customers']); ?>).
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subject <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" :name="emailAudience === 'all' ? 'email_subject' : 'subject'" required placeholder="Email subject">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                        <div id="unified-email-editor" style="min-height: 220px; background: white; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
                        <textarea :name="emailAudience === 'all' ? 'email_message' : 'message'" id="unified_email_message" style="display:none;"></textarea>
                        <small class="text-gray-500 text-xs">Use the editor toolbar to format your message</small>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showEmailModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-send mr-2"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showAnnouncementModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showAnnouncementModal = false" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-600 to-purple-700 rounded-t-2xl">
                    <h3 class="text-2xl font-bold text-white flex items-center gap-2">
                        <i class="bi bi-megaphone"></i> Post Announcement
                    </h3>
                    <button type="button" @click="showAnnouncementModal = false" class="text-white hover:text-purple-100 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="announcement_target" x-model="announcementTarget">
                            <option value="all">All Users</option>
                            <option value="specific">Specific User</option>
                        </select>
                    </div>
                    
                    <div x-show="announcementTarget === 'specific'" x-transition>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select User <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="target_customer_id" :required="announcementTarget === 'specific'">
                            <option value="">-- Select User --</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['email']); ?> (<?php echo htmlspecialchars($customer['username'] ?? 'N/A'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Announcement Title <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="announcement_title" required placeholder="Enter announcement title">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="announcement_type">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Danger (Red)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Duration <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="announcement_duration" x-model="announcementDuration">
                            <option value="permanent">Permanent (Until manually deactivated)</option>
                            <option value="timed">Timed (Auto-expire after X days)</option>
                        </select>
                    </div>
                    
                    <div x-show="announcementDuration === 'timed'" x-transition>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Expire After (Days) <span class="text-red-600">*</span></label>
                        <input type="number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" name="expiry_days" min="1" max="365" placeholder="e.g., 7" :required="announcementDuration === 'timed'">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                        <div id="announcement-editor" style="min-height: 180px; background: white; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
                        <textarea name="announcement_message" id="announcement_message" style="display:none;"></textarea>
                        <small class="text-gray-500 text-xs">Use the editor toolbar to format your message</small>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showAnnouncementModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-megaphone mr-2"></i> Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<link href="/assets/css/quill.snow.css" rel="stylesheet">
<script src="/assets/js/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const unifiedEditorElement = document.getElementById('unified-email-editor');
    if (unifiedEditorElement) {
        const unifiedQuill = new Quill('#unified-email-editor', {
            theme: 'snow',
            placeholder: 'Type your message here...',
            modules: {
                toolbar: [
                    [{ 'header': [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        const editorContainer = document.querySelector('#unified-email-editor .ql-editor');
        if (editorContainer) {
            editorContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
            editorContainer.style.fontSize = '15px';
            editorContainer.style.lineHeight = '1.6';
            editorContainer.style.color = '#374151';
            editorContainer.style.minHeight = '220px';
        }

        const unifiedEmailForm = unifiedEditorElement.closest('form');
        if (unifiedEmailForm) {
            unifiedEmailForm.addEventListener('submit', function(e) {
                const messageField = document.querySelector('#unified_email_message');
                messageField.value = unifiedQuill.root.innerHTML;
                
                if (unifiedQuill.getText().trim().length === 0) {
                    e.preventDefault();
                    alert('Please enter a message before sending.');
                    return false;
                }
            });
        }
    }

    const announcementEditorElement = document.getElementById('announcement-editor');
    if (announcementEditorElement) {
        const announcementQuill = new Quill('#announcement-editor', {
            theme: 'snow',
            placeholder: 'Type your announcement message...',
            modules: {
                toolbar: [
                    [{ 'header': [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        const annEditorContainer = document.querySelector('#announcement-editor .ql-editor');
        if (annEditorContainer) {
            annEditorContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
            annEditorContainer.style.fontSize = '15px';
            annEditorContainer.style.lineHeight = '1.6';
            annEditorContainer.style.color = '#374151';
            annEditorContainer.style.minHeight = '180px';
        }

        const announcementForm = announcementEditorElement.closest('form');
        if (announcementForm) {
            announcementForm.addEventListener('submit', function(e) {
                const messageField = document.querySelector('#announcement_message');
                messageField.value = announcementQuill.root.innerHTML;
                
                if (announcementQuill.getText().trim().length === 0) {
                    e.preventDefault();
                    alert('Please enter a message before posting.');
                    return false;
                }
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
