# Affiliate Enhancements

## Overview

This document outlines improvements to the affiliate system including real-time tracking, fraud detection, marketing assets, and an enhanced analytics dashboard.

---

## 1. Real-Time Tracking

### Live Dashboard Updates

```php
/**
 * Real-time affiliate statistics
 */
function getAffiliateRealTimeStats($affiliateId) {
    $db = getDb();
    
    // Today's stats
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
        WHERE affiliate_code = (SELECT code FROM affiliates WHERE id = ?)
        AND status = 'paid'
        AND date(paid_at) = date('now')
    ");
    $stmt->execute([$affiliateId]);
    $ordersToday = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Conversion rate
    $conversionRate = $clicksToday['unique_visitors_today'] > 0 
        ? round(($ordersToday['orders_today'] / $clicksToday['unique_visitors_today']) * 100, 2)
        : 0;
    
    // Pending commissions
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(affiliate_commission), 0) as pending_commission
        FROM pending_orders 
        WHERE affiliate_code = (SELECT code FROM affiliates WHERE id = ?)
        AND status = 'paid'
        AND affiliate_paid = 0
    ");
    $stmt->execute([$affiliateId]);
    $pending = $stmt->fetchColumn();
    
    return [
        'clicks_today' => (int)$clicksToday['clicks_today'],
        'unique_visitors_today' => (int)$clicksToday['unique_visitors_today'],
        'orders_today' => (int)$ordersToday['orders_today'],
        'revenue_today' => (float)$ordersToday['revenue_today'],
        'commission_today' => (float)$ordersToday['commission_today'],
        'conversion_rate' => $conversionRate,
        'pending_commission' => (float)$pending,
        'last_updated' => date('Y-m-d H:i:s')
    ];
}
```

### Live Activity Feed

```html
<!-- Affiliate Dashboard - Live Activity -->
<div class="live-activity" x-data="liveActivity()">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">Live Activity</h3>
        <span class="live-dot pulse"></span>
    </div>
    
    <div class="activity-feed max-h-64 overflow-y-auto">
        <template x-for="event in events" :key="event.id">
            <div class="activity-item" :class="event.type">
                <div class="activity-icon">
                    <i :class="getEventIcon(event.type)"></i>
                </div>
                <div class="activity-content">
                    <p x-text="event.message"></p>
                    <span class="text-xs text-gray-500" x-text="formatTime(event.time)"></span>
                </div>
            </div>
        </template>
        
        <div x-show="events.length === 0" class="text-center text-gray-400 py-4">
            No activity yet today
        </div>
    </div>
</div>

<script>
function liveActivity() {
    return {
        events: [],
        
        init() {
            this.loadEvents();
            // Poll every 30 seconds
            setInterval(() => this.loadEvents(), 30000);
        },
        
        async loadEvents() {
            const response = await fetch('/api/affiliate/live-activity.php');
            const data = await response.json();
            if (data.success) {
                this.events = data.events;
            }
        },
        
        getEventIcon(type) {
            const icons = {
                'click': 'bi bi-cursor-fill text-blue-500',
                'order': 'bi bi-cart-check-fill text-green-500',
                'commission': 'bi bi-cash-coin text-yellow-500'
            };
            return icons[type] || 'bi bi-circle-fill';
        },
        
        formatTime(time) {
            const date = new Date(time);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
        }
    }
}
</script>
```

---

## 2. Fraud Detection

### Detection Rules

