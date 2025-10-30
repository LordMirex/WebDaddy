<?php
$pageTitle = 'Affiliates Management';

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
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists.');
                    }
                    
                    $stmt = $db->prepare("SELECT id FROM affiliates WHERE code = ?");
                    $stmt->execute([$code]);
                    if ($stmt->fetch()) {
                        throw new Exception('Affiliate code already exists.');
                    }
                    
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Auto-generate name from email
                    $name = explode('@', $email)[0];
                    
                    $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, '', ?, 'affiliate', 'active')");
                    $stmt->execute([$name, $email, $passwordHash]);
                    
                    $dbType = getDbType();
                    if ($dbType === 'pgsql') {
                        $userId = $db->lastInsertId('users_id_seq');
                    } else {
                        $userId = $db->lastInsertId();
                    }
                    
                    $stmt = $db->prepare("INSERT INTO affiliates (user_id, code, status) VALUES (?, ?, 'active')");
                    $stmt->execute([$userId, $code]);
                    
                    $db->commit();
                    $successMessage = 'Affiliate account created successfully!';
                    logActivity('affiliate_created', "Affiliate created: $email", getAdminId());
                } catch (Exception $e) {
                    $db->rollBack();
                    $errorMessage = $e->getMessage();
                }
            }
        } elseif ($action === 'update_status') {
            $affiliateId = intval($_POST['affiliate_id']);
            $status = sanitizeInput($_POST['status']);
            
            try {
                $stmt = $db->prepare("UPDATE affiliates SET status = ? WHERE id = ?");
                $stmt->execute([$status, $affiliateId]);
                $successMessage = 'Affiliate status updated!';
                logActivity('affiliate_status_updated', "Affiliate #$affiliateId status: $status", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
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
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAffiliateModal">
        <i class="bi bi-plus-circle"></i> Create Affiliate Account
    </button>
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
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($affiliates)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
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
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/?ref=' . $viewAffiliate['code']); ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
