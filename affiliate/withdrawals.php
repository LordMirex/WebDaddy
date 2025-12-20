<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
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

// OVERRIDE: Calculate accurate commission from sales table (single source of truth)
$stmtCommission = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total_earned FROM sales WHERE affiliate_id = ?");
$stmtCommission->execute([$affiliateId]);
$commissionData = $stmtCommission->fetch(PDO::FETCH_ASSOC);

$stmtPaid = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM withdrawal_requests WHERE affiliate_id = ? AND status = 'paid'");
$stmtPaid->execute([$affiliateId]);
$paidData = $stmtPaid->fetch(PDO::FETCH_ASSOC);

// FIX: Get all in-progress withdrawal requests (anything not paid or rejected) to subtract from available balance
// This covers 'pending' and any future intermediate statuses like 'processing' or 'approved'
$stmtInProgress = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_in_progress FROM withdrawal_requests WHERE affiliate_id = ? AND status NOT IN ('paid', 'rejected')");
$stmtInProgress->execute([$affiliateId]);
$inProgressData = $stmtInProgress->fetch(PDO::FETCH_ASSOC);

$affiliateInfo['commission_earned'] = (float)$commissionData['total_earned'];
$affiliateInfo['commission_paid'] = (float)$paidData['total_paid'];
$affiliateInfo['withdrawal_in_progress'] = (float)$inProgressData['total_in_progress'];
// FIX: Available balance = Total Earned - Paid - In-Progress Withdrawals (prevents double withdrawal)
$affiliateInfo['commission_pending'] = $affiliateInfo['commission_earned'] - $affiliateInfo['commission_paid'] - $affiliateInfo['withdrawal_in_progress'];

$error = '';
$success = '';

