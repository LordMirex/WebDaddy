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

// Get active announcements (for all affiliates or specifically for this affiliate)
try {
    $stmt = $db->prepare("
        SELECT * FROM announcements 
        WHERE is_active = true 
        AND (affiliate_id IS NULL OR affiliate_id = ?)
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$affiliateId]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching announcements: ' . $e->getMessage());
    $announcements = [];
}

$referralLink = SITE_URL . '/?aff=' . $affiliateCode;

$pageTitle = 'Affiliate Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center space-x-3 mb-2">
        <div class="w-12 h-12 bg-gradient-to-br from-primary-600 to-primary-800 rounded-lg flex items-center justify-center shadow-lg">
            <i class="bi bi-speedometer2 text-2xl text-gold"></i>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">Welcome back, <span class="font-semibold text-primary-700"><?php echo htmlspecialchars(getAffiliateName()); ?></span>!</p>
        </div>
    </div>
</div>

<!-- Announcements -->
<?php if (!empty($announcements)): ?>
<div class="mb-6 space-y-4 bg-green-50 bg-yellow-50 bg-red-50 bg-blue-50 border-green-500 border-yellow-500 border-red-500 border-blue-500 text-green-600 text-yellow-600 text-red-600 text-blue-600 hidden">
    <!-- Safelist for Tailwind CDN -->
