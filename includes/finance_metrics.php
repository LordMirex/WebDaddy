<?php
/**
 * Financial Metrics Helper
 * 
 * Provides standardized financial calculations across all admin pages
 * to ensure consistency in revenue, commission, and sales reporting.
 */

/**
 * Get comprehensive revenue metrics
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering
 * @param array $params Parameters for prepared statement
 * @return array Standardized financial metrics
 */
function getRevenueMetrics($db, $dateFilter = '', $params = []) {
    $query = "
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(s.amount_paid), 0) as total_revenue,
            COALESCE(SUM(s.commission_amount), 0) as total_commission
        FROM sales s
        WHERE 1=1 $dateFilter
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate derived metrics
    $metrics['net_revenue'] = $metrics['total_revenue'] - $metrics['total_commission'];
    $metrics['avg_order_value'] = $metrics['total_sales'] > 0 
        ? $metrics['total_revenue'] / $metrics['total_sales'] 
        : 0;
    
    return $metrics;
}

/**
 * Get discount metrics
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering (converts sales table filter to pending_orders)
 * @param array $params Parameters for prepared statement
 * @return array Discount metrics
 */
function getDiscountMetrics($db, $dateFilter = '', $params = []) {
    // Convert date filter from sales table (s.created_at) to pending_orders table (created_at)
    $poDateFilter = str_replace('s.created_at', 'created_at', $dateFilter);
    
    $query = "
        SELECT 
            COUNT(*) as orders_with_discount,
            COALESCE(SUM(discount_amount), 0) as total_discount,
            COALESCE(AVG(discount_amount), 0) as avg_discount
        FROM pending_orders
        WHERE status IN ('paid', 'completed') AND discount_amount > 0 $poDateFilter
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total_discount' => (float)($result['total_discount'] ?? 0),
        'orders_with_discount' => (int)($result['orders_with_discount'] ?? 0),
        'avg_discount' => (float)($result['avg_discount'] ?? 0)
    ];
}

/**
 * Get revenue breakdown by order type (templates, tools, mixed)
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering
 * @param array $params Parameters for prepared statement
 * @return array Revenue breakdown by type
 */
function getRevenueByOrderType($db, $dateFilter = '', $params = []) {
    $query = "
        SELECT 
            COALESCE(po.order_type, 'template') as order_type,
            COUNT(DISTINCT s.id) as order_count,
            COALESCE(SUM(s.amount_paid), 0) as revenue,
            COALESCE(SUM(s.commission_amount), 0) as commission
        FROM sales s
        JOIN pending_orders po ON s.pending_order_id = po.id
        WHERE 1=1 $dateFilter
        GROUP BY po.order_type
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize breakdown
    $breakdown = [
        'template' => ['orders' => 0, 'revenue' => 0, 'commission' => 0],
        'tools' => ['orders' => 0, 'revenue' => 0, 'commission' => 0],
        'mixed' => ['orders' => 0, 'revenue' => 0, 'commission' => 0]
    ];
    
    // Populate breakdown
    foreach ($results as $row) {
        $type = $row['order_type'] ?? 'template';
        if (isset($breakdown[$type])) {
            $breakdown[$type] = [
                'orders' => (int)$row['order_count'],
                'revenue' => (float)$row['revenue'],
                'commission' => (float)$row['commission']
            ];
        }
    }
    
    return $breakdown;
}

/**
 * Get actual tool sales from order_items (includes tools in mixed orders)
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering (on sales table)
 * @param array $params Parameters for prepared statement
 * @return array Tool sales metrics
 */
function getToolSalesMetrics($db, $dateFilter = '', $params = []) {
    $query = "
        SELECT 
            COUNT(DISTINCT s.id) as order_count,
            SUM(oi.quantity) as total_quantity,
            COALESCE(SUM(oi.final_amount), 0) as revenue
        FROM sales s
        JOIN pending_orders po ON s.pending_order_id = po.id
        JOIN order_items oi ON oi.pending_order_id = po.id
        WHERE oi.product_type = 'tool' $dateFilter
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'orders' => (int)($result['order_count'] ?? 0),
        'quantity' => (int)($result['total_quantity'] ?? 0),
        'revenue' => (float)($result['revenue'] ?? 0)
    ];
}

/**
 * Get actual template sales from order_items (includes templates in mixed orders)
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering (on sales table)
 * @param array $params Parameters for prepared statement
 * @return array Template sales metrics
 */
