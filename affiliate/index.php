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
$affiliateCode = getAffiliateCode();

$stats = [
    'total_clicks' => $affiliateInfo['total_clicks'] ?? 0,
    'total_sales' => $affiliateInfo['total_sales'] ?? 0,
    'commission_earned' => $affiliateInfo['commission_earned'] ?? 0,
    'commission_pending' => $affiliateInfo['commission_pending'] ?? 0,
    'commission_paid' => $affiliateInfo['commission_paid'] ?? 0
];

try {
    $stmt = $db->prepare("
        SELECT s.*, po.customer_name, po.customer_email, t.name as template_name, t.price as template_price
        FROM sales s
        INNER JOIN pending_orders po ON s.pending_order_id = po.id
        INNER JOIN templates t ON po.template_id = t.id
        WHERE s.affiliate_id = ?
        ORDER BY s.payment_confirmed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$affiliateId]);
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching recent sales: ' . $e->getMessage());
    $recentSales = [];
}

$referralLink = SITE_URL . '/?aff=' . $affiliateCode;

$pageTitle = 'Affiliate Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
    <p>Welcome back, <?php echo htmlspecialchars(getAffiliateName()); ?>!</p>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-primary">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-link-45deg"></i> Your Referral Link
                </h5>
                <div class="input-group">
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="referralLink" 
                           value="<?php echo htmlspecialchars($referralLink); ?>" 
                           readonly>
                    <button class="btn btn-primary" type="button" onclick="copyReferralLink()">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                <small class="text-muted">Your Affiliate Code: <strong><?php echo htmlspecialchars($affiliateCode); ?></strong></small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="info-card info-info">
            <h6>
                <i class="bi bi-mouse"></i> Total Clicks
            </h6>
            <h2><?php echo number_format($stats['total_clicks']); ?></h2>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="info-card info-success">
            <h6>
                <i class="bi bi-cart-check"></i> Total Sales
            </h6>
            <h2><?php echo number_format($stats['total_sales']); ?></h2>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="info-card info-primary">
            <h6>
                <i class="bi bi-hourglass-split"></i> Pending
            </h6>
            <h2><?php echo formatCurrency($stats['commission_pending']); ?></h2>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="info-card info-warning">
            <h6>
                <i class="bi bi-check-circle"></i> Paid
            </h6>
            <h2><?php echo formatCurrency($stats['commission_paid']); ?></h2>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="bi bi-bar-chart"></i> Commission Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <h6 class="text-muted">Total Earned</h6>
                        <h3 class="text-success"><?php echo formatCurrency($stats['commission_earned']); ?></h3>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <h6 class="text-muted">Pending Commission</h6>
                        <h3 class="text-primary"><?php echo formatCurrency($stats['commission_pending']); ?></h3>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <h6 class="text-muted">Total Paid Out</h6>
                        <h3 class="text-info"><?php echo formatCurrency($stats['commission_paid']); ?></h3>
                    </div>
                </div>
                <?php if ($stats['commission_pending'] > 0): ?>
                    <div class="text-center mt-3">
                        <a href="/affiliate/withdrawals.php" class="btn btn-success btn-lg">
                            <i class="bi bi-wallet2"></i> Request Withdrawal
                        </a>
                    </div>
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
                    <i class="bi bi-clock-history"></i> Recent Sales
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentSales)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No sales yet. Share your referral link to start earning commissions!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Template</th>
                                    <th>Amount</th>
                                    <th>Commission</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($sale['payment_confirmed_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['customer_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($sale['customer_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['template_name']); ?></td>
                                        <td><?php echo formatCurrency($sale['amount_paid']); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo formatCurrency($sale['commission_amount']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyReferralLink() {
    const linkInput = document.getElementById('referralLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(linkInput.value).then(function() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-primary');
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy: ' + err);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