</div>
<div class="mb-6 space-y-4">
    <?php foreach ($announcements as $announcement): ?>
    <div x-data="{ open: true }" x-show="open" class="bg-<?php echo $announcement['type'] === 'success' ? 'green' : ($announcement['type'] === 'warning' ? 'yellow' : ($announcement['type'] === 'danger' ? 'red' : 'blue')); ?>-50 border-l-4 border-<?php echo $announcement['type'] === 'success' ? 'green' : ($announcement['type'] === 'warning' ? 'yellow' : ($announcement['type'] === 'danger' ? 'red' : 'blue')); ?>-500 p-6 rounded-lg shadow-sm relative">
        <button @click="open = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="flex items-start space-x-3">
            <i class="bi bi-megaphone text-<?php echo $announcement['type'] === 'success' ? 'green' : ($announcement['type'] === 'warning' ? 'yellow' : ($announcement['type'] === 'danger' ? 'red' : 'blue')); ?>-600 text-2xl mt-1"></i>
            <div class="flex-1 pr-8">
                <h5 class="font-bold text-gray-900 mb-2 text-lg">
                    <?php echo htmlspecialchars($announcement['title']); ?>
                </h5>
                <p class="text-gray-700 mb-3"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                <p class="text-sm text-gray-500 flex items-center">
                    <i class="bi bi-clock mr-2"></i>
                    <?php echo date('M d, Y \a\t g:i A', strtotime($announcement['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Referral Link Card -->
<div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-xl shadow-xl p-6 mb-6">
    <div class="flex items-center space-x-3 mb-4">
        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
            <i class="bi bi-link-45deg text-2xl text-gold"></i>
        </div>
        <h5 class="text-xl font-bold text-white">Your Referral Link</h5>
    </div>
    
    <div x-data="{ copied: false }" class="flex flex-col sm:flex-row gap-3">
        <input type="text" 
               id="referralLink" 
               value="<?php echo htmlspecialchars($referralLink); ?>" 
               readonly
               class="flex-1 px-4 py-3 bg-white border-2 border-white/30 rounded-lg text-gray-900 font-medium focus:ring-2 focus:ring-gold focus:border-gold">
        <button @click="
            navigator.clipboard.writeText('<?php echo htmlspecialchars($referralLink); ?>').then(() => {
                copied = true;
                setTimeout(() => copied = false, 2000);
            })
        " 
                x-bind:class="{ 'bg-green-500 hover:bg-green-600': copied, 'bg-white hover:bg-gray-100': !copied }"
                class="px-6 py-3 bg-white hover:bg-gray-100 bg-green-500 hover:bg-green-600 text-primary-900 font-bold rounded-lg shadow-lg transition-all duration-200 hover:shadow-xl flex items-center justify-center space-x-2 whitespace-nowrap">
            <i x-bind:class="{ 'bi-check-lg': copied, 'bi-clipboard': !copied }" class="text-lg bi-clipboard bi-check-lg"></i>
            <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
        </button>
    </div>
    
    <p class="text-white/90 text-sm mt-3">
        <i class="bi bi-person-badge mr-2"></i>
        Your Affiliate Code: <strong class="text-gold"><?php echo htmlspecialchars($affiliateCode); ?></strong>
    </p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <!-- Total Clicks -->
    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow p-4 sm:p-6 border-l-4 border-blue-500 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="bi bi-mouse text-xl sm:text-2xl text-blue-600"></i>
            </div>
        </div>
        <h6 class="text-xs sm:text-sm font-semibold text-gray-600 mb-1 uppercase tracking-wide truncate">Total Clicks</h6>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate"><?php echo formatNumber($stats['total_clicks']); ?></h2>
    </div>
    
    <!-- Total Sales -->
    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow p-4 sm:p-6 border-l-4 border-green-500 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="bi bi-cart-check text-xl sm:text-2xl text-green-600"></i>
            </div>
        </div>
        <h6 class="text-xs sm:text-sm font-semibold text-gray-600 mb-1 uppercase tracking-wide truncate">Total Sales</h6>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate"><?php echo formatNumber($stats['total_sales']); ?></h2>
    </div>
    
    <!-- Pending Commission -->
    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow p-4 sm:p-6 border-l-4 border-yellow-500 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="bi bi-hourglass-split text-xl sm:text-2xl text-yellow-600"></i>
            </div>
        </div>
        <h6 class="text-xs sm:text-sm font-semibold text-gray-600 mb-1 uppercase tracking-wide truncate">Pending</h6>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate"><?php echo formatCurrency($stats['commission_pending']); ?></h2>
    </div>
    
    <!-- Paid Commission -->
    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow p-4 sm:p-6 border-l-4 border-purple-500 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="bi bi-check-circle text-xl sm:text-2xl text-purple-600"></i>
            </div>
        </div>
        <h6 class="text-xs sm:text-sm font-semibold text-gray-600 mb-1 uppercase tracking-wide truncate">Paid</h6>
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate"><?php echo formatCurrency($stats['commission_paid']); ?></h2>
    </div>
</div>

<!-- Commission Summary -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <div class="flex items-center space-x-3 mb-6">
        <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
            <i class="bi bi-bar-chart text-xl text-primary-600"></i>
        </div>
        <h5 class="text-xl font-bold text-gray-900">Commission Summary</h5>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <h6 class="text-sm font-semibold text-gray-600 mb-2">Total Earned</h6>
            <h3 class="text-3xl font-bold text-green-600"><?php echo formatCurrency($stats['commission_earned']); ?></h3>
        </div>
        <div class="text-center p-4 bg-blue-50 rounded-lg">
            <h6 class="text-sm font-semibold text-gray-600 mb-2">Pending Commission</h6>
            <h3 class="text-3xl font-bold text-primary-600"><?php echo formatCurrency($stats['commission_pending']); ?></h3>
        </div>
        <div class="text-center p-4 bg-purple-50 rounded-lg">
            <h6 class="text-sm font-semibold text-gray-600 mb-2">Total Paid Out</h6>
            <h3 class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($stats['commission_paid']); ?></h3>
        </div>
    </div>
    
    <?php if ($stats['commission_pending'] > 0): ?>
    <div class="text-center">
        <a href="/affiliate/withdrawals.php" class="inline-flex items-center space-x-2 px-8 py-4 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
            <i class="bi bi-wallet2 text-xl"></i>
            <span>Request Withdrawal</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Sales -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-clock-history text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Recent Sales</h5>
        </div>
    </div>
    
    <div class="p-6">
        <?php if (empty($recentSales)): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="bi bi-info-circle text-blue-600 text-xl mr-3"></i>
                    <p class="text-blue-700 font-medium">No sales yet. Share your referral link to start earning commissions!</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Customer</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Template</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentSales as $sale): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <?php echo date('M d, Y', strtotime($sale['payment_confirmed_at'])); ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($sale['customer_email']); ?></div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($sale['template_name']); ?></td>
                            <td class="px-4 py-4 text-sm font-semibold text-gray-900"><?php echo formatCurrency($sale['amount_paid']); ?></td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                                    <?php echo formatCurrency($sale['commission_amount']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Cards -->
            <div class="md:hidden space-y-4">
                <?php foreach ($recentSales as $sale): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex justify-between items-start mb-3">
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                            <?php echo formatCurrency($sale['commission_amount']); ?>
                        </span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="text-gray-900 font-medium"><?php echo date('M d, Y', strtotime($sale['payment_confirmed_at'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Template:</span>
                            <span class="text-gray-900"><?php echo htmlspecialchars($sale['template_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="text-gray-900 font-semibold"><?php echo formatCurrency($sale['amount_paid']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
