<?php
/**
 * Weekly Report Generator for WebDaddy Empire
 * Generates analytics reports and sends via email with database backup attachment
 */

class ReportGenerator {
    
    public static function generateWeeklyReport($db) {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => 'Weekly Report - ' . date('Y-m-d', strtotime('-7 days')) . ' to ' . date('Y-m-d'),
        ];
        
        try {
            // Total Profit from sales table
            $profit = $db->query("SELECT COALESCE(SUM(COALESCE(final_amount, amount_paid)), 0) as total FROM sales WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['profit_this_week'] = floatval($profit['total'] ?? 0);
            
            $profitTotal = $db->query("SELECT COALESCE(SUM(COALESCE(final_amount, amount_paid)), 0) as total FROM sales")->fetch(PDO::FETCH_ASSOC);
            $report['profit_all_time'] = floatval($profitTotal['total'] ?? 0);
            
            // Template Views
            $templateViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE template_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_week'] = intval($templateViews['views'] ?? 0);
            
            $templateViewsTotal = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE template_id IS NOT NULL AND action_type = 'view'")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_total'] = intval($templateViewsTotal['views'] ?? 0);
            
            // Tool Views
            $toolViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE tool_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_week'] = intval($toolViews['views'] ?? 0);
            
            $toolViewsTotal = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE tool_id IS NOT NULL AND action_type = 'view'")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_total'] = intval($toolViewsTotal['views'] ?? 0);
            
            // Total Orders from sales table
            $orders = $db->query("SELECT COUNT(*) as count FROM sales WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['orders_this_week'] = intval($orders['count'] ?? 0);
            
            $ordersTotal = $db->query("SELECT COUNT(*) as count FROM sales")->fetch(PDO::FETCH_ASSOC);
            $report['orders_total'] = intval($ordersTotal['count'] ?? 0);
            
            // Top Products
            $topProducts = $db->query("
                SELECT t.name, COUNT(*) as sales 
                FROM order_items oi 
                JOIN tools t ON oi.product_id = t.id 
                WHERE oi.product_type = 'tool' 
                AND oi.created_at >= date('now', '-7 days') 
                GROUP BY t.id 
                ORDER BY sales DESC 
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $report['top_products'] = $topProducts ?? [];
            
            // Database size
            $dbPath = __DIR__ . '/../database/webdaddy.db';
            $report['db_size'] = filesize($dbPath) ? round(filesize($dbPath) / 1024 / 1024, 2) : 0;
            
            return $report;
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public static function generateReportHTML($report) {
        $profit = number_format($report['profit_this_week'] ?? 0, 2);
        $profitTotal = number_format($report['profit_all_time'] ?? 0, 2);
        $templateViews = $report['template_views_week'] ?? 0;
        $toolViews = $report['tool_views_week'] ?? 0;
        $orders = $report['orders_this_week'] ?? 0;
        $ordersTotal = $report['orders_total'] ?? 0;
        $dbSize = $report['db_size'] ?? 0;
        $topProducts = $report['top_products'] ?? [];
        
        $topProductsHTML = '';
        if (!empty($topProducts)) {
            foreach ($topProducts as $product) {
                $name = htmlspecialchars($product['name'] ?? 'Unknown');
                $sales = intval($product['sales'] ?? 0);
                $topProductsHTML .= "<tr><td style='padding:12px; border-bottom:1px solid #e5e7eb;'>{$name}</td><td style='padding:12px; border-bottom:1px solid #e5e7eb; text-align:center; font-weight:bold; color:#2563eb;'>{$sales}</td></tr>";
            }
        } else {
            $topProductsHTML = "<tr><td colspan='2' style='padding:20px; text-align:center; color:#999;'>No sales this week</td></tr>";
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1f2937; line-height: 1.6; }
        .container { max-width: 650px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 40px 30px; text-align: center; border-bottom: 5px solid #1e40af; }
        .header h1 { font-size: 32px; margin-bottom: 8px; font-weight: 700; }
        .header p { font-size: 14px; opacity: 0.95; margin: 4px 0; }
        .content { padding: 30px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1e3a8a; margin-bottom: 18px; display: flex; align-items: center; }
        .section-title i { margin-right: 8px; font-size: 20px; }
        .metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .metric-card { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 18px; border-radius: 8px; border-left: 4px solid #2563eb; }
        .metric-label { font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .metric-value { font-size: 28px; font-weight: 700; color: #2563eb; }
        .metric-sub { font-size: 11px; color: #999; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        thead { background: #2563eb; }
        th { color: white; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        tr:last-child td { border-bottom: none; }
        .highlight-section { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 20px; border-radius: 8px; border-left: 4px solid #22c55e; margin: 20px 0; }
        .highlight-section p { font-size: 14px; color: #166534; margin: 8px 0; }
        .highlight-section strong { font-weight: 700; }
        .footer { background: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb; font-size: 12px; color: #999; }
        .info-box { background: #f3f4f6; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #0ea5e9; }
        .info-box-title { font-weight: 700; color: #0369a1; margin-bottom: 8px; font-size: 13px; }
        .info-box-text { font-size: 12px; color: #374151; }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>📊 WebDaddy Empire</h1>
            <p>Weekly Analytics Report</p>
            <p style="margin-top: 12px; font-size: 13px; opacity: 0.9;">{$report['period']}</p>
            <p style="margin-top: 8px; font-size: 11px; opacity: 0.85;">Generated: {$report['generated_at']}</p>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <!-- FINANCIAL SECTION -->
            <div class="section">
                <div class="section-title">💰 Financial Summary</div>
                <div class="metric-grid">
                    <div class="metric-card">
                        <div class="metric-label">This Week</div>
                        <div class="metric-value">₦{$profit}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">All-Time Total</div>
                        <div class="metric-value">₦{$profitTotal}</div>
                    </div>
                </div>
            </div>

            <!-- TRAFFIC SECTION -->
            <div class="section">
                <div class="section-title">📈 Traffic & Views</div>
                <div class="metric-grid">
                    <div class="metric-card">
                        <div class="metric-label">Template Views</div>
                        <div class="metric-value">{$templateViews}</div>
                        <div class="metric-sub">this week</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Tool Views</div>
                        <div class="metric-value">{$toolViews}</div>
                        <div class="metric-sub">this week</div>
                    </div>
                </div>
            </div>

            <!-- ORDERS SECTION -->
            <div class="section">
                <div class="section-title">📦 Orders</div>
                <div class="metric-grid">
                    <div class="metric-card">
                        <div class="metric-label">This Week</div>
                        <div class="metric-value">{$orders}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Total Orders</div>
                        <div class="metric-value">{$ordersTotal}</div>
                    </div>
                </div>
            </div>

            <!-- TOP PRODUCTS -->
            <div class="section">
                <div class="section-title">🏆 Top Products (This Week)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align: center;">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$topProductsHTML}
                    </tbody>
                </table>
            </div>

            <!-- DATABASE INFO -->
            <div class="highlight-section">
                <p><strong>📁 Database Backup Attached</strong></p>
                <p>Database Size: <strong>{$dbSize} MB</strong></p>
                <p style="font-size: 12px; margin-top: 8px;">Your database backup has been attached to this email for safekeeping.</p>
            </div>

            <!-- INFO BOX -->
            <div class="info-box">
                <div class="info-box-title">ℹ️ Report Information</div>
                <div class="info-box-text">
                    This report is automatically generated every Monday at 3 AM. It contains analytics data from the past 7 days and a backup of your database.
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p>WebDaddy Empire Automated Reports</p>
            <p style="margin-top: 8px; color: #ccc;">© 2025 WebDaddy Empire</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
