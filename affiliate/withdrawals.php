<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAffiliate();

$affiliateInfo = getAffiliateInfo();
if (!$affiliateInfo) {
    logoutAffiliate();
    header('Location: /affiliate/login.php');
    exit;
}

$db = getDb();
$affiliateId = getAffiliateId();

$error = '';
$success = '';

$stmt = $db->prepare("SELECT bank_details FROM users WHERE id = ?");
$stmt->execute([$_SESSION['affiliate_user_id']]);
$userBankInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$savedBankDetails = null;
if (!empty($userBankInfo['bank_details'])) {
    $savedBankDetails = json_decode($userBankInfo['bank_details'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($savedBankDetails) {
        $bankName = $savedBankDetails['bank_name'];
        $accountNumber = $savedBankDetails['account_number'];
        $accountName = $savedBankDetails['account_name'];
    } else {
        $bankName = sanitizeInput($_POST['bank_name'] ?? '');
        $accountNumber = sanitizeInput($_POST['account_number'] ?? '');
        $accountName = sanitizeInput($_POST['account_name'] ?? '');
    }
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif ($amount > $affiliateInfo['commission_pending']) {
        $error = 'Insufficient balance. Available: ' . formatCurrency($affiliateInfo['commission_pending']);
    } elseif (empty($bankName) || empty($accountNumber) || empty($accountName)) {
        $error = 'Please fill in all bank details or save them in your settings.';
    } else {
        $bankDetails = json_encode([
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => $accountName
        ]);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO withdrawal_requests (affiliate_id, amount, bank_details_json, status)
                VALUES (?, ?, ?, 'pending')
            ");
            
            if ($stmt->execute([$affiliateId, $amount, $bankDetails])) {
                $success = 'Withdrawal request submitted successfully! We will process it soon.';
                $_POST = [];
            } else {
                $error = 'Failed to submit withdrawal request. Please try again.';
            }
        } catch (PDOException $e) {
            error_log('Withdrawal request error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

try {
    $stmt = $db->prepare("
        SELECT * FROM withdrawal_requests
        WHERE affiliate_id = ?
        ORDER BY requested_at DESC
    ");
    $stmt->execute([$affiliateId]);
    $withdrawalRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching withdrawal requests: ' . $e->getMessage());
    $withdrawalRequests = [];
}

$pageTitle = 'Withdrawals';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-wallet2"></i> Withdrawals
    </h1>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 opacity-75">
                    <i class="bi bi-cash-stack"></i> Available Balance
                </h6>
                <h2 class="card-title mb-0"><?php echo formatCurrency($affiliateInfo['commission_pending']); ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 opacity-75">
                    <i class="bi bi-check-circle"></i> Total Paid
                </h6>
                <h2 class="card-title mb-0"><?php echo formatCurrency($affiliateInfo['commission_paid']); ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 opacity-75">
                    <i class="bi bi-hourglass-split"></i> Total Earned
                </h6>
                <h2 class="card-title mb-0"><?php echo formatCurrency($affiliateInfo['commission_earned']); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-plus-circle"></i> Request Withdrawal
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($affiliateInfo['commission_pending'] <= 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You don't have any pending commission available for withdrawal.
                    </div>
                <?php else: ?>
                    <?php if ($savedBankDetails): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <strong>Bank details loaded from settings!</strong><br>
                            <small>Payment will be sent to: <?php echo htmlspecialchars($savedBankDetails['bank_name']); ?> - <?php echo htmlspecialchars($savedBankDetails['account_number']); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> <strong>No saved bank details</strong><br>
                            <small>
                                You can <a href="/affiliate/settings.php" class="alert-link">save your bank details in settings</a> 
                                to make future withdrawals faster!
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <?php if ($savedBankDetails): ?>
                            <div class="mb-4">
                                <label for="amount" class="form-label fs-5">
                                    <i class="bi bi-currency-exchange"></i> How much would you like to withdraw?
                                </label>
                                <input type="number" 
                                       class="form-control form-control-lg" 
                                       id="amount" 
                                       name="amount" 
                                       step="0.01"
                                       max="<?php echo $affiliateInfo['commission_pending']; ?>"
                                       placeholder="Enter amount to withdraw"
                                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                       required
                                       autofocus>
                                <small class="text-muted">
                                    Available balance: <strong><?php echo formatCurrency($affiliateInfo['commission_pending']); ?></strong>
                                </small>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Payment will be sent to:</h6>
                                    <p class="mb-1"><strong>Bank:</strong> <?php echo htmlspecialchars($savedBankDetails['bank_name']); ?></p>
                                    <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($savedBankDetails['account_number']); ?></p>
                                    <p class="mb-0"><strong>Account Name:</strong> <?php echo htmlspecialchars($savedBankDetails['account_name']); ?></p>
                                    <small class="text-muted">
                                        <a href="/affiliate/settings.php">Change bank details</a>
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">
                                            <i class="bi bi-currency-exchange"></i> Withdrawal Amount
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="amount" 
                                               name="amount" 
                                               step="0.01"
                                               max="<?php echo $affiliateInfo['commission_pending']; ?>"
                                               placeholder="Enter amount"
                                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                               required>
                                        <small class="text-muted">
                                            Available: <?php echo formatCurrency($affiliateInfo['commission_pending']); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bank_name" class="form-label">
                                            <i class="bi bi-bank"></i> Bank Name
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="bank_name" 
                                               name="bank_name" 
                                               placeholder="e.g., First Bank"
                                               value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="account_number" class="form-label">
                                            <i class="bi bi-credit-card"></i> Account Number
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="account_number" 
                                               name="account_number" 
                                               placeholder="Enter account number"
                                               value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="account_name" class="form-label">
                                            <i class="bi bi-person"></i> Account Name
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="account_name" 
                                               name="account_name" 
                                               placeholder="Enter account name"
                                               value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid">
                            <button type="submit" name="request_withdrawal" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Submit Withdrawal Request
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history"></i> Withdrawal History
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($withdrawalRequests)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No withdrawal requests yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Bank Details</th>
                                    <th>Status</th>
                                    <th>Processed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawalRequests as $request): ?>
                                    <?php 
                                        $bankDetails = json_decode($request['bank_details_json'], true);
                                        
                                        $statusBadge = '';
                                        switch ($request['status']) {
                                            case 'pending':
                                                $statusBadge = '<span class="badge bg-warning">Pending</span>';
                                                break;
                                            case 'approved':
                                                $statusBadge = '<span class="badge bg-info">Approved</span>';
                                                break;
                                            case 'paid':
                                                $statusBadge = '<span class="badge bg-success">Paid</span>';
                                                break;
                                            case 'rejected':
                                                $statusBadge = '<span class="badge bg-danger">Rejected</span>';
                                                break;
                                            default:
                                                $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($request['status']) . '</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($request['requested_at'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($request['amount']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($bankDetails['bank_name'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($bankDetails['account_number'] ?? 'N/A'); ?><br>
                                                <?php echo htmlspecialchars($bankDetails['account_name'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td>
                                            <?php if ($request['processed_at']): ?>
                                                <small><?php echo date('M d, Y', strtotime($request['processed_at'])); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($request['admin_notes'])): ?>
                                        <tr>
                                            <td colspan="5" class="bg-light">
                                                <small>
                                                    <strong>Admin Notes:</strong> 
                                                    <?php echo htmlspecialchars($request['admin_notes']); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