function getTemplateSalesMetrics($db, $dateFilter = '', $params = []) {
    $query = "
        SELECT 
            COUNT(DISTINCT s.id) as order_count,
            SUM(oi.quantity) as total_quantity,
            COALESCE(SUM(oi.final_amount), 0) as revenue
        FROM sales s
        JOIN pending_orders po ON s.pending_order_id = po.id
        JOIN order_items oi ON oi.pending_order_id = po.id
        WHERE oi.product_type = 'template' $dateFilter
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'orders' => (int)($result['order_count'] ?? 0),
        'quantity' => (int)($result['total_quantity'] ?? 0),
        'revenue' => (float)($result['revenue'] ?? 0)
    ];
}

/**
 * Get top selling products (templates and tools combined)
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering
 * @param array $params Parameters for prepared statement
 * @param int $limit Number of products to return
 * @return array Top selling products
 */
function getTopProducts($db, $dateFilter = '', $params = [], $limit = 10) {
    $query = "
        SELECT 
            oi.product_id,
            oi.product_type,
            oi.metadata_json,
            CASE 
                WHEN oi.product_type = 'template' THEN t.name
                WHEN oi.product_type = 'tool' THEN tool.name
            END as product_name,
            COUNT(DISTINCT s.id) as sales_count,
            COALESCE(SUM(oi.final_amount), 0) as revenue,
            SUM(oi.quantity) as total_quantity
        FROM sales s
        JOIN pending_orders po ON s.pending_order_id = po.id
        JOIN order_items oi ON oi.pending_order_id = po.id
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tool ON oi.product_type = 'tool' AND oi.product_id = tool.id
        WHERE 1=1 $dateFilter
        GROUP BY oi.product_id, oi.product_type
        ORDER BY revenue DESC
        LIMIT ?
    ";
    
    $allParams = array_merge($params, [$limit]);
    $stmt = $db->prepare($query);
    $stmt->execute($allParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback to metadata if name is empty
    foreach ($products as &$product) {
        if (empty($product['product_name']) && !empty($product['metadata_json'])) {
            $metadata = @json_decode($product['metadata_json'], true);
            if (is_array($metadata) && isset($metadata['name'])) {
                $product['product_name'] = $metadata['name'];
            }
        }
        if (empty($product['product_name'])) {
            $product['product_name'] = 'Unknown Product';
        }
    }
    
    return $products;
}

/**
 * Get top performing affiliates
 * 
 * @param PDO $db Database connection
 * @param string $dateFilter SQL WHERE clause for date filtering
 * @param array $params Parameters for prepared statement
 * @param int $limit Number of affiliates to return
 * @return array Top affiliates
 */
function getTopAffiliates($db, $dateFilter = '', $params = [], $limit = 5) {
    $query = "
        SELECT 
            a.code,
            u.name as affiliate_name,
            COUNT(s.id) as sales_count,
            COALESCE(SUM(s.commission_amount), 0) as total_commission,
            COALESCE(SUM(s.amount_paid), 0) as total_sales_value
        FROM sales s
        JOIN affiliates a ON s.affiliate_id = a.id
        JOIN users u ON a.user_id = u.id
        WHERE s.affiliate_id IS NOT NULL $dateFilter
        GROUP BY a.id, a.code, u.name
        ORDER BY total_commission DESC
        LIMIT ?
    ";
    
    $allParams = array_merge($params, [$limit]);
    $stmt = $db->prepare($query);
    $stmt->execute($allParams);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build standard date filter for sales queries
 * 
 * @param string $period Period identifier (today, week, month, all, custom)
 * @param string $startDate Start date for custom period
 * @param string $endDate End date for custom period
 * @return array ['filter' => SQL string, 'params' => array of parameters]
 */
function buildDateFilter($period, $startDate = '', $endDate = '') {
    $filter = '';
    $params = [];
    
    switch ($period) {
        case 'today':
            $filter = "AND DATE(s.created_at) = DATE('now')";
            break;
        case '7days':
        case 'week':
            $filter = "AND DATE(s.created_at) >= DATE('now', '-7 days')";
            break;
        case '30days':
        case 'month':
            $filter = "AND DATE(s.created_at) >= DATE('now', '-30 days')";
            break;
        case '90days':
            $filter = "AND DATE(s.created_at) >= DATE('now', '-90 days')";
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $filter = "AND DATE(s.created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }
            break;
        case 'all':
        default:
            // No filter
            break;
    }
    
    return ['filter' => $filter, 'params' => $params];
}
