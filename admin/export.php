<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/finance_metrics.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

$exportType = $_GET['export'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if ($exportType) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $exportType . '_' . $startDate . '_to_' . $endDate . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($exportType === 'orders') {
        fputcsv($output, [
            'Order ID', 'Date', 'Customer Name', 'Customer Email', 'Customer Phone',
            'Order Type', 'Status', 'Original Amount', 'Discount', 'Final Amount',
            'Payment Method', 'Affiliate Code', 'Commission Amount', 'Notes'
        ]);
        
        // Use sales table as source of truth for commission (actual paid commissions)
        $stmt = $db->prepare("
            SELECT po.*, s.commission_amount
            FROM pending_orders po
            LEFT JOIN sales s ON po.id = s.pending_order_id
            WHERE po.created_at BETWEEN ? AND ?
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['id'],
                date('Y-m-d H:i', strtotime($order['created_at'])),
                $order['customer_name'],
                $order['customer_email'],
                $order['customer_phone'],
                $order['order_type'] ?? 'template',
                $order['status'],
                number_format($order['original_price'] ?? $order['final_amount'], 2, '.', ''),
                number_format($order['discount_amount'] ?? 0, 2, '.', ''),
                number_format($order['final_amount'] ?? 0, 2, '.', ''),
                $order['payment_method'] ?? 'manual',
                $order['affiliate_code'] ?? '',
                number_format($order['commission_amount'] ?? 0, 2, '.', ''),
                $order['payment_notes'] ?? ''
            ]);
        }
        
        logActivity('export_orders', "Exported orders from $startDate to $endDate", getAdminId());
        
    } elseif ($exportType === 'order_items') {
        fputcsv($output, [
            'Order ID', 'Item ID', 'Product Type', 'Product Name', 'Quantity',
            'Unit Price', 'Discount', 'Final Amount', 'Domain', 'Order Date'
        ]);
        
        $stmt = $db->prepare("
            SELECT oi.*, po.created_at as order_date,
                   CASE 
                       WHEN oi.product_type = 'template' THEN t.name 
                       WHEN oi.product_type = 'tool' THEN tl.name 
                   END as product_name
            FROM order_items oi
            JOIN pending_orders po ON oi.pending_order_id = po.id
            LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
            LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
            WHERE po.created_at BETWEEN ? AND ?
            ORDER BY po.created_at DESC, oi.id ASC
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $metadata = !empty($item['metadata_json']) ? json_decode($item['metadata_json'], true) : [];
            fputcsv($output, [
                $item['pending_order_id'],
                $item['id'],
                $item['product_type'],
                $item['product_name'] ?? 'Unknown',
                $item['quantity'],
                number_format($item['unit_price'], 2, '.', ''),
                number_format($item['discount_amount'] ?? 0, 2, '.', ''),
                number_format($item['final_amount'], 2, '.', ''),
                $metadata['domain_name'] ?? '',
                date('Y-m-d H:i', strtotime($item['order_date']))
            ]);
        }
        
        logActivity('export_order_items', "Exported order items from $startDate to $endDate", getAdminId());
        
    } elseif ($exportType === 'deliveries') {
        fputcsv($output, [
            'Delivery ID', 'Order ID', 'Product Type', 'Product Name', 'Status',
            'Domain', 'Username', 'Login URL', 'Hosting Provider',
            'Created At', 'Delivered At', 'Email Sent At', 'Retry Count'
        ]);
        
        $stmt = $db->prepare("
            SELECT d.*, po.customer_name, po.customer_email
            FROM deliveries d
            JOIN pending_orders po ON d.pending_order_id = po.id
            WHERE d.created_at BETWEEN ? AND ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($deliveries as $d) {
            fputcsv($output, [
                $d['id'],
                $d['pending_order_id'],
                $d['product_type'],
                $d['product_name'],
                $d['delivery_status'],
                $d['hosted_domain'] ?? '',
                $d['template_admin_username'] ?? '',
                $d['template_login_url'] ?? '',
                $d['hosting_provider'] ?? '',
                $d['created_at'],
                $d['delivered_at'] ?? '',
                $d['email_sent_at'] ?? '',
                $d['retry_count'] ?? 0
            ]);
        }
        
        logActivity('export_deliveries', "Exported deliveries from $startDate to $endDate", getAdminId());
        
    } elseif ($exportType === 'affiliates') {
        fputcsv($output, [
            'Affiliate Code', 'Affiliate Name', 'Email', 'Status',
            'Total Orders', 'Total Revenue', 'Total Commission', 'Paid Commission',
            'Unpaid Commission', 'Created Date'
        ]);
        
        $stmt = $db->prepare("
            SELECT a.*,
                   COUNT(po.id) as total_orders,
                   SUM(CASE WHEN po.status = 'paid' THEN po.final_amount ELSE 0 END) as total_revenue,
                   SUM(CASE WHEN po.status = 'paid' THEN po.final_amount * 0.3 ELSE 0 END) as total_commission
            FROM affiliates a
            LEFT JOIN pending_orders po ON po.affiliate_code = a.code AND po.created_at BETWEEN ? AND ?
            GROUP BY a.id, a.code, a.name, a.email, a.status, a.total_earnings, a.pending_earnings, a.created_at
            ORDER BY total_commission DESC
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($affiliates as $aff) {
            fputcsv($output, [
                $aff['code'],
                $aff['name'],
                $aff['email'],
                $aff['status'],
                $aff['total_orders'] ?? 0,
                number_format($aff['total_revenue'] ?? 0, 2, '.', ''),
                number_format($aff['total_commission'] ?? 0, 2, '.', ''),
                number_format($aff['total_earnings'] ?? 0, 2, '.', ''),
                number_format($aff['pending_earnings'] ?? 0, 2, '.', ''),
                $aff['created_at'] ?? ''
            ]);
        }
        
        logActivity('export_affiliates', "Exported affiliates from $startDate to $endDate", getAdminId());
        
    } elseif ($exportType === 'download_analytics') {
        fputcsv($output, [
            'Token ID', 'Order ID', 'Tool ID', 'Tool Name', 'File Name',
            'Download Count', 'Max Downloads', 'Created At', 'Expires At', 'Status'
        ]);
        
        $stmt = $db->prepare("
            SELECT dt.*, tf.file_name, tf.tool_id, tl.name as tool_name
            FROM download_tokens dt
            JOIN tool_files tf ON dt.file_id = tf.id
            JOIN tools tl ON tf.tool_id = tl.id
            WHERE dt.created_at BETWEEN ? AND ?
            ORDER BY dt.created_at DESC
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tokens as $t) {
            $expiresAt = $t['expires_at'] ?? '';
            $downloadCount = intval($t['download_count'] ?? 0);
            $maxDownloads = intval($t['max_downloads'] ?? 5);
            
            $status = 'Active';
            if ($expiresAt && strtotime($expiresAt) < time()) {
                $status = 'Expired';
            } elseif ($downloadCount >= $maxDownloads) {
                $status = 'Max Downloads';
            }
            
            fputcsv($output, [
                $t['id'] ?? '',
                $t['pending_order_id'] ?? '',
                $t['tool_id'] ?? '',
                $t['tool_name'] ?? 'Unknown',
                $t['file_name'] ?? 'Unknown',
                $downloadCount,
                $maxDownloads,
                $t['created_at'] ?? '',
                $expiresAt,
                $status
            ]);
        }
        
        logActivity('export_downloads', "Exported download analytics from $startDate to $endDate", getAdminId());
        
    } elseif ($exportType === 'financial_summary') {
        fputcsv($output, ['Metric', 'Value (NGN)']);
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = 'paid' THEN final_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'paid' AND affiliate_code IS NOT NULL THEN final_amount * 0.3 ELSE 0 END) as total_commissions,
                SUM(CASE WHEN order_type = 'template' AND status = 'paid' THEN final_amount ELSE 0 END) as template_revenue,
                SUM(CASE WHEN (order_type = 'tool' OR order_type = 'tools') AND status = 'paid' THEN final_amount ELSE 0 END) as tool_revenue,
                SUM(CASE WHEN order_type = 'mixed' AND status = 'paid' THEN final_amount ELSE 0 END) as mixed_revenue
            FROM pending_orders
            WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDateTime, $endDateTime]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Round to 2 decimal places to avoid floating-point precision artifacts
        $totalRevenue = round(floatval($summary['total_revenue'] ?? 0), 2);
        $totalCommissions = round(floatval($summary['total_commissions'] ?? 0), 2);
        $templateRevenue = round(floatval($summary['template_revenue'] ?? 0), 2);
        $toolRevenue = round(floatval($summary['tool_revenue'] ?? 0), 2);
        $mixedRevenue = round(floatval($summary['mixed_revenue'] ?? 0), 2);
        $netRevenue = round($totalRevenue - $totalCommissions, 2);
        
        fputcsv($output, ['Report Period', "$startDate to $endDate"]);
        fputcsv($output, ['Total Orders', $summary['total_orders'] ?? 0]);
        fputcsv($output, ['Paid Orders', $summary['paid_orders'] ?? 0]);
        fputcsv($output, ['Total Revenue', $totalRevenue]);
        fputcsv($output, ['Template Revenue', $templateRevenue]);
        fputcsv($output, ['Tool Revenue', $toolRevenue]);
        fputcsv($output, ['Mixed Order Revenue', $mixedRevenue]);
        fputcsv($output, ['Total Affiliate Commissions', $totalCommissions]);
        fputcsv($output, ['Net Revenue (After Commissions)', $netRevenue]);
        
        logActivity('export_financial_summary', "Exported financial summary from $startDate to $endDate", getAdminId());
    }
    
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-download text-primary-600"></i> Export Reports
    </h1>
    <p class="text-gray-600 mt-2">Download CSV reports for orders, deliveries, affiliates, and more</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="bi bi-calendar3 text-primary-600"></i> Date Range
    </h3>
    
    <form id="dateRangeForm" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="setDateRange('today')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">Today</button>
            <button type="button" onclick="setDateRange('week')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">This Week</button>
            <button type="button" onclick="setDateRange('month')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">This Month</button>
            <button type="button" onclick="setDateRange('year')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">This Year</button>
        </div>
    </form>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-blue-100">
                <i class="bi bi-cart text-blue-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Orders</h4>
                <p class="text-sm text-gray-500">All order records with customer info</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Includes order ID, customer details, amounts, payment method, affiliate code, and status.</p>
        <button onclick="downloadReport('orders')" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Orders CSV
        </button>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-purple-100">
                <i class="bi bi-list-ul text-purple-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Order Items</h4>
                <p class="text-sm text-gray-500">Line items for each order</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Detailed breakdown of each product in orders with quantities, prices, and discounts.</p>
        <button onclick="downloadReport('order_items')" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Items CSV
        </button>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-green-100">
                <i class="bi bi-truck text-green-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Deliveries</h4>
                <p class="text-sm text-gray-500">All delivery records</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Delivery status, domains, credentials info, hosting providers, and timestamps.</p>
        <button onclick="downloadReport('deliveries')" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Deliveries CSV
        </button>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-orange-100">
                <i class="bi bi-people text-orange-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Affiliates</h4>
                <p class="text-sm text-gray-500">Affiliate performance report</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Affiliate codes, names, order counts, revenue generated, and commissions earned.</p>
        <button onclick="downloadReport('affiliates')" class="w-full px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Affiliates CSV
        </button>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-indigo-100">
                <i class="bi bi-file-earmark-arrow-down text-indigo-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Download Analytics</h4>
                <p class="text-sm text-gray-500">Tool download statistics</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Download tokens, usage counts, expiry dates, and file access statistics.</p>
        <button onclick="downloadReport('download_analytics')" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Analytics CSV
        </button>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-emerald-100">
                <i class="bi bi-currency-dollar text-emerald-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-gray-900">Financial Summary</h4>
                <p class="text-sm text-gray-500">Revenue and commission overview</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">Summary of total revenue, order counts, breakdown by type, and net after commissions.</p>
        <button onclick="downloadReport('financial_summary')" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <i class="bi bi-download"></i> Download Summary CSV
        </button>
    </div>
</div>

<div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <div class="flex items-start gap-3">
        <i class="bi bi-info-circle text-blue-600 text-xl"></i>
        <div>
            <h4 class="font-semibold text-blue-800 mb-1">CSV Export Tips</h4>
            <ul class="text-sm text-blue-700 space-y-1 mt-2">
                <li>All exports are in UTF-8 format for proper character encoding</li>
                <li>Open CSV files in Excel or Google Sheets for analysis</li>
                <li>Financial amounts are exported in NGN (Nigerian Naira)</li>
                <li>Exports are logged for audit purposes</li>
            </ul>
        </div>
    </div>
</div>

<script>
function setDateRange(range) {
    const today = new Date();
    let startDate, endDate = today.toISOString().split('T')[0];
    
    switch(range) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            startDate = weekAgo.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = today.toISOString().slice(0, 8) + '01';
            break;
        case 'year':
            startDate = today.getFullYear() + '-01-01';
            break;
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
}

function downloadReport(type) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (!startDate || !endDate) {
        alert('Please select a date range');
        return;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        alert('Start date cannot be after end date');
        return;
    }
    
    window.location.href = `?export=${type}&start_date=${startDate}&end_date=${endDate}`;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