```php
/**
 * Affiliate fraud detection system
 */
class AffiliateFraudDetector {
    private $db;
    private $rules = [
        'click_velocity' => ['threshold' => 100, 'window' => 3600], // 100 clicks/hour
        'same_ip_orders' => ['threshold' => 3, 'window' => 86400], // 3 orders from same IP/day
        'self_referral' => true,
        'bot_detection' => true,
        'suspicious_patterns' => true
    ];
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Analyze click for fraud
     */
    public function analyzeClick($affiliateId, $ipAddress, $userAgent) {
        $flags = [];
        
        // Check click velocity
        if ($this->checkClickVelocity($affiliateId, $ipAddress)) {
            $flags[] = ['type' => 'high_click_velocity', 'severity' => 'medium'];
        }
        
        // Check for bot signatures
        if ($this->isBot($userAgent)) {
            $flags[] = ['type' => 'bot_detected', 'severity' => 'high'];
        }
        
        // Check for VPN/Proxy
        if ($this->isVpnOrProxy($ipAddress)) {
            $flags[] = ['type' => 'vpn_proxy_detected', 'severity' => 'low'];
        }
        
        // Log flags
        if (!empty($flags)) {
            $this->logFraudFlags($affiliateId, 'click', $flags, $ipAddress);
        }
        
        return $flags;
    }
    
    /**
     * Analyze order for fraud
     */
    public function analyzeOrder($orderId, $affiliateId) {
        $flags = [];
        $db = $this->db;
        
        $stmt = $db->prepare("
            SELECT po.*, a.email as affiliate_email
            FROM pending_orders po
            JOIN affiliates a ON po.affiliate_code = a.code
            WHERE po.id = ? AND a.id = ?
        ");
        $stmt->execute([$orderId, $affiliateId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) return $flags;
        
        // Check for self-referral
        if ($this->isSelfReferral($order)) {
            $flags[] = ['type' => 'self_referral', 'severity' => 'high'];
        }
        
        // Check for multiple orders from same IP
        if ($this->checkSameIpOrders($affiliateId, $order['ip_address'] ?? '')) {
            $flags[] = ['type' => 'same_ip_multiple_orders', 'severity' => 'medium'];
        }
        
        // Check for email pattern abuse
        if ($this->checkEmailPatternAbuse($affiliateId, $order['customer_email'])) {
            $flags[] = ['type' => 'email_pattern_abuse', 'severity' => 'medium'];
        }
        
        // Check conversion timing
        if ($this->checkSuspiciousTiming($affiliateId, $orderId)) {
            $flags[] = ['type' => 'suspicious_timing', 'severity' => 'low'];
        }
        
        // Log and handle flags
        if (!empty($flags)) {
            $this->logFraudFlags($affiliateId, 'order', $flags, null, $orderId);
            
            // Auto-action based on severity
            $highSeverity = array_filter($flags, fn($f) => $f['severity'] === 'high');
            if (count($highSeverity) > 0) {
                $this->holdCommission($orderId);
            }
        }
        
        return $flags;
    }
    
    /**
     * Check for high click velocity
     */
    private function checkClickVelocity($affiliateId, $ipAddress) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM affiliate_clicks 
            WHERE affiliate_id = ? 
            AND ip_address = ?
            AND created_at > datetime('now', '-1 hour')
        ");
        $stmt->execute([$affiliateId, $ipAddress]);
        return $stmt->fetchColumn() > $this->rules['click_velocity']['threshold'];
    }
    
    /**
     * Detect bot user agents
     */
    private function isBot($userAgent) {
        $botPatterns = [
            '/bot/i', '/crawler/i', '/spider/i', '/curl/i', 
            '/wget/i', '/python/i', '/scrapy/i', '/headless/i'
        ];
        
        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for self-referral
     */
    private function isSelfReferral($order) {
        // Check if order email matches affiliate email
        if (strtolower($order['customer_email']) === strtolower($order['affiliate_email'])) {
            return true;
        }
        
        // Check for similar email patterns
        $orderDomain = substr($order['customer_email'], strpos($order['customer_email'], '@'));
        $affiliateDomain = substr($order['affiliate_email'], strpos($order['affiliate_email'], '@'));
        
        // If same custom domain (not common providers)
        $commonDomains = ['@gmail.com', '@yahoo.com', '@hotmail.com', '@outlook.com'];
        if (!in_array($orderDomain, $commonDomains) && $orderDomain === $affiliateDomain) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log fraud flags
     */
    private function logFraudFlags($affiliateId, $context, $flags, $ipAddress = null, $orderId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO affiliate_fraud_logs 
            (affiliate_id, context, flags, ip_address, order_id, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $affiliateId, 
            $context, 
            json_encode($flags), 
            $ipAddress, 
            $orderId
        ]);
        
        // Notify admin for high severity
        $highSeverity = array_filter($flags, fn($f) => $f['severity'] === 'high');
        if (count($highSeverity) > 0) {
            sendAdminNotification("Affiliate fraud alert: High severity flags detected", [
                'affiliate_id' => $affiliateId,
                'context' => $context,
                'flags' => $flags
            ]);
        }
    }
    
    /**
     * Hold commission pending review
     */
    private function holdCommission($orderId) {
        $stmt = $this->db->prepare("
            UPDATE pending_orders 
            SET affiliate_commission_held = 1,
                affiliate_hold_reason = 'fraud_review'
            WHERE id = ?
        ");
        $stmt->execute([$orderId]);
    }
}

// Database tables
/*
CREATE TABLE affiliate_fraud_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    context TEXT NOT NULL,
    flags TEXT NOT NULL,
    ip_address TEXT,
    order_id INTEGER REFERENCES pending_orders(id),
    is_reviewed INTEGER DEFAULT 0,
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TEXT,
    action_taken TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_fraud_logs_affiliate ON affiliate_fraud_logs(affiliate_id);
CREATE INDEX idx_fraud_logs_reviewed ON affiliate_fraud_logs(is_reviewed);

-- Add to pending_orders
ALTER TABLE pending_orders ADD COLUMN affiliate_commission_held INTEGER DEFAULT 0;
ALTER TABLE pending_orders ADD COLUMN affiliate_hold_reason TEXT;
*/
```

