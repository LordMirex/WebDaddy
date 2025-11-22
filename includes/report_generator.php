<?php
/**
 * Weekly Report Generator for WebDaddy Empire
 * Professional analytics reports with accurate data and beautiful styling
 */

class ReportGenerator {
    
    public static function generateWeeklyReport($db) {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => 'Weekly Report - ' . date('Y-m-d', strtotime('-7 days')) . ' to ' . date('Y-m-d'),
        ];
        
        try {
            // Revenue from actual sales (this week)
            $revenue = $db->query("SELECT COALESCE(SUM(COALESCE(final_amount, amount_paid)), 0) as total FROM sales WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['revenue_week'] = floatval($revenue['total'] ?? 0);
            
            // Revenue all-time
            $revenueTotal = $db->query("SELECT COALESCE(SUM(COALESCE(final_amount, amount_paid)), 0) as total FROM sales")->fetch(PDO::FETCH_ASSOC);
            $report['revenue_total'] = floatval($revenueTotal['total'] ?? 0);
            
            // Actual completed sales (this week)
            $salesWeek = $db->query("SELECT COUNT(*) as count FROM sales WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['sales_week'] = intval($salesWeek['count'] ?? 0);
            
            // Total sales all-time
            $salesTotal = $db->query("SELECT COUNT(*) as count FROM sales")->fetch(PDO::FETCH_ASSOC);
            $report['sales_total'] = intval($salesTotal['count'] ?? 0);
            
            // Template page views (this week)
            $templateViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE template_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['template_views_week'] = intval($templateViews['views'] ?? 0);
            
            // Tool page views (this week)
            $toolViews = $db->query("SELECT COUNT(*) as views FROM page_interactions WHERE tool_id IS NOT NULL AND action_type = 'view' AND created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['tool_views_week'] = intval($toolViews['views'] ?? 0);
            
            // Total visitors/sessions
            $visitors = $db->query("SELECT COUNT(DISTINCT session_id) as visitors FROM page_interactions WHERE created_at >= date('now', '-7 days')")->fetch(PDO::FETCH_ASSOC);
            $report['visitors_week'] = intval($visitors['visitors'] ?? 0);
            
            // Affiliate count
            $affiliates = $db->query("SELECT COUNT(*) as count FROM affiliates")->fetch(PDO::FETCH_ASSOC);
            $report['total_affiliates'] = intval($affiliates['count'] ?? 0);
            
            // Active affiliates (with sales)
            $activeAffiliates = $db->query("SELECT COUNT(DISTINCT affiliate_id) as count FROM sales WHERE affiliate_id IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
            $report['active_affiliates'] = intval($activeAffiliates['count'] ?? 0);
            
            // Total templates in catalog
            $templates = $db->query("SELECT COUNT(*) as count FROM templates")->fetch(PDO::FETCH_ASSOC);
            $report['total_templates'] = intval($templates['count'] ?? 0);
            
            // Total tools in catalog
            $tools = $db->query("SELECT COUNT(*) as count FROM tools")->fetch(PDO::FETCH_ASSOC);
            $report['total_tools'] = intval($tools['count'] ?? 0);
            
            // Top selling products (actual sales, not views)
            $topSelling = $db->query("
                SELECT t.name, COUNT(*) as sales_count
                FROM order_items oi 
                JOIN tools t ON oi.product_id = t.id 
                WHERE oi.product_type = 'tool' 
                AND oi.created_at >= date('now', '-7 days') 
                GROUP BY t.id 
                ORDER BY sales_count DESC 
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $report['top_sellers'] = $topSelling ?? [];
            
            // Database size
            $dbPath = __DIR__ . '/../database/webdaddy.db';
            $report['db_size'] = filesize($dbPath) ? round(filesize($dbPath) / 1024 / 1024, 2) : 0;
            
            return $report;
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public static function generateReportHTML($report) {
        $revenue = number_format($report['revenue_week'] ?? 0, 2);
        $revenueTotal = number_format($report['revenue_total'] ?? 0, 2);
        $sales = $report['sales_week'] ?? 0;
        $salesTotal = $report['sales_total'] ?? 0;
        $templateViews = $report['template_views_week'] ?? 0;
        $toolViews = $report['tool_views_week'] ?? 0;
        $visitors = $report['visitors_week'] ?? 0;
        $affiliates = $report['total_affiliates'] ?? 0;
        $activeAffiliates = $report['active_affiliates'] ?? 0;
        $templates = $report['total_templates'] ?? 0;
        $tools = $report['total_tools'] ?? 0;
        $topSellers = $report['top_sellers'] ?? [];
        $dbSize = $report['db_size'] ?? 0;
        
        $topSellersHTML = '';
        if (!empty($topSellers)) {
            foreach ($topSellers as $product) {
                $name = htmlspecialchars($product['name'] ?? 'Unknown');
                $count = intval($product['sales_count'] ?? 0);
                $topSellersHTML .= "<tr style='border-bottom: 1px solid #e5e7eb;'><td style='padding: 12px 15px; font-size: 14px;'>{$name}</td><td style='padding: 12px 15px; text-align: right; font-weight: 600; color: #2563eb; font-size: 14px;'>{$count}</td></tr>";
            }
        } else {
            $topSellersHTML = "<tr><td colspan='2' style='padding: 20px; text-align: center; color: #999; font-size: 13px;'>No sales this week</td></tr>";
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Report</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; color: #1f2937;">
    <table style="width: 100%; background-color: #f8fafc; padding: 20px 0;" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table style="width: 100%; max-width: 700px; background-color: #ffffff; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 40px 30px; text-align: center; border-bottom: 5px solid #1e40af;">
                            <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: white;">📊 WebDaddy Empire</h1>
                            <p style="margin: 8px 0 0 0; font-size: 16px; color: rgba(255,255,255,0.9);">Weekly Analytics Report</p>
                            <p style="margin: 12px 0 0 0; font-size: 13px; color: rgba(255,255,255,0.8);">{$report['period']}</p>
                            <p style="margin: 8px 0 0 0; font-size: 11px; color: rgba(255,255,255,0.7);">Generated: {$report['generated_at']}</p>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="padding: 30px;">

                            <!-- REVENUE SECTION -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="margin-bottom: 15px;">
                                        <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 700; color: #1e3a8a;">💰 Revenue</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width: 50%; padding-right: 8px;">
                                                    <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 18px 15px; border-radius: 8px; border-left: 4px solid #2563eb; text-align: center;">
                                                        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">This Week</p>
                                                        <p style="margin: 0; font-size: 26px; font-weight: 700; color: #2563eb;">₦{$revenue}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding-left: 8px;">
                                                    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 18px 15px; border-radius: 8px; border-left: 4px solid #22c55e; text-align: center;">
                                                        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">All-Time</p>
                                                        <p style="margin: 0; font-size: 26px; font-weight: 700; color: #22c55e;">₦{$revenueTotal}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- SALES & VISITORS -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="margin-bottom: 15px;">
                                        <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 700; color: #1e3a8a;">📦 Sales & Visitors</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width: 33%; padding-right: 6px;">
                                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 15px; border-radius: 6px; border-left: 4px solid #f59e0b; text-align: center;">
                                                        <p style="margin: 0 0 6px 0; font-size: 10px; font-weight: 600; color: #92400e; text-transform: uppercase;">Sales (Week)</p>
                                                        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #f59e0b;">{$sales}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 33%; padding: 0 3px;">
                                                    <div style="background: linear-gradient(135deg, #ddd6fe 0%, #c7d2fe 100%); padding: 15px; border-radius: 6px; border-left: 4px solid #6366f1; text-align: center;">
                                                        <p style="margin: 0 0 6px 0; font-size: 10px; font-weight: 600; color: #4338ca; text-transform: uppercase;">Visitors</p>
                                                        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #6366f1;">{$visitors}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 34%; padding-left: 6px;">
                                                    <div style="background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%); padding: 15px; border-radius: 6px; border-left: 4px solid #ef4444; text-align: center;">
                                                        <p style="margin: 0 0 6px 0; font-size: 10px; font-weight: 600; color: #991b1b; text-transform: uppercase;">Total Sales</p>
                                                        <p style="margin: 0; font-size: 22px; font-weight: 700; color: #ef4444;">{$salesTotal}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- PAGE VIEWS -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="margin-bottom: 15px;">
                                        <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 700; color: #1e3a8a;">👀 Page Views (This Week)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width: 50%; padding-right: 8px;">
                                                    <div style="background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%); padding: 15px; border-radius: 6px; border-left: 4px solid #0284c7;">
                                                        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #0369a1; text-transform: uppercase;">Template Views</p>
                                                        <p style="margin: 0; font-size: 24px; font-weight: 700; color: #0284c7;">{$templateViews}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding-left: 8px;">
                                                    <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
                                                        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #047857; text-transform: uppercase;">Tool Views</p>
                                                        <p style="margin: 0; font-size: 24px; font-weight: 700; color: #10b981;">{$toolViews}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- AFFILIATES & CATALOG -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="margin-bottom: 15px;">
                                        <p style="margin: 0 0 15px 0; font-size: 16px; font-weight: 700; color: #1e3a8a;">🤝 Affiliates & Catalog</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width: 25%; padding-right: 6px;">
                                                    <div style="background: #f3f4f6; padding: 12px; border-radius: 6px; border-left: 4px solid #8b5cf6; text-align: center;">
                                                        <p style="margin: 0 0 4px 0; font-size: 9px; font-weight: 600; color: #6b21a8; text-transform: uppercase;">Affiliates</p>
                                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #8b5cf6;">{$affiliates}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 25%; padding: 0 3px;">
                                                    <div style="background: #f3f4f6; padding: 12px; border-radius: 6px; border-left: 4px solid #06b6d4; text-align: center;">
                                                        <p style="margin: 0 0 4px 0; font-size: 9px; font-weight: 600; color: #164e63; text-transform: uppercase;">Active</p>
                                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #06b6d4;">{$activeAffiliates}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 25%; padding: 0 3px;">
                                                    <div style="background: #f3f4f6; padding: 12px; border-radius: 6px; border-left: 4px solid #ec4899; text-align: center;">
                                                        <p style="margin: 0 0 4px 0; font-size: 9px; font-weight: 600; color: #831843; text-transform: uppercase;">Templates</p>
                                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #ec4899;">{$templates}</p>
                                                    </div>
                                                </td>
                                                <td style="width: 25%; padding-left: 6px;">
                                                    <div style="background: #f3f4f6; padding: 12px; border-radius: 6px; border-left: 4px solid #14b8a6; text-align: center;">
                                                        <p style="margin: 0 0 4px 0; font-size: 9px; font-weight: 600; color: #134e4a; text-transform: uppercase;">Tools</p>
                                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #14b8a6;">{$tools}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- TOP SELLERS -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #1e3a8a;">🏆 Top Selling Products (This Week)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="width: 100%; background-color: #f9fafb; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
                                            <thead>
                                                <tr style="background-color: #2563eb;">
                                                    <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: white; font-size: 13px;">Product Name</th>
                                                    <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: white; font-size: 13px;">Sales</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {$topSellersHTML}
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- DATABASE INFO -->
                            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 18px 15px; border-radius: 8px; border-left: 4px solid #22c55e;">
                                        <p style="margin: 0 0 6px 0; font-size: 13px; font-weight: 700; color: #166534;">📁 Database Backup Attached</p>
                                        <p style="margin: 0; font-size: 12px; color: #166534;">Size: <strong>{$dbSize} MB</strong> • Your database backup is attached for safekeeping</p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 30px; border-top: 1px solid #e5e7eb; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #999;">WebDaddy Empire Automated Reports</p>
                            <p style="margin: 4px 0 0 0; font-size: 11px; color: #ccc;">© 2025 WebDaddy Empire</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
