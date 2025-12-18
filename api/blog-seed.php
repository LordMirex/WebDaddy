<?php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';

$db = getDb();

// Check if already seeded
$check = $db->query("SELECT COUNT(*) as cnt FROM blog_posts")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
if ($check > 0) {
    echo json_encode(['status' => 'already_seeded', 'posts' => $check]);
    exit;
}

// Create categories
$categories = [
    ['Web Design & Development', 'web-design-development'],
    ['Business Growth', 'business-growth'],
    ['Digital Marketing', 'digital-marketing'],
    ['E-Commerce', 'e-commerce'],
    ['SEO & Rankings', 'seo-rankings']
];

foreach ($categories as [$name, $slug]) {
    $stmt = $db->prepare("INSERT INTO blog_categories (name, slug, status) VALUES (?, ?, 'active')");
    $stmt->execute([$name, $slug]);
}

// Get category IDs
$cats = [];
$result = $db->query("SELECT id, slug FROM blog_categories");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $cats[$row['slug']] = $row['id'];
}

// Insert professional blog posts
$posts = [
    ['How to Build a Professional Website That Converts Visitors into Customers', 'professional-website-converts', $cats['web-design-development'], 'Learn conversion optimization techniques', 'https://images.unsplash.com/photo-1561070791-2526d30994b5'],
    ['The Complete SEO Checklist for Nigerian Businesses', 'seo-checklist-nigerian', $cats['seo-rankings'], 'Comprehensive SEO guide to rank higher on Google', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71'],
    ['E-Commerce Success: Building Your First Online Store', 'ecommerce-first-store', $cats['e-commerce'], 'Launch your e-commerce business in 48 hours', 'https://images.unsplash.com/photo-1556740738-b6a63e27c4df'],
    ['Content Marketing Strategy: Attract Customers Through Valuable Content', 'content-marketing-strategy', $cats['digital-marketing'], 'Build authority and drive organic traffic', 'https://images.unsplash.com/photo-1552664730-d307ca884978'],
    ['10 Web Design Mistakes That Kill Conversions', 'web-design-mistakes', $cats['web-design-development'], 'Avoid costly design pitfalls', 'https://images.unsplash.com/photo-1561070791-2526d30994b5']
];

$created = 0;

foreach ($posts as [$title, $slug, $cat_id, $excerpt, $img]) {
    $stmt = $db->prepare("
        INSERT INTO blog_posts 
        (title, slug, excerpt, meta_description, featured_image, featured_image_alt, author_name, status, category_id, created_at, updated_at, publish_date)
        VALUES (?, ?, ?, ?, ?, 'Blog image', 'WebDaddy Team', 'published', ?, datetime('now'), datetime('now'), datetime('now'))
    ");
    
    if ($stmt->execute([$title, $slug, $excerpt, $excerpt, $img . '?w=800', $cat_id])) {
        $created++;
    }
}

echo json_encode(['status' => 'success', 'posts_created' => $created, 'categories' => count($categories)]);
?>
