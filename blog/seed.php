<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Clear and insert 5 professional blog posts
$db->exec("DELETE FROM blog_posts");

$now = date('Y-m-d H:i:s');
$sql = "INSERT INTO blog_posts (title, slug, excerpt, author_name, status, created_at, updated_at, publish_date) VALUES 
('Professional Website That Converts Visitors into Customers', 'professional-website-converts', 'Learn proven conversion optimization techniques for Nigerian e-commerce businesses', 'WebDaddy Team', 'published', '$now', '$now', '$now'),
('The Complete SEO Checklist for Nigerian Businesses', 'seo-checklist-nigerian', 'Rank higher on Google with proven on-page and technical SEO strategies', 'WebDaddy Team', 'published', '$now', '$now', '$now'),
('E-Commerce Success: Building Your First Online Store', 'ecommerce-first-store', 'Launch profitable online store in Nigeria in 48 hours', 'WebDaddy Team', 'published', '$now', '$now', '$now'),
('Content Marketing Strategy: Attract Customers Through Valuable Content', 'content-marketing-strategy', 'Build authority and drive consistent organic traffic with proven content tactics', 'WebDaddy Team', 'published', '$now', '$now', '$now'),
('10 Web Design Mistakes That Kill Conversions (And How to Fix Them)', 'web-design-mistakes-kill', 'Avoid costly design errors that are costing you customers', 'WebDaddy Team', 'published', '$now', '$now', '$now')";

if ($db->exec($sql)) {
    header('Location: /blog');
    echo "✅ Blog posts created! Redirecting...";
} else {
    echo "❌ Error creating posts";
}
?>
