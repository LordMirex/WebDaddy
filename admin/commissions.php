<?php
$pageTitle = 'Commission Management';

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

// Handle withdrawal processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_payout') {
        $withdrawalId = intval($_POST['withdrawal_id']);
        $result = processCommissionPayout($withdrawalId);
        if ($result['success']) {
            $successMessage = $result['message'];
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// Get commission report
$report = getCommissionReport();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-wallet2 text-primary-600"></i> Commission Management
    </h1>
    <p class="text-gray-600 mt-2">Monitor affiliate commissions, withdrawals, and payouts</p>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg mb-6">
    <i class="bi bi-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<!-- Commission Totals -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Pending</h6>
            <i class="bi bi-hourglass-split text-2xl text-yellow-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900">₦<?php echo number_format($report['totals']['total_pending'] ?? 0, 2); ?></div>
        <small class="text-gray-500">Ready for payout</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Earned</h6>
            <i class="bi bi-graph-up text-2xl text-green-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900">₦<?php echo number_format($report['totals']['total_earned'] ?? 0, 2); ?></div>
        <small class="text-gray-500">All time commission</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Paid</h6>
            <i class="bi bi-check-circle text-2xl text-blue-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900">₦<?php echo number_format($report['totals']['total_paid'] ?? 0, 2); ?></div>
        <small class="text-gray-500">Paid out to affiliates</small>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Pending Withdrawals</h6>
            <i class="bi bi-inbox text-2xl text-purple-600"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?php echo count($report['pending_withdrawals'] ?? []); ?></div>
        <small class="text-gray-500">Awaiting processing</small>
    </div>
</div>

<!-- Top Earning Affiliates -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-trophy text-primary-600"></i> Top Earning Affiliates
        </h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Affiliate</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Commission Earned</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Pending</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['top_earners'] ?? [] as $affiliate): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($affiliate['code']); ?></td>
                    <td class="px-6 py-4 text-sm text-right text-gray-900">₦<?php echo number_format($affiliate['commission_earned'], 2); ?></td>
                    <td class="px-6 py-4 text-sm text-right text-gray-900">₦<?php echo number_format($affiliate['commission_pending'], 2); ?></td>
                    <td class="px-6 py-4 text-sm text-right text-gray-900"><?php echo $affiliate['total_sales']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Withdrawals -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-inbox text-primary-600"></i> Pending Withdrawal Requests
        </h5>
    </div>
    <?php if (empty($report['pending_withdrawals'])): ?>
    <div class="px-6 py-8 text-center text-gray-500">No pending withdrawal requests</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Affiliate</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Requested</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['pending_withdrawals'] as $withdrawal): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($withdrawal['code']); ?></td>
                    <td class="px-6 py-4 text-sm text-right text-gray-900 font-semibold">₦<?php echo number_format($withdrawal['amount_requested'], 2); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($withdrawal['requested_at'])); ?></td>
                    <td class="px-6 py-4 text-sm">
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="process_payout">
                            <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                            <button type="submit" class="text-white bg-green-600 hover:bg-green-700 px-3 py-2 rounded text-xs font-semibold transition-colors">
                                <i class="bi bi-check-circle mr-1"></i>Process
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Payouts -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-check-circle text-primary-600"></i> Recent Payouts
        </h5>
    </div>
    <?php if (empty($report['recent_payouts'])): ?>
    <div class="px-6 py-8 text-center text-gray-500">No payouts processed yet</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Affiliate</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Processed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['recent_payouts'] as $payout): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payout['code']); ?></td>
                    <td class="px-6 py-4 text-sm text-right text-gray-900 font-semibold">₦<?php echo number_format($payout['amount_requested'], 2); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($payout['processed_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
