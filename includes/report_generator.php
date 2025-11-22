<?php
/**
 * Weekly Report Generator for WebDaddy Empire
 * Generates analytics reports and sends via email
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
            
            // Template Views - WHERE template_id IS NOT NULL
            $templateViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE template_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_week'] = intval($templateViews['views'] ?? 0);
            
            $templateViewsTotal = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE template_id IS NOT NULL AND action_type = 'view'")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_total'] = intval($templateViewsTotal['views'] ?? 0);
            
            // Tool Views - WHERE tool_id IS NOT NULL
            $toolViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE tool_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_week'] = intval($toolViews['views'] ?? 0);
            
            $toolViewsTotal = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE tool_id IS NOT NULL AND action_type = 'view'")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_total'] = intval($toolViewsTotal['views'] ?? 0);
            
            // Total Orders from sales table
            $orders = $db->query("SELECT COUNT(*) as count FROM sales WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['orders_this_week'] = intval($orders['count'] ?? 0);
            
            $ordersTotal = $db->query("SELECT COUNT(*) as count FROM sales")->fetch(PDO::FETCH_ASSOC);
            $report['orders_total'] = intval($ordersTotal['count'] ?? 0);
            
            // Top Products - Tools sold this week
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
    
    public static function createReportZip($report, $dbPath) {
        $tmpDir = sys_get_temp_dir() . '/webdaddy_report_' . time();
        @mkdir($tmpDir, 0755, true);
        
        // Create report HTML
        $html = self::generateReportHTML($report);
        file_put_contents($tmpDir . '/weekly_report.html', $html);
        
        // Copy database backup
        $backupName = 'webdaddy_backup_' . date('Y-m-d_H-i-s') . '.db';
        @copy($dbPath, $tmpDir . '/' . $backupName);
        
        // Create ZIP file
        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE)) {
            $zip->addFile($tmpDir . '/weekly_report.html', 'weekly_report.html');
            if (file_exists($tmpDir . '/' . $backupName)) {
                $zip->addFile($tmpDir . '/' . $backupName, $backupName);
            }
            $zip->close();
        }
        
        // Cleanup
        foreach (glob($tmpDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);
        
        return file_exists($zipPath) ? $zipPath : false;
    }
    
    private static function generateReportHTML($report) {
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
                $topProductsHTML .= "<tr><td style='padding:8px;'>{$name}</td><td style='padding:8px;'>{$sales} sales</td></tr>";
            }
        } else {
            $topProductsHTML = "<tr><td colspan='2' style='padding:8px; text-align:center; color:#999;'>No sales this week</td></tr>";
        }
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 20px; background: #f9fafb; }
        .container { max-width: 700px; margin: 0 auto; }
        .header { background: #2563eb; color: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 5px 0 0 0; font-size: 14px; opacity: 0.9; }
        .section { background: white; padding: 20px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .section h2 { margin: 0 0 15px 0; font-size: 18px; color: #1e3a8a; }
        .metric-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .metric { background: #f3f4f6; padding: 15px; border-left: 4px solid #2563eb; border-radius: 4px; }
        .metric-value { font-size: 28px; font-weight: bold; color: #2563eb; }
        .metric-label { font-size: 12px; color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2563eb; color: white; padding: 10px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 10px; }
        .footer { text-align: center; font-size: 12px; color: #999; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 WebDaddy Empire</h1>
            <p>Weekly Analytics Report</p>
            <p>{$report['period']}</p>
            <p style="margin-top: 10px; font-size: 12px;">Generated: {$report['generated_at']}</p>
        </div>
        
        <div class="section">
            <h2>💰 Financial Summary</h2>
            <div class="metric-row">
                <div class="metric">
                    <div class="metric-label">This Week</div>
                    <div class="metric-value">₦{$profit}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">All-Time</div>
                    <div class="metric-value">₦{$profitTotal}</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>📈 Traffic & Views</h2>
            <div class="metric-row">
                <div class="metric">
                    <div class="metric-label">Template Views (Week)</div>
                    <div class="metric-value">{$templateViews}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Tool Views (Week)</div>
                    <div class="metric-value">{$toolViews}</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>📦 Orders</h2>
            <div class="metric-row">
                <div class="metric">
                    <div class="metric-label">This Week</div>
                    <div class="metric-value">{$orders}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-value">{$ordersTotal}</div>
                </div>
            </div>
        </div>
        
        <div class="section">
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
        </div>
        
        <div class="section" style="background: #f0f9ff; border-left-color: #0ea5e9;">
            <h2 style="color: #0369a1; margin: 0;">📁 Database Information</h2>
            <p style="margin: 10px 0 0 0; font-size: 14px;">Database Size: <strong>{$dbSize} MB</strong></p>
            <p style="margin: 5px 0; font-size: 14px;">Backup included in attachment</p>
        </div>
        
        <div class="footer">
            <p>This is an automated weekly report from WebDaddy Empire</p>
            <p>Database backup file included</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
