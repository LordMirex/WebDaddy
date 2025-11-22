<?php
/**
 * Weekly Report Generator for WebDaddy Empire
 * Generates analytics reports and sends via email
 */

class ReportGenerator {
    
    public static function generateWeeklyReport($db) {
        // Gather analytics data
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => 'Weekly Report - ' . date('Y-m-d', strtotime('-7 days')) . ' to ' . date('Y-m-d'),
        ];
        
        try {
            // Total Profit
            $profit = $db->query("SELECT COALESCE(SUM(price), 0) as total FROM orders WHERE status = 'completed' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['profit_this_week'] = $profit['total'] ?? 0;
            $report['profit_all_time'] = $db->query("SELECT COALESCE(SUM(price), 0) as total FROM orders WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Template Views
            $templateViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE action_type = 'view' AND page_type = 'template' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_week'] = $templateViews['views'] ?? 0;
            $report['template_views_total'] = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE action_type = 'view' AND page_type = 'template'")->fetch(PDO::FETCH_ASSOC)['views'] ?? 0;
            
            // Tool Views
            $toolViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE action_type = 'view' AND page_type = 'tool' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_week'] = $toolViews['views'] ?? 0;
            $report['tool_views_total'] = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE action_type = 'view' AND page_type = 'tool'")->fetch(PDO::FETCH_ASSOC)['views'] ?? 0;
            
            // Total Orders
            $orders = $db->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['orders_this_week'] = $orders['count'] ?? 0;
            $report['orders_total'] = $db->query("SELECT COUNT(*) as count FROM orders")->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Top Products
            $topProducts = $db->query("SELECT name, COUNT(*) as sales FROM order_items oi JOIN tools t ON oi.tool_id = t.id WHERE oi.created_at >= date('now', '-7 days') GROUP BY t.id ORDER BY sales DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $report['top_products'] = $topProducts;
            
            return $report;
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public static function createReportZip($report, $dbPath) {
        $tmpDir = sys_get_temp_dir() . '/webdaddy_report_' . time();
        mkdir($tmpDir);
        
        // Create report HTML
        $html = self::generateReportHTML($report);
        file_put_contents($tmpDir . '/weekly_report.html', $html);
        
        // Copy database backup
        copy($dbPath, $tmpDir . '/webdaddy_backup_' . date('Y-m-d') . '.db');
        
        // Create ZIP file
        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($tmpDir . '/weekly_report.html', 'weekly_report.html');
        $zip->addFile($tmpDir . '/webdaddy_backup_' . date('Y-m-d') . '.db', 'webdaddy_backup_' . date('Y-m-d') . '.db');
        $zip->close();
        
        // Cleanup
        array_map('unlink', glob($tmpDir . '/*'));
        rmdir($tmpDir);
        
        return $zipPath;
    }
    
    private static function generateReportHTML($report) {
        $profit = $report['profit_this_week'] ?? 0;
        $profitTotal = $report['profit_all_time'] ?? 0;
        $templateViews = $report['template_views_week'] ?? 0;
        $toolViews = $report['tool_views_week'] ?? 0;
        $orders = $report['orders_this_week'] ?? 0;
        $topProducts = $report['top_products'] ?? [];
        
        $topProductsHTML = '';
        foreach ($topProducts as $product) {
            $topProductsHTML .= "<tr><td style='padding:8px;'>{$product['name']}</td><td style='padding:8px;'>{$product['sales']} sales</td></tr>";
        }
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .metric { background: #f3f4f6; padding: 15px; margin: 10px 0; border-left: 4px solid #2563eb; border-radius: 4px; }
        .metric-value { font-size: 24px; font-weight: bold; color: #2563eb; }
        .metric-label { font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #2563eb; color: white; padding: 10px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 WebDaddy Empire - Weekly Report</h1>
        <p>{$report['period']}</p>
        <p>Generated: {$report['generated_at']}</p>
    </div>
    
    <h2>💰 Financial Summary</h2>
    <div class="metric">
        <div class="metric-label">This Week's Profit</div>
        <div class="metric-value">₦" . number_format($profit, 2) . "</div>
    </div>
    <div class="metric">
        <div class="metric-label">All-Time Profit</div>
        <div class="metric-value">₦" . number_format($profitTotal, 2) . "</div>
    </div>
    
    <h2>📈 Traffic & Views</h2>
    <div class="metric">
        <div class="metric-label">Template Views (This Week)</div>
        <div class="metric-value">{$templateViews}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Tool Views (This Week)</div>
        <div class="metric-value">{$toolViews}</div>
    </div>
    
    <h2>📦 Orders</h2>
    <div class="metric">
        <div class="metric-label">Orders This Week</div>
        <div class="metric-value">{$orders}</div>
    </div>
    
    <h2>🏆 Top Products (This Week)</h2>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Sales</th>
            </tr>
        </thead>
        <tbody>
            {$topProductsHTML}
        </tbody>
    </table>
    
    <hr>
    <p style="color: #999; font-size: 12px; margin-top: 30px;">
        This is an automated weekly report. Database backup included in this email.
    </p>
</body>
</html>
HTML;
    }
}