### Fraud Dashboard

```html
<!-- Admin Affiliate Fraud Review -->
<div class="fraud-review-dashboard">
    <h2 class="text-xl font-bold mb-6">Affiliate Fraud Review</h2>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <span class="stat-label">Pending Review</span>
            <span class="stat-value text-yellow-600">12</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Held Commissions</span>
            <span class="stat-value text-red-600">₦45,000</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Confirmed Fraud (30d)</span>
            <span class="stat-value text-red-600">3</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">False Positives (30d)</span>
            <span class="stat-value text-green-600">8</span>
        </div>
    </div>
    
    <!-- Fraud Cases List -->
    <div class="fraud-cases">
        <div class="fraud-case">
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-red">HIGH</span>
                        <span class="font-semibold">Affiliate: John Marketer (AFF001)</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">
                        Self-referral detected on Order #1234
                    </p>
                </div>
                <span class="text-sm text-gray-500">2 hours ago</span>
            </div>
            
            <div class="mt-3 bg-gray-50 rounded p-3">
                <p class="text-sm"><strong>Order:</strong> #1234 - ₦25,000</p>
                <p class="text-sm"><strong>Commission:</strong> ₦2,500 (HELD)</p>
                <p class="text-sm"><strong>Flags:</strong> self_referral, same_ip_multiple_orders</p>
            </div>
            
            <div class="flex gap-2 mt-3">
                <button class="btn btn-sm btn-success">Approve Commission</button>
                <button class="btn btn-sm btn-danger">Reject & Flag</button>
                <button class="btn btn-sm btn-secondary">View Details</button>
            </div>
        </div>
    </div>
</div>
```

---

## 3. Marketing Assets

### Asset Library

