<?php
/**
 * User Support Tickets List
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'support';
$pageTitle = 'Support';

$status = isset($_GET['status']) && in_array($_GET['status'], ['all', 'open', 'awaiting_reply', 'resolved']) ? $_GET['status'] : 'all';
$tickets = getCustomerTickets($customer['id'], $status);

$db = getDb();
$stats = [
    'all' => $db->prepare("SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = ?")->execute([$customer['id']]) ? $db->query("SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = {$customer['id']}")->fetchColumn() : 0,
    'open' => $db->query("SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = {$customer['id']} AND status NOT IN ('resolved', 'closed')")->fetchColumn(),
    'awaiting_reply' => $db->query("SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = {$customer['id']} AND status = 'awaiting_reply'")->fetchColumn(),
    'resolved' => $db->query("SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = {$customer['id']} AND status IN ('resolved', 'closed')")->fetchColumn()
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <p class="text-gray-500">Need help? View your support tickets or create a new one.</p>
        <a href="/user/new-ticket.php" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition font-medium">
            <i class="bi-plus-circle mr-2"></i>New Ticket
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <a href="?status=all" 
           class="p-4 rounded-xl border-2 transition <?= $status === 'all' ? 'border-amber-500 bg-amber-50' : 'bg-white border-gray-100 hover:border-amber-200' ?>">
            <p class="text-2xl font-bold <?= $status === 'all' ? 'text-amber-600' : 'text-gray-900' ?>"><?= $stats['all'] ?></p>
            <p class="text-sm <?= $status === 'all' ? 'text-amber-700' : 'text-gray-500' ?>">All Tickets</p>
        </a>
        <a href="?status=open" 
           class="p-4 rounded-xl border-2 transition <?= $status === 'open' ? 'border-blue-500 bg-blue-50' : 'bg-white border-gray-100 hover:border-blue-200' ?>">
            <p class="text-2xl font-bold <?= $status === 'open' ? 'text-blue-600' : 'text-gray-900' ?>"><?= $stats['open'] ?></p>
            <p class="text-sm <?= $status === 'open' ? 'text-blue-700' : 'text-gray-500' ?>">Open</p>
        </a>
        <a href="?status=awaiting_reply" 
           class="p-4 rounded-xl border-2 transition <?= $status === 'awaiting_reply' ? 'border-yellow-500 bg-yellow-50' : 'bg-white border-gray-100 hover:border-yellow-200' ?>">
            <p class="text-2xl font-bold <?= $status === 'awaiting_reply' ? 'text-yellow-600' : 'text-gray-900' ?>"><?= $stats['awaiting_reply'] ?></p>
            <p class="text-sm <?= $status === 'awaiting_reply' ? 'text-yellow-700' : 'text-gray-500' ?>">Awaiting Reply</p>
        </a>
        <a href="?status=resolved" 
           class="p-4 rounded-xl border-2 transition <?= $status === 'resolved' ? 'border-green-500 bg-green-50' : 'bg-white border-gray-100 hover:border-green-200' ?>">
            <p class="text-2xl font-bold <?= $status === 'resolved' ? 'text-green-600' : 'text-gray-900' ?>"><?= $stats['resolved'] ?></p>
            <p class="text-sm <?= $status === 'resolved' ? 'text-green-700' : 'text-gray-500' ?>">Resolved</p>
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-4 border-b">
            <h2 class="font-bold text-gray-900">
                <?= $status !== 'all' ? ucfirst(str_replace('_', ' ', $status)) . ' ' : '' ?>Tickets
            </h2>
        </div>
        
        <?php if (empty($tickets)): ?>
        <div class="p-8 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi-chat-dots text-gray-400 text-2xl"></i>
            </div>
            <p class="text-gray-500 mb-4">No tickets found<?= $status !== 'all' ? ' with this status' : '' ?>.</p>
            <a href="/user/new-ticket.php" class="inline-block px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                Create New Ticket
            </a>
        </div>
        <?php else: ?>
        <div class="divide-y">
            <?php foreach ($tickets as $ticket): ?>
            <?php
                switch($ticket['status']) {
                    case 'open': $statusColor = 'bg-blue-100 text-blue-700'; break;
                    case 'in_progress': $statusColor = 'bg-purple-100 text-purple-700'; break;
                    case 'awaiting_reply': $statusColor = 'bg-yellow-100 text-yellow-700'; break;
                    case 'resolved': $statusColor = 'bg-green-100 text-green-700'; break;
                    case 'closed': $statusColor = 'bg-gray-100 text-gray-700'; break;
                    default: $statusColor = 'bg-gray-100 text-gray-700';
                }
                
                switch($ticket['category']) {
                    case 'order': $categoryIcon = 'bi-bag'; break;
                    case 'delivery': $categoryIcon = 'bi-truck'; break;
                    case 'refund': $categoryIcon = 'bi-arrow-counterclockwise'; break;
                    case 'technical': $categoryIcon = 'bi-gear'; break;
                    case 'account': $categoryIcon = 'bi-person'; break;
                    default: $categoryIcon = 'bi-question-circle';
                }
                
                $hasNewReply = $ticket['last_reply_by'] === 'admin' && 
                               $ticket['status'] !== 'resolved' && 
                               $ticket['status'] !== 'closed';
            ?>
            <a href="/user/ticket.php?id=<?= $ticket['id'] ?>" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="<?= $categoryIcon ?> text-gray-500"></i>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($ticket['subject']) ?></h3>
                                    <?php if ($hasNewReply): ?>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-500 text-white">New Reply</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 mt-1">
                                    #<?= $ticket['id'] ?> &middot; <?= ucfirst($ticket['category']) ?>
                                    <?php if ($ticket['order_id']): ?>
                                    &middot; Order #<?= $ticket['order_id'] ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statusColor ?> flex-shrink-0">
                                <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                            </span>
                        </div>
                        
                        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?= htmlspecialchars(substr($ticket['message'], 0, 150)) ?><?= strlen($ticket['message']) > 150 ? '...' : '' ?></p>
                        
                        <p class="text-xs text-gray-400 mt-2">
                            <i class="bi-clock mr-1"></i>
                            <?= date('M j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
                            <?php if ($ticket['last_reply_at']): ?>
                            &middot; Last reply <?= date('M j', strtotime($ticket['last_reply_at'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <i class="bi-chevron-right text-gray-400 flex-shrink-0"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
