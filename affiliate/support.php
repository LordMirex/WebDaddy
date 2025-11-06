<?php
$pageTitle = 'Support';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAffiliate();

$db = getDb();
$affiliateId = getAffiliateId();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_ticket') {
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = sanitizeInput($_POST['priority'] ?? 'normal');
        
        if (empty($subject) || empty($message)) {
            $errorMessage = 'Subject and message are required.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO support_tickets (affiliate_id, subject, message, priority) VALUES (?, ?, ?, ?)");
                $stmt->execute([$affiliateId, $subject, $message, $priority]);
                $ticketId = $db->lastInsertId();
                
                $affiliateName = getAffiliateName();
                @sendNewSupportTicketNotificationToAdmin($ticketId, $affiliateName, $subject, $message, $priority);
                
                $successMessage = "Support ticket created successfully! We'll respond soon.";
            } catch (PDOException $e) {
                $errorMessage = 'Failed to create ticket: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reply_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if ($ticketId && !empty($message)) {
            try {
                $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND affiliate_id = ?");
                $stmt->execute([$ticketId, $affiliateId]);
                
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)");
                    $stmt->execute([$ticketId, $affiliateId, $message]);
                    
                    $stmt = $db->prepare("UPDATE support_tickets SET status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$ticketId]);
                    
                    $successMessage = "Reply added successfully!";
                }
            } catch (PDOException $e) {
                $errorMessage = 'Failed to add reply: ' . $e->getMessage();
            }
        }
    }
}

$tickets = $db->prepare("SELECT * FROM support_tickets WHERE affiliate_id = ? ORDER BY created_at DESC");
$tickets->execute([$affiliateId]);
$myTickets = $tickets->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-headset text-primary-600"></i> Support Center
    </h1>
    <p class="text-gray-600 mt-2">Get help from our team</p>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4"><i class="bi bi-plus-circle"></i> Create New Ticket</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_ticket">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Subject</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Priority</label>
                    <select name="priority" class="w-full px-4 py-2 border rounded-lg">
                        <option value="low">Low</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Message</label>
                    <textarea name="message" rows="6" required class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium">
                    <i class="bi bi-send"></i> Submit Ticket
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold mb-4"><i class="bi bi-ticket"></i> My Tickets (<?php echo count($myTickets); ?>)</h2>
            <?php if (empty($myTickets)): ?>
                <p class="text-gray-500">No tickets yet. Create one above if you need help!</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($myTickets as $ticket): ?>
                        <?php
                        $statusColors = ['open' => 'blue', 'in_progress' => 'yellow', 'closed' => 'green'];
                        $priorityColors = ['low' => 'gray', 'normal' => 'blue', 'high' => 'red'];
                        $color = $statusColors[$ticket['status']] ?? 'gray';
                        $priColor = $priorityColors[$ticket['priority']] ?? 'gray';
                        
                        $replies = $db->prepare("SELECT tr.*, u.name FROM ticket_replies tr LEFT JOIN users u ON tr.user_id = u.id WHERE ticket_id = ? ORDER BY created_at ASC");
                        $replies->execute([$ticket['id']]);
                        $ticketReplies = $replies->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div class="border border-<?php echo $color; ?>-200 rounded-lg p-4" x-data="{ showReplies: false }">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                <div class="flex gap-2">
                                    <span class="px-2 py-1 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 rounded text-xs font-semibold"><?php echo $ticket['status']; ?></span>
                                    <span class="px-2 py-1 bg-<?php echo $priColor; ?>-100 text-<?php echo $priColor; ?>-800 rounded text-xs font-semibold"><?php echo $ticket['priority']; ?></span>
                                </div>
                            </div>
                            <p class="text-gray-700 text-sm mb-2"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></span>
                                <button @click="showReplies = !showReplies" class="text-primary-600 hover:underline">
                                    <?php echo count($ticketReplies); ?> replies
                                </button>
                            </div>
                            
                            <div x-show="showReplies" x-collapse class="mt-4 pt-4 border-t">
                                <?php foreach ($ticketReplies as $reply): ?>
                                    <div class="mb-3 p-3 <?php echo $reply['is_admin'] ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-gray-50'; ?> rounded">
                                        <div class="text-xs font-semibold mb-1"><?php echo $reply['is_admin'] ? '🛡️ Admin' : '👤 You'; ?> - <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></div>
                                        <p class="text-sm"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="reply_ticket">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <textarea name="message" rows="3" placeholder="Add a reply..." required class="w-full px-3 py-2 border rounded text-sm mb-2"></textarea>
                                        <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded text-sm">
                                            <i class="bi bi-reply"></i> Reply
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h3 class="font-bold text-lg mb-4"><i class="bi bi-info-circle"></i> Need Quick Help?</h3>
            <p class="text-sm text-gray-600 mb-4">For urgent matters, contact us directly on WhatsApp!</p>
            <a href="https://wa.me/<?php echo WHATSAPP_NUMBER; ?>?text=Hello%2C%20I%20need%20help%20with%20my%20affiliate%20account" 
               target="_blank"
               class="block w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-center">
                <i class="bi bi-whatsapp"></i> Chat on WhatsApp
            </a>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
            <h4 class="font-semibold text-blue-900 mb-2">💡 Tips for Faster Support</h4>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• Be specific about your issue</li>
                <li>• Include relevant order/transaction IDs</li>
                <li>• Attach screenshots if needed</li>
                <li>• Check your email for replies</li>
            </ul>
        </div>
    </div>
</div>

<!-- WhatsApp Float Button -->
<a href="https://wa.me/<?php echo WHATSAPP_NUMBER; ?>?text=Hello%2C%20I%20need%20help" 
   target="_blank"
   class="fixed bottom-6 right-6 w-16 h-16 bg-green-500 hover:bg-green-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl z-50 transition-all hover:scale-110">
    <i class="bi bi-whatsapp"></i>
</a>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