```sql
CREATE TABLE affiliate_marketing_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('banner', 'logo', 'social_image', 'email_template', 'video', 'other')),
    description TEXT,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    dimensions TEXT, -- e.g., "728x90"
    thumbnail_path TEXT,
    download_count INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE affiliate_asset_downloads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES affiliate_marketing_assets(id) ON DELETE CASCADE,
    affiliate_id INTEGER NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    downloaded_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

### Asset Library UI

```html
<!-- Affiliate Marketing Assets -->
<div class="marketing-assets" x-data="assetLibrary()">
    <h2 class="text-xl font-bold mb-6">Marketing Assets</h2>
    
    <!-- Category Tabs -->
    <div class="tabs mb-6">
        <button @click="category = 'all'" :class="category === 'all' ? 'active' : ''">All</button>
        <button @click="category = 'banner'" :class="category === 'banner' ? 'active' : ''">Banners</button>
        <button @click="category = 'social_image'" :class="category === 'social_image' ? 'active' : ''">Social Media</button>
        <button @click="category = 'email_template'" :class="category === 'email_template' ? 'active' : ''">Email Templates</button>
    </div>
    
    <!-- Assets Grid -->
    <div class="grid grid-cols-3 gap-6">
        <template x-for="asset in filteredAssets" :key="asset.id">
            <div class="asset-card">
                <div class="asset-preview">
                    <img :src="asset.thumbnail_path || asset.file_path" :alt="asset.name">
                </div>
                <div class="asset-info">
                    <h4 x-text="asset.name" class="font-semibold"></h4>
                    <p class="text-sm text-gray-500">
                        <span x-text="asset.dimensions"></span> • 
                        <span x-text="formatFileSize(asset.file_size)"></span>
                    </p>
                </div>
                <div class="asset-actions">
                    <button @click="preview(asset)" class="btn btn-sm btn-secondary">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button @click="download(asset)" class="btn btn-sm btn-primary">
                        <i class="bi bi-download"></i> Download
                    </button>
                </div>
            </div>
        </template>
    </div>
    
    <!-- Affiliate Link Generator -->
    <div class="link-generator mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="font-semibold mb-4">Your Affiliate Links</h3>
        
        <div class="space-y-4">
            <!-- Main Link -->
            <div>
                <label class="text-sm text-gray-600">Main Affiliate Link</label>
                <div class="flex gap-2">
                    <input type="text" 
                           :value="mainLink" 
                           readonly 
                           class="input flex-1">
                    <button @click="copyLink(mainLink)" class="btn btn-secondary">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
            
            <!-- Product-Specific Links -->
            <div>
                <label class="text-sm text-gray-600">Product-Specific Link</label>
                <div class="flex gap-2">
                    <select x-model="selectedProduct" class="input flex-1">
                        <option value="">Select a product...</option>
                        <template x-for="product in products" :key="product.id">
                            <option :value="product.slug" x-text="product.name"></option>
                        </template>
                    </select>
                    <input type="text" 
                           :value="productLink" 
                           readonly 
                           class="input flex-1">
                    <button @click="copyLink(productLink)" class="btn btn-secondary">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 4. Analytics Dashboard

### Enhanced Analytics

```php
/**
 * Comprehensive affiliate analytics
 */
function getAffiliateAnalytics($affiliateId, $dateRange = '30d') {
    $db = getDb();
    
    // Calculate date range
    $days = (int)str_replace('d', '', $dateRange);
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    
    $affiliate = getAffiliateById($affiliateId);
    $code = $affiliate['code'];
    
    // Daily stats
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
            COALESCE(t.name, tl.name) as product_name,
            COUNT(*) as order_count,
            SUM(po.final_amount) as revenue,
            SUM(po.affiliate_commission) as commission
        FROM pending_orders po
        LEFT JOIN order_items oi ON po.id = oi.order_id
        LEFT JOIN templates t ON oi.product_id = t.id AND oi.product_type = 'template'
        LEFT JOIN tools tl ON oi.product_id = tl.id AND oi.product_type = 'tool'
        WHERE po.affiliate_code = ?
        AND po.status = 'paid'
        AND po.paid_at >= ?
        GROUP BY COALESCE(t.name, tl.name)
        ORDER BY commission DESC
        LIMIT 10
    ");
    $stmt->execute([$code, $startDate]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Traffic sources
    $stmt = $db->prepare("
        SELECT 
            referrer_domain,
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
            'clicks' => (int)$clickTotals['total_clicks'],
            'unique_visitors' => (int)$clickTotals['unique_visitors'],
            'orders' => (int)$totals['total_orders'],
            'revenue' => (float)$totals['total_revenue'],
            'commission' => (float)$totals['total_commission'],
            'avg_order_value' => (float)$totals['avg_order_value'],
            'conversion_rate' => $conversionRate
        ],
        'daily_clicks' => $dailyClicks,
        'daily_conversions' => $dailyConversions,
        'top_products' => $topProducts,
        'traffic_sources' => $trafficSources
    ];
}
```

