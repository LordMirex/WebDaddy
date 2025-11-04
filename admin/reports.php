<?php
$pageTitle = 'Sales Reports & Analytics';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get filter parameters
$period = $_GET['period'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build date filter
$dateFilter = '';
$params = [];

if ($period === 'today') {
    $dateFilter = "AND DATE(datetime(s.created_at)) = DATE('now')";
} elseif ($period === 'week') {
    $dateFilter = "AND datetime(s.created_at) >= datetime('now', '-7 days')";
} elseif ($period === 'month') {
    $dateFilter = "AND datetime(s.created_at) >= datetime('now', '-30 days')";
} elseif ($period === 'custom' && $startDate && $endDate) {
    $dateFilter = "AND DATE(datetime(s.created_at)) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
}

// Total Sales Revenue
$query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM sales s WHERE 1=1 $dateFilter";
$stmt = $db->prepare($query);
$stmt->execute($params);
$totalRevenue = $stmt->fetchColumn();

// Total Orders
$query = "SELECT COUNT(*) FROM sales s WHERE 1=1 $dateFilter";
$stmt = $db->prepare($query);
$stmt->execute($params);
$totalOrders = $stmt->fetchColumn();

// Average Order Value
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Total Commission Paid
$query = "SELECT COALESCE(SUM(commission_amount), 0) FROM sales s WHERE 1=1 $dateFilter";
$stmt = $db->prepare($query);
$stmt->execute($params);
$totalCommission = $stmt->fetchColumn();

// Net Revenue (after commission)
$netRevenue = $totalRevenue - $totalCommission;

// Top Selling Templates
$query = "
    SELECT 
        t.name,
        COUNT(s.id) as sales_count,
        SUM(s.amount_paid) as revenue
    FROM sales s
    JOIN pending_orders po ON s.pending_order_id = po.id
    JOIN templates t ON po.template_id = t.id
    WHERE 1=1 $dateFilter
    GROUP BY t.id, t.name
    ORDER BY sales_count DESC
    LIMIT 5
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$topTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Affiliates
$query = "
    SELECT 
        a.code,
        u.name as affiliate_name,
        COUNT(s.id) as sales_count,
        SUM(s.commission_amount) as total_commission
    FROM sales s
    JOIN affiliates a ON s.affiliate_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE s.affiliate_id IS NOT NULL $dateFilter
    GROUP BY a.id, a.code, u.name
    ORDER BY total_commission DESC
    LIMIT 5
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$topAffiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Sales
$query = "
    SELECT 
        s.*,
        po.customer_name,
        po.customer_email,
        t.name as template_name,
        a.code as affiliate_code
    FROM sales s
    JOIN pending_orders po ON s.pending_order_id = po.id
    JOIN templates t ON po.template_id = t.id
    LEFT JOIN affiliates a ON s.affiliate_id = a.id
    WHERE 1=1 $dateFilter
    ORDER BY s.created_at DESC
    LIMIT 20
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales by day (last 30 days for chart)
$query = "
    SELECT 
        DATE(datetime(created_at)) as sale_date,
        COUNT(*) as orders,
        SUM(amount_paid) as revenue
    FROM sales
    WHERE datetime(created_at) >= datetime('now', '-30 days')
    GROUP BY DATE(datetime(created_at))
    ORDER BY sale_date ASC
";
$salesByDay = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-graph-up"></i> Sales Reports & Analytics</h1>
    <p class="text-muted">Comprehensive sales and revenue analytics</p>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Period</label>
                <select name="period" id="periodSelect" class="form-select">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div class="col-md-3" id="customDates" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            
            <div class="col-md-3" id="customDatesEnd" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>;">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-filter"></i> Apply Filter
                </button>
                <a href="/admin/reports.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Key Metrics -->
<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-currency-dollar"></i> Total Revenue</h6>
                <div class="stat-number"><?php echo formatCurrency($totalRevenue); ?></div>
                <small class="text-success"><i class="bi bi-arrow-up"></i> Gross</small>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-graph-up"></i> Net Revenue</h6>
                <div class="stat-number"><?php echo formatCurrency($netRevenue); ?></div>
                <small class="text-muted">After commissions</small>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-cart-check"></i> Total Orders</h6>
                <div class="stat-number"><?php echo $totalOrders; ?></div>
                <small class="text-muted">Completed sales</small>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="bi bi-calculator"></i> Avg Order Value</h6>
                <div class="stat-number"><?php echo formatCurrency($avgOrderValue); ?></div>
                <small class="text-muted">Per order</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Sales Trend (Last 30 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Selling Templates</h5>
            </div>
            <div class="card-body">
                <?php if (empty($topTemplates)): ?>
                <p class="text-muted mb-0">No sales data available</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topTemplates as $template): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($template['name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo $template['sales_count']; ?></span>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo formatCurrency($template['revenue']); ?></strong>
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
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-star"></i> Top Affiliates</h5>
            </div>
            <div class="card-body">
                <?php if (empty($topAffiliates)): ?>
                <p class="text-muted mb-0">No affiliate sales data available</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topAffiliates as $affiliate): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($affiliate['affiliate_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($affiliate['code']); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $affiliate['sales_count']; ?></span>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo formatCurrency($affiliate['total_commission']); ?></strong>
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

<!-- Recent Sales Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Sales</h5>
        <a href="/admin/orders.php?export=csv" class="btn btn-sm btn-success">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($recentSales)): ?>
        <p class="text-muted mb-0">No sales found for the selected period</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Customer</th>
                        <th>Template</th>
                        <th>Amount</th>
                        <th>Commission</th>
                        <th>Affiliate</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><strong>#<?php echo $sale['id']; ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($sale['customer_name']); ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($sale['customer_email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($sale['template_name']); ?></td>
                        <td><strong><?php echo formatCurrency($sale['amount_paid']); ?></strong></td>
                        <td>
                            <?php if ($sale['commission_amount'] > 0): ?>
                            <span class="text-warning"><?php echo formatCurrency($sale['commission_amount']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sale['affiliate_code']): ?>
                            <span class="badge bg-info"><?php echo htmlspecialchars($sale['affiliate_code']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">Direct</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Toggle custom date fields
document.getElementById('periodSelect').addEventListener('change', function() {
    const isCustom = this.value === 'custom';
    document.getElementById('customDates').style.display = isCustom ? 'block' : 'none';
    document.getElementById('customDatesEnd').style.display = isCustom ? 'block' : 'none';
});

// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesData = <?php echo json_encode($salesByDay); ?>;

const labels = salesData.map(d => d.sale_date);
const orders = salesData.map(d => parseInt(d.orders));
const revenue = salesData.map(d => parseFloat(d.revenue));

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Orders',
                data: orders,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y',
            },
            {
                label: 'Revenue (₦)',
                data: revenue,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Orders'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Revenue (₦)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            },
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
