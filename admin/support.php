<?php
$pageTitle = 'Support Tickets';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if ($ticketId && !empty($message)) {
            try {
                $stmt = $db->prepare("SELECT st.subject, u.name as affiliate_name, u.email as affiliate_email
                                      FROM support_tickets st
                                      JOIN affiliates a ON st.affiliate_id = a.id
                                      JOIN users u ON a.user_id = u.id
                                      WHERE st.id = ?");
                $stmt->execute([$ticketId]);
                $ticketInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 1, ?)");
                $stmt->execute([$ticketId, getAdminId(), $message]);
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$ticketId]);
                
                if ($ticketInfo) {
                    @sendSupportTicketReplyEmail(
                        $ticketInfo['affiliate_name'],
                        $ticketInfo['affiliate_email'],
                        $ticketId,
                        $ticketInfo['subject'],
                        $message
                    );
                }
                
                $successMessage = "Reply sent successfully! Email notification sent to affiliate.";
                logActivity('ticket_replied', "Replied to ticket #$ticketId", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Failed to send reply: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'close_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        if ($ticketId) {
            try {
                $stmt = $db->prepare("SELECT st.subject, u.name as affiliate_name, u.email as affiliate_email
                                      FROM support_tickets st
                                      JOIN affiliates a ON st.affiliate_id = a.id
                                      JOIN users u ON a.user_id = u.id
                                      WHERE st.id = ?");
                $stmt->execute([$ticketId]);
                $ticketInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$ticketId]);
                
                if ($ticketInfo) {
                    @sendSupportTicketClosedEmail(
                        $ticketInfo['affiliate_name'],
                        $ticketInfo['affiliate_email'],
                        $ticketId,
                        $ticketInfo['subject']
                    );
                }
                
                $successMessage = "Ticket closed successfully! Email notification sent to affiliate.";
                logActivity('ticket_closed', "Closed ticket #$ticketId", getAdminId());
            } catch (PDOException $e) {
                $errorMessage = 'Failed to close ticket: ' . $e->getMessage();
            }
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';

$sql = "SELECT st.*, a.id as aff_id, u.name as affiliate_name, u.email as affiliate_email
        FROM support_tickets st
        JOIN affiliates a ON st.affiliate_id = a.id
        JOIN users u ON a.user_id = u.id";

if ($statusFilter !== 'all') {
    $sql .= " WHERE st.status = :status";
}

$sql .= " ORDER BY st.updated_at DESC";

$stmt = $db->prepare($sql);
if ($statusFilter !== 'all') {
    $stmt->execute([':status' => $statusFilter]);
} else {
    $stmt->execute();
}
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'open' => $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn(),
    'in_progress' => $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'in_progress'")->fetchColumn(),
    'closed' => $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'closed'")->fetchColumn()
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-headset text-primary-600"></i> Support Tickets
    </h1>
    <p class="text-gray-600 mt-2">Manage affiliate support requests</p>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg">
    ✅ <?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg">
    ❌ <?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <a href="?status=open" class="bg-white rounded-xl shadow-md p-6 border-2 <?php echo $statusFilter === 'open' ? 'border-blue-500' : 'border-gray-100'; ?> hover:border-blue-300 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-semibold">Open Tickets</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats['open']; ?></p>
            </div>
            <i class="bi bi-inbox text-4xl text-blue-600"></i>
        </div>
    </a>
    
    <a href="?status=in_progress" class="bg-white rounded-xl shadow-md p-6 border-2 <?php echo $statusFilter === 'in_progress' ? 'border-yellow-500' : 'border-gray-100'; ?> hover:border-yellow-300 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-semibold">In Progress</p>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['in_progress']; ?></p>
            </div>
            <i class="bi bi-hourglass-split text-4xl text-yellow-600"></i>
        </div>
    </a>
    
    <a href="?status=closed" class="bg-white rounded-xl shadow-md p-6 border-2 <?php echo $statusFilter === 'closed' ? 'border-green-500' : 'border-gray-100'; ?> hover:border-green-300 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-semibold">Closed</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['closed']; ?></p>
            </div>
            <i class="bi bi-check-circle text-4xl text-green-600"></i>
        </div>
    </a>
