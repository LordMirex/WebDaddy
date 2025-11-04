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
            
            if (empty($title) || empty($message)) {
                $errorMessage = 'Title and message are required.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO announcements (title, message, type, is_active, created_by)
                        VALUES (?, ?, ?, true, ?)
                    ");
                    $stmt->execute([$title, $message, $type, getAdminId()]);
                    
                    $successMessage = 'Announcement posted successfully! It will appear on all affiliate dashboards.';
                    logActivity('announcement_created', "Posted announcement: $title", getAdminId());
                } catch (PDOException $e) {
                    $errorMessage = 'Database error: ' . $e->getMessage();
                }
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
                    $stmt = $db->prepare("UPDATE affiliates SET commission_pending = commission_pending - ?, commission_paid = commission_paid + ? WHERE id = ?");
                    $stmt->execute([$request['amount'], $request['amount'], $request['affiliate_id']]);
                } elseif ($withdrawalStatus === 'rejected') {
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> Affiliates Management</h1>
    <div>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#emailAllAffiliatesModal">
            <i class="bi bi-envelope"></i> Email All Affiliates
        </button>
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#announcementModal">
            <i class="bi bi-megaphone"></i> Post Announcement
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAffiliateModal">
            <i class="bi bi-plus-circle"></i> Create Affiliate
        </button>
    </div>
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

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#affiliates-tab">
            <i class="bi bi-people"></i> Affiliates
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#withdrawals-tab">
            <i class="bi bi-cash-coin"></i> Withdrawal Requests
            <?php 
            $pendingCount = count(array_filter($withdrawalRequests, fn($w) => $w['status'] === 'pending'));
            if ($pendingCount > 0): ?>
            <span class="badge bg-danger"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="affiliates-tab">
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by name, email, code...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Clicks</th>
                                <th>Sales</th>
                                <th>Earnings</th>
                                <th>Commission Rate</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($affiliates)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="text-muted mt-2">No affiliates found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($affiliates as $affiliate): ?>
                            <tr>
                                <td><strong>#<?php echo $affiliate['id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($affiliate['name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($affiliate['email']); ?></small>
                                </td>
                                <td><code><?php echo htmlspecialchars($affiliate['code']); ?></code></td>
                                <td><?php echo number_format($affiliate['total_clicks']); ?></td>
                                <td><?php echo number_format($affiliate['total_sales']); ?></td>
                                <td>
                                    <strong><?php echo formatCurrency($affiliate['commission_earned']); ?></strong><br>
                                    <small class="text-warning">Pending: <?php echo formatCurrency($affiliate['commission_pending']); ?></small><br>
                                    <small class="text-success">Paid: <?php echo formatCurrency($affiliate['commission_paid']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $displayRate = $affiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                                    $isCustom = $affiliate['custom_commission_rate'] !== null;
                                    ?>
                                    <span class="badge <?php echo $isCustom ? 'bg-info' : 'bg-secondary'; ?>">
                                        <?php echo number_format($displayRate * 100, 1); ?>%
                                    </span>
                                    <?php if (!$isCustom): ?>
                                    <br><small class="text-muted">Default</small>
                                    <?php else: ?>
                                    <br><small class="text-info">Custom</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="affiliate_id" value="<?php echo $affiliate['id']; ?>">
                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="width: auto;">
                                            <option value="active" <?php echo $affiliate['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $affiliate['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo $affiliate['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="?view=<?php echo $affiliate['id']; ?>" class="btn btn-sm btn-primary">
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
    
    <div class="tab-pane fade" id="withdrawals-tab">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Affiliate</th>
                                <th>Amount</th>
                                <th>Bank Details</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($withdrawalRequests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="text-muted mt-2">No withdrawal requests</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($withdrawalRequests as $wr): ?>
                            <tr>
                                <td><strong>#<?php echo $wr['id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($wr['affiliate_name']); ?><br>
                                    <small class="text-muted"><code><?php echo htmlspecialchars($wr['affiliate_code']); ?></code></small>
                                </td>
                                <td><strong><?php echo formatCurrency($wr['amount']); ?></strong></td>
                                <td>
                                    <?php 
                                    $bankDetails = json_decode($wr['bank_details_json'], true);
                                    if ($bankDetails): ?>
                                    <small>
                                        <?php echo htmlspecialchars($bankDetails['bank_name'] ?? 'N/A'); ?><br>
                                        <?php echo htmlspecialchars($bankDetails['account_number'] ?? 'N/A'); ?><br>
                                        <?php echo htmlspecialchars($bankDetails['account_name'] ?? 'N/A'); ?>
                                    </small>
                                    <?php else: ?>
                                    <small class="text-muted">No details</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'paid' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $class = $statusClass[$wr['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $class; ?>">
                                        <?php echo ucfirst($wr['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($wr['requested_at'])); ?></td>
                                <td>
                                    <?php if ($wr['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#processWithdrawalModal<?php echo $wr['id']; ?>">
                                        <i class="bi bi-check-circle"></i> Process
                                    </button>
                                    
                                    <div class="modal fade" id="processWithdrawalModal<?php echo $wr['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Process Withdrawal #<?php echo $wr['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="process_withdrawal">
                                                        <input type="hidden" name="request_id" value="<?php echo $wr['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status *</label>
                                                            <select class="form-select" name="withdrawal_status" required>
                                                                <option value="approved">Approve</option>
                                                                <option value="paid">Mark as Paid</option>
                                                                <option value="rejected">Reject</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Admin Notes</label>
                                                            <textarea class="form-control" name="admin_notes" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Submit</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
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
</div>

<div class="modal fade" id="createAffiliateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Affiliate Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_affiliate">
                    
                    <div class="mb-3">
                        <label class="form-label">Affiliate Code *</label>
                        <input type="text" class="form-control" name="code" required pattern="[A-Za-z0-9]{4,20}" placeholder="Enter unique code (e.g., ADMIN2024)">
                        <small class="text-muted">4-20 characters, letters and numbers only</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required placeholder="Enter email address">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="6" placeholder="Enter password">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Affiliate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email All Affiliates Modal -->
<div class="modal fade" id="emailAllAffiliatesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> Email All Affiliates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="email_all_affiliates">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will send an email to all active affiliates.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" name="email_subject" required placeholder="Email subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <div id="bulk-email-editor" style="min-height: 200px; background: white; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
                        <textarea id="email_message" name="email_message" style="display:none;" required></textarea>
                        <small class="text-muted">Use the editor toolbar to format your message</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Post Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone"></i> Post Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_announcement">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will appear on all affiliate dashboards.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="announcement_title" required placeholder="Announcement title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="announcement_message" rows="4" required placeholder="Type announcement message..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="announcement_type">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Important (Red)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-megaphone"></i> Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($viewAffiliate): ?>
<div class="modal fade show" id="viewAffiliateModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-circle"></i> Affiliate Details: <?php echo htmlspecialchars($viewAffiliate['name']); ?>
                </h5>
                <a href="/admin/affiliates.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Personal Information</h6>
                        <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($viewAffiliate['name']); ?></p>
                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($viewAffiliate['email']); ?></p>
                        <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($viewAffiliate['phone'] ?? 'N/A'); ?></p>
                        <p class="mb-2"><strong>Code:</strong> <code><?php echo htmlspecialchars($viewAffiliate['code']); ?></code></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Performance Statistics</h6>
                        <p class="mb-2"><strong>Total Clicks:</strong> <?php echo number_format($viewAffiliate['total_clicks']); ?></p>
                        <p class="mb-2"><strong>Total Sales:</strong> <?php echo number_format($viewAffiliate['total_sales']); ?></p>
                        <p class="mb-2"><strong>Commission Earned:</strong> <?php echo formatCurrency($viewAffiliate['commission_earned']); ?></p>
                        <p class="mb-2"><strong>Commission Pending:</strong> <span class="text-warning"><?php echo formatCurrency($viewAffiliate['commission_pending']); ?></span></p>
                        <p class="mb-2"><strong>Commission Paid:</strong> <span class="text-success"><?php echo formatCurrency($viewAffiliate['commission_paid']); ?></span></p>
                    </div>
                </div>
                
                <!-- Custom Commission Rate -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-percent"></i> Commission Rate Settings</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $currentRate = $viewAffiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                        $isCustomRate = $viewAffiliate['custom_commission_rate'] !== null;
                        ?>
                        <div class="alert <?php echo $isCustomRate ? 'alert-info' : 'alert-secondary'; ?>">
                            <strong>Current Rate:</strong> <?php echo number_format($currentRate * 100, 1); ?>%
                            <?php if ($isCustomRate): ?>
                            <span class="badge bg-info">Custom Rate</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Default Rate</span>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="update_commission_rate">
                            <input type="hidden" name="affiliate_id" value="<?php echo $viewAffiliate['id']; ?>">
                            
                            <div class="col-md-8">
                                <label class="form-label">Set Custom Commission Rate</label>
                                <div class="input-group">
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        name="custom_rate" 
                                        step="0.01" 
                                        min="0" 
                                        max="1" 
                                        placeholder="e.g., 0.35 for 35%"
                                        value="<?php echo $isCustomRate ? $currentRate : ''; ?>"
                                    >
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">
                                    Enter a decimal (e.g., 0.35 for 35%, 0.40 for 40%). 
                                    Default: <?php echo (AFFILIATE_COMMISSION_RATE * 100); ?>%
                                </small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-grid gap-2 w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Update Rate
                                    </button>
                                    <?php if ($isCustomRate): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-secondary"
                                        onclick="document.querySelector('[name=custom_rate]').value='default'; this.form.submit();"
                                    >
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($viewAffiliate['bank_details'])): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Bank Details</h6>
                    <div class="alert alert-secondary">
                        <pre class="mb-0"><?php echo htmlspecialchars($viewAffiliate['bank_details']); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Referral Link</h6>
                    <div class="input-group">
                        <input type="text" class="form-control" id="affiliateRefLink" value="<?php echo htmlspecialchars(SITE_URL . '/?aff=' . $viewAffiliate['code']); ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copyAffiliateLink(event)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <small class="text-muted">Share this link to track referrals for this affiliate</small>
                </div>
                
                <h6 class="text-muted mb-2">Sales History</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Customer</th>
                                <th>Template</th>
                                <th>Amount</th>
                                <th>Commission</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($affiliateSales)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-3">
                                    <small class="text-muted">No sales yet</small>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($affiliateSales as $sale): ?>
                            <tr>
                                <td>#<?php echo $sale['id']; ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($sale['template_name']); ?></td>
                                <td><?php echo formatCurrency($sale['amount_paid']); ?></td>
                                <td><?php echo formatCurrency($sale['commission_amount']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="/admin/affiliates.php" class="btn btn-secondary">Close</a>
            </div>
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
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const bootstrapModal = bootstrap.Modal.getInstance(modal);
        if (bootstrapModal) {
            bootstrapModal.hide();
        }
    });
    
    setTimeout(function() {
        if (window.location.href.indexOf('?') > -1 || window.location.href.indexOf('&') > -1) {
            window.location.href = '/admin/affiliates.php';
        }
    }, 100);
    <?php endif; ?>
    
    const forms = document.querySelectorAll('.modal form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
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
        const bulkEmailForm = document.querySelector('#emailAllAffiliatesModal form');
        if (bulkEmailForm) {
            bulkEmailForm.addEventListener('submit', function(e) {
                const messageField = document.querySelector('#email_message');
                messageField.value = bulkQuill.root.innerHTML;
                
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

function copyAffiliateLink(event) {
    const linkInput = document.getElementById('affiliateRefLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(linkInput.value).then(function() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy link: ' + err);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
