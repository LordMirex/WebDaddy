<?php
$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$totalTemplates = $db->query("SELECT COUNT(*) FROM templates")->fetchColumn();
$activeTemplates = $db->query("SELECT COUNT(*) FROM templates WHERE active = true")->fetchColumn();
$totalOrders = $db->query("SELECT COUNT(*) FROM pending_orders")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM pending_orders WHERE status = 'pending'")->fetchColumn();
$totalSales = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM sales")->fetchColumn();
$totalAffiliates = $db->query("SELECT COUNT(*) FROM affiliates WHERE status = 'active'")->fetchColumn();
$pendingWithdrawals = $db->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();

$recentOrders = getOrders('pending');
$recentOrders = array_slice($recentOrders, 0, 5);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
    <p class="text-muted">Welcome back, <?php echo htmlspecialchars(getAdminName()); ?>!</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-grid"></i> Templates</h6>
                <div class="stat-number"><?php echo $activeTemplates; ?></div>
                <small class="text-muted"><?php echo $totalTemplates; ?> total</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-cart"></i> Orders</h6>
                <div class="stat-number"><?php echo $pendingOrders; ?></div>
                <small class="text-muted"><?php echo $totalOrders; ?> total</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-check-circle"></i> Sales</h6>
                <div class="stat-number"><?php echo $totalSales; ?></div>
                <small class="text-muted"><?php echo formatCurrency($totalRevenue); ?> revenue</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-people"></i> Affiliates</h6>
                <div class="stat-number"><?php echo $totalAffiliates; ?></div>
                <small class="text-muted"><?php echo $pendingWithdrawals; ?> pending withdrawals</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Pending Orders</h5>
                <a href="/admin/orders.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> No pending orders at the moment.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Template</th>
                                <th>Domain</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['template_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['domain_name'] ?? 'Not selected'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="/admin/orders.php?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