### Analytics Dashboard UI

```html
<!-- Affiliate Analytics Dashboard -->
<div class="analytics-dashboard" x-data="affiliateAnalytics()">
    <!-- Date Range Selector -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Performance Analytics</h2>
        <select x-model="dateRange" @change="loadData()" class="input-sm">
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
        </select>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Total Clicks</span>
                <i class="bi bi-cursor text-blue-500"></i>
            </div>
            <span class="stat-value" x-text="formatNumber(data.totals.clicks)"></span>
            <span class="text-sm text-gray-500" x-text="`${data.totals.unique_visitors} unique`"></span>
        </div>
        
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Conversions</span>
                <i class="bi bi-cart-check text-green-500"></i>
            </div>
            <span class="stat-value" x-text="data.totals.orders"></span>
            <span class="text-sm text-green-600" x-text="`${data.totals.conversion_rate}% rate`"></span>
        </div>
        
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Revenue Generated</span>
                <i class="bi bi-graph-up text-purple-500"></i>
            </div>
            <span class="stat-value" x-text="formatCurrency(data.totals.revenue)"></span>
        </div>
        
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Your Earnings</span>
                <i class="bi bi-cash-coin text-yellow-500"></i>
            </div>
            <span class="stat-value text-green-600" x-text="formatCurrency(data.totals.commission)"></span>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Performance Chart -->
        <div class="chart-card">
            <h3 class="font-semibold mb-4">Clicks & Conversions</h3>
            <canvas id="performanceChart"></canvas>
        </div>
        
        <!-- Earnings Chart -->
        <div class="chart-card">
            <h3 class="font-semibold mb-4">Earnings Over Time</h3>
            <canvas id="earningsChart"></canvas>
        </div>
    </div>
    
    <!-- Tables Row -->
    <div class="grid grid-cols-2 gap-6">
        <!-- Top Products -->
        <div class="table-card">
            <h3 class="font-semibold mb-4">Top Selling Products</h3>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">Product</th>
                        <th class="text-right">Sales</th>
                        <th class="text-right">Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="product in data.top_products" :key="product.product_name">
                        <tr>
                            <td x-text="product.product_name"></td>
                            <td class="text-right" x-text="product.order_count"></td>
                            <td class="text-right text-green-600" x-text="formatCurrency(product.commission)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Traffic Sources -->
        <div class="table-card">
            <h3 class="font-semibold mb-4">Traffic Sources</h3>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">Source</th>
                        <th class="text-right">Clicks</th>
                        <th class="text-right">Unique</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="source in data.traffic_sources" :key="source.referrer_domain">
                        <tr>
                            <td x-text="source.referrer_domain || 'Direct'"></td>
                            <td class="text-right" x-text="source.clicks"></td>
                            <td class="text-right" x-text="source.unique_visitors"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

---

## 5. Implementation Checklist

### Phase 1: Real-Time Tracking
- [ ] Implement real-time stats API
- [ ] Build live activity feed
- [ ] Add auto-refresh to dashboard
- [ ] Create notification system

### Phase 2: Fraud Detection
- [ ] Create fraud detection tables
- [ ] Implement AffiliateFraudDetector
- [ ] Add click analysis
- [ ] Add order analysis
- [ ] Build fraud review dashboard

### Phase 3: Marketing Assets
- [ ] Create asset tables
- [ ] Build upload system (admin)
- [ ] Create asset library UI
- [ ] Implement download tracking
- [ ] Add link generator

### Phase 4: Analytics
- [ ] Implement comprehensive analytics
- [ ] Build analytics dashboard
- [ ] Add charts (Chart.js)
- [ ] Create export functionality

---

## Related Documents

- [06_ADMIN_UPDATES.md](./06_ADMIN_UPDATES.md) - Admin panel
- [19_ADMIN_AUTOMATION.md](./19_ADMIN_AUTOMATION.md) - Automation
- [20_SECURITY_HARDENING.md](./20_SECURITY_HARDENING.md) - Security
