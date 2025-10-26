<?php
$pageTitle = 'Orders Management';

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
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'mark_paid') {
            $orderId = intval($_POST['order_id']);
            $amountPaid = floatval($_POST['amount_paid']);
            $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'WhatsApp');
            $paymentNotes = sanitizeInput($_POST['payment_notes'] ?? '');
            
            if ($orderId <= 0 || $amountPaid <= 0) {
                $errorMessage = 'Invalid order ID or amount.';
            } else {
                if (markOrderPaid($orderId, getAdminId(), $amountPaid, $paymentNotes)) {
                    try {
                        $stmt = $db->prepare("UPDATE sales SET payment_method = ? WHERE pending_order_id = ?");
                        $stmt->execute([$paymentMethod, $orderId]);
                    } catch (PDOException $e) {
                        error_log('Error updating payment method: ' . $e->getMessage());
                    }
                    
                    $successMessage = 'Order marked as paid successfully!';
                    logActivity('order_marked_paid', "Order #$orderId marked as paid", getAdminId());
                } else {
                    $errorMessage = 'Failed to mark order as paid. Please try again.';
                }
            }
        } elseif ($action === 'assign_domain') {
            $orderId = intval($_POST['order_id']);
            $domainId = intval($_POST['domain_id']);
            
            if ($orderId <= 0 || $domainId <= 0) {
                $errorMessage = 'Invalid order or domain ID.';
            } else {
                if (assignDomainToCustomer($domainId, $orderId)) {
                    try {
                        $stmt = $db->prepare("UPDATE pending_orders SET chosen_domain_id = ? WHERE id = ?");
                        $stmt->execute([$domainId, $orderId]);
                    } catch (PDOException $e) {
                        error_log('Error updating order domain: ' . $e->getMessage());
                    }
                    
                    $successMessage = 'Domain assigned successfully!';
                    logActivity('domain_assigned', "Domain #$domainId assigned to order #$orderId", getAdminId());
                } else {
                    $errorMessage = 'Failed to assign domain. It may already be in use.';
                }
            }
        } elseif ($action === 'cancel_order') {
            $orderId = intval($_POST['order_id']);
            
            try {
                $stmt = $db->prepare("UPDATE pending_orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$orderId]);
                
                if ($stmt->rowCount() > 0) {
                    $successMessage = 'Order cancelled successfully!';
                    logActivity('order_cancelled', "Order #$orderId cancelled", getAdminId());
                } else {
                    $errorMessage = 'Cannot cancel this order.';
                }
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$searchTerm = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTemplate = $_GET['template'] ?? '';

$sql = "SELECT po.*, t.name as template_name, t.price as template_price, d.domain_name,
        (SELECT COUNT(*) FROM sales WHERE pending_order_id = po.id) as is_paid
        FROM pending_orders po
        LEFT JOIN templates t ON po.template_id = t.id
        LEFT JOIN domains d ON po.chosen_domain_id = d.id
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (po.customer_name LIKE ? OR po.customer_email LIKE ? OR po.customer_phone LIKE ? OR po.business_name LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($filterStatus)) {
    $sql .= " AND po.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterTemplate)) {
    $sql .= " AND po.template_id = ?";
    $params[] = intval($filterTemplate);
}

$sql .= " ORDER BY po.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = getTemplates(false);

$viewOrder = null;
$availableDomains = [];
if (isset($_GET['view'])) {
    $viewOrder = getOrderById(intval($_GET['view']));
    if ($viewOrder) {
        $availableDomains = getAvailableDomains($viewOrder['template_id']);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-cart"></i> Orders Management</h1>
</div>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by name, email, phone...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Template</label>
                <select class="form-select" name="template">
                    <option value="">All Templates</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?php echo $tpl['id']; ?>" <?php echo $filterTemplate == $tpl['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tpl['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Template</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-2">No orders found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?><br>
                                <i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                            </small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($order['template_name']); ?><br>
                            <small class="text-muted"><?php echo formatCurrency($order['template_price']); ?></small>
                        </td>
                        <td>
                            <?php if ($order['domain_name']): ?>
                            <i class="bi bi-globe"></i> <?php echo htmlspecialchars($order['domain_name']); ?>
                            <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = [
                                'pending' => 'warning',
                                'paid' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $statusIcon = [
                                'pending' => 'hourglass-split',
                                'paid' => 'check-circle',
                                'cancelled' => 'x-circle'
                            ];
                            $class = $statusClass[$order['status']] ?? 'secondary';
                            $icon = $statusIcon[$order['status']] ?? 'circle';
                            ?>
                            <span class="badge bg-<?php echo $class; ?>">
                                <i class="bi bi-<?php echo $icon; ?>"></i> <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($viewOrder): ?>
<div class="modal fade show" id="viewOrderModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cart"></i> Order #<?php echo $viewOrder['id']; ?> Details
                </h5>
                <a href="/admin/orders.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Customer Information</h6>
                        <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($viewOrder['customer_name']); ?></p>
                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($viewOrder['customer_email']); ?></p>
                        <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($viewOrder['customer_phone']); ?></p>
                        <?php if (!empty($viewOrder['business_name'])): ?>
                        <p class="mb-2"><strong>Business:</strong> <?php echo htmlspecialchars($viewOrder['business_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Order Information</h6>
                        <p class="mb-2"><strong>Template:</strong> <?php echo htmlspecialchars($viewOrder['template_name']); ?></p>
                        <p class="mb-2"><strong>Price:</strong> <?php echo formatCurrency($viewOrder['template_price']); ?></p>
                        <p class="mb-2"><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $statusClass[$viewOrder['status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($viewOrder['status']); ?>
                            </span>
                        </p>
                        <p class="mb-2"><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($viewOrder['created_at'])); ?></p>
                        <?php if (!empty($viewOrder['affiliate_code'])): ?>
                        <p class="mb-2"><strong>Affiliate Code:</strong> <code><?php echo htmlspecialchars($viewOrder['affiliate_code']); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($viewOrder['message_text'])): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Customer Message</h6>
                    <div class="alert alert-info">
                        <?php echo nl2br(htmlspecialchars($viewOrder['message_text'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($viewOrder['custom_fields'])): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Custom Fields</h6>
                    <div class="alert alert-secondary">
                        <pre class="mb-0"><?php echo htmlspecialchars($viewOrder['custom_fields']); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Domain Assignment</h6>
                    <?php if ($viewOrder['domain_name']): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-globe"></i> Assigned Domain: <strong><?php echo htmlspecialchars($viewOrder['domain_name']); ?></strong>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No domain assigned yet
                    </div>
                    
                    <?php if ($viewOrder['status'] !== 'cancelled' && !empty($availableDomains)): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="action" value="assign_domain">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <select class="form-select" name="domain_id" required>
                                    <option value="">Select a domain to assign...</option>
                                    <?php foreach ($availableDomains as $domain): ?>
                                    <option value="<?php echo $domain['id']; ?>">
                                        <?php echo htmlspecialchars($domain['domain_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-link"></i> Assign Domain
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php elseif ($viewOrder['status'] !== 'cancelled' && empty($availableDomains)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> No available domains for this template. <a href="/admin/domains.php">Add domains</a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($viewOrder['status'] === 'pending'): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Payment Processing</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Amount Paid *</label>
                                <input type="number" class="form-control" name="amount_paid" value="<?php echo $viewOrder['template_price']; ?>" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select" name="payment_method">
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Card">Card</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Payment Notes</label>
                                <textarea class="form-control" name="payment_notes" rows="2" placeholder="Optional notes about the payment..."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cash-coin"></i> Mark as Paid
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if ($viewOrder['status'] === 'pending'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                        <i class="bi bi-x-circle"></i> Cancel Order
                    </button>
                </form>
                <?php endif; ?>
                <a href="/admin/orders.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
