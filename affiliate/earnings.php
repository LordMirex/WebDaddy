<?php
$pageTitle = 'Earnings History';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAffiliate();

$db = getDb();
$affiliateId = $_SESSION['affiliate_id'];

// Get affiliate stats
$stmt = $db->prepare("SELECT * FROM affiliates WHERE id = ?");
$stmt->execute([$affiliateId]);
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all sales with commission
$query = "
    SELECT 
        s.*,
        po.customer_name,
        t.name as template_name,
        t.price as template_price
    FROM sales s
    JOIN pending_orders po ON s.pending_order_id = po.id
    JOIN templates t ON po.template_id = t.id
    WHERE s.affiliate_id = ?
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
$stmt->execute([$affiliateId, $perPage, $offset]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE affiliate_id = ?");
$stmt->execute([$affiliateId]);
$totalSales = $stmt->fetchColumn();
$totalPages = ceil($totalSales / $perPage);

// Calculate monthly earnings
$monthlyQuery = "
    SELECT 
        TO_CHAR(created_at, 'YYYY-MM') as month,
        COUNT(*) as sales_count,
        SUM(commission_amount) as total_commission
    FROM sales
    WHERE affiliate_id = ?
    GROUP BY TO_CHAR(created_at, 'YYYY-MM')
    ORDER BY month DESC
    LIMIT 12
";
$stmt = $db->prepare($monthlyQuery);
$stmt->execute([$affiliateId]);
$monthlyEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="bi bi-currency-dollar"></i> Earnings History</h1>
            <p class="text-muted">Track all your commissions and sales</p>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Total Earned</h6>
                    <h3 class="card-title mb-0"><?php echo formatCurrency($affiliate['commission_earned']); ?></h3>
                    <small class="opacity-75">All-time</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Pending</h6>
                    <h3 class="card-title mb-0"><?php echo formatCurrency($affiliate['commission_pending']); ?></h3>
                    <small class="opacity-75">Available for withdrawal</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Paid Out</h6>
                    <h3 class="card-title mb-0"><?php echo formatCurrency($affiliate['commission_paid']); ?></h3>
                    <small class="opacity-75">Withdrawn</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Total Sales</h6>
                    <h3 class="card-title mb-0"><?php echo $affiliate['total_sales']; ?></h3>
                    <small class="opacity-75">Conversions</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Monthly Breakdown -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calendar3"></i> Monthly Earnings (Last 12 Months)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($monthlyEarnings)): ?>
            <p class="text-muted text-center py-3">No earnings data yet</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Sales</th>
                            <th class="text-end">Total Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyEarnings as $monthly): ?>
                        <tr>
                            <td>
                                <?php 
                                $date = DateTime::createFromFormat('Y-m', $monthly['month']);
                                echo $date->format('F Y'); 
                                ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?php echo $monthly['sales_count']; ?></span>
                            </td>
                            <td class="text-end">
                                <strong><?php echo formatCurrency($monthly['total_commission']); ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <th>Total</th>
                            <th class="text-center">
                                <?php 
                                $totalCount = array_sum(array_column($monthlyEarnings, 'sales_count'));
                                echo $totalCount;
                                ?>
                            </th>
                            <th class="text-end">
                                <?php 
                                $totalAmount = array_sum(array_column($monthlyEarnings, 'total_commission'));
                                echo formatCurrency($totalAmount);
                                ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detailed Sales List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-ul"></i> All Sales (<?php echo number_format($totalSales); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sales)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-2">No sales yet. Keep promoting your referral link!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sale ID</th>
                            <th>Customer</th>
                            <th>Template</th>
                            <th>Template Price</th>
                            <th>Your Commission</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary">#<?php echo $sale['id']; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($sale['template_name']); ?></td>
                            <td><?php echo formatCurrency($sale['template_price']); ?></td>
                            <td>
                                <strong class="text-success"><?php echo formatCurrency($sale['commission_amount']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Paid
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center text-muted mt-2">
                <small>Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle"></i>
        <strong>Commission Rate:</strong> You earn <?php echo (AFFILIATE_COMMISSION_RATE * 100); ?>% commission on every sale.
        Commissions are calculated on the original template price, even when customers use your discount code.
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