$stmt = $db->prepare("SELECT bank_details FROM users WHERE id = ?");
$stmt->execute([$_SESSION['affiliate_user_id']]);
$userBankInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$savedBankDetails = null;
if (!empty($userBankInfo['bank_details'])) {
    $savedBankDetails = json_decode($userBankInfo['bank_details'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['request_withdrawal']) || isset($_POST['amount']))) {
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
            // ✅ FIX: Start transaction for atomic operation
            $db->beginTransaction();
            
            // Create withdrawal request
            $stmt = $db->prepare("
                INSERT INTO withdrawal_requests (affiliate_id, amount, bank_details_json, status)
                VALUES (?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $_SESSION['affiliate_id'],
                $amount,
                $bankDetails
            ]);
            
            $withdrawalId = $db->lastInsertId('withdrawal_requests_id_seq') ?: $db->lastInsertId();
            
            // ✅ FIX: Deduct amount from commission_pending with atomic guard
            $stmt = $db->prepare("
                UPDATE affiliates 
                SET commission_pending = commission_pending - ? 
                WHERE id = ? AND commission_pending >= ?
            ");
            $stmt->execute([$amount, $_SESSION['affiliate_id'], $amount]);
            
            // ✅ FIX: Verify the update succeeded (prevents race condition)
            if ($stmt->rowCount() === 0) {
                // Rollback if balance check failed (concurrent request issue)
                $db->rollBack();
                $error = 'Insufficient balance or concurrent request detected. Please try again.';
            } else {
                // ✅ FIX: Commit transaction only if balance was successfully deducted
                $db->commit();
                
                // Log activity
                if (function_exists('logActivity')) {
                    logActivity('withdrawal_requested', "Withdrawal request #{$withdrawalId} for " . formatCurrency($amount), $_SESSION['affiliate_user_id']);
                }
                
                // Send notification email to admin
                $stmt = $db->prepare("
                    SELECT u.name, u.email
                    FROM users u
                    JOIN affiliates a ON u.id = a.user_id
                    WHERE a.id = ?
                ");
                $stmt->execute([$_SESSION['affiliate_id']]);
                $affiliateInfo_email = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($affiliateInfo_email) {
                    sendWithdrawalRequestToAdmin(
                        $affiliateInfo_email['name'],
                        $affiliateInfo_email['email'],
                        number_format($amount, 2),
                        $withdrawalId
                    );
                }
                
                // ✅ FIX: Recalculate balance properly including the new pending withdrawal
                // Re-fetch all the calculations (same logic as at the top of the page)
                $stmtCommissionRefresh = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total_earned FROM sales WHERE affiliate_id = ?");
                $stmtCommissionRefresh->execute([$affiliateId]);
                $commissionDataRefresh = $stmtCommissionRefresh->fetch(PDO::FETCH_ASSOC);
                
                $stmtPaidRefresh = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM withdrawal_requests WHERE affiliate_id = ? AND status = 'paid'");
                $stmtPaidRefresh->execute([$affiliateId]);
                $paidDataRefresh = $stmtPaidRefresh->fetch(PDO::FETCH_ASSOC);
                
                $stmtInProgressRefresh = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_in_progress FROM withdrawal_requests WHERE affiliate_id = ? AND status NOT IN ('paid', 'rejected')");
                $stmtInProgressRefresh->execute([$affiliateId]);
                $inProgressDataRefresh = $stmtInProgressRefresh->fetch(PDO::FETCH_ASSOC);
                
                $affiliateInfo['commission_earned'] = (float)$commissionDataRefresh['total_earned'];
                $affiliateInfo['commission_paid'] = (float)$paidDataRefresh['total_paid'];
                $affiliateInfo['withdrawal_in_progress'] = (float)$inProgressDataRefresh['total_in_progress'];
                $affiliateInfo['commission_pending'] = $affiliateInfo['commission_earned'] - $affiliateInfo['commission_paid'] - $affiliateInfo['withdrawal_in_progress'];
                
                $success = 'Withdrawal request submitted successfully! Reference: WD#' . $withdrawalId . '. We will process it within 24-48 hours. Your new available balance is: ' . formatCurrency($affiliateInfo['commission_pending']);
            }
            
        } catch (PDOException $e) {
            // ✅ FIX: Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error creating withdrawal request: ' . $e->getMessage());
            $error = 'Database error. Please try again later.';
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

<!-- Page Header -->
<div class="mb-8 pb-4 border-b border-gray-200">
    <div class="flex items-center space-x-3">
        <div class="w-12 h-12 bg-gradient-to-br from-green-600 to-green-800 rounded-lg flex items-center justify-center shadow-lg">
            <i class="bi bi-wallet2 text-2xl text-gold"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">Withdrawals</h1>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <!-- Available Balance -->
    <div class="bg-gradient-to-br from-primary-600 to-primary-800 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2 flex items-center">
            <i class="bi bi-cash-stack mr-2"></i> Available Balance
        </h6>
        <h2 class="text-3xl font-bold"><?php echo formatCurrency($affiliateInfo['commission_pending']); ?></h2>
    </div>
    
    <!-- Total Paid -->
    <div class="bg-gradient-to-br from-green-500 to-green-700 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2 flex items-center">
            <i class="bi bi-check-circle mr-2"></i> Total Paid
        </h6>
        <h2 class="text-3xl font-bold"><?php echo formatCurrency($affiliateInfo['commission_paid']); ?></h2>
    </div>
    
    <!-- Total Earned -->
    <div class="bg-gradient-to-br from-yellow-500 to-yellow-700 text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform">
        <h6 class="text-sm font-semibold opacity-90 mb-2 flex items-center">
            <i class="bi bi-hourglass-split mr-2"></i> Total Earned
        </h6>
        <h2 class="text-3xl font-bold"><?php echo formatCurrency($affiliateInfo['commission_earned']); ?></h2>
    </div>
</div>

<!-- Request Withdrawal Form -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6" x-data="{ showForm: false }">
    <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-4 text-white">
        <h5 class="text-xl font-bold flex items-center">
            <i class="bi bi-plus-circle mr-2"></i> Request Withdrawal
        </h5>
    </div>
    
    <div class="p-6">
        <?php if ($error): ?>
        <div x-data="{ show: true }" x-show="show" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-4 relative">
            <button @click="show = false" class="absolute top-4 right-4 text-red-500 hover:text-red-700">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="flex items-center pr-8">
                <i class="bi bi-exclamation-triangle text-red-600 text-xl mr-3"></i>
                <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div x-data="{ show: true }" x-show="show" class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-4 relative">
            <button @click="show = false" class="absolute top-4 right-4 text-green-500 hover:text-green-700">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="flex items-center pr-8">
                <i class="bi bi-check-circle text-green-600 text-xl mr-3"></i>
                <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($affiliateInfo['commission_pending'] <= 0): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="bi bi-info-circle text-blue-600 text-xl mr-3"></i>
                    <p class="text-blue-700">You don't have any pending commission available for withdrawal.</p>
                </div>
            </div>
        <?php else: ?>
            <?php if ($savedBankDetails): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-4">
                <div class="flex items-start">
                    <i class="bi bi-check-circle text-green-600 text-xl mr-3 mt-0.5"></i>
                    <div>
                        <p class="text-green-700 font-bold">Bank details loaded from settings!</p>
                        <p class="text-sm text-green-600 mt-1">
                            Payment will be sent to: <?php echo htmlspecialchars($savedBankDetails['bank_name']); ?> - <?php echo htmlspecialchars($savedBankDetails['account_number']); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg mb-4">
                <div class="flex items-start">
                    <i class="bi bi-exclamation-triangle text-yellow-600 text-xl mr-3 mt-0.5"></i>
                    <div>
                        <p class="text-yellow-700 font-bold">No saved bank details</p>
                        <p class="text-sm text-yellow-600 mt-1">
                            You can <a href="/affiliate/settings.php" class="underline hover:text-yellow-800">save your bank details in settings</a> 
                            to make future withdrawals faster!
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
                    
            <form method="POST" action="" x-data="{ submitting: false }" @submit="submitting = true">
                <?php if ($savedBankDetails): ?>
                    <div class="mb-6">
                        <label for="amount" class="block text-lg font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="bi bi-currency-exchange mr-2"></i> How much would you like to withdraw?
                        </label>
                        <input type="number" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors text-lg" 
                               id="amount" 
                               name="amount" 
                               step="0.01"
                               max="<?php echo $affiliateInfo['commission_pending']; ?>"
                               placeholder="Enter amount to withdraw"
                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                               required
                               autofocus>
                        <p class="text-sm text-gray-600 mt-2">
                            Available balance: <strong class="text-primary-700"><?php echo formatCurrency($affiliateInfo['commission_pending']); ?></strong>
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                        <h6 class="font-bold text-gray-900 mb-3">Payment will be sent to:</h6>
                        <div class="space-y-2 text-sm">
                            <p class="text-gray-700"><strong class="text-gray-900">Bank:</strong> <?php echo htmlspecialchars($savedBankDetails['bank_name']); ?></p>
                            <p class="text-gray-700"><strong class="text-gray-900">Account Number:</strong> <?php echo htmlspecialchars($savedBankDetails['account_number']); ?></p>
                            <p class="text-gray-700"><strong class="text-gray-900">Account Name:</strong> <?php echo htmlspecialchars($savedBankDetails['account_name']); ?></p>
                        </div>
                        <a href="/affiliate/settings.php" class="text-sm text-primary-600 hover:text-primary-800 underline mt-2 inline-block">
                            Change bank details
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="bi bi-currency-exchange mr-2"></i> Withdrawal Amount
                            </label>
                            <input type="number" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01"
                                   max="<?php echo $affiliateInfo['commission_pending']; ?>"
                                   placeholder="Enter amount"
                                   value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                   required>
                            <p class="text-sm text-gray-600 mt-1">
                                Available: <?php echo formatCurrency($affiliateInfo['commission_pending']); ?>
                            </p>
                        </div>
                        
                        <div x-data="bankSearch()">
                            <label for="bank_name" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="bi bi-bank mr-2"></i> Bank Name
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       x-model="searchQuery"
                                       @input="filterBanks()"
                                       @focus="showDropdown = true; filterBanks();"
                                       @blur="setTimeout(() => showDropdown = false, 200)"
                                       @keydown.down="selectedIndex = Math.min(selectedIndex + 1, filteredBanks.length - 1)"
                                       @keydown.up="selectedIndex = Math.max(selectedIndex - 1, 0)"
                                       @keydown.enter="selectBank()"
                                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                       id="bank_name" 
                                       name="bank_name" 
                                       placeholder="Click to see all banks or search..."
                                       value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>"
                                       required>
                                <i class="bi bi-search absolute right-3 top-2.5 text-gray-400"></i>
                                
                                <!-- Dropdown List -->
                                <div x-show="showDropdown && filteredBanks.length > 0" 
                                     class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto z-50 mt-1">
                                    <template x-for="(bank, index) in filteredBanks" :key="index">
                                        <div @click="selectBankItem(bank)"
                                             :class="index === selectedIndex ? 'bg-primary-100' : 'hover:bg-gray-50'"
                                             class="px-4 py-2 cursor-pointer border-b last:border-b-0">
                                            <p class="font-medium text-gray-900" x-text="bank.name"></p>
                                            <p class="text-xs text-gray-500" x-text="'Code: ' + bank.code"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        
                        <div x-data="accountValidator()">
                            <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="bi bi-credit-card mr-2"></i> Account Number
                            </label>
                            <input type="text" 
                                   class="w-full px-4 py-2 border-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                   :class="validationError ? 'border-red-500' : 'border-gray-300'"
                                   id="account_number" 
                                   name="account_number" 
                                   placeholder="Enter 10 digit account number"
                                   @input="validateAccount($event)"
                                   @blur="checkAccount()"
                                   inputmode="numeric"
                                   value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>"
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   required>
                            <div x-show="validationError" class="mt-2 p-2 bg-red-50 rounded-lg border border-red-200">
                                <p class="text-sm text-red-700"><i class="bi bi-exclamation-circle mr-1"></i><span x-text="validationError"></span></p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="account_name" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="bi bi-person mr-2"></i> Account Name
                            </label>
                            <input type="text" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                                   id="account_name" 
                                   name="account_name" 
                                   placeholder="Enter account name"
                                   value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" 
                        name="request_withdrawal" 
                        :disabled="submitting"
                        class="w-full px-6 py-4 bg-gradient-to-r from-primary-600 to-primary-800 hover:from-primary-700 hover:to-primary-900 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center space-x-2">
                    <i class="bi bi-send text-lg" x-show="!submitting"></i>
                    <i class="bi bi-arrow-repeat animate-spin text-lg" x-show="submitting" style="display: none;"></i>
                    <span x-text="submitting ? 'Submitting...' : 'Submit Withdrawal Request'">Submit Withdrawal Request</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Withdrawal History -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-clock-history text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Withdrawal History</h5>
        </div>
    </div>
    
    <?php if (empty($withdrawalRequests)): ?>
    <div class="p-12 text-center">
        <i class="bi bi-inbox text-6xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-600 font-medium">No withdrawal requests yet.</p>
    </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Bank Details</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Processed Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($withdrawalRequests as $request): ?>
                        <?php $bankDetails = json_decode($request['bank_details_json'], true); ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <?php echo date('M d, Y', strtotime($request['requested_at'])); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-sm font-bold text-gray-900"><?php echo formatCurrency($request['amount']); ?></span>
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($bankDetails['bank_name'] ?? 'N/A'); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($bankDetails['account_number'] ?? 'N/A'); ?><br>
                                    <?php echo htmlspecialchars($bankDetails['account_name'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <?php 
                                switch ($request['status']) {
                                    case 'pending':
                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 whitespace-nowrap"><i class="bi bi-clock"></i><span class="hidden sm:inline sm:ml-1">Pending</span></span>';
                                        break;
                                    case 'approved':
                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 whitespace-nowrap"><i class="bi bi-check-circle"></i><span class="hidden sm:inline sm:ml-1">Approved</span></span>';
                                        break;
                                    case 'paid':
                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 whitespace-nowrap"><i class="bi bi-check2-circle"></i><span class="hidden sm:inline sm:ml-1">Paid</span></span>';
                                        break;
                                    case 'rejected':
                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 whitespace-nowrap"><i class="bi bi-x-circle"></i><span class="hidden sm:inline sm:ml-1">Rejected</span></span>';
                                        break;
                                    default:
                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 whitespace-nowrap">' . htmlspecialchars($request['status']) . '</span>';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                <?php if ($request['processed_at']): ?>
                                    <?php echo date('M d, Y', strtotime($request['processed_at'])); ?>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($request['admin_notes'])): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-3 bg-blue-50">
                                    <div class="text-sm">
                                        <strong class="text-gray-900">Admin Notes:</strong> 
                                        <span class="text-gray-700"><?php echo htmlspecialchars($request['admin_notes']); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards -->
        <div class="lg:hidden p-4 space-y-4">
            <?php foreach ($withdrawalRequests as $request): ?>
                <?php $bankDetails = json_decode($request['bank_details_json'], true); ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-sm text-gray-600"><?php echo date('M d, Y', strtotime($request['requested_at'])); ?></span>
                        <?php 
                        switch ($request['status']) {
                            case 'pending':
                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 whitespace-nowrap"><i class="bi bi-clock mr-1"></i> Pending</span>';
                                break;
                            case 'approved':
                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 whitespace-nowrap"><i class="bi bi-check-circle mr-1"></i> Approved</span>';
                                break;
                            case 'paid':
                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 whitespace-nowrap"><i class="bi bi-check2-circle mr-1"></i> Paid</span>';
                                break;
                            case 'rejected':
                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 whitespace-nowrap"><i class="bi bi-x-circle mr-1"></i> Rejected</span>';
                                break;
                            default:
                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 whitespace-nowrap">' . htmlspecialchars($request['status']) . '</span>';
                        }
                        ?>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between border-b border-gray-300 pb-2">
                            <span class="text-gray-600 font-semibold">Amount:</span>
                            <span class="text-gray-900 font-bold"><?php echo formatCurrency($request['amount']); ?></span>
                        </div>
                        <div>
                            <div class="text-gray-600 font-semibold mb-1">Bank Details:</div>
                            <div class="text-gray-900"><?php echo htmlspecialchars($bankDetails['bank_name'] ?? 'N/A'); ?></div>
                            <div class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($bankDetails['account_number'] ?? 'N/A'); ?><br>
                                <?php echo htmlspecialchars($bankDetails['account_name'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <?php if ($request['processed_at']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Processed:</span>
                            <span class="text-gray-900"><?php echo date('M d, Y', strtotime($request['processed_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($request['admin_notes'])): ?>
                        <div class="mt-3 pt-3 border-t border-gray-300">
                            <div class="text-sm">
                                <strong class="text-gray-900">Admin Notes:</strong> 
                                <span class="text-gray-700"><?php echo htmlspecialchars($request['admin_notes']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Bank Dropdown Component
function bankSearch() {
    return {
        banks: [],
        filteredBanks: [],
        searchQuery: '',
        showDropdown: false,
        selectedIndex: 0,
        
        async init() {
            try {
                const response = await fetch('/assets/data/banks.json');
                this.banks = await response.json();
                this.filteredBanks = this.banks;
            } catch (e) {
                console.error('Error loading banks:', e);
            }
        },
        
        filterBanks() {
            const query = this.searchQuery.toLowerCase().trim();
            this.selectedIndex = 0;
            if (!query) {
                this.filteredBanks = this.banks;
            } else {
                this.filteredBanks = this.banks.filter(bank => 
                    bank.name.toLowerCase().includes(query) || 
                    bank.code.includes(query)
                );
            }
        },
        
        selectBankItem(bank) {
            this.searchQuery = bank.name;
            this.showDropdown = false;
            // Keep filteredBanks populated so dropdown can be opened again
            this.filterBanks();
        },
        
        selectBank() {
            if (this.filteredBanks.length > 0 && this.selectedIndex >= 0) {
                this.selectBankItem(this.filteredBanks[this.selectedIndex]);
            }
        }
    };
}

// Account Number Validator Component
function accountValidator() {
    return {
        validationError: '',
        
        validateAccount(event) {
            const input = event.target;
            // Only allow numbers
            input.value = input.value.replace(/[^0-9]/g, '');
            
            if (input.value.length > 0 && input.value.length < 10) {
                this.validationError = '';
            }
        },
        
        checkAccount() {
            const accountNumber = document.querySelector('input[name="account_number"]').value;
            if (!accountNumber) {
                this.validationError = '';
                return;
            }
            
            if (accountNumber.length !== 10) {
                this.validationError = `Account number must be 10 digits (currently ${accountNumber.length})`;
            } else if (!/^[0-9]{10}$/.test(accountNumber)) {
                this.validationError = 'Account number must contain only digits';
            } else {
                this.validationError = '';
            }
        }
    };
}

// Initialize on DOM ready
document.addEventListener('alpine:init', () => {
    console.log('Bank dropdown initialized');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
