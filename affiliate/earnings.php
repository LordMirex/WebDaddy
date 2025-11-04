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
        strftime('%Y-%m', datetime(created_at)) as month,
        COUNT(*) as sales_count,
        SUM(commission_amount) as total_commission
    FROM sales
    WHERE affiliate_id = ?
    GROUP BY strftime('%Y-%m', datetime(created_at))
    ORDER BY month DESC
    LIMIT 12
";
$stmt = $db->prepare($monthlyQuery);
$stmt->execute([$affiliateId]);
$monthlyEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center space-x-3 mb-2">
        <div class="w-12 h-12 bg-gradient-to-br from-green-600 to-green-800 rounded-lg flex items-center justify-center shadow-lg">
            <i class="bi bi-currency-dollar text-2xl text-gold"></i>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Earnings History</h1>
            <p class="text-gray-600">Track all your commissions and sales</p>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Earned -->
    <div class="bg-gradient-to-br from-primary-600 to-primary-800 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2">Total Earned</h6>
        <h3 class="text-3xl font-bold mb-1"><?php echo formatCurrency($affiliate['commission_earned']); ?></h3>
        <small class="text-xs opacity-75">All-time</small>
    </div>
    
    <!-- Pending -->
    <div class="bg-gradient-to-br from-yellow-500 to-yellow-700 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2">Pending</h6>
        <h3 class="text-3xl font-bold mb-1"><?php echo formatCurrency($affiliate['commission_pending']); ?></h3>
        <small class="text-xs opacity-75">Available for withdrawal</small>
    </div>
    
    <!-- Paid Out -->
    <div class="bg-gradient-to-br from-green-500 to-green-700 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2">Paid Out</h6>
        <h3 class="text-3xl font-bold mb-1"><?php echo formatCurrency($affiliate['commission_paid']); ?></h3>
        <small class="text-xs opacity-75">Withdrawn</small>
    </div>
    
    <!-- Total Sales -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2">Total Sales</h6>
        <h3 class="text-3xl font-bold mb-1"><?php echo $affiliate['total_sales']; ?></h3>
        <small class="text-xs opacity-75">Conversions</small>
    </div>
</div>
    
<!-- Monthly Breakdown -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-calendar3 text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Monthly Earnings (Last 12 Months)</h5>
        </div>
    </div>
    
    <div class="p-6">
        <?php if (empty($monthlyEarnings)): ?>
            <p class="text-gray-500 text-center py-6">No earnings data yet</p>
        <?php else: ?>
            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Month</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Sales</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Total Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($monthlyEarnings as $monthly): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 text-sm text-gray-900">
                                <?php 
                                $date = DateTime::createFromFormat('Y-m', $monthly['month']);
                                echo $date->format('F Y'); 
                                ?>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-800">
                                    <?php echo $monthly['sales_count']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right text-sm font-bold text-gray-900">
                                <?php echo formatCurrency($monthly['total_commission']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 border-t-2 border-gray-300">
                            <th class="px-4 py-4 text-left text-sm font-bold text-gray-900">Total</th>
                            <th class="px-4 py-4 text-center text-sm font-bold text-gray-900">
                                <?php 
                                $totalCount = array_sum(array_column($monthlyEarnings, 'sales_count'));
                                echo $totalCount;
                                ?>
                            </th>
                            <th class="px-4 py-4 text-right text-sm font-bold text-gray-900">
                                <?php 
                                $totalAmount = array_sum(array_column($monthlyEarnings, 'total_commission'));
                                echo formatCurrency($totalAmount);
                                ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Mobile Cards -->
            <div class="md:hidden space-y-3">
                <?php foreach ($monthlyEarnings as $monthly): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="font-semibold text-gray-900 mb-3">
                        <?php 
                        $date = DateTime::createFromFormat('Y-m', $monthly['month']);
                        echo $date->format('F Y'); 
                        ?>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Sales</div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-800">
                                <?php echo $monthly['sales_count']; ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-600 mb-1">Commission</div>
                            <div class="font-bold text-gray-900"><?php echo formatCurrency($monthly['total_commission']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Mobile Total -->
                <div class="bg-primary-50 rounded-lg p-4 border-2 border-primary-200">
                    <div class="font-bold text-gray-900 mb-3">Total</div>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-sm text-gray-600 mb-1">Total Sales</div>
                            <div class="font-bold text-gray-900"><?php echo array_sum(array_column($monthlyEarnings, 'sales_count')); ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-600 mb-1">Total Earned</div>
                            <div class="font-bold text-gray-900"><?php echo formatCurrency(array_sum(array_column($monthlyEarnings, 'total_commission'))); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
    
<!-- Detailed Sales List -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-list-ul text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">All Sales (<?php echo number_format($totalSales); ?>)</h5>
        </div>
    </div>
    
    <?php if (empty($sales)): ?>
    <div class="p-12 text-center">
        <i class="bi bi-inbox text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">No sales yet. Keep promoting your referral link!</p>
    </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Sale ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Template</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Template Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Your Commission</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($sales as $sale): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                            <?php echo date('M d, Y', strtotime($sale['created_at'])); ?>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-200 text-gray-700">
                                #<?php echo $sale['id']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($sale['template_name']); ?></td>
                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo formatCurrency($sale['template_price']); ?></td>
                        <td class="px-4 py-4 text-sm font-bold text-green-600">
                            <?php echo formatCurrency($sale['commission_amount']); ?>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <i class="bi bi-check-circle mr-1"></i> Paid
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards -->
        <div class="lg:hidden p-4 space-y-4">
            <?php foreach ($sales as $sale): ?>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="flex justify-between items-start mb-3">
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-200 text-gray-700">
                        #<?php echo $sale['id']; ?>
                    </span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        <i class="bi bi-check-circle mr-1"></i> Paid
                    </span>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date:</span>
                        <span class="text-gray-900 font-medium"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Customer:</span>
                        <span class="text-gray-900"><?php echo htmlspecialchars($sale['customer_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Template:</span>
                        <span class="text-gray-900"><?php echo htmlspecialchars($sale['template_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Price:</span>
                        <span class="text-gray-900"><?php echo formatCurrency($sale['template_price']); ?></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-300 pt-2 mt-2">
                        <span class="text-gray-600 font-semibold">Your Commission:</span>
                        <span class="text-green-600 font-bold"><?php echo formatCurrency($sale['commission_amount']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        
    <?php if ($totalPages > 1): ?>
    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
        <nav class="flex flex-col sm:flex-row items-center justify-center gap-2">
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" 
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="bi bi-chevron-left mr-1"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="<?php echo $i === $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-medium border border-gray-300 rounded-lg transition-colors">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" 
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Next <i class="bi bi-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </nav>
        <div class="text-center text-gray-500 text-sm mt-3">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Commission Info -->
<div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mt-6">
    <div class="flex items-start">
        <i class="bi bi-info-circle text-blue-600 text-xl mr-3 mt-0.5"></i>
        <div>
            <p class="text-blue-900">
                <strong class="font-bold">Commission Rate:</strong> You earn <?php echo (AFFILIATE_COMMISSION_RATE * 100); ?>% commission on every sale.
                Commissions are calculated on the original template price, even when customers use your discount code.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
