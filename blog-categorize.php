<?php
/**
 * Blog Post Categorization Script
 * Run this once on your cPanel to spread blog posts across proper categories
 * 
 * Instructions:
 * 1. Upload this file to your cPanel public_html folder
 * 2. Visit: https://yoursite.com/blog-categorize.php
 * 3. It will categorize all posts and show a summary
 * 4. Delete this file after running
 */

// Add your cPanel database connection here
// Replace these with your actual cPanel database credentials
$host = 'localhost';
$db = 'webdaddy_online'; // Usually something like: account_dbname
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("sqlite::memory:", $user, $pass);
    // For SQLite, use: $pdo = new PDO('sqlite:/path/to/webdaddy.db');
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Mapping of blog post slugs to category IDs
$categorization = [
    // MARKETING CATEGORY (30% of posts)
    'affiliate-marketing-blueprint-income' => 'Marketing',
    'ai-automation-marketing-2025' => 'Marketing',
    'brand-crisis-management-reputation' => 'Marketing',
    'brand-identity-development-guide' => 'Marketing',
    'content-marketing-strategy-sales' => 'Marketing',
    'conversion-rate-optimization-growth' => 'Marketing',
    'customer-retention-strategy-loyalty' => 'Marketing',
    'email-marketing-automation-guide-2025' => 'Marketing',
    'google-ads-roi-strategy' => 'Marketing',
    'influencer-marketing-partnerships' => 'Marketing',
    'saas-marketing-enterprise-strategy' => 'Marketing',
    'startup-growth-hacking-1million' => 'Marketing',
    'video-marketing-strategy-2025' => 'Marketing',
    
    // SEO CATEGORY (30% of posts)
    'analytics-mastery-data-driven' => 'SEO',
    'complete-seo-audit-checklist-2025' => 'SEO',
    'ecommerce-product-page-optimization' => 'SEO',
    'local-seo-dominate-search-results' => 'SEO',
    'social-commerce-sales-channel' => 'SEO',
    'wordpress-security-guide-2025' => 'SEO',
    
    // WEBSITE DESIGN CATEGORY (40% of posts)
    'high-converting-landing-page-2025' => 'Website Design',
    'mobile-first-design-2025' => 'Website Design',
    'website-speed-optimization-guide' => 'Website Design',
];

echo "<h1>Blog Post Categorization Script</h1>";
echo "<p>This script will assign blog posts to proper categories.</p>";

// Get category IDs
try {
    $stmt = $pdo->query("SELECT id, name FROM blog_categories WHERE status = 'active'");
    $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "<h2>Categories Found:</h2>";
    echo "<ul>";
    foreach ($categories as $id => $name) {
        echo "<li>$id - $name</li>";
    }
    echo "</ul>";
    
    // Update posts with their categories
    $updated = 0;
    echo "<h2>Posts Categorized:</h2>";
    echo "<ul>";
    
    foreach ($categorization as $slug => $categoryName) {
        $categoryId = array_key_first(array_filter($categories, function($n) use ($categoryName) {
            return stripos($n, $categoryName) !== false;
        })) ?: null;
        
        if ($categoryId) {
            $stmt = $pdo->prepare("UPDATE blog_posts SET category_id = ? WHERE slug = ? AND status = 'published'");
            $result = $stmt->execute([$categoryId, $slug]);
            
            if ($stmt->rowCount() > 0) {
                echo "<li>✓ $slug → $categoryName</li>";
                $updated++;
            }
        }
    }
    echo "</ul>";
    echo "<p><strong>Total posts updated: $updated</strong></p>";
    echo "<p style='color:green;'>✅ Blog categorization complete! You can now delete this file.</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
