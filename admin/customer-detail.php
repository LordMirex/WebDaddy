<?php
$pageTitle = 'Customer Details';

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

$customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$customerId) {
    header('Location: /admin/customers.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_status') {
            $status = sanitizeInput($_POST['status']);
            $validStatuses = ['active', 'inactive', 'suspended', 'unverified'];
            
            if (in_array($status, $validStatuses)) {
                try {
                    $stmt = $db->prepare("UPDATE customers SET status = ?, updated_at = datetime('now') WHERE id = ?");
                    $stmt->execute([$status, $customerId]);
                    
                    if ($status === 'suspended') {
                        $stmt = $db->prepare("
                            UPDATE customer_sessions 
                            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'account_suspended'
                            WHERE customer_id = ? AND is_active = 1
                        ");
                        $stmt->execute([$customerId]);
                    }
                    
                    $successMessage = 'Customer status updated successfully!';
                    logActivity('customer_status_updated', "Customer #$customerId status changed to $status", getAdminId());
                } catch (PDOException $e) {
                    error_log('Customer status update error: ' . $e->getMessage());
                    $errorMessage = 'Failed to update customer status.';
                }
            }
        } elseif ($action === 'revoke_session') {
            $sessionId = intval($_POST['session_id']);
            try {
                $stmt = $db->prepare("
                    UPDATE customer_sessions 
                    SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'admin_revoked'
                    WHERE id = ? AND customer_id = ?
                ");
                $stmt->execute([$sessionId, $customerId]);
                $successMessage = 'Session revoked successfully!';
                logActivity('customer_session_revoked', "Session #$sessionId revoked for customer #$customerId", getAdminId());
            } catch (PDOException $e) {
                error_log('Session revoke error: ' . $e->getMessage());
                $errorMessage = 'Failed to revoke session.';
            }
        } elseif ($action === 'revoke_all_sessions') {
            try {
                $stmt = $db->prepare("
                    UPDATE customer_sessions 
                    SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'admin_revoked_all'
                    WHERE customer_id = ? AND is_active = 1
                ");
                $stmt->execute([$customerId]);
                $successMessage = 'All sessions revoked successfully!';
                logActivity('customer_all_sessions_revoked', "All sessions revoked for customer #$customerId", getAdminId());
            } catch (PDOException $e) {
                error_log('Revoke all sessions error: ' . $e->getMessage());
                $errorMessage = 'Failed to revoke sessions.';
            }
        } elseif ($action === 'generate_otp') {
            // Call the OTP API with proper error handling
            $ch = curl_init();
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $url = "$protocol://$host/admin/api/generate-user-otp.php";
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['customer_id' => $customerId]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($response === false || !empty($curlError)) {
                error_log("OTP API cURL error: $curlError");
                $errorMessage = 'Failed to connect to OTP service. Please try again.';
            } else {
                $result = json_decode($response, true);
                
                if ($httpCode === 200 && isset($result['success']) && $result['success']) {
                    $successMessage = 'OTP generated successfully! Code: ' . $result['otp_code'] . ' (expires in 10 minutes)';
                } else {
                    $errorMessage = $result['error'] ?? 'Failed to generate OTP.';
                }
            }
        }
    }
}

// Get customer details with stats
$stmt = $db->prepare("
    SELECT 
        c.*,
        COUNT(DISTINCT po.id) as total_orders,
        SUM(CASE WHEN po.status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
        COALESCE(SUM(CASE WHEN po.status = 'paid' THEN po.final_amount ELSE 0 END), 0) as total_spent,
        (SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = c.id) as ticket_count
    FROM customers c
    LEFT JOIN pending_orders po ON po.customer_id = c.id
    WHERE c.id = ?
    GROUP BY c.id
");
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: /admin/customers.php');
    exit;
}

// Get recent orders
$ordersStmt = $db->prepare("
    SELECT po.*, 
           GROUP_CONCAT(COALESCE(oi.template_name, oi.tool_name)) as items
    FROM pending_orders po
    LEFT JOIN order_items oi ON oi.pending_order_id = po.id
    WHERE po.customer_id = ?
    GROUP BY po.id
    ORDER BY po.created_at DESC
    LIMIT 20
");
$ordersStmt->execute([$customerId]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get support tickets
$ticketsStmt = $db->prepare("
    SELECT * FROM customer_support_tickets 
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$ticketsStmt->execute([$customerId]);
$tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity log
$activityStmt = $db->prepare("
    SELECT * FROM customer_activity_log 
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$activityStmt->execute([$customerId]);
$activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Get active sessions
$sessionsStmt = $db->prepare("
    SELECT * FROM customer_sessions 
    WHERE customer_id = ?
    ORDER BY is_active DESC, last_activity_at DESC
    LIMIT 10
");
$sessionsStmt->execute([$customerId]);
$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent OTPs
$otpStmt = $db->prepare("
    SELECT avo.*, u.name as admin_name
    FROM admin_verification_otps avo
    LEFT JOIN users u ON u.id = avo.admin_id
    WHERE avo.customer_id = ?
    ORDER BY avo.created_at DESC
    LIMIT 5
");
$otpStmt->execute([$customerId]);
$recentOtps = $otpStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Back Button & Title -->
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="/admin/customers.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
            <i class="bi bi-arrow-left mr-1"></i> Back
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Customer Details</h1>
    </div>
    <div class="flex gap-2">
        <?php if ($customer['status'] !== 'suspended'): ?>
        <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend this customer?');">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="status" value="suspended">
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                <i class="bi bi-x-circle mr-1"></i> Suspend
            </button>
        </form>
        <?php else: ?>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="status" value="active">
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                <i class="bi bi-check-circle mr-1"></i> Activate
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
    <i class="bi bi-x-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Customer Profile -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-primary-600 font-bold text-3xl">
                        <?php echo strtoupper(substr($customer['full_name'] ?? $customer['email'], 0, 1)); ?>
                    </span>
                </div>
                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($customer['full_name'] ?? 'Not set'); ?></h2>
                <p class="text-gray-500"><?php echo htmlspecialchars($customer['email']); ?></p>
                
                <?php
                $statusColors = [
                    'active' => 'bg-green-100 text-green-800',
                    'inactive' => 'bg-gray-100 text-gray-800',
                    'suspended' => 'bg-red-100 text-red-800',
                    'unverified' => 'bg-yellow-100 text-yellow-800',
                    'pending_setup' => 'bg-purple-100 text-purple-800'
                ];
                $statusColor = $statusColors[$customer['status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <span class="inline-flex items-center px-3 py-1 <?php echo $statusColor; ?> rounded-full text-sm font-semibold mt-2">
                    <?php echo ucfirst($customer['status']); ?>
                </span>
            </div>
            
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Phone</span>
                    <span class="font-medium"><?php echo htmlspecialchars($customer['phone'] ?? 'Not set'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">WhatsApp</span>
                    <span class="font-medium"><?php echo htmlspecialchars($customer['whatsapp_number'] ?? 'Not set'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Username</span>
                    <span class="font-medium"><?php echo htmlspecialchars($customer['username'] ?? 'Not set'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Email Verified</span>
                    <span class="font-medium">
                        <?php if ($customer['email_verified']): ?>
                        <i class="bi bi-check-circle text-green-600"></i> Yes
                        <?php else: ?>
                        <i class="bi bi-x-circle text-red-600"></i> No
                        <?php endif; ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Phone Verified</span>
                    <span class="font-medium">
                        <?php if ($customer['phone_verified']): ?>
                        <i class="bi bi-check-circle text-green-600"></i> Yes
                        <?php else: ?>
                        <i class="bi bi-x-circle text-red-600"></i> No
                        <?php endif; ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Joined</span>
                    <span class="font-medium"><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Last Login</span>
                    <span class="font-medium"><?php echo $customer['last_login_at'] ? getRelativeTime($customer['last_login_at']) : 'Never'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
            <h3 class="font-bold text-gray-900 mb-4">Statistics</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($customer['total_orders']); ?></div>
                    <div class="text-sm text-gray-500">Total Orders</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo number_format($customer['paid_orders']); ?></div>
                    <div class="text-sm text-gray-500">Paid Orders</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center col-span-2">
                    <div class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($customer['total_spent']); ?></div>
                    <div class="text-sm text-gray-500">Total Spent</div>
                </div>
            </div>
        </div>
        
        <!-- Identity Verification OTP -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
            <h3 class="font-bold text-gray-900 mb-4">
                <i class="bi bi-shield-check text-primary-600"></i> Identity Verification OTP
            </h3>
            <p class="text-sm text-gray-500 mb-4">Generate a 6-digit OTP code that the customer will see on their dashboard for identity verification.</p>
            
            <form method="POST" onsubmit="return confirm('Generate a new OTP for this customer?');">
                <input type="hidden" name="action" value="generate_otp">
                <button type="submit" class="w-full px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                    <i class="bi bi-key mr-2"></i> Generate OTP
                </button>
            </form>
            
            <?php if (!empty($recentOtps)): ?>
            <div class="mt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Recent OTPs</h4>
                <div class="space-y-2">
                    <?php foreach ($recentOtps as $otp): ?>
                    <div class="flex justify-between items-center text-sm p-2 bg-gray-50 rounded">
                        <code class="font-mono font-bold"><?php echo htmlspecialchars($otp['otp_code']); ?></code>
                        <div class="text-right">
                            <div class="<?php echo $otp['is_used'] ? 'text-green-600' : (strtotime($otp['expires_at']) < time() ? 'text-red-600' : 'text-gray-600'); ?>">
                                <?php 
                                if ($otp['is_used']) echo 'Used';
                                elseif (strtotime($otp['expires_at']) < time()) echo 'Expired';
                                else echo 'Valid';
                                ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo getRelativeTime($otp['created_at']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="lg:col-span-2" x-data="{ activeTab: 'orders' }">
        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
            <div class="flex border-b border-gray-200">
                <button @click="activeTab = 'orders'" 
                        :class="activeTab === 'orders' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-6 py-4 font-semibold transition-colors">
                    <i class="bi bi-bag mr-1"></i> Orders (<?php echo count($orders); ?>)
                </button>
                <button @click="activeTab = 'tickets'" 
                        :class="activeTab === 'tickets' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-6 py-4 font-semibold transition-colors">
                    <i class="bi bi-headset mr-1"></i> Tickets (<?php echo count($tickets); ?>)
                </button>
                <button @click="activeTab = 'activity'" 
                        :class="activeTab === 'activity' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-6 py-4 font-semibold transition-colors">
                    <i class="bi bi-clock-history mr-1"></i> Activity
                </button>
                <button @click="activeTab = 'sessions'" 
                        :class="activeTab === 'sessions' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-6 py-4 font-semibold transition-colors">
                    <i class="bi bi-laptop mr-1"></i> Sessions
                </button>
            </div>
            
            <!-- Orders Tab -->
            <div x-show="activeTab === 'orders'" class="p-6">
                <?php if (empty($orders)): ?>
                <div class="text-center py-8">
                    <i class="bi bi-bag-x text-4xl text-gray-300"></i>
                    <p class="text-gray-500 mt-2">No orders yet</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orders as $order): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold text-gray-900">Order #<?php echo $order['id']; ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['items'] ?? 'N/A'); ?></div>
                                <div class="text-xs text-gray-400 mt-1"><?php echo getRelativeTime($order['created_at']); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-green-600"><?php echo formatCurrency($order['final_amount']); ?></div>
                                <?php echo getStatusBadge($order['status']); ?>
                                <a href="/admin/orders.php?search=<?php echo $order['id']; ?>" class="block mt-2 text-primary-600 hover:text-primary-700 text-sm">
                                    View <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tickets Tab -->
            <div x-show="activeTab === 'tickets'" class="p-6">
                <?php if (empty($tickets)): ?>
                <div class="text-center py-8">
                    <i class="bi bi-headset text-4xl text-gray-300"></i>
                    <p class="text-gray-500 mt-2">No support tickets</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($tickets as $ticket): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo truncateText($ticket['message'], 100); ?></div>
                                <div class="flex gap-2 mt-2">
                                    <?php
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-700',
                                        'normal' => 'bg-blue-100 text-blue-700',
                                        'high' => 'bg-orange-100 text-orange-700',
                                        'urgent' => 'bg-red-100 text-red-700'
                                    ];
                                    ?>
                                    <span class="text-xs px-2 py-1 rounded <?php echo $priorityColors[$ticket['priority']] ?? 'bg-gray-100'; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                                        <?php echo ucfirst($ticket['category']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php echo getStatusBadge($ticket['status']); ?>
                                <div class="text-xs text-gray-400 mt-1"><?php echo getRelativeTime($ticket['created_at']); ?></div>
                                <a href="/admin/customer-tickets.php?ticket_id=<?php echo $ticket['id']; ?>" class="block mt-2 text-primary-600 hover:text-primary-700 text-sm">
                                    View <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Activity Tab -->
            <div x-show="activeTab === 'activity'" class="p-6">
                <?php if (empty($activities)): ?>
                <div class="text-center py-8">
                    <i class="bi bi-clock-history text-4xl text-gray-300"></i>
                    <p class="text-gray-500 mt-2">No activity recorded</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($activities as $activity): ?>
                    <div class="flex gap-4 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-activity text-primary-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($activity['action']); ?></div>
                            <?php if ($activity['details']): ?>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['details']); ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400 mt-1">
                                <?php echo getRelativeTime($activity['created_at']); ?>
                                <?php if ($activity['ip_address']): ?>
                                • IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sessions Tab -->
            <div x-show="activeTab === 'sessions'" class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold text-gray-900">Active Sessions</h4>
                    <form method="POST" onsubmit="return confirm('Revoke all active sessions?');">
                        <input type="hidden" name="action" value="revoke_all_sessions">
                        <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm transition-colors">
                            <i class="bi bi-x-circle mr-1"></i> Revoke All
                        </button>
                    </form>
                </div>
                
                <?php if (empty($sessions)): ?>
                <div class="text-center py-8">
                    <i class="bi bi-laptop text-4xl text-gray-300"></i>
                    <p class="text-gray-500 mt-2">No sessions found</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($sessions as $session): ?>
                    <div class="flex justify-between items-center p-4 border border-gray-200 rounded-lg <?php echo $session['is_active'] ? 'bg-green-50' : 'bg-gray-50'; ?>">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 <?php echo $session['is_active'] ? 'bg-green-100' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center">
                                <i class="bi bi-<?php echo strpos(strtolower($session['user_agent'] ?? ''), 'mobile') !== false ? 'phone' : 'laptop'; ?> <?php echo $session['is_active'] ? 'text-green-600' : 'text-gray-500'; ?>"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($session['device_name'] ?? 'Unknown Device'); ?>
                                    <?php if ($session['is_active']): ?>
                                    <span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Active</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    IP: <?php echo htmlspecialchars($session['ip_address'] ?? 'Unknown'); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    Last activity: <?php echo getRelativeTime($session['last_activity_at']); ?>
                                    • Created: <?php echo getRelativeTime($session['created_at']); ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($session['is_active']): ?>
                        <form method="POST" onsubmit="return confirm('Revoke this session?');">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                            <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm transition-colors">
                                <i class="bi bi-x-circle"></i> Revoke
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-sm text-gray-500">
                            <?php echo $session['revoke_reason'] ? ucfirst(str_replace('_', ' ', $session['revoke_reason'])) : 'Expired'; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
