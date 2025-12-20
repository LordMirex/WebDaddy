<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
$customer = requireCustomer();

$page = 'referral';
$pageTitle = 'Refer & Earn';

$db = getDb();
$customerId = $customer['id'];

$referral = createUserReferral($customerId);
$stats = getUserReferralStats($customerId);
$recentSales = getUserReferralRecentSales($customerId, 10);
$withdrawalHistory = getUserReferralWithdrawalHistory($customerId);

// Build referral link with ref code (safe fallback in case stats is null)
$referralCode = '';
if ($stats && !empty($stats['referral']['referral_code'])) {
    $referralCode = $stats['referral']['referral_code'];
} elseif ($referral && !empty($referral['referral_code'])) {
    $referralCode = $referral['referral_code'];
}
$referralLink = SITE_URL . '/?ref=' . $referralCode;

$savedBankDetails = null;
if (!empty($customer['bank_details'])) {
    $savedBankDetails = json_decode($customer['bank_details'], true);
}

// Whitelist valid tabs to prevent XSS injection
$validTabs = ['overview', 'sales', 'withdraw', 'withdrawals'];
$activeTab = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'overview';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        if (isset($_POST['request_withdrawal'])) {
            $amount = floatval($_POST['amount'] ?? 0);
            
            $bankDetails = [
                'bank_name' => sanitizeInput($_POST['bank_name'] ?? ''),
                'account_number' => sanitizeInput($_POST['account_number'] ?? ''),
                'account_name' => sanitizeInput($_POST['account_name'] ?? '')
            ];
            
            $result = createUserReferralWithdrawal($customerId, $amount, $bankDetails);
            
            if ($result['success']) {
                $success = $result['message'];
                $stats = getUserReferralStats($customerId);
                $withdrawalHistory = getUserReferralWithdrawalHistory($customerId);
                $activeTab = 'withdrawals';
            } else {
                $error = $result['message'];
            }
        }
        
        if (isset($_POST['save_bank_details'])) {
            $bankDetails = [
                'bank_name' => sanitizeInput($_POST['bank_name'] ?? ''),
                'account_number' => sanitizeInput($_POST['account_number'] ?? ''),
                'account_name' => sanitizeInput($_POST['account_name'] ?? '')
            ];
            
            if (!empty($bankDetails['bank_name']) && !empty($bankDetails['account_number']) && !empty($bankDetails['account_name'])) {
                $stmt = $db->prepare("UPDATE customers SET bank_details = ? WHERE id = ?");
                $stmt->execute([json_encode($bankDetails), $customerId]);
                $savedBankDetails = $bankDetails;
                $success = 'Bank details saved successfully!';
            } else {
                $error = 'Please fill in all bank details.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <i class="bi bi-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
        <i class="bi bi-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <div class="bg-gradient-to-r from-amber-500 to-orange-600 rounded-2xl p-6 mb-8 text-white">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold mb-2"><i class="bi bi-gift mr-2"></i>Refer Friends & Earn Money</h2>
                <p class="opacity-90">Share your referral link and earn <strong>20% commission</strong> on every purchase!</p>
            </div>
            <div class="bg-white/20 rounded-xl p-4 backdrop-blur-sm">
                <p class="text-sm opacity-90 mb-1">Your Available Balance</p>
                <p class="text-3xl font-bold">₦<?= number_format($stats['available_balance'] ?? 0, 2) ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-900">Your Referral Link</h3>
        </div>
        <div class="p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text" id="referral-link" value="<?= htmlspecialchars($referralLink) ?>" readonly
                       class="flex-1 px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-700 focus:outline-none">
                <button onclick="copyReferralLink(event)" 
                        class="px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-semibold">
                    <i class="bi bi-clipboard mr-2"></i>Copy Link
                </button>
            </div>
            <p class="text-sm text-gray-500 mt-3">
                <i class="bi bi-info-circle mr-1"></i>Share this link with friends. When they make a purchase, you earn 20% commission!
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 text-center">
            <div class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_clicks'] ?? 0) ?></div>
            <div class="text-sm text-gray-600">Total Clicks</div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 text-center">
            <div class="text-3xl font-bold text-green-600"><?= number_format($stats['total_sales'] ?? 0) ?></div>
            <div class="text-sm text-gray-600">Total Sales</div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 text-center">
            <div class="text-3xl font-bold text-amber-600">₦<?= number_format($stats['total_earned'] ?? 0, 0) ?></div>
            <div class="text-sm text-gray-600">Total Earned</div>
        </div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4 text-center">
            <div class="text-3xl font-bold text-purple-600">₦<?= number_format($stats['total_paid'] ?? 0, 0) ?></div>
            <div class="text-sm text-gray-600">Total Paid Out</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden" x-data="{ activeTab: '<?= htmlspecialchars($activeTab) ?>' }">
        <div class="border-b">
            <nav class="flex overflow-x-auto" role="tablist">
                <button type="button" @click="activeTab = 'overview'" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors" :class="activeTab === 'overview' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-600 hover:text-gray-900'">
                    <i class="bi bi-bar-chart mr-2"></i>Overview
                </button>
                <button type="button" @click="activeTab = 'sales'" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors" :class="activeTab === 'sales' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-600 hover:text-gray-900'">
                    <i class="bi bi-cart-check mr-2"></i>Sales History
                </button>
                <button type="button" @click="activeTab = 'withdraw'" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors" :class="activeTab === 'withdraw' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-600 hover:text-gray-900'">
                    <i class="bi bi-cash-stack mr-2"></i>Request Withdrawal
                </button>
                <button type="button" @click="activeTab = 'withdrawals'" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 transition-colors" :class="activeTab === 'withdrawals' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-600 hover:text-gray-900'">
                    <i class="bi bi-clock-history mr-2"></i>Transaction History
                </button>
            </nav>
        </div>
        
        <div class="p-6">
            <div x-show="activeTab === 'overview'" x-cloak>
                <h4 class="text-lg font-bold text-gray-900 mb-4">How It Works</h4>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-share text-2xl text-amber-600"></i>
                        </div>
                        <h5 class="font-semibold mb-2">1. Share Your Link</h5>
                        <p class="text-sm text-gray-600">Copy your unique referral link and share it with friends, family, or on social media.</p>
                    </div>
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-cart-check text-2xl text-green-600"></i>
                        </div>
                        <h5 class="font-semibold mb-2">2. Friends Purchase</h5>
                        <p class="text-sm text-gray-600">When someone uses your link and makes a purchase, you earn a commission.</p>
                    </div>
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-cash text-2xl text-purple-600"></i>
                        </div>
                        <h5 class="font-semibold mb-2">3. Get Paid</h5>
                        <p class="text-sm text-gray-600">Request a withdrawal anytime. We'll send your earnings directly to your bank account.</p>
                    </div>
                </div>
                
                <div class="mt-8 p-4 bg-amber-50 rounded-xl">
                    <h5 class="font-semibold text-amber-800 mb-2"><i class="bi bi-star-fill mr-2"></i>Commission Rate</h5>
                    <p class="text-amber-700">You earn <strong>20%</strong> of every sale made through your referral link. There's no limit to how much you can earn!</p>
                </div>
            </div>
            
            <div x-show="activeTab === 'sales'" x-cloak>
                <h4 class="text-lg font-bold text-gray-900 mb-4">Recent Sales</h4>
                <?php if (empty($recentSales)): ?>
                <div class="text-center py-12 text-gray-500">
                    <i class="bi bi-cart-x text-5xl mb-4 block"></i>
                    <p>No sales yet. Share your referral link to start earning!</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-2 text-sm font-semibold text-gray-700">Date</th>
                                <th class="text-left py-3 px-2 text-sm font-semibold text-gray-700">Customer</th>
                                <th class="text-right py-3 px-2 text-sm font-semibold text-gray-700">Amount</th>
                                <th class="text-right py-3 px-2 text-sm font-semibold text-gray-700">Commission</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($recentSales as $sale): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-2 text-sm"><?= date('M d, Y', strtotime($sale['created_at'])) ?></td>
                                <td class="py-3 px-2 text-sm">
                                    <?= htmlspecialchars(substr($sale['customer_name'], 0, 3) . '***') ?>
                                </td>
                                <td class="py-3 px-2 text-sm text-right">₦<?= number_format($sale['amount_paid'], 2) ?></td>
                                <td class="py-3 px-2 text-sm text-right font-semibold text-green-600">+₦<?= number_format($sale['commission_amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div x-show="activeTab === 'withdraw'" x-cloak>
                <h4 class="text-lg font-bold text-gray-900 mb-4">Request Withdrawal</h4>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="text-center mb-6">
                            <p class="text-sm text-gray-600 mb-1">Available Balance</p>
                            <p class="text-4xl font-bold text-green-600">₦<?= number_format($stats['available_balance'] ?? 0, 2) ?></p>
                            <?php if (($stats['in_progress'] ?? 0) > 0): ?>
                            <p class="text-sm text-amber-600 mt-2">
                                <i class="bi bi-clock mr-1"></i>₦<?= number_format($stats['in_progress'], 2) ?> pending withdrawal
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (($stats['available_balance'] ?? 0) > 0): ?>
                        <form method="POST" class="space-y-4">
                            <?= csrfTokenField() ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₦)</label>
                                <input type="number" name="amount" step="0.01" min="100" max="<?= $stats['available_balance'] ?>"
                                       value="<?= $stats['available_balance'] ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                       required>
                            </div>
                            
                            <div x-data="bankSearch()">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                <div class="relative">
                                    <input type="text" 
                                           x-model="searchQuery"
                                           @input="filterBanks()"
                                           @focus="showDropdown = true; filterBanks();"
                                           @blur="setTimeout(() => showDropdown = false, 200)"
                                           @keydown.down="selectedIndex = Math.min(selectedIndex + 1, filteredBanks.length - 1)"
                                           @keydown.up="selectedIndex = Math.max(selectedIndex - 1, 0)"
                                           @keydown.enter="selectBank()"
                                           name="bank_name" 
                                           value="<?= htmlspecialchars($savedBankDetails['bank_name'] ?? '') ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                           placeholder="Click to see all banks or search..." required>
                                    <i class="bi bi-search absolute right-3 top-3.5 text-gray-400"></i>
                                    
                                    <!-- Dropdown List -->
                                    <div x-show="showDropdown && filteredBanks.length > 0" 
                                         class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto z-50 mt-1">
                                        <template x-for="(bank, index) in filteredBanks" :key="index">
                                            <div @click="selectBankItem(bank)"
                                                 :class="index === selectedIndex ? 'bg-amber-100' : 'hover:bg-gray-50'"
                                                 class="px-4 py-2 cursor-pointer border-b last:border-b-0">
                                                <p class="font-medium text-gray-900" x-text="bank.name"></p>
                                                <p class="text-xs text-gray-500" x-text="'Code: ' + bank.code"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            
                            <div x-data="accountValidator()">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                                <input type="text" 
                                       name="account_number" 
                                       value="<?= htmlspecialchars($savedBankDetails['account_number'] ?? '') ?>"
                                       @input="validateAccount($event)"
                                       @blur="checkAccount()"
                                       inputmode="numeric"
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                       :class="validationError ? 'border-red-500' : 'border-gray-300'"
                                       placeholder="10 digit account number" required maxlength="10" pattern="[0-9]{10}">
                                <div x-show="validationError" class="mt-2 p-2 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-sm text-red-700"><i class="bi bi-exclamation-circle mr-1"></i><span x-text="validationError"></span></p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                                <input type="text" name="account_name" value="<?= htmlspecialchars($savedBankDetails['account_name'] ?? '') ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                       placeholder="Name on account" required>
                            </div>
                            
                            <button type="submit" name="request_withdrawal"
                                    class="w-full px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-semibold">
                                <i class="bi bi-cash-stack mr-2"></i>Request Withdrawal
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="bi bi-wallet2 text-4xl mb-4 block"></i>
                            <p>No available balance to withdraw.</p>
                            <p class="text-sm mt-2">Share your referral link to start earning!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-blue-50 rounded-xl p-6">
                        <h5 class="font-semibold text-blue-900 mb-4"><i class="bi bi-info-circle mr-2"></i>Withdrawal Information</h5>
                        <ul class="space-y-3 text-sm text-blue-800">
                            <li class="flex items-start">
                                <i class="bi bi-check-circle text-blue-600 mr-2 mt-0.5"></i>
                                <span>Minimum withdrawal: ₦100</span>
                            </li>
                            <li class="flex items-start">
                                <i class="bi bi-check-circle text-blue-600 mr-2 mt-0.5"></i>
                                <span>Withdrawals are processed within 24-48 hours</span>
                            </li>
                            <li class="flex items-start">
                                <i class="bi bi-check-circle text-blue-600 mr-2 mt-0.5"></i>
                                <span>Funds are sent directly to your bank account</span>
                            </li>
                            <li class="flex items-start">
                                <i class="bi bi-check-circle text-blue-600 mr-2 mt-0.5"></i>
                                <span>Make sure your bank details are correct</span>
                            </li>
                        </ul>
                        
                        <?php if (!$savedBankDetails): ?>
                        <div class="mt-6 p-4 bg-amber-100 rounded-lg">
                            <p class="text-sm text-amber-800">
                                <i class="bi bi-lightbulb mr-1"></i>
                                <strong>Tip:</strong> Save your bank details in your profile for faster withdrawals.
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div x-show="activeTab === 'withdrawals'" x-cloak>
                <h4 class="text-lg font-bold text-gray-900 mb-4">Transaction History</h4>
                <?php if (empty($withdrawalHistory)): ?>
                <div class="text-center py-12 text-gray-500">
                    <i class="bi bi-clock-history text-5xl mb-4 block"></i>
                    <p>No withdrawal history yet.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-2 text-sm font-semibold text-gray-700">Date</th>
                                <th class="text-right py-3 px-2 text-sm font-semibold text-gray-700">Amount</th>
                                <th class="text-center py-3 px-2 text-sm font-semibold text-gray-700">Status</th>
                                <th class="text-left py-3 px-2 text-sm font-semibold text-gray-700">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($withdrawalHistory as $withdrawal): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-2 text-sm"><?= date('M d, Y', strtotime($withdrawal['requested_at'])) ?></td>
                                <td class="py-3 px-2 text-sm text-right font-semibold">₦<?= number_format($withdrawal['amount'], 2) ?></td>
                                <td class="py-3 px-2 text-center">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-blue-100 text-blue-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    $color = $statusColors[$withdrawal['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $color ?>">
                                        <?= ucfirst($withdrawal['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-sm text-gray-600">
                                    <?= $withdrawal['admin_notes'] ? htmlspecialchars($withdrawal['admin_notes']) : '-' ?>
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
function copyReferralLink(e) {
    const linkInput = document.getElementById('referral-link');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(linkInput.value).then(() => {
        const btn = e.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check mr-2"></i>Copied!';
        btn.classList.add('bg-green-600');
        btn.classList.remove('bg-amber-600');
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('bg-green-600');
            btn.classList.add('bg-amber-600');
        }, 2000);
    });
}

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
