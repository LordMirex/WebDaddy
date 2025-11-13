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
        } elseif ($action === 'bulk_mark_paid') {
            $orderIds = $_POST['order_ids'] ?? [];
            $successCount = 0;
            $failCount = 0;
            
            foreach ($orderIds as $orderId) {
                $orderId = intval($orderId);
                if ($orderId > 0) {
                    // Get order details with template/tool prices as fallback for legacy orders
                    $stmt = $db->prepare("
                        SELECT po.*,
                               t.price as template_price,
                               tool.price as tool_price
                        FROM pending_orders po
                        LEFT JOIN templates t ON po.template_id = t.id
                        LEFT JOIN tools tool ON po.tool_id = tool.id
                        WHERE po.id = ? AND po.status = 'pending'
                    ");
                    $stmt->execute([$orderId]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        // Priority order for calculating payable amount:
                        // 1. Use final_amount if set (most accurate)
                        // 2. Use original_price if set
                        // 3. Calculate from order_items
                        // 4. Fall back to template_price or tool_price for legacy orders
                        $payableAmount = $order['final_amount'] ?? $order['original_price'] ?? 0;
                        
                        if ($payableAmount == 0) {
                            // Try to get from order_items
                            $itemsStmt = $db->prepare("SELECT SUM(final_amount) as total FROM order_items WHERE pending_order_id = ?");
                            $itemsStmt->execute([$orderId]);
                            $itemsTotal = $itemsStmt->fetchColumn();
                            
                            if ($itemsTotal > 0) {
                                $payableAmount = $itemsTotal;
                            } else {
                                // Last resort: use template or tool price for legacy orders
                                $basePrice = $order['template_price'] ?? $order['tool_price'] ?? 0;
                                if ($basePrice > 0) {
                                    // Apply affiliate discount if applicable
                                    if (!empty($order['affiliate_code'])) {
                                        $discountRate = CUSTOMER_DISCOUNT_RATE;
                                        $payableAmount = $basePrice * (1 - $discountRate);
                                    } else {
                                        $payableAmount = $basePrice;
                                    }
                                }
                            }
                        }
                        
                        if ($payableAmount > 0 && markOrderPaid($orderId, getAdminId(), $payableAmount, 'Bulk processed')) {
                            $successCount++;
                        } else {
                            $failCount++;
                            error_log("Bulk payment failed for order #{$orderId}: payableAmount = {$payableAmount}");
                        }
                    } else {
                        $failCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $successMessage = "Successfully marked {$successCount} order(s) as paid";
                if ($failCount > 0) {
                    $successMessage .= ". {$failCount} failed.";
                }
                logActivity('bulk_orders_processed', "Bulk processed {$successCount} orders", getAdminId());
            } else {
                $errorMessage = 'No orders were processed.';
            }
        } elseif ($action === 'bulk_cancel') {
            $orderIds = $_POST['order_ids'] ?? [];
            $successCount = 0;
            
            foreach ($orderIds as $orderId) {
                $orderId = intval($orderId);
                if ($orderId > 0) {
                    $stmt = $db->prepare("UPDATE pending_orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$orderId]);
                    if ($stmt->rowCount() > 0) {
                        $successCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $successMessage = "Cancelled {$successCount} order(s) successfully!";
                logActivity('bulk_orders_cancelled', "Bulk cancelled {$successCount} orders", getAdminId());
            } else {
                $errorMessage = 'No orders were cancelled.';
            }
        }
    }
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT po.*, t.name as template_name, t.price as template_price,
            tool.name as tool_name, tool.price as tool_price, d.domain_name,
            (SELECT COUNT(*) FROM sales WHERE pending_order_id = po.id) as is_paid,
            (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
            FROM pending_orders po
            LEFT JOIN templates t ON po.template_id = t.id
            LEFT JOIN tools tool ON po.tool_id = tool.id
            LEFT JOIN domains d ON po.chosen_domain_id = d.id
            ORDER BY po.created_at DESC";
    
    $orders = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Order ID', 'Order Type', 'Customer Name', 'Email', 'Phone', 'Products', 'Item Count', 'Price (NGN)', 'Affiliate Code', 'Domain', 'Status', 'Is Paid', 'Order Date'], ',', '"');
    
    foreach ($orders as $order) {
        // Get order items for accurate product list
        $orderItems = getOrderItems($order['id']);
        $orderType = $order['order_type'] ?? 'template';
        $productsList = '';
        $itemCount = 0;
        
        if (!empty($orderItems)) {
            $productNames = [];
            foreach ($orderItems as $item) {
                $productName = $item['product_type'] === 'template' ? $item['template_name'] : $item['tool_name'];
                $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                $productNames[] = $productName . $qty;
            }
            $productsList = implode('; ', $productNames);
            $itemCount = count($orderItems);
        } elseif ($order['template_name']) {
            $productsList = $order['template_name'];
            $itemCount = 1;
        } elseif ($order['tool_name']) {
            $productsList = $order['tool_name'];
            $itemCount = 1;
        }
        
        // Use final_amount for accurate pricing
        $payableAmount = $order['final_amount'] ?? $order['original_price'] ?? $order['template_price'] ?? $order['tool_price'] ?? 0;
        
        fputcsv($output, [
            (string)($order['id'] ?? ''),
            ucfirst($orderType),
            $order['customer_name'] ?? '',
            $order['customer_email'] ?? '',
            $order['customer_phone'] ?? '',
            $productsList,
            $itemCount,
            number_format($payableAmount, 2, '.', ''),
            $order['affiliate_code'] ?? 'Direct',
            $order['domain_name'] ?? 'Not assigned',
            $order['status'] ?? '',
            $order['is_paid'] ? 'Yes' : 'No',
            $order['created_at'] ? date('Y-m-d H:i:s', strtotime($order['created_at'])) : ''
        ], ',', '"');
    }
    
    fclose($output);
    exit;
}

$searchTerm = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTemplate = $_GET['template'] ?? '';
$filterOrderType = $_GET['order_type'] ?? '';

$sql = "SELECT po.*, t.name as template_name, t.price as template_price, 
        tool.name as tool_name, tool.price as tool_price, d.domain_name,
        (SELECT COUNT(*) FROM sales WHERE pending_order_id = po.id) as is_paid,
        (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
        FROM pending_orders po
        LEFT JOIN templates t ON po.template_id = t.id
        LEFT JOIN tools tool ON po.tool_id = tool.id
        LEFT JOIN domains d ON po.chosen_domain_id = d.id
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (po.customer_name LIKE ? OR po.customer_email LIKE ? OR po.customer_phone LIKE ? OR po.business_name LIKE ? 
              OR t.name LIKE ? OR tool.name LIKE ?
              OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.pending_order_id = po.id AND (oi.template_name LIKE ? OR oi.tool_name LIKE ?)))";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
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

if (!empty($filterOrderType)) {
    if ($filterOrderType === 'templates_only') {
        $sql .= " AND (
                    (EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template')
                     AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool'))
                    OR (po.template_id IS NOT NULL AND po.tool_id IS NULL 
                        AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id))
                  )";
    } elseif ($filterOrderType === 'tools_only') {
        $sql .= " AND (
                    (EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool')
                     AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template'))
                    OR (po.tool_id IS NOT NULL AND po.template_id IS NULL 
                        AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id))
                  )";
    } elseif ($filterOrderType === 'mixed') {
        $sql .= " AND EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template')
                  AND EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool')";
    }
}

$sql .= " ORDER BY po.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = getTemplates(false);

$viewOrder = null;
$viewOrderItems = [];
$availableDomains = [];
if (isset($_GET['view'])) {
    $viewOrder = getOrderById(intval($_GET['view']));
    if ($viewOrder) {
        $viewOrderItems = getOrderItems($viewOrder['id']);
        if ($viewOrder['template_id']) {
            $availableDomains = getAvailableDomains($viewOrder['template_id']);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-cart text-primary-600"></i> Orders Management
    </h1>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-check-circle text-xl"></i>
        <span><?php echo htmlspecialchars($successMessage); ?></span>
    </div>
    <button @click="show = false" class="text-green-700 hover:text-green-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-exclamation-triangle text-xl"></i>
        <span><?php echo htmlspecialchars($errorMessage); ?></span>
    </div>
    <button @click="show = false" class="text-red-700 hover:text-red-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by name, email, phone, products...">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Order Type</label>
                <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="order_type">
                    <option value="">All Types</option>
                    <option value="templates_only" <?php echo $filterOrderType === 'templates_only' ? 'selected' : ''; ?>>Templates Only</option>
                    <option value="tools_only" <?php echo $filterOrderType === 'tools_only' ? 'selected' : ''; ?>>Tools Only</option>
                    <option value="mixed" <?php echo $filterOrderType === 'mixed' ? 'selected' : ''; ?>>Mixed Orders</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Template</label>
                <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template">
                    <option value="">All Templates</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?php echo $tpl['id']; ?>" <?php echo $filterTemplate == $tpl['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tpl['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                    <i class="bi bi-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-4 border-b border-gray-200 gap-3">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-cart text-primary-600"></i> Orders (<?php echo count($orders); ?>)
        </h5>
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <a href="/admin/orders.php?export=csv" class="w-full sm:w-auto px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-sm text-center whitespace-nowrap">
                <i class="bi bi-download mr-1"></i> Export CSV
            </a>
            <button type="button" class="w-full sm:w-auto px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap" id="bulkMarkPaidBtn" disabled>
                <i class="bi bi-check-circle mr-1"></i> Mark Selected as Paid
            </button>
            <button type="button" class="w-full sm:w-auto px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap" id="bulkCancelBtn" disabled>
                <i class="bi bi-x-circle mr-1"></i> Cancel Selected
            </button>
        </div>
    </div>
    <div class="p-6">
        <form id="bulkActionsForm" method="POST" action="">
            <input type="hidden" name="action" id="bulkAction" value="">
            
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm" style="width: 40px;">
                                <input type="checkbox" id="selectAll" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                            </th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Order ID</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Customer</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Products</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Total</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Status</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Date</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Actions</th>
                        </tr>
                    </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="bi bi-inbox text-6xl text-gray-300"></i>
                            <p class="text-gray-500 mt-4">No orders found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-2">
                            <?php if ($order['status'] === 'pending'): ?>
                            <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 order-checkbox">
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $order['id']; ?></td>
                        <td class="py-3 px-2">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="text-xs text-gray-500 space-y-0.5 mt-1">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </div>
                        </td>
                        <td class="py-3 px-2">
                            <?php
                            // Use order_items as canonical source, fallback to legacy data
                            $orderItems = getOrderItems($order['id']);
                            $orderType = $order['order_type'] ?? 'template';
                            
                            if (!empty($orderItems)) {
                                $itemCount = count($orderItems);
                                $hasTemplates = false;
                                $hasTools = false;
                                
                                foreach ($orderItems as $item) {
                                    if ($item['product_type'] === 'template') $hasTemplates = true;
                                    if ($item['product_type'] === 'tool') $hasTools = true;
                                }
                                
                                // Order type badge
                                if ($hasTemplates && $hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"><i class="bi bi-box-seam mr-1"></i>Mixed</span></div>';
                                } elseif ($hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tools</span></div>';
                                } else {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                }
                                
                                echo '<div class="text-sm text-gray-900">';
                                foreach (array_slice($orderItems, 0, 2) as $item) {
                                    $productType = $item['product_type'];
                                    $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                    $typeIcon = ($productType === 'template') ? '🎨' : '🔧';
                                    $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                                    echo $typeIcon . ' ' . htmlspecialchars($productName) . $qty . '<br/>';
                                }
                                if ($itemCount > 2) {
                                    echo '<span class="text-xs text-gray-500">+' . ($itemCount - 2) . ' more item' . ($itemCount - 2 > 1 ? 's' : '') . '</span>';
                                }
                                echo '</div>';
                            } elseif ($order['template_name']) {
                                // Legacy template-only order
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                echo '<div class="text-gray-900 text-sm">🎨 ' . htmlspecialchars($order['template_name']) . '</div>';
                            } elseif ($order['tool_name']) {
                                // Legacy tool-only order
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tool</span></div>';
                                echo '<div class="text-gray-900 text-sm">🔧 ' . htmlspecialchars($order['tool_name']) . '</div>';
                            } else {
                                echo '<span class="text-gray-400">No items</span>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-2">
                            <?php
                            // Show final amount (from cart or template order)
                            $totalAmount = $order['final_amount'] ?? $order['template_price'] ?? 0;
                            
                            // Apply discount if affiliate code present
                            if (!empty($order['affiliate_code'])) {
                                echo '<div class="text-gray-900 font-bold">' . formatCurrency($totalAmount) . '</div>';
                                echo '<div class="text-xs text-green-600">Affiliate: ' . htmlspecialchars($order['affiliate_code']) . '</div>';
                            } else {
                                echo '<div class="text-gray-900 font-bold">' . formatCurrency($totalAmount) . '</div>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-2">
                            <?php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'paid' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800'
                            ];
                            $statusIcons = [
                                'pending' => 'hourglass-split',
                                'paid' => 'check-circle',
                                'cancelled' => 'x-circle'
                            ];
                            $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                            $icon = $statusIcons[$order['status']] ?? 'circle';
                            ?>
                            <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold whitespace-nowrap">
                                <i class="bi bi-<?php echo $icon; ?>"></i>
                                <span class="hidden sm:inline sm:ml-1"><?php echo ucfirst($order['status']); ?></span>
                            </span>
                        </td>
                        <td class="py-3 px-2 text-gray-700 text-sm">
                            <div class="font-medium"><?php echo date('D, M d, Y', strtotime($order['created_at'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-2">
                            <a href="?view=<?php echo $order['id']; ?>" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm inline-flex items-center gap-1">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="md:hidden">
            <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <i class="bi bi-inbox text-6xl text-gray-300"></i>
                    <p class="text-gray-500 mt-4">No orders found</p>
                </div>
            <?php else: ?>
                <!-- Mobile Bulk Actions -->
                <div class="mb-4 p-3 bg-gray-100 rounded-lg">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                        <input type="checkbox" id="selectAllMobile" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <span>Select All Orders</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-xs disabled:opacity-50 disabled:cursor-not-allowed" id="bulkMarkPaidBtnMobile" disabled>
                            <i class="bi bi-check-circle"></i> Mark Paid
                        </button>
                        <button type="button" class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-xs disabled:opacity-50 disabled:cursor-not-allowed" id="bulkCancelBtnMobile" disabled>
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                </div>
                
                <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <?php if ($order['status'] === 'pending'): ?>
                            <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 order-checkbox">
                            <?php endif; ?>
                            <span class="font-bold text-gray-900">#<?php echo $order['id']; ?></span>
                        </div>
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-red-100 text-red-800'
                        ];
                        $statusIcons = [
                            'pending' => 'hourglass-split',
                            'paid' => 'check-circle',
                            'cancelled' => 'x-circle'
                        ];
                        $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                        $icon = $statusIcons[$order['status']] ?? 'circle';
                        ?>
                        <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold">
                            <i class="bi bi-<?php echo $icon; ?> mr-1"></i><?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-700">Customer</div>
                            <div class="text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="text-xs text-gray-500 mt-1">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm font-semibold text-gray-700">Products</div>
                            <?php
                            $orderItems = getOrderItems($order['id']);
                            $orderType = $order['order_type'] ?? 'template';
                            
                            if (!empty($orderItems)) {
                                $itemCount = count($orderItems);
                                $hasTemplates = false;
                                $hasTools = false;
                                
                                foreach ($orderItems as $item) {
                                    if ($item['product_type'] === 'template') $hasTemplates = true;
                                    if ($item['product_type'] === 'tool') $hasTools = true;
                                }
                                
                                if ($hasTemplates && $hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"><i class="bi bi-box-seam mr-1"></i>Mixed</span></div>';
                                } elseif ($hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tools</span></div>';
                                } else {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                }
                                
                                echo '<div class="text-sm text-gray-900">';
                                foreach (array_slice($orderItems, 0, 3) as $item) {
                                    $productType = $item['product_type'];
                                    $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                    $typeIcon = ($productType === 'template') ? '🎨' : '🔧';
                                    $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                                    echo $typeIcon . ' ' . htmlspecialchars($productName) . $qty . '<br/>';
                                }
                                if ($itemCount > 3) {
                                    echo '<span class="text-xs text-gray-500">+' . ($itemCount - 3) . ' more</span>';
                                }
                                echo '</div>';
                            } elseif ($order['template_name']) {
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                echo '<div class="text-gray-900 text-sm">🎨 ' . htmlspecialchars($order['template_name']) . '</div>';
                            } elseif ($order['tool_name']) {
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tool</span></div>';
                                echo '<div class="text-gray-900 text-sm">🔧 ' . htmlspecialchars($order['tool_name']) . '</div>';
                            } else {
                                echo '<span class="text-gray-400">No items</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <div>
                                <div class="text-sm font-semibold text-gray-700">Total</div>
                                <?php
                                $totalAmount = $order['final_amount'] ?? $order['template_price'] ?? 0;
                                ?>
                                <div class="text-lg font-bold text-gray-900"><?php echo formatCurrency($totalAmount); ?></div>
                                <?php if (!empty($order['affiliate_code'])): ?>
                                <div class="text-xs text-green-600">Affiliate: <?php echo htmlspecialchars($order['affiliate_code']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-700">Date</div>
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="?view=<?php echo $order['id']; ?>" class="block w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm text-center font-medium">
                        <i class="bi bi-eye mr-1"></i> View Details
                    </a>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        </form>
    </div>
</div>

<script>
// Select All functionality (Desktop)
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleBulkButtons();
});

// Select All functionality (Mobile)
document.getElementById('selectAllMobile')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleBulkButtons();
});

// Individual checkbox change
document.querySelectorAll('.order-checkbox').forEach(cb => {
    cb.addEventListener('change', toggleBulkButtons);
});

function toggleBulkButtons() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    const markPaidBtn = document.getElementById('bulkMarkPaidBtn');
    const cancelBtn = document.getElementById('bulkCancelBtn');
    const markPaidBtnMobile = document.getElementById('bulkMarkPaidBtnMobile');
    const cancelBtnMobile = document.getElementById('bulkCancelBtnMobile');
    
    if (checked.length > 0) {
        markPaidBtn.disabled = false;
        cancelBtn.disabled = false;
        if (markPaidBtnMobile) markPaidBtnMobile.disabled = false;
        if (cancelBtnMobile) cancelBtnMobile.disabled = false;
    } else {
        markPaidBtn.disabled = true;
        cancelBtn.disabled = true;
        if (markPaidBtnMobile) markPaidBtnMobile.disabled = true;
        if (cancelBtnMobile) cancelBtnMobile.disabled = true;
    }
}

// Bulk Mark Paid (Desktop)
document.getElementById('bulkMarkPaidBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Mark ${checked.length} order(s) as paid?`)) {
        document.getElementById('bulkAction').value = 'bulk_mark_paid';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Cancel (Desktop)
document.getElementById('bulkCancelBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Cancel ${checked.length} order(s)? This cannot be undone.`)) {
        document.getElementById('bulkAction').value = 'bulk_cancel';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Mark Paid (Mobile)
document.getElementById('bulkMarkPaidBtnMobile')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Mark ${checked.length} order(s) as paid?`)) {
        document.getElementById('bulkAction').value = 'bulk_mark_paid';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Cancel (Mobile)
document.getElementById('bulkCancelBtnMobile')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Cancel ${checked.length} order(s)? This cannot be undone.`)) {
        document.getElementById('bulkAction').value = 'bulk_cancel';
        document.getElementById('bulkActionsForm').submit();
    }
});
</script>

<?php if ($viewOrder): ?>
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-cart text-primary-600"></i> Order #<?php echo $viewOrder['id']; ?> Details
            </h3>
            <a href="/admin/orders.php" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Customer Information</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700"><span class="font-semibold">Name:</span> <?php echo htmlspecialchars($viewOrder['customer_name']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Email:</span> <?php echo htmlspecialchars($viewOrder['customer_email']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Phone:</span> <?php echo htmlspecialchars($viewOrder['customer_phone']); ?></p>
                        <?php if (!empty($viewOrder['business_name'])): ?>
                        <p class="text-gray-700"><span class="font-semibold">Business:</span> <?php echo htmlspecialchars($viewOrder['business_name']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Order Information</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700">
                            <span class="font-semibold">Order Type:</span> 
                            <?php
                            $orderType = $viewOrder['order_type'] ?? 'template';
                            $typeColors = ['template' => 'bg-blue-100 text-blue-800', 'tool' => 'bg-purple-100 text-purple-800', 'tools' => 'bg-purple-100 text-purple-800', 'mixed' => 'bg-green-100 text-green-800'];
                            $typeColor = $typeColors[$orderType] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-3 py-1 <?php echo $typeColor; ?> rounded-full text-xs font-semibold uppercase">
                                <?php echo htmlspecialchars($orderType); ?>
                            </span>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-semibold">Items:</span> 
                            <span class="font-bold"><?php echo count($viewOrderItems); ?></span>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-semibold">Total Amount:</span> 
                            <span class="text-lg font-bold text-green-600"><?php echo formatCurrency($viewOrder['final_amount'] ?? 0); ?></span>
                        </p>
                        
                        <p class="text-gray-700 flex items-center gap-2">
                            <span class="font-semibold">Status:</span>
                            <?php
                            $statusColors = ['pending' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-800'];
                            $color = $statusColors[$viewOrder['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold">
                                <?php echo ucfirst($viewOrder['status']); ?>
                            </span>
                        </p>
                        <p class="text-gray-700"><span class="font-semibold">Date:</span> <?php echo date('M d, Y H:i', strtotime($viewOrder['created_at'])); ?></p>
                        <?php if (!empty($viewOrder['affiliate_code'])): ?>
                        <p class="text-gray-700"><span class="font-semibold">Affiliate Code:</span> <code class="bg-gray-100 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($viewOrder['affiliate_code']); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($viewOrderItems)): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Order Items</h6>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Discount</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($viewOrderItems as $item): 
                                $productType = $item['product_type'];
                                $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                $typeLabel = ($productType === 'template') ? '🎨 Template' : '🔧 Tool';
                                
                                $metadata = !empty($item['metadata_json']) ? json_decode($item['metadata_json'], true) : null;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($productName); ?></div>
                                    <?php if ($metadata && !empty($metadata['category'])): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($metadata['category']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?php echo $typeLabel; ?></td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 font-medium"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700"><?php echo formatCurrency($item['unit_price']); ?></td>
                                <td class="px-4 py-3 text-right text-sm text-green-600">
                                    <?php echo $item['discount_amount'] > 0 ? '-' . formatCurrency($item['discount_amount']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900"><?php echo formatCurrency($item['final_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Subtotal:</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900"><?php echo formatCurrency($viewOrder['original_price'] ?? 0); ?></td>
                            </tr>
                            <?php if (!empty($viewOrder['affiliate_code']) && ($viewOrder['discount_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                    Affiliate Discount (20%):
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-green-600">-<?php echo formatCurrency($viewOrder['discount_amount']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="5" class="px-4 py-3 text-right text-base font-bold text-gray-900">TOTAL:</td>
                                <td class="px-4 py-3 text-right text-lg font-extrabold text-primary-600"><?php echo formatCurrency($viewOrder['final_amount'] ?? 0); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($viewOrder['message_text'])): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Customer Message</h6>
                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">
                    <?php echo nl2br(htmlspecialchars($viewOrder['message_text'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($viewOrder['custom_fields'])): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Custom Fields</h6>
                <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($viewOrder['custom_fields']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Domain Assignment</h6>
                <?php if ($viewOrder['domain_name']): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                    <i class="bi bi-globe"></i> Assigned Domain: <strong><?php echo htmlspecialchars($viewOrder['domain_name']); ?></strong>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">
                    <i class="bi bi-exclamation-triangle"></i> No domain assigned yet
                </div>
                
                <?php if ($viewOrder['status'] !== 'cancelled' && !empty($availableDomains)): ?>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action" value="assign_domain">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    <div class="grid md:grid-cols-3 gap-2">
                        <div class="md:col-span-2">
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="domain_id" required>
                                <option value="">Select a domain to assign...</option>
                                <?php foreach ($availableDomains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>">
                                    <?php echo htmlspecialchars($domain['domain_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                                <i class="bi bi-link"></i> Assign Domain
                            </button>
                        </div>
                    </div>
                </form>
                <?php elseif ($viewOrder['status'] !== 'cancelled' && empty($availableDomains)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mt-3">
                    <i class="bi bi-exclamation-circle"></i> No available domains for this template. <a href="/admin/domains.php" class="underline font-semibold">Add domains</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($viewOrder['status'] === 'pending'): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Payment Processing</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Amount Paid <span class="text-red-600">*</span></label>
                            <input type="number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="amount_paid" value="<?php echo $viewOrder['template_price']; ?>" step="0.01" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="payment_method">
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Notes</label>
                            <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="payment_notes" rows="2" placeholder="Optional notes about the payment..."></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors">
                                <i class="bi bi-cash-coin mr-2"></i> Mark as Paid
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <?php if ($viewOrder['status'] === 'pending'): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-colors" onclick="return confirm('Are you sure you want to cancel this order?')">
                    <i class="bi bi-x-circle mr-2"></i> Cancel Order
                </button>
            </form>
            <?php endif; ?>
            <a href="/admin/orders.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
