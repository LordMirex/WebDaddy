<?php
/**
 * User Single Ticket Detail Page
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticketId) {
    header('Location: /user/support.php');
    exit;
}

$ticket = getTicketForCustomer($ticketId, $customer['id']);

if (!$ticket) {
    header('Location: /user/support.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply' && !in_array($ticket['status'], ['resolved', 'closed'])) {
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            $errorMessage = 'Please enter a message.';
        } elseif (strlen($message) < 10) {
            $errorMessage = 'Message must be at least 10 characters.';
        } else {
            $replyId = addTicketReply($ticketId, $customer['id'], $message);
            if ($replyId) {
                $successMessage = 'Your reply has been sent successfully.';
                $ticket = getTicketForCustomer($ticketId, $customer['id']);
            } else {
                $errorMessage = 'Failed to send reply. Please try again.';
            }
        }
    }
}

$replies = getTicketReplies($ticketId);

$page = 'support';
$pageTitle = 'Ticket #' . $ticketId;

$statusColor = match($ticket['status']) {
    'open' => 'bg-blue-100 text-blue-700 border-blue-200',
    'in_progress' => 'bg-purple-100 text-purple-700 border-purple-200',
    'awaiting_reply' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'resolved' => 'bg-green-100 text-green-700 border-green-200',
    'closed' => 'bg-gray-100 text-gray-700 border-gray-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200'
};

$priorityColor = match($ticket['priority']) {
    'low' => 'bg-gray-100 text-gray-600',
    'normal' => 'bg-blue-100 text-blue-600',
    'high' => 'bg-orange-100 text-orange-600',
    'urgent' => 'bg-red-100 text-red-600',
    default => 'bg-gray-100 text-gray-600'
};

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="/user/support.php" class="text-amber-600 hover:text-amber-700 inline-flex items-center text-sm font-medium">
            <i class="bi-arrow-left mr-2"></i>Back to Tickets
        </a>
    </div>
    
    <?php if ($successMessage): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start space-x-3">
        <i class="bi-check-circle-fill text-green-600 text-xl"></i>
        <div>
            <p class="text-green-800 font-medium"><?= htmlspecialchars($successMessage) ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start space-x-3">
        <i class="bi-exclamation-circle-fill text-red-600 text-xl"></i>
        <div>
            <p class="text-red-800 font-medium"><?= htmlspecialchars($errorMessage) ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-4 border-b">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($ticket['subject']) ?></h2>
                            <p class="text-sm text-gray-500 mt-1">Ticket #<?= $ticket['id'] ?> &middot; <?= date('M j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?></p>
                        </div>
                        <span class="px-3 py-1 text-sm font-medium rounded-full border <?= $statusColor ?>">
                            <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                        </span>
                    </div>
                </div>
                
                <div class="divide-y">
                    <div class="p-4 bg-gray-50">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-amber-600 flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?= strtoupper(substr(getCustomerName(), 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900"><?= htmlspecialchars(getCustomerName()) ?></span>
                                    <span class="text-xs text-gray-500"><?= date('M j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?></span>
                                </div>
                                <div class="mt-2 text-gray-700 whitespace-pre-wrap break-words support-content"><?= nl2br(htmlspecialchars($ticket['message'])) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php foreach ($replies as $reply): ?>
                    <?php $isAdmin = $reply['author_type'] === 'admin'; ?>
                    <div class="p-4 <?= $isAdmin ? 'bg-blue-50' : '' ?>">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full <?= $isAdmin ? 'bg-blue-600' : 'bg-amber-600' ?> flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?php if ($isAdmin): ?>
                                <i class="bi-headset text-sm"></i>
                                <?php else: ?>
                                <?= strtoupper(substr(getCustomerName(), 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900">
                                        <?= $isAdmin ? 'Support Team' : htmlspecialchars(getCustomerName()) ?>
                                    </span>
                                    <?php if ($isAdmin): ?>
                                    <span class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">Staff</span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-500"><?= date('M j, Y \a\t g:i A', strtotime($reply['created_at'])) ?></span>
                                </div>
                                <div class="mt-2 text-gray-700 whitespace-pre-wrap break-words support-content"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!in_array($ticket['status'], ['resolved', 'closed'])): ?>
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Reply to Ticket</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="reply">
                    <textarea name="message" rows="4" required minlength="10"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                              placeholder="Type your message here..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-500">Our team typically responds within 24 hours.</p>
                        <button type="submit" class="px-6 py-2 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 transition inline-flex items-center">
                            <i class="bi-send mr-2"></i>Send Reply
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
                <i class="bi-check-circle text-green-500 text-3xl mb-3"></i>
                <h3 class="font-bold text-gray-900 mb-2">Ticket Resolved</h3>
                <p class="text-gray-600 mb-4">This ticket has been closed. If you need further assistance, please create a new ticket.</p>
                <a href="/user/new-ticket.php" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                    <i class="bi-plus-circle mr-2"></i>Create New Ticket
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Ticket Details</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full <?= $statusColor ?>">
                            <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Priority</p>
                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full <?= $priorityColor ?>">
                            <?= ucfirst($ticket['priority']) ?>
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Category</p>
                        <p class="text-gray-900 font-medium mt-1"><?= ucfirst($ticket['category']) ?></p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Created</p>
                        <p class="text-gray-900 mt-1"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></p>
                    </div>
                    
                    <?php if ($ticket['last_reply_at']): ?>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Last Reply</p>
                        <p class="text-gray-900 mt-1"><?= date('M j, Y', strtotime($ticket['last_reply_at'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($ticket['order_id']): ?>
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Linked Order</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="bi-bag text-gray-500"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Order #<?= $ticket['order_id'] ?></p>
                        <a href="/user/order-detail.php?id=<?= $ticket['order_id'] ?>" class="text-sm text-amber-600 hover:underline">
                            View Order Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <i class="bi-clock text-amber-600 text-xl"></i>
                    <div>
                        <h4 class="font-semibold text-amber-800">Response Time</h4>
                        <p class="text-sm text-amber-700 mt-1">We aim to respond to all tickets within 24 hours during business days.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
