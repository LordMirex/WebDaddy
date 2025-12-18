<?php
$pageTitle = 'User Referral Withdrawals';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'process_withdrawal') {
            $requestId = intval($_POST['request_id']);
            $withdrawalStatus = sanitizeInput($_POST['withdrawal_status']);
            $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT * FROM user_referral_withdrawals WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request || $request['status'] !== 'pending') {
                    throw new Exception('Invalid withdrawal request.');
                }
                
                $stmt = $db->prepare("UPDATE user_referral_withdrawals SET status = ?, admin_notes = ?, processed_at = datetime('now'), processed_by = ? WHERE id = ?");
                $stmt->execute([$withdrawalStatus, $adminNotes, getAdminId(), $requestId]);
                
                $db->commit();
                
                $stmt = $db->prepare("
                    SELECT c.email, c.username 
                    FROM user_referrals ur
                    JOIN customers c ON ur.customer_id = c.id
                    WHERE ur.id = ?
                ");
                $stmt->execute([$request['referral_id']]);
                $customerUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customerUser) {
                    $statusText = $withdrawalStatus === 'paid' ? 'approved and paid' : 'rejected';
                    $subject = "Referral Withdrawal Request " . ucfirst($withdrawalStatus);
                    $body = "
                        <h2>Referral Withdrawal Update</h2>
                        <p>Hello " . htmlspecialchars($customerUser['username']) . ",</p>
                        <p>Your referral withdrawal request for <strong>" . formatCurrency($request['amount']) . "</strong> has been <strong>$statusText</strong>.</p>
                        " . ($adminNotes ? "<p><strong>Admin notes:</strong> " . htmlspecialchars($adminNotes) . "</p>" : "") . "
                        <p>Thank you for being a valued referrer!</p>
                    ";
                    sendUserEmail($customerUser['email'], $subject, $body, 'withdrawal_update');
                }
                
                $successMessage = "Withdrawal request #$requestId has been $withdrawalStatus.";
                logActivity('user_referral_withdrawal_processed', "User referral withdrawal #$requestId: $withdrawalStatus", getAdminId());
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $whereClause .= " AND urw.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereClause .= " AND (c.email LIKE ? OR c.username LIKE ? OR ur.referral_code LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$perPage = 25;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = "
    SELECT COUNT(*) 
    FROM user_referral_withdrawals urw
    JOIN user_referrals ur ON urw.referral_id = ur.id
    JOIN customers c ON ur.customer_id = c.id
    $whereClause
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalWithdrawals = $countStmt->fetchColumn();
$totalPages = ceil($totalWithdrawals / $perPage);

$sql = "
    SELECT 
        urw.*,
        urw.referral_id,
        ur.referral_code,
        c.email as customer_email,
        c.username as customer_username,
        c.phone as customer_phone
    FROM user_referral_withdrawals urw
    JOIN user_referrals ur ON urw.referral_id = ur.id
    JOIN customers c ON ur.customer_id = c.id
    $whereClause
    ORDER BY urw.requested_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount
    FROM user_referral_withdrawals
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-cash-stack text-blue-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></div>
                <div class="text-xs text-gray-500">Total Requests</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-clock text-yellow-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['pending']); ?></div>
                <div class="text-xs text-gray-500">Pending</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-check-circle text-green-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['paid']); ?></div>
                <div class="text-xs text-gray-500">Paid</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-hourglass text-orange-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($stats['pending_amount']); ?></div>
                <div class="text-xs text-gray-500">Pending Amount</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-wallet2 text-purple-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($stats['paid_amount']); ?></div>
                <div class="text-xs text-gray-500">Total Paid</div>
            </div>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo $successMessage; ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
    <i class="bi bi-x-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                   placeholder="Search by email, username, or referral code..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        </div>
        <div class="w-48">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                <i class="bi bi-search mr-1"></i> Filter
            </button>
            <a href="/admin/user-referral-withdrawals.php" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                <i class="bi bi-x-circle mr-1"></i> Clear
            </a>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-share text-primary-600"></i> User Referral Withdrawals
            <span class="text-sm font-normal text-gray-500">(<?php echo number_format($totalWithdrawals); ?> total)</span>
        </h3>
    </div>
    
    <?php if (empty($withdrawals)): ?>
    <div class="p-8 text-center text-gray-500">
        <i class="bi bi-inbox text-4xl mb-3 block"></i>
        <p>No withdrawal requests found.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-gray-300 bg-gray-50">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Customer</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Referral Code</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Amount</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Payment Info</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Requested</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($withdrawal['customer_username']); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($withdrawal['customer_email']); ?></div>
                        <?php if ($withdrawal['customer_phone']): ?>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($withdrawal['customer_phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <code class="bg-gray-100 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($withdrawal['referral_code']); ?></code>
                    </td>
                    <td class="py-3 px-4 font-bold text-gray-900">
                        <?php echo formatCurrency($withdrawal['amount']); ?>
                    </td>
                    <td class="py-3 px-4 text-sm">
                        <?php 
                        $bankDetails = json_decode($withdrawal['bank_details_json'] ?? '{}', true) ?: [];
                        ?>
                        <?php if (!empty($bankDetails['bank_name'])): ?>
                        <div><strong>Bank:</strong> <?php echo htmlspecialchars($bankDetails['bank_name']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($bankDetails['account_number'])): ?>
                        <div><strong>Acct:</strong> <?php echo htmlspecialchars($bankDetails['account_number']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($bankDetails['account_name'])): ?>
                        <div><strong>Name:</strong> <?php echo htmlspecialchars($bankDetails['account_name']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $statusColor = $statusColors[$withdrawal['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                            <?php echo ucfirst($withdrawal['status']); ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-sm text-gray-500">
                        <?php echo date('M j, Y g:ia', strtotime($withdrawal['requested_at'])); ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php if ($withdrawal['status'] === 'pending'): ?>
                        <button type="button" 
                                onclick="openProcessModal(<?php echo $withdrawal['id']; ?>, '<?php echo formatCurrency($withdrawal['amount']); ?>', '<?php echo htmlspecialchars($withdrawal['customer_username']); ?>')"
                                class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-sm rounded-lg transition-colors">
                            <i class="bi bi-gear mr-1"></i> Process
                        </button>
                        <?php else: ?>
                        <span class="text-gray-400 text-sm">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
        <div class="text-sm text-gray-500">
            Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalWithdrawals); ?> of <?php echo $totalWithdrawals; ?>
        </div>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="processModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">Process Withdrawal</h3>
            <button onclick="closeProcessModal()" class="text-gray-400 hover:text-gray-600">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="process_withdrawal">
            <input type="hidden" name="request_id" id="modal_request_id">
            <div class="p-6 space-y-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Customer</div>
                    <div class="font-bold text-gray-900" id="modal_customer_name"></div>
                    <div class="text-lg font-bold text-primary-600 mt-1" id="modal_amount"></div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="withdrawal_status" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">Select status...</option>
                        <option value="paid">Mark as Paid</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admin Notes (optional)</label>
                    <textarea name="admin_notes" rows="3" 
                              placeholder="Add notes about this withdrawal..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" onclick="closeProcessModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
                    Process Withdrawal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openProcessModal(requestId, amount, customerName) {
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_amount').textContent = amount;
    document.getElementById('modal_customer_name').textContent = customerName;
    document.getElementById('processModal').classList.remove('hidden');
}

function closeProcessModal() {
    document.getElementById('processModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