</div>

<div class="bg-white rounded-xl shadow-md">
    <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="text-xl font-bold">
            <?php echo $statusFilter !== 'all' ? ucfirst($statusFilter) . ' ' : 'All '; ?>Tickets (<?php echo count($tickets); ?>)
        </h2>
        <a href="?status=all" class="text-primary-600 hover:underline text-sm">View All</a>
    </div>
    
    <div class="p-6">
        <?php if (empty($tickets)): ?>
            <p class="text-gray-500 text-center py-8">No tickets found</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    $replies = $db->prepare("SELECT tr.*, u.name FROM ticket_replies tr LEFT JOIN users u ON tr.user_id = u.id WHERE ticket_id = ? ORDER BY created_at ASC");
                    $replies->execute([$ticket['id']]);
                    $ticketReplies = $replies->fetchAll(PDO::FETCH_ASSOC);
                    
                    $statusColors = ['open' => 'blue', 'in_progress' => 'yellow', 'closed' => 'green'];
                    $priorityColors = ['low' => 'gray', 'normal' => 'blue', 'high' => 'red'];
                    $color = $statusColors[$ticket['status']] ?? 'gray';
                    $priColor = $priorityColors[$ticket['priority']] ?? 'gray';
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4" x-data="{ showDetails: false }">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="font-bold text-lg">#{<?php echo $ticket['id']; ?>} <?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                    <span class="px-2 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 rounded text-xs font-semibold"><?php echo $ticket['status']; ?></span>
                                    <span class="px-2 py-1 bg-<?php echo $priColor; ?>-100 text-<?php echo $priColor; ?>-800 rounded text-xs font-semibold"><?php echo $ticket['priority']; ?></span>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($ticket['affiliate_name']); ?> 
                                    (<?php echo htmlspecialchars($ticket['affiliate_email']); ?>)
                                </p>
                                <p class="text-sm text-gray-500">
                                    <i class="bi bi-clock"></i> <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?>
                                </p>
                            </div>
                            <button @click="showDetails = !showDetails" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded text-sm">
                                <span x-text="showDetails ? 'Hide' : 'View'"></span>
                            </button>
                        </div>
                        
                        <div x-show="showDetails" x-collapse class="mt-4 pt-4 border-t">
                            <div class="bg-gray-50 p-4 rounded mb-4">
                                <p class="font-semibold text-sm mb-2">Original Message:</p>
                                <p class="text-sm"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                            </div>
                            
                            <?php if (!empty($ticketReplies)): ?>
                                <div class="space-y-3 mb-4">
                                    <p class="font-semibold text-sm">Conversation:</p>
                                    <?php foreach ($ticketReplies as $reply): ?>
                                        <div class="p-3 rounded <?php echo $reply['is_admin'] ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-gray-50'; ?>">
                                            <div class="text-xs font-semibold mb-1">
                                                <?php echo $reply['is_admin'] ? '🛡️ Admin' : '👤 ' . htmlspecialchars($reply['name']); ?> - 
                                                <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                            </div>
                                            <p class="text-sm"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($ticket['status'] !== 'closed'): ?>
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <label class="block text-sm font-semibold mb-2">Send Reply:</label>
                                    <textarea name="message" rows="4" required class="w-full px-3 py-2 border rounded mb-2"></textarea>
                                    <div class="flex gap-2">
                                        <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded">
                                            <i class="bi bi-send"></i> Send Reply
                                        </button>
                                    </div>
                                </form>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Close this ticket?');">
                                    <input type="hidden" name="action" value="close_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                                        <i class="bi bi-check-circle"></i> Close Ticket
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-green-600 font-semibold"><i class="bi bi-check-circle"></i> This ticket is closed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
