<?php
$pageTitle = 'Customer Support Tickets';

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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'reply_ticket') {
            $ticketId = intval($_POST['ticket_id']);
            $message = trim($_POST['message']);
            $newStatus = sanitizeInput($_POST['new_status'] ?? '');
            
            if (empty($message)) {
                $errorMessage = 'Reply message is required.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Get ticket info for email notification
                    $ticketInfoStmt = $db->prepare("
                        SELECT cst.*, c.username as customer_name, c.email as customer_email
                        FROM customer_support_tickets cst
                        JOIN customers c ON cst.customer_id = c.id
                        WHERE cst.id = ?
                    ");
                    $ticketInfoStmt->execute([$ticketId]);
                    $ticketInfo = $ticketInfoStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Add reply
                    $stmt = $db->prepare("
                        INSERT INTO customer_ticket_replies (ticket_id, author_type, author_id, author_name, message)
                        VALUES (?, 'admin', ?, ?, ?)
                    ");
                    $stmt->execute([$ticketId, getAdminId(), getAdminName(), $message]);
                    
                    // Update ticket
                    $updateSql = "UPDATE customer_support_tickets SET last_reply_at = datetime('now'), last_reply_by = 'admin', updated_at = datetime('now')";
                    $updateParams = [];
                    
                    if (!empty($newStatus)) {
                        $updateSql .= ", status = ?";
                        $updateParams[] = $newStatus;
                        
                        if ($newStatus === 'resolved') {
                            $updateSql .= ", resolved_at = datetime('now')";
                        } elseif ($newStatus === 'closed') {
                            $updateSql .= ", closed_at = datetime('now')";
                        }
                    }
                    
                    $updateSql .= " WHERE id = ?";
                    $updateParams[] = $ticketId;
                    
                    $stmt = $db->prepare($updateSql);
                    $stmt->execute($updateParams);
                    
                    // Create in-app notification for customer
                    if ($ticketInfo) {
                        $notifStmt = $db->prepare("
                            INSERT INTO customer_notifications (customer_id, type, title, message, priority, created_at)
                            VALUES (?, 'ticket_reply', ?, ?, 'high', datetime('now'))
                        ");
                        $notifStmt->execute([
                            $ticketInfo['customer_id'],
                            "Support Reply - Ticket #{$ticketId}",
                            substr($message, 0, 150) . (strlen($message) > 150 ? '...' : '')
                        ]);
                    }
                    
                    $db->commit();
                    
                    // Send email notification to customer (outside transaction)
                    if ($ticketInfo && !empty($ticketInfo['customer_email'])) {
                        @sendCustomerTicketReplyEmail(
                            $ticketInfo['customer_name'] ?? 'Customer',
                            $ticketInfo['customer_email'],
                            $ticketId,
                            $ticketInfo['subject'] ?? 'Support Ticket',
                            $message
                        );
                    }
                    
                    $successMessage = 'Reply sent successfully!';
                    logActivity('customer_ticket_replied', "Replied to ticket #$ticketId", getAdminId());
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log('Ticket reply error: ' . $e->getMessage());
                    $errorMessage = 'Failed to send reply.';
                }
            }
        } elseif ($action === 'update_status') {
            $ticketId = intval($_POST['ticket_id']);
            $status = sanitizeInput($_POST['status']);
            
            $validStatuses = ['open', 'awaiting_reply', 'in_progress', 'resolved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                $errorMessage = 'Invalid status.';
            } else {
                try {
                    $updateSql = "UPDATE customer_support_tickets SET status = ?, updated_at = datetime('now')";
                    if ($status === 'resolved') {
                        $updateSql .= ", resolved_at = datetime('now')";
                    } elseif ($status === 'closed') {
                        $updateSql .= ", closed_at = datetime('now')";
                    }
                    $updateSql .= " WHERE id = ?";
                    
                    $stmt = $db->prepare($updateSql);
                    $stmt->execute([$status, $ticketId]);
                    
                    $successMessage = 'Ticket status updated!';
                    logActivity('customer_ticket_status_updated', "Ticket #$ticketId status: $status", getAdminId());
                } catch (PDOException $e) {
                    error_log('Ticket status update error: ' . $e->getMessage());
                    $errorMessage = 'Failed to update ticket status.';
                }
            }
        } elseif ($action === 'bulk_update') {
            $ticketIds = $_POST['ticket_ids'] ?? [];
            $bulkStatus = sanitizeInput($_POST['bulk_status'] ?? '');
            
            if (empty($ticketIds)) {
                $errorMessage = 'No tickets selected.';
            } elseif (empty($bulkStatus)) {
                $errorMessage = 'Please select a status.';
            } else {
                try {
                    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                    $stmt = $db->prepare("UPDATE customer_support_tickets SET status = ?, updated_at = datetime('now') WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$bulkStatus], $ticketIds));
                    
                    $successMessage = count($ticketIds) . ' ticket(s) updated successfully!';
                    logActivity('customer_tickets_bulk_updated', "Bulk updated " . count($ticketIds) . " tickets to $bulkStatus", getAdminId());
                } catch (PDOException $e) {
                    error_log('Bulk update error: ' . $e->getMessage());
                    $errorMessage = 'Failed to update tickets.';
                }
            }
        }
    }
}

// Check if viewing a specific ticket
$viewTicketId = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

if ($viewTicketId) {
    // Get ticket details
    $stmt = $db->prepare("
        SELECT cst.*, c.email as customer_email, c.username as customer_name, c.phone as customer_phone
        FROM customer_support_tickets cst
        JOIN customers c ON c.id = cst.customer_id
        WHERE cst.id = ?
    ");
    $stmt->execute([$viewTicketId]);
    $viewTicket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($viewTicket) {
        // Get replies
        $repliesStmt = $db->prepare("
            SELECT * FROM customer_ticket_replies 
            WHERE ticket_id = ?
            ORDER BY created_at ASC
        ");
        $repliesStmt->execute([$viewTicketId]);
        $replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$priorityFilter = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : '';
$categoryFilter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($statusFilter)) {
    $whereConditions[] = "cst.status = ?";
    $params[] = $statusFilter;
}

if (!empty($priorityFilter)) {
    $whereConditions[] = "cst.priority = ?";
    $params[] = $priorityFilter;
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "cst.category = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(cst.subject LIKE ? OR c.email LIKE ? OR c.username LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countSql = "SELECT COUNT(*) FROM customer_support_tickets cst JOIN customers c ON c.id = cst.customer_id $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalTickets = $countStmt->fetchColumn();
$totalPages = ceil($totalTickets / $perPage);

// Get tickets
$sql = "
    SELECT cst.*, c.email as customer_email, c.username as customer_name
    FROM customer_support_tickets cst
    JOIN customers c ON c.id = cst.customer_id
    $whereClause
    ORDER BY 
        CASE cst.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
        END,
        cst.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'awaiting_reply' THEN 1 ELSE 0 END) as awaiting,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
    FROM customer_support_tickets
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($viewTicketId && $viewTicket): ?>
<!-- Ticket Detail View -->
<div class="mb-6">
    <a href="/admin/customer-tickets.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors inline-flex items-center">
        <i class="bi bi-arrow-left mr-2"></i> Back to Tickets
    </a>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
    <i class="bi bi-x-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Ticket Info -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
            <h3 class="font-bold text-gray-900 mb-4">Ticket #<?php echo $viewTicket['id']; ?></h3>
            
            <div class="space-y-4">
                <div>
                    <span class="text-sm text-gray-500">Status</span>
                    <div class="mt-1">
                        <?php
                        $statusColors = [
                            'open' => 'bg-blue-100 text-blue-800',
                            'awaiting_reply' => 'bg-yellow-100 text-yellow-800',
                            'in_progress' => 'bg-purple-100 text-purple-800',
                            'resolved' => 'bg-green-100 text-green-800',
                            'closed' => 'bg-gray-100 text-gray-800'
                        ];
                        ?>
                        <span class="inline-flex items-center px-3 py-1 <?php echo $statusColors[$viewTicket['status']] ?? 'bg-gray-100'; ?> rounded-full text-sm font-semibold">
                            <?php echo ucfirst(str_replace('_', ' ', $viewTicket['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <div>
                    <span class="text-sm text-gray-500">Priority</span>
                    <div class="mt-1">
                        <?php
                        $priorityColors = [
                            'low' => 'bg-gray-100 text-gray-700',
                            'normal' => 'bg-blue-100 text-blue-700',
                            'high' => 'bg-orange-100 text-orange-700',
                            'urgent' => 'bg-red-100 text-red-700'
                        ];
                        ?>
                        <span class="inline-flex items-center px-3 py-1 <?php echo $priorityColors[$viewTicket['priority']] ?? 'bg-gray-100'; ?> rounded-full text-sm font-semibold">
                            <?php echo ucfirst($viewTicket['priority']); ?>
                        </span>
                    </div>
                </div>
                
                <div>
                    <span class="text-sm text-gray-500">Category</span>
                    <div class="font-medium"><?php echo ucfirst($viewTicket['category']); ?></div>
                </div>
                
                <div>
                    <span class="text-sm text-gray-500">Created</span>
                    <div class="font-medium"><?php echo date('M d, Y H:i', strtotime($viewTicket['created_at'])); ?></div>
                </div>
                
                <?php if ($viewTicket['order_id']): ?>
                <div>
                    <span class="text-sm text-gray-500">Related Order</span>
                    <div>
                        <a href="/admin/orders.php?view=<?php echo $viewTicket['order_id']; ?>" class="text-primary-600 hover:text-primary-700">
                            Order #<?php echo $viewTicket['order_id']; ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Status Update -->
            <div class="mt-6 pt-4 border-t border-gray-200">
                <h4 class="font-semibold text-gray-700 mb-2">Update Status</h4>
                <form method="POST" class="space-y-2">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="ticket_id" value="<?php echo $viewTicket['id']; ?>">
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="open" <?php echo $viewTicket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $viewTicket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="awaiting_reply" <?php echo $viewTicket['status'] === 'awaiting_reply' ? 'selected' : ''; ?>>Awaiting Reply</option>
                        <option value="resolved" <?php echo $viewTicket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $viewTicket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <button type="submit" class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
            <h3 class="font-bold text-gray-900 mb-4">Customer</h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
                    <span class="text-primary-600 font-bold">
                        <?php echo strtoupper(substr($viewTicket['customer_name'] ?? $viewTicket['customer_email'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <div class="font-medium"><?php echo htmlspecialchars($viewTicket['customer_name'] ?? 'Not set'); ?></div>
                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($viewTicket['customer_email']); ?></div>
                </div>
            </div>
            <a href="/admin/customer-detail.php?id=<?php echo $viewTicket['customer_id']; ?>" 
               class="block text-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors text-sm">
                View Customer Profile <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
    
    <!-- Conversation -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($viewTicket['subject']); ?></h3>
            </div>
            
            <div class="p-6 space-y-4 max-h-[500px] overflow-y-auto">
                <!-- Original Message -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                <span class="text-primary-600 font-bold text-xs">
                                    <?php echo strtoupper(substr($viewTicket['customer_name'] ?? $viewTicket['customer_email'], 0, 1)); ?>
                                </span>
                            </div>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($viewTicket['customer_name'] ?? 'Customer'); ?></span>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo getRelativeTime($viewTicket['created_at']); ?></span>
                    </div>
                    <div class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($viewTicket['message']); ?></div>
                </div>
                
                <!-- Replies -->
                <?php foreach ($replies as $reply): 
                    // Get display name for the reply author
                    $replyAuthorName = $reply['author_name'];
                    if (empty($replyAuthorName)) {
                        // Fallback to customer name from ticket for customer replies
                        if ($reply['author_type'] === 'customer') {
                            $replyAuthorName = $viewTicket['customer_name'] ?? $viewTicket['customer_email'] ?? 'Customer';
                        } else {
                            $replyAuthorName = 'Admin';
                        }
                    }
                ?>
                <div class="<?php echo $reply['author_type'] === 'admin' ? 'bg-blue-50 ml-8' : 'bg-gray-50'; ?> rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 <?php echo $reply['author_type'] === 'admin' ? 'bg-blue-100' : 'bg-primary-100'; ?> rounded-full flex items-center justify-center">
                                <span class="<?php echo $reply['author_type'] === 'admin' ? 'text-blue-600' : 'text-primary-600'; ?> font-bold text-xs">
                                    <?php echo strtoupper(substr($replyAuthorName, 0, 1)); ?>
                                </span>
                            </div>
                            <span class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($replyAuthorName); ?>
                                <?php if ($reply['author_type'] === 'admin'): ?>
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded ml-1">Admin</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo getRelativeTime($reply['created_at']); ?></span>
                    </div>
                    <div class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($reply['message']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Reply Form -->
            <?php if ($viewTicket['status'] !== 'closed'): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <form method="POST">
                    <input type="hidden" name="action" value="reply_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo $viewTicket['id']; ?>">
                    
                    <div class="mb-4">
                        <textarea name="message" rows="4" required placeholder="Type your reply..."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600">Update status to:</label>
                            <select name="new_status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">No change</option>
                                <option value="in_progress">In Progress</option>
                                <option value="awaiting_reply">Awaiting Reply</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                            <i class="bi bi-send mr-1"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Ticket List View -->

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-ticket text-blue-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></div>
                <div class="text-xs text-gray-500">Total</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-envelope-open text-green-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['open']); ?></div>
                <div class="text-xs text-gray-500">Open</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-arrow-repeat text-purple-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['in_progress']); ?></div>
                <div class="text-xs text-gray-500">In Progress</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-clock text-yellow-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['awaiting']); ?></div>
                <div class="text-xs text-gray-500">Awaiting</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-exclamation-triangle text-red-600 text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($stats['urgent']); ?></div>
                <div class="text-xs text-gray-500">Urgent</div>
            </div>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
    <i class="bi bi-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
    <i class="bi bi-x-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                   placeholder="Search by subject, customer..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        </div>
        <div class="w-40">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">All</option>
                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="awaiting_reply" <?php echo $statusFilter === 'awaiting_reply' ? 'selected' : ''; ?>>Awaiting Reply</option>
                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
            <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">All</option>
                <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="normal" <?php echo $priorityFilter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
            <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">All</option>
                <option value="general" <?php echo $categoryFilter === 'general' ? 'selected' : ''; ?>>General</option>
                <option value="order" <?php echo $categoryFilter === 'order' ? 'selected' : ''; ?>>Order</option>
                <option value="delivery" <?php echo $categoryFilter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                <option value="refund" <?php echo $categoryFilter === 'refund' ? 'selected' : ''; ?>>Refund</option>
                <option value="technical" <?php echo $categoryFilter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                <option value="account" <?php echo $categoryFilter === 'account' ? 'selected' : ''; ?>>Account</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                <i class="bi bi-search mr-1"></i> Filter
            </button>
            <a href="/admin/customer-tickets.php" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                Clear
            </a>
        </div>
    </form>
</div>

<!-- Tickets Table -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <form method="POST" x-data="{ selectedTickets: [] }">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-headset text-primary-600"></i> Support Tickets
                <span class="text-sm font-normal text-gray-500">(<?php echo number_format($totalTickets); ?>)</span>
            </h3>
            
            <!-- Bulk Actions -->
            <div class="flex items-center gap-2" x-show="selectedTickets.length > 0">
                <span class="text-sm text-gray-600" x-text="selectedTickets.length + ' selected'"></span>
                <select name="bulk_status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Bulk Action</option>
                    <option value="in_progress">Mark In Progress</option>
                    <option value="resolved">Mark Resolved</option>
                    <option value="closed">Mark Closed</option>
                </select>
                <button type="submit" name="action" value="bulk_update" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm">
                    Apply
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300 bg-gray-50">
                        <th class="py-3 px-4 w-10">
                            <input type="checkbox" @change="selectedTickets = $event.target.checked ? <?php echo json_encode(array_column($tickets, 'id')); ?> : []" 
                                   class="rounded border-gray-300">
                        </th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Ticket</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Customer</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Priority</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Category</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Status</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Last Update</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="bi bi-ticket text-6xl text-gray-300"></i>
                            <p class="text-gray-500 mt-4">No tickets found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <input type="checkbox" name="ticket_ids[]" value="<?php echo $ticket['id']; ?>"
                                   x-model="selectedTickets" class="rounded border-gray-300">
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900">#<?php echo $ticket['id']; ?></div>
                            <div class="text-sm text-gray-600 max-w-xs truncate"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo getRelativeTime($ticket['created_at']); ?></div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($ticket['customer_name'] ?? 'N/A'); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($ticket['customer_email']); ?></div>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $priorityColors = [
                                'low' => 'bg-gray-100 text-gray-700',
                                'normal' => 'bg-blue-100 text-blue-700',
                                'high' => 'bg-orange-100 text-orange-700',
                                'urgent' => 'bg-red-100 text-red-700'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2 py-1 <?php echo $priorityColors[$ticket['priority']] ?? 'bg-gray-100'; ?> rounded-full text-xs font-semibold">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-700">
                            <?php echo ucfirst($ticket['category']); ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $statusColors = [
                                'open' => 'bg-blue-100 text-blue-800',
                                'awaiting_reply' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-purple-100 text-purple-800',
                                'resolved' => 'bg-green-100 text-green-800',
                                'closed' => 'bg-gray-100 text-gray-800'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2 py-1 <?php echo $statusColors[$ticket['status']] ?? 'bg-gray-100'; ?> rounded-full text-xs font-semibold">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500">
                            <?php echo getRelativeTime($ticket['updated_at']); ?>
                            <?php if ($ticket['last_reply_by']): ?>
                            <div class="text-xs">by <?php echo ucfirst($ticket['last_reply_by']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <a href="/admin/customer-tickets.php?ticket_id=<?php echo $ticket['id']; ?>" 
                               class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition-colors">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
        <div class="text-sm text-gray-500">
            Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalTickets); ?> of <?php echo $totalTickets; ?>
        </div>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                <i class="bi bi-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&category=<?php echo urlencode($categoryFilter); ?>&search=<?php echo urlencode($searchQuery); ?>" 
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                Next <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
