<?php
$pageTitle = 'Affiliates Management';

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
        
        if ($action === 'create_affiliate') {
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $code = strtoupper(sanitizeInput($_POST['code']));
            
            if (empty($email) || empty($password) || empty($code)) {
                $errorMessage = 'All required fields must be filled.';
            } elseif (!validateEmail($email)) {
                $errorMessage = 'Invalid email address.';
            } elseif (!preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
                $errorMessage = 'Affiliate code must be 4-20 characters (letters and numbers only).';
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    if ($stmt === false) {
                        throw new Exception('Database error occurred.');
                    }
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists.');
                    }
                    
                    $stmt = $db->prepare("SELECT id FROM affiliates WHERE code = ?");
                    if ($stmt === false) {
                        throw new Exception('Database error occurred.');
                    }
                    $stmt->execute([$code]);
                    if ($stmt->fetch()) {
                        throw new Exception('Affiliate code already exists.');
                    }
                    
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Auto-generate name from email
                    $name = explode('@', $email)[0];
                    
                    $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, '', ?, 'affiliate', 'active')");
                    if ($stmt === false) {
                        throw new Exception('Database error occurred.');
                    }
                    $result = $stmt->execute([$name, $email, $passwordHash]);
                    if ($result === false) {
                        throw new Exception('Failed to create user account.');
                    }
                    
                    $dbType = getDbType();
                    if ($dbType === 'pgsql') {
                        $userId = $db->lastInsertId('users_id_seq');
                    } else {
                        $userId = $db->lastInsertId();
                    }
                    
                    $stmt = $db->prepare("INSERT INTO affiliates (user_id, code, status) VALUES (?, ?, 'active')");
                    if ($stmt === false) {
                        throw new Exception('Database error occurred.');
                    }
                    $result = $stmt->execute([$userId, $code]);
                    if ($result === false) {
                        throw new Exception('Failed to create affiliate record.');
                    }
                    
                    $db->commit();
                    $successMessage = 'Affiliate account created successfully!';
                    logActivity('affiliate_created', "Affiliate created: $email", getAdminId());
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('Affiliate creation error: ' . $e->getMessage());
                    $errorMessage = $e->getMessage();
                }
            }
        } elseif ($action === 'update_status') {
            $affiliateId = intval($_POST['affiliate_id']);
            $status = sanitizeInput($_POST['status']);
            
            try {
                $stmt = $db->prepare("UPDATE affiliates SET status = ? WHERE id = ?");
                if ($stmt === false) {
                    throw new PDOException('Failed to prepare statement');
                }
                $result = $stmt->execute([$status, $affiliateId]);
                if ($result === false) {
                    throw new PDOException('Failed to update status');
                }
                $successMessage = 'Affiliate status updated!';
                logActivity('affiliate_status_updated', "Affiliate #$affiliateId status: $status", getAdminId());
            } catch (PDOException $e) {
                error_log('Affiliate status update error: ' . $e->getMessage());
                $errorMessage = 'Database error occurred. Please try again.';
            }
        } elseif ($action === 'update_commission_rate') {
            $affiliateId = intval($_POST['affiliate_id']);
            $customRate = $_POST['custom_rate'] ?? '';
            
            try {
                // If empty or 'default', set to NULL to use system default
                if (empty($customRate) || $customRate === 'default') {
                    $stmt = $db->prepare("UPDATE affiliates SET custom_commission_rate = NULL WHERE id = ?");
                    if ($stmt === false) {
                        throw new PDOException('Failed to prepare statement');
                    }
                    $result = $stmt->execute([$affiliateId]);
                    if ($result === false) {
                        throw new PDOException('Failed to update commission rate');
                    }
                    $successMessage = 'Commission rate reset to default (' . (AFFILIATE_COMMISSION_RATE * 100) . '%)';
                } else {
                    // Validate rate (should be between 0 and 1)
                    $rate = floatval($customRate);
                    if ($rate < 0 || $rate > 1) {
                        throw new Exception('Commission rate must be between 0% and 100%');
                    }
                    
                    $stmt = $db->prepare("UPDATE affiliates SET custom_commission_rate = ? WHERE id = ?");
                    if ($stmt === false) {
                        throw new PDOException('Failed to prepare statement');
                    }
                    $result = $stmt->execute([$rate, $affiliateId]);
                    if ($result === false) {
                        throw new PDOException('Failed to update commission rate');
                    }
                    $successMessage = 'Custom commission rate updated to ' . ($rate * 100) . '%';
                }
                logActivity('affiliate_commission_updated', "Affiliate #$affiliateId commission rate updated", getAdminId());
            } catch (Exception $e) {
                error_log('Commission rate update error: ' . $e->getMessage());
                $errorMessage = $e->getMessage();
            } catch (PDOException $e) {
                error_log('Commission rate update error: ' . $e->getMessage());
                $errorMessage = 'Database error occurred. Please try again.';
            }
        } elseif ($action === 'email_all_affiliates') {
            $subject = sanitizeInput($_POST['email_subject']);
            $message = trim($_POST['email_message'] ?? '');
            
            if (empty($subject) || empty($message)) {
                $errorMessage = 'Subject and message are required.';
            } else {
                try {
                    // Get all active affiliates
                    $stmt = $db->query("
                        SELECT u.email, u.name 
                        FROM affiliates a
                        JOIN users u ON a.user_id = u.id
                        WHERE a.status = 'active' AND u.status = 'active'
                    ");
                    $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $sentCount = 0;
                    $failedCount = 0;
                    
                    foreach ($affiliates as $affiliate) {
                        try {
                            sendCustomEmailToAffiliate(
                                $affiliate['name'],
                                $affiliate['email'],
                                $subject,
                                $message
                            );
                            $sentCount++;
                        } catch (Exception $e) {
                            $failedCount++;
                            error_log('Failed to send email to ' . $affiliate['email'] . ': ' . $e->getMessage());
                        }
                    }
                    
                    $successMessage = "Email sent to $sentCount affiliate(s). Failed: $failedCount";
                    logActivity('email_all_affiliates', "Sent bulk email: $subject", getAdminId());
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'create_announcement') {
            $title = sanitizeInput($_POST['announcement_title']);
            $message = sanitizeInput($_POST['announcement_message']);
            $type = sanitizeInput($_POST['announcement_type'] ?? 'info');
            $affiliateId = !empty($_POST['announcement_affiliate_id']) ? intval($_POST['announcement_affiliate_id']) : null;
            
            if (empty($title) || empty($message)) {
                $errorMessage = 'Title and message are required.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO announcements (title, message, type, is_active, created_by, affiliate_id)
                        VALUES (?, ?, ?, true, ?, ?)
                    ");
                    $stmt->execute([$title, $message, $type, getAdminId(), $affiliateId]);
                    
                    if ($affiliateId) {
                        $stmt = $db->prepare("SELECT u.name FROM affiliates a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
                        $stmt->execute([$affiliateId]);
                        $affiliateName = $stmt->fetchColumn();
                        $successMessage = 'Announcement posted successfully! It will appear on ' . htmlspecialchars($affiliateName) . '\'s dashboard.';
                    } else {
                        $successMessage = 'Announcement posted successfully! It will appear on all affiliate dashboards.';
                    }
                    logActivity('announcement_created', "Posted announcement: $title", getAdminId());
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'email_single_affiliate') {
            $affiliateId = intval($_POST['affiliate_id'] ?? 0);
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (empty($affiliateId) || empty($subject) || empty($message)) {
                $errorMessage = 'All fields are required.';
            } else {
                try {
                    $stmt = $db->prepare("
                        SELECT u.name, u.email 
                        FROM affiliates a
                        JOIN users u ON a.user_id = u.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$affiliateId]);
                    $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$affiliate) {
                        $errorMessage = 'Affiliate not found.';
                    } else {
                        sendCustomEmailToAffiliate(
                            $affiliate['name'],
                            $affiliate['email'],
                            $subject,
                            $message
                        );
                        $successMessage = 'Email sent successfully to ' . htmlspecialchars($affiliate['name']) . '!';
                        logActivity('email_single_affiliate', "Sent email to affiliate: {$affiliate['email']} - Subject: $subject", getAdminId());
                    }
                } catch (Exception $e) {
                    error_log('Single affiliate email error: ' . $e->getMessage());
                    $errorMessage = 'Failed to send email. Please check your email configuration.';
                }
            }
        } elseif ($action === 'toggle_announcement') {
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            $currentStatus = intval($_POST['current_status'] ?? 0);
            $newStatus = $currentStatus ? 0 : 1;
            
            try {
                $stmt = $db->prepare("UPDATE announcements SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $announcementId]);
                
                $successMessage = $newStatus ? 'Announcement activated successfully!' : 'Announcement deactivated successfully!';
                logActivity('announcement_toggled', "Toggled announcement #$announcementId to " . ($newStatus ? 'active' : 'inactive'), getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Failed to update announcement: ' . $e->getMessage();
            }
        } elseif ($action === 'delete_announcement') {
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            
            try {
                $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$announcementId]);
                
                $successMessage = 'Announcement deleted successfully!';
                logActivity('announcement_deleted', "Deleted announcement #$announcementId", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Failed to delete announcement: ' . $e->getMessage();
            }
        } elseif ($action === 'process_withdrawal') {
            $requestId = intval($_POST['request_id']);
            $withdrawalStatus = sanitizeInput($_POST['withdrawal_status']);
            $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request || $request['status'] !== 'pending') {
                    throw new Exception('Invalid withdrawal request.');
                }
                
                $stmt = $db->prepare("UPDATE withdrawal_requests SET status = ?, admin_notes = ?, processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ?");
                $stmt->execute([$withdrawalStatus, $adminNotes, getAdminId(), $requestId]);
                
                if ($withdrawalStatus === 'paid') {
                    // Money was already deducted from commission_pending when request was made
                    // Just move it to commission_paid
                    $stmt = $db->prepare("UPDATE affiliates SET commission_paid = commission_paid + ? WHERE id = ?");
                    $stmt->execute([$request['amount'], $request['affiliate_id']]);
                } elseif ($withdrawalStatus === 'rejected') {
                    // Return money to commission_pending since withdrawal was rejected
                    $stmt = $db->prepare("UPDATE affiliates SET commission_pending = commission_pending + ? WHERE id = ?");
                    $stmt->execute([$request['amount'], $request['affiliate_id']]);
                }
                
                $db->commit();
                
                // Get affiliate details for email
                $stmt = $db->prepare("
                    SELECT u.name, u.email 
                    FROM affiliates a
                    JOIN users u ON a.user_id = u.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$request['affiliate_id']]);
                $affiliateUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Send appropriate email based on status
                if ($affiliateUser && !empty($affiliateUser['email'])) {
                    if ($withdrawalStatus === 'paid' || $withdrawalStatus === 'approved') {
                        sendWithdrawalApprovedEmail(
                            $affiliateUser['name'],
                            $affiliateUser['email'],
                            number_format($request['amount'], 2),
                            $requestId
                        );
                    } elseif ($withdrawalStatus === 'rejected') {
                        sendWithdrawalRejectedEmail(
                            $affiliateUser['name'],
                            $affiliateUser['email'],
                            number_format($request['amount'], 2),
                            $requestId,
                            $adminNotes
                        );
                    }
                }
                
                $successMessage = "Withdrawal request $withdrawalStatus successfully!";
                logActivity('withdrawal_processed', "Withdrawal #$requestId $withdrawalStatus", getAdminId());
            } catch (Exception $e) {
                $db->rollBack();
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$searchTerm = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT a.*, u.name, u.email, u.phone, u.bank_details,
        (SELECT COUNT(*) FROM withdrawal_requests WHERE affiliate_id = a.id AND status = 'pending') as pending_withdrawals
        FROM affiliates a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR a.code LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($filterStatus)) {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all announcements
$announcementsQuery = "
    SELECT ann.*, 
           u.name as created_by_name,
           a.code as affiliate_code,
           (SELECT name FROM users WHERE id = (SELECT user_id FROM affiliates WHERE id = ann.affiliate_id)) as target_affiliate_name
    FROM announcements ann
    LEFT JOIN users u ON ann.created_by = u.id
    LEFT JOIN affiliates a ON ann.affiliate_id = a.id
    ORDER BY ann.created_at DESC
";
$allAnnouncements = $db->query($announcementsQuery)->fetchAll(PDO::FETCH_ASSOC);

$withdrawalRequests = $db->query("
    SELECT wr.*, a.code as affiliate_code, u.name as affiliate_name, u.email as affiliate_email
    FROM withdrawal_requests wr
    LEFT JOIN affiliates a ON wr.affiliate_id = a.id
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY wr.requested_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$viewAffiliate = null;
$affiliateSales = [];
if (isset($_GET['view'])) {
    $stmt = $db->prepare("
        SELECT a.*, u.name, u.email, u.phone, u.bank_details
        FROM affiliates a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([intval($_GET['view'])]);
    $viewAffiliate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($viewAffiliate) {
        $stmt = $db->prepare("
            SELECT s.*, po.customer_name, po.customer_email, t.name as template_name
            FROM sales s
            LEFT JOIN pending_orders po ON s.pending_order_id = po.id
            LEFT JOIN templates t ON po.template_id = t.id
            WHERE s.affiliate_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$viewAffiliate['id']]);
        $affiliateSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div x-data="{ 
    activeTab: 'affiliates', 
    showCreateModal: false, 
    showEmailModal: false, 
    showEmailSingleModal: false,
    showAnnouncementModal: false,
    processWithdrawalId: null
}">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 gap-3 sm:gap-4">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <i class="bi bi-people text-primary-600"></i> Affiliates Management
        </h1>
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <button @click="showEmailModal = true" class="w-full sm:w-auto px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-sm whitespace-nowrap">
                <i class="bi bi-envelope mr-1"></i> Email All Affiliates
            </button>
            <button @click="showEmailSingleModal = true" class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors text-sm whitespace-nowrap">
                <i class="bi bi-envelope-fill mr-1"></i> Email Single Affiliate
            </button>
            <button @click="showAnnouncementModal = true" class="w-full sm:w-auto px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors text-sm whitespace-nowrap">
                <i class="bi bi-megaphone mr-1"></i> Post Announcement
            </button>
            <button @click="showCreateModal = true" class="w-full sm:w-auto px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors text-sm whitespace-nowrap">
                <i class="bi bi-plus-circle mr-1"></i> Create Affiliate
            </button>
        </div>
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

    <div class="flex border-b border-gray-300 mb-6">
        <button @click="activeTab = 'affiliates'" 
                :class="activeTab === 'affiliates' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-600 hover:text-gray-800'"
                class="px-6 py-3 font-semibold transition-colors">
            <i class="bi bi-people mr-2"></i> Affiliates
        </button>
        <button @click="activeTab = 'withdrawals'" 
                :class="activeTab === 'withdrawals' ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-600 hover:text-gray-800'"
                class="px-6 py-3 font-semibold transition-colors flex items-center gap-2">
            <i class="bi bi-cash-coin"></i> Withdrawal Requests
            <?php 
            $pendingCount = count(array_filter($withdrawalRequests, fn($w) => $w['status'] === 'pending'));
            if ($pendingCount > 0): ?>
            <span class="px-2 py-1 bg-red-600 text-white rounded-full text-xs font-bold"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <div x-show="activeTab === 'affiliates'">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by name, email, code...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                            <i class="bi bi-search mr-2"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">ID</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Name</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Code</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Clicks</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Sales</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Earnings</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Commission Rate</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Status</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($affiliates)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-12">
                                    <i class="bi bi-inbox text-6xl text-gray-300"></i>
                                    <p class="text-gray-500 mt-4">No affiliates found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($affiliates as $affiliate): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $affiliate['id']; ?></td>
                                <td class="py-3 px-2">
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($affiliate['name']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($affiliate['email']); ?></div>
                                </td>
                                <td class="py-3 px-2"><code class="bg-gray-100 px-2 py-1 rounded text-sm font-mono"><?php echo htmlspecialchars($affiliate['code']); ?></code></td>
                                <td class="py-3 px-2 text-gray-700"><?php echo number_format($affiliate['total_clicks']); ?></td>
                                <td class="py-3 px-2 text-gray-700"><?php echo number_format($affiliate['total_sales']); ?></td>
                                <td class="py-3 px-2">
                                    <div class="text-gray-900 font-bold"><?php echo formatCurrency($affiliate['commission_earned']); ?></div>
                                    <div class="text-xs text-yellow-600 mt-1">Pending: <?php echo formatCurrency($affiliate['commission_pending']); ?></div>
                                    <div class="text-xs text-green-600">Paid: <?php echo formatCurrency($affiliate['commission_paid']); ?></div>
                                </td>
                                <td class="py-3 px-2">
                                    <?php
                                    $displayRate = $affiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                                    $isCustom = $affiliate['custom_commission_rate'] !== null;
                                    ?>
                                    <div>
                                        <span class="px-3 py-1 <?php echo $isCustom ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?> rounded-full text-xs font-semibold">
                                            <?php echo number_format($displayRate * 100, 1); ?>%
                                        </span>
                                    </div>
                                    <div class="text-xs <?php echo $isCustom ? 'text-blue-600' : 'text-gray-500'; ?> mt-1">
                                        <?php echo $isCustom ? 'Custom' : 'Default'; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-2">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="affiliate_id" value="<?php echo $affiliate['id']; ?>">
                                        <select class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status" onchange="this.form.submit()">
                                            <option value="active" <?php echo $affiliate['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $affiliate['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo $affiliate['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-3 px-2">
                                    <a href="?view=<?php echo $affiliate['id']; ?>" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm inline-flex items-center gap-1">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div x-show="activeTab === 'withdrawals'">
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Request ID</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Affiliate</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Amount</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Bank Details</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Status</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Requested</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($withdrawalRequests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-12">
                                    <i class="bi bi-inbox text-6xl text-gray-300"></i>
                                    <p class="text-gray-500 mt-4">No withdrawal requests</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($withdrawalRequests as $wr): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $wr['id']; ?></td>
                                <td class="py-3 px-2">
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($wr['affiliate_name']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><code class="bg-gray-100 px-1 py-0.5 rounded"><?php echo htmlspecialchars($wr['affiliate_code']); ?></code></div>
                                </td>
                                <td class="py-3 px-2 font-bold text-gray-900"><?php echo formatCurrency($wr['amount']); ?></td>
                                <td class="py-3 px-2">
                                    <?php 
                                    $bankDetails = json_decode($wr['bank_details_json'], true);
                                    if ($bankDetails): ?>
                                    <div class="text-xs text-gray-700 space-y-0.5">
                                        <div><?php echo htmlspecialchars($bankDetails['bank_name'] ?? 'N/A'); ?></div>
                                        <div><?php echo htmlspecialchars($bankDetails['account_number'] ?? 'N/A'); ?></div>
                                        <div><?php echo htmlspecialchars($bankDetails['account_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">No details</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-2">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-blue-100 text-blue-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusIcons = [
                                        'pending' => 'clock',
                                        'approved' => 'check-circle',
                                        'paid' => 'check2-circle',
                                        'rejected' => 'x-circle'
                                    ];
                                    $color = $statusColors[$wr['status']] ?? 'bg-gray-100 text-gray-800';
                                    $icon = $statusIcons[$wr['status']] ?? 'circle';
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold whitespace-nowrap">
                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                        <span class="hidden sm:inline sm:ml-1"><?php echo ucfirst($wr['status']); ?></span>
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-gray-700 text-sm"><?php echo date('M d, Y', strtotime($wr['requested_at'])); ?></td>
                                <td class="py-3 px-2">
                                    <?php if ($wr['status'] === 'pending'): ?>
                                    <button type="button" @click="processWithdrawalId = <?php echo $wr['id']; ?>" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm">
                                        <i class="bi bi-check-circle mr-1"></i> Process
                                    </button>
                                    
                                    <div x-show="processWithdrawalId === <?php echo $wr['id']; ?>" 
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0"
                                         x-transition:enter-end="opacity-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100"
                                         x-transition:leave-end="opacity-0"
                                         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
                                         style="display: none;">
                                        <div @click.away="processWithdrawalId = null" 
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 transform scale-95"
                                             x-transition:enter-end="opacity-100 transform scale-100"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-start="opacity-100 transform scale-100"
                                             x-transition:leave-end="opacity-0 transform scale-95"
                                             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
                                            <form method="POST">
                                                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                                                    <h3 class="text-2xl font-bold text-gray-900">Process Withdrawal #<?php echo $wr['id']; ?></h3>
                                                    <button type="button" @click="processWithdrawalId = null" class="text-gray-400 hover:text-gray-600 text-2xl">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                                <div class="p-6 space-y-4">
                                                    <input type="hidden" name="action" value="process_withdrawal">
                                                    <input type="hidden" name="request_id" value="<?php echo $wr['id']; ?>">
                                                    
                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status <span class="text-red-600">*</span></label>
                                                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="withdrawal_status" required>
                                                            <option value="approved">Approve</option>
                                                            <option value="paid">Mark as Paid</option>
                                                            <option value="rejected">Reject</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Admin Notes</label>
                                                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="admin_notes" rows="3"></textarea>
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                                                    <button type="button" @click="processWithdrawalId = null" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                                                    <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements Management Section -->
    <div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-megaphone text-primary-600"></i> Announcements Management
            </h3>
            <p class="text-sm text-gray-500 mt-1">View and manage all announcements sent to affiliates</p>
        </div>
        <div class="p-6">
            <?php if (empty($allAnnouncements)): ?>
            <div class="text-center py-12">
                <i class="bi bi-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No announcements posted yet</p>
                <button @click="showAnnouncementModal = true" class="mt-4 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors">
                    <i class="bi bi-plus-circle mr-2"></i> Post Your First Announcement
                </button>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Target</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Created</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($allAnnouncements as $announcement): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                <div class="text-sm text-gray-500 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['message'], 0, 100)); ?>...</div>
                            </td>
                            <td class="px-4 py-4">
                                <?php if ($announcement['affiliate_id']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="bi bi-person-fill mr-1"></i>
                                    <?php echo htmlspecialchars($announcement['target_affiliate_name'] ?: 'Unknown'); ?>
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <i class="bi bi-people-fill mr-1"></i> All Affiliates
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4">
                                <?php
                                $typeColors = [
                                    'info' => 'bg-blue-100 text-blue-800',
                                    'success' => 'bg-green-100 text-green-800',
                                    'warning' => 'bg-yellow-100 text-yellow-800',
                                    'danger' => 'bg-red-100 text-red-800'
                                ];
                                $colorClass = $typeColors[$announcement['type']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                                    <?php echo ucfirst($announcement['type']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <?php if ($announcement['is_active']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="bi bi-check-circle-fill mr-1"></i> Active
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <i class="bi bi-x-circle-fill mr-1"></i> Inactive
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600">
                                <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($announcement['created_at'])); ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $announcement['is_active']; ?>">
                                        <button type="submit" class="text-sm px-3 py-1.5 <?php echo $announcement['is_active'] ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white rounded transition-colors" title="<?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="bi bi-<?php echo $announcement['is_active'] ? 'pause' : 'play'; ?>-fill"></i>
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this announcement? This cannot be undone.');" class="inline">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="text-sm px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded transition-colors" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
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

    <!-- Create Affiliate Modal -->
    <div x-show="showCreateModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showCreateModal = false" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900">Create Affiliate Account</h3>
                    <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="create_affiliate">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Affiliate Code <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="code" required pattern="[A-Za-z0-9]{4,20}" placeholder="Enter unique code (e.g., ADMIN2024)">
                        <small class="text-gray-500 text-xs">4-20 characters, letters and numbers only</small>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email <span class="text-red-600">*</span></label>
                        <input type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="email" required placeholder="Enter email address">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password <span class="text-red-600">*</span></label>
                        <input type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="password" required minlength="6" placeholder="Enter password">
                        <small class="text-gray-500 text-xs">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showCreateModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-plus-circle mr-2"></i> Create Affiliate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email All Affiliates Modal -->
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
                        <i class="bi bi-envelope text-green-600"></i> Email All Affiliates
                    </h3>
                    <button type="button" @click="showEmailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="email_all_affiliates">
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                        <i class="bi bi-info-circle mr-2"></i> This will send an email to all active affiliates.
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subject <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="email_subject" required placeholder="Email subject">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                        <div id="bulk-email-editor" style="min-height: 200px; background: white; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
                        <textarea id="email_message" name="email_message" style="display:none;"></textarea>
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

    <!-- Post Announcement Modal -->
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
             class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <form method="POST">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-megaphone text-primary-600"></i> Post Announcement
                    </h3>
                    <button type="button" @click="showAnnouncementModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4" x-data="{ selectedAffiliate: '' }">
                    <input type="hidden" name="action" value="create_announcement">
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                        <i class="bi bi-info-circle mr-2"></i> 
                        <span x-show="!selectedAffiliate">This will appear on all affiliate dashboards.</span>
                        <span x-show="selectedAffiliate" style="display: none;">This will appear only on the selected affiliate's dashboard.</span>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience</label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="announcement_affiliate_id" x-model="selectedAffiliate">
                            <option value="">All Affiliates</option>
                            <?php foreach ($affiliates as $aff): ?>
                            <option value="<?php echo $aff['id']; ?>">
                                <?php echo htmlspecialchars($aff['name']); ?> (<?php echo htmlspecialchars($aff['code']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Title <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="announcement_title" required placeholder="Announcement title">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="announcement_message" rows="4" required placeholder="Type announcement message..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="announcement_type">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Important (Red)</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showAnnouncementModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-megaphone mr-2"></i> Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Single Affiliate Modal -->
    <div x-show="showEmailSingleModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div @click.away="showEmailSingleModal = false" 
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
                        <i class="bi bi-envelope-fill text-blue-600"></i> Email Single Affiliate
                    </h3>
                    <button type="button" @click="showEmailSingleModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="email_single_affiliate">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Select Affiliate <span class="text-red-600">*</span></label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="affiliate_id" required>
                            <option value="">-- Choose Affiliate --</option>
                            <?php foreach ($affiliates as $aff): ?>
                            <option value="<?php echo $aff['id']; ?>">
                                <?php echo htmlspecialchars($aff['name']); ?> 
                                (<?php echo htmlspecialchars($aff['email']); ?>) 
                                - Code: <?php echo htmlspecialchars($aff['code']); ?>
                                <?php if ($aff['status'] !== 'active'): ?>
                                    [<?php echo strtoupper($aff['status']); ?>]
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subject <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="subject" required placeholder="Email subject">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                        <div id="single-email-editor" style="min-height: 250px; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem;"></div>
                        <textarea id="single_email_message" name="message" style="display:none;"></textarea>
                        <small class="text-gray-500 text-xs">Use the editor toolbar to format your message with headings, bold, lists, links, etc.</small>
                    </div>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                        <i class="bi bi-info-circle mr-2"></i> The email will be sent using the professional WebDaddy template with your custom message.
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="showEmailSingleModal = false" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-send mr-2"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php if ($viewAffiliate): ?>
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-person-circle text-primary-600"></i> Affiliate Details: <?php echo htmlspecialchars($viewAffiliate['name']); ?>
            </h3>
            <a href="/admin/affiliates.php" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Personal Information</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700"><span class="font-semibold">Name:</span> <?php echo htmlspecialchars($viewAffiliate['name']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Email:</span> <?php echo htmlspecialchars($viewAffiliate['email']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Phone:</span> <?php echo htmlspecialchars($viewAffiliate['phone'] ?? 'N/A'); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Code:</span> <code class="bg-gray-100 px-2 py-1 rounded font-mono"><?php echo htmlspecialchars($viewAffiliate['code']); ?></code></p>
                    </div>
                </div>
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Performance Statistics</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700"><span class="font-semibold">Total Clicks:</span> <?php echo number_format($viewAffiliate['total_clicks']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Total Sales:</span> <?php echo number_format($viewAffiliate['total_sales']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Commission Earned:</span> <?php echo formatCurrency($viewAffiliate['commission_earned']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Commission Pending:</span> <span class="text-yellow-600 font-semibold"><?php echo formatCurrency($viewAffiliate['commission_pending']); ?></span></p>
                        <p class="text-gray-700"><span class="font-semibold">Commission Paid:</span> <span class="text-green-600 font-semibold"><?php echo formatCurrency($viewAffiliate['commission_paid']); ?></span></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h6 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-percent text-primary-600"></i> Commission Rate Settings
                    </h6>
                </div>
                <div class="p-6">
                    <?php
                    $currentRate = $viewAffiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                    $isCustomRate = $viewAffiliate['custom_commission_rate'] !== null;
                    ?>
                    <div class="<?php echo $isCustomRate ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'; ?> border p-4 rounded-lg mb-4">
                        <span class="font-semibold text-gray-900">Current Rate:</span> <?php echo number_format($currentRate * 100, 1); ?>%
                        <?php if ($isCustomRate): ?>
                        <span class="ml-3 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">Custom Rate</span>
                        <?php else: ?>
                        <span class="ml-3 px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">Default Rate</span>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="grid md:grid-cols-3 gap-4">
                        <input type="hidden" name="action" value="update_commission_rate">
                        <input type="hidden" name="affiliate_id" value="<?php echo $viewAffiliate['id']; ?>">
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Set Custom Commission Rate</label>
                            <div class="flex">
                                <input 
                                    type="number" 
                                    class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" 
                                    name="custom_rate" 
                                    step="0.01" 
                                    min="0" 
                                    max="1" 
                                    placeholder="e.g., 0.35 for 35%"
                                    value="<?php echo $isCustomRate ? $currentRate : ''; ?>"
                                >
                                <span class="px-4 py-3 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-700 font-semibold">%</span>
                            </div>
                            <small class="text-gray-500 text-xs mt-1 block">
                                Enter a decimal (e.g., 0.35 for 35%, 0.40 for 40%). 
                                Default: <?php echo (AFFILIATE_COMMISSION_RATE * 100); ?>%
                            </small>
                        </div>
                        <div class="flex flex-col justify-end gap-2">
                            <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors">
                                <i class="bi bi-save mr-2"></i> Update Rate
                            </button>
                            <?php if ($isCustomRate): ?>
                            <button 
                                type="button" 
                                class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors"
                                onclick="document.querySelector('[name=custom_rate]').value='default'; this.form.submit();"
                            >
                                <i class="bi bi-arrow-counterclockwise mr-2"></i> Reset to Default
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($viewAffiliate['bank_details'])): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Bank Details</h6>
                <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($viewAffiliate['bank_details']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Referral Link</h6>
                <div class="flex">
                    <input type="text" class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg bg-gray-50" id="affiliateRefLink" value="<?php echo htmlspecialchars(SITE_URL . '/?aff=' . $viewAffiliate['code']); ?>" readonly>
                    <button class="px-6 py-3 bg-primary-100 hover:bg-primary-200 text-primary-700 border border-primary-300 rounded-r-lg transition-colors font-medium" type="button" onclick="copyAffiliateLink(event)">
                        <i class="bi bi-clipboard mr-2"></i> Copy
                    </button>
                </div>
                <small class="text-gray-500 text-xs mt-1 block">Share this link to track referrals for this affiliate</small>
            </div>
            
            <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Sales History</h6>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Sale ID</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Customer</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Template</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Amount</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Commission</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($affiliateSales)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-6 text-gray-500 text-sm">
                                No sales yet
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($affiliateSales as $sale): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-2 text-gray-900">#<?php echo $sale['id']; ?></td>
                            <td class="py-2 px-2 text-gray-700"><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                            <td class="py-2 px-2 text-gray-700"><?php echo htmlspecialchars($sale['template_name']); ?></td>
                            <td class="py-2 px-2 text-gray-900 font-semibold"><?php echo formatCurrency($sale['amount_paid']); ?></td>
                            <td class="py-2 px-2 text-green-600 font-semibold"><?php echo formatCurrency($sale['commission_amount']); ?></td>
                            <td class="py-2 px-2 text-gray-600 text-xs"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <a href="/admin/affiliates.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($successMessage): ?>
    setTimeout(function() {
        if (window.location.href.indexOf('?') > -1 || window.location.href.indexOf('&') > -1) {
            window.location.href = '/admin/affiliates.php';
        }
    }, 100);
    <?php endif; ?>
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">&#8987;</span>Processing...';
            }
        });
    });
});

// Initialize Quill editor for bulk email modal if it exists
document.addEventListener('DOMContentLoaded', function() {
    const bulkEditorElement = document.getElementById('bulk-email-editor');
    if (bulkEditorElement) {
        const bulkQuill = new Quill('#bulk-email-editor', {
            theme: 'snow',
            placeholder: 'Type your message here...',
            modules: {
                toolbar: [
                    [{ 'header': [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        // Set editor font styling
        const editorContainer = document.querySelector('#bulk-email-editor .ql-editor');
        if (editorContainer) {
            editorContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
            editorContainer.style.fontSize = '15px';
            editorContainer.style.lineHeight = '1.6';
            editorContainer.style.color = '#374151';
            editorContainer.style.minHeight = '180px';
        }

        // Sync Quill content to hidden textarea before form submission
        const bulkEmailForm = bulkEditorElement.closest('form');
        if (bulkEmailForm) {
            bulkEmailForm.addEventListener('submit', function(e) {
                const messageField = document.querySelector('#email_message');
                if (messageField) {
                    messageField.value = bulkQuill.root.innerHTML;
                }
                
                // Validate that content exists
                if (bulkQuill.getText().trim().length === 0) {
                    e.preventDefault();
                    alert('Please enter a message before sending.');
                    return false;
                }
            });
        }
    }
});

// Initialize Quill editor for single email modal if it exists
document.addEventListener('DOMContentLoaded', function() {
    const singleEditorElement = document.getElementById('single-email-editor');
    if (singleEditorElement) {
        const singleQuill = new Quill('#single-email-editor', {
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

        // Set editor font styling
        const editorContainer = document.querySelector('#single-email-editor .ql-editor');
        if (editorContainer) {
            editorContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
            editorContainer.style.fontSize = '15px';
            editorContainer.style.lineHeight = '1.6';
            editorContainer.style.color = '#374151';
            editorContainer.style.minHeight = '220px';
        }

        // Sync Quill content to hidden textarea before form submission
        const singleEmailForm = singleEditorElement.closest('form');
        if (singleEmailForm) {
            singleEmailForm.addEventListener('submit', function(e) {
                const messageField = document.querySelector('#single_email_message');
                messageField.value = singleQuill.root.innerHTML;
                
                // Validate that content exists
                if (singleQuill.getText().trim().length === 0) {
                    e.preventDefault();
                    alert('Please enter a message before sending.');
                    return false;
                }
            });
        }
    }
});

function copyAffiliateLink(event) {
    const linkInput = document.getElementById('affiliateRefLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(linkInput.value).then(function() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check mr-2"></i> Copied!';
        btn.className = 'px-6 py-3 bg-green-600 text-white border border-green-700 rounded-r-lg transition-colors font-medium';
        
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.className = 'px-6 py-3 bg-primary-100 hover:bg-primary-200 text-primary-700 border border-primary-300 rounded-r-lg transition-colors font-medium';
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy link: ' + err);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
