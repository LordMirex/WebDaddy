<?php
/**
 * Affiliate Real-Time Statistics
 * Provides live dashboard data and comprehensive analytics for affiliates
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Get real-time affiliate statistics for today
 */
function getAffiliateRealTimeStats($affiliateId) {
    $db = getDb();
    
    try {
        // Get affiliate code
        $stmt = $db->prepare("SELECT code FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) {
            return null;
        }
        
        $code = $affiliate['code'];
        
        // Today's clicks
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as clicks_today,
                COUNT(DISTINCT ip_address) as unique_visitors_today
            FROM affiliate_clicks 
            WHERE affiliate_id = ? 
            AND date(created_at) = date('now')
        ");
        $stmt->execute([$affiliateId]);
        $clicksToday = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Today's conversions
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as orders_today,
                COALESCE(SUM(final_amount), 0) as revenue_today,
                COALESCE(SUM(affiliate_commission), 0) as commission_today
            FROM pending_orders 
            WHERE affiliate_code = ?
            AND status = 'paid'
            AND date(paid_at) = date('now')
        ");
        $stmt->execute([$code]);
        $ordersToday = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Conversion rate
        $conversionRate = $clicksToday['unique_visitors_today'] > 0 
            ? round(($ordersToday['orders_today'] / $clicksToday['unique_visitors_today']) * 100, 2)
            : 0;
        
        // Pending commissions
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(affiliate_commission), 0) as pending_commission
            FROM pending_orders 
            WHERE affiliate_code = ?
            AND status = 'paid'
            AND affiliate_paid = 0
        ");
        $stmt->execute([$code]);
        $pending = $stmt->fetchColumn();
        
        return [
            'clicks_today' => (int)($clicksToday['clicks_today'] ?? 0),
            'unique_visitors_today' => (int)($clicksToday['unique_visitors_today'] ?? 0),
            'orders_today' => (int)($ordersToday['orders_today'] ?? 0),
            'revenue_today' => (float)($ordersToday['revenue_today'] ?? 0),
            'commission_today' => (float)($ordersToday['commission_today'] ?? 0),
            'conversion_rate' => $conversionRate,
            'pending_commission' => (float)($pending ?? 0),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    } catch (PDOException $e) {
        error_log('Error getting affiliate real-time stats: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get comprehensive affiliate analytics for a date range
 */
function getAffiliateAnalytics($affiliateId, $dateRange = '30d') {
    $db = getDb();
    
    try {
        // Calculate date range
        $days = (int)str_replace('d', '', $dateRange);
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get affiliate code
        $stmt = $db->prepare("SELECT code FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) {
            return null;
        }
        
        $code = $affiliate['code'];
        
        // Daily clicks
        $stmt = $db->prepare("
            SELECT 
                date(created_at) as date,
                COUNT(*) as clicks,
                COUNT(DISTINCT ip_address) as unique_clicks
            FROM affiliate_clicks 
            WHERE affiliate_id = ?
            AND date(created_at) >= ?
            GROUP BY date(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$affiliateId, $startDate]);
        $dailyClicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily conversions
        $stmt = $db->prepare("
            SELECT 
                date(paid_at) as date,
                COUNT(*) as orders,
                SUM(final_amount) as revenue,
                SUM(affiliate_commission) as commission
            FROM pending_orders 
            WHERE affiliate_code = ?
            AND status = 'paid'
            AND date(paid_at) >= ?
            GROUP BY date(paid_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$code, $startDate]);
        $dailyConversions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top products
        $stmt = $db->prepare("
            SELECT 
                COALESCE(t.name, tl.name, 'Unknown Product') as product_name,
                COUNT(*) as order_count,
                SUM(po.final_amount) as revenue,
                SUM(po.affiliate_commission) as commission
            FROM pending_orders po
            LEFT JOIN templates t ON po.template_id = t.id
            LEFT JOIN tools tl ON po.tool_id = tl.id
            WHERE po.affiliate_code = ?
            AND po.status = 'paid'
            AND po.paid_at >= ?
            GROUP BY COALESCE(t.name, tl.name, 'Unknown Product')
            ORDER BY commission DESC
            LIMIT 10
        ");
        $stmt->execute([$code, $startDate]);
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Traffic sources
        $stmt = $db->prepare("
            SELECT 
                COALESCE(referrer_domain, 'Direct') as referrer_domain,
                COUNT(*) as clicks,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM affiliate_clicks 
            WHERE affiliate_id = ?
            AND created_at >= ?
            GROUP BY referrer_domain
            ORDER BY clicks DESC
            LIMIT 10
        ");
        $stmt->execute([$affiliateId, $startDate]);
        $trafficSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Totals
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(final_amount), 0) as total_revenue,
                COALESCE(SUM(affiliate_commission), 0) as total_commission,
                COALESCE(AVG(final_amount), 0) as avg_order_value
            FROM pending_orders 
            WHERE affiliate_code = ?
            AND status = 'paid'
            AND paid_at >= ?
        ");
        $stmt->execute([$code, $startDate]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM affiliate_clicks 
            WHERE affiliate_id = ?
            AND created_at >= ?
        ");
        $stmt->execute([$affiliateId, $startDate]);
        $clickTotals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate conversion rate
        $conversionRate = $clickTotals['unique_visitors'] > 0 
            ? round(($totals['total_orders'] / $clickTotals['unique_visitors']) * 100, 2)
            : 0;
        
        return [
            'period' => $dateRange,
            'totals' => [
                'clicks' => (int)($clickTotals['total_clicks'] ?? 0),
                'unique_visitors' => (int)($clickTotals['unique_visitors'] ?? 0),
                'orders' => (int)($totals['total_orders'] ?? 0),
                'revenue' => (float)($totals['total_revenue'] ?? 0),
                'commission' => (float)($totals['total_commission'] ?? 0),
                'avg_order_value' => (float)($totals['avg_order_value'] ?? 0),
                'conversion_rate' => $conversionRate
            ],
            'daily_clicks' => $dailyClicks,
            'daily_conversions' => $dailyConversions,
            'top_products' => $topProducts,
            'traffic_sources' => $trafficSources
        ];
    } catch (PDOException $e) {
        error_log('Error getting affiliate analytics: ' . $e->getMessage());
        return null;
    }
}

/**
 * Record an affiliate click
 */
function recordAffiliateClick($affiliateId, $ipAddress = null, $userAgent = null, $referrerDomain = null) {
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO affiliate_clicks (affiliate_id, ip_address, user_agent, referrer_domain, created_at)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$affiliateId, $ipAddress, $userAgent, $referrerDomain]);
        
        // Update total clicks on affiliate record
        $stmt = $db->prepare("UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE id = ?");
        $stmt->execute([$affiliateId]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('Error recording affiliate click: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activity events for live dashboard
 */
function getAffiliateRecentActivity($affiliateId, $limit = 20) {
    $db = getDb();
    
    try {
        // Get affiliate code
        $stmt = $db->prepare("SELECT code FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) {
            return [];
        }
        
        $code = $affiliate['code'];
        $events = [];
        
        // Get recent clicks (last 24 hours)
        $stmt = $db->prepare("
            SELECT 
                id, 
                'click' as type, 
                ip_address,
                referrer_domain,
                created_at as time
            FROM affiliate_clicks 
            WHERE affiliate_id = ? 
            AND created_at >= datetime('now', '-24 hours')
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$affiliateId, $limit]);
        $clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($clicks as $click) {
            $events[] = [
                'id' => 'click_' . $click['id'],
                'type' => 'click',
                'message' => 'New visitor from ' . ($click['referrer_domain'] ?: 'direct link'),
                'time' => $click['time']
            ];
        }
        
        // Get recent orders (last 7 days)
        $stmt = $db->prepare("
            SELECT 
                id,
                'order' as type,
                final_amount,
                affiliate_commission,
                paid_at as time
            FROM pending_orders 
            WHERE affiliate_code = ?
            AND status = 'paid'
            AND paid_at >= datetime('now', '-7 days')
            ORDER BY paid_at DESC
            LIMIT ?
        ");
        $stmt->execute([$code, $limit]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            $events[] = [
                'id' => 'order_' . $order['id'],
                'type' => 'order',
                'message' => 'Sale completed - ₦' . number_format($order['final_amount'], 2),
                'time' => $order['time']
            ];
            
            if ($order['affiliate_commission'] > 0) {
                $events[] = [
                    'id' => 'commission_' . $order['id'],
                    'type' => 'commission',
                    'message' => 'Commission earned - ₦' . number_format($order['affiliate_commission'], 2),
                    'time' => $order['time']
                ];
            }
        }
        
        // Sort by time descending
        usort($events, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        return array_slice($events, 0, $limit);
    } catch (PDOException $e) {
        error_log('Error getting affiliate recent activity: ' . $e->getMessage());
        return [];
    }
}
