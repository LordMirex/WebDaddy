<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$db = getDatabase();

echo "🧪 GENERATING TEST DATA...\n\n";

// 1. INSERT 40 TEMPLATES
echo "📝 Creating 40 templates...\n";
$categories = ['Business', 'E-Commerce', 'Portfolio', 'Blog', 'Agency', 'Saas', 'Restaurant', 'Education'];
$features = ['Responsive Design', 'SEO Optimized', 'Fast Loading', 'Mobile Friendly', 'SSL Secure', 'Admin Panel', 'Analytics', 'Email Integration'];

for ($i = 1; $i <= 40; $i++) {
    $category = $categories[($i - 1) % count($categories)];
    $price = 5000 + ($i * 500);
    $featureList = implode(',', array_slice($features, 0, rand(4, 7)));
    $slug = strtolower(str_replace(' ', '-', "template-$i-$category"));
    
    $sql = "INSERT OR IGNORE INTO templates (name, slug, description, category, price, features, thumbnail_url, banner_url, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $db->execute($sql, [
        "Premium $category Template #$i",
        $slug,
        "High-quality $category template for professional websites. Includes $featureList.",
        $category,
        $price,
        $featureList,
        "/uploads/templates/images/template_$i.jpg",
        "/uploads/templates/images/banner_$i.jpg",
        'active',
        date('Y-m-d H:i:s')
    ]);
}
echo "✅ Created 40 templates\n\n";

// 2. INSERT 40 TOOLS
echo "🛠️ Creating 40 tools...\n";
$toolTypes = ['Website Builder', 'SEO Tool', 'Analytics', 'Email Marketing', 'Social Media', 'Design Tool', 'Content Creator', 'Optimizer'];

for ($i = 1; $i <= 40; $i++) {
    $type = $toolTypes[($i - 1) % count($toolTypes)];
    $price = 2000 + ($i * 300);
    $slug = strtolower(str_replace(' ', '-', "tool-$i-$type"));
    
    $sql = "INSERT OR IGNORE INTO tools (name, slug, description, category, price, features, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $db->execute($sql, [
        "Professional $type Tool #$i",
        $slug,
        "Powerful $type tool for boosting your business. Includes premium features and 24/7 support.",
        $type,
        $price,
        "Advanced Features,Priority Support,Regular Updates,API Access",
        'active',
        date('Y-m-d H:i:s')
    ]);
}
echo "✅ Created 40 tools\n\n";

// 3. CREATE 5 AFFILIATE ACCOUNTS
echo "👥 Creating 5 affiliate accounts...\n";
$affiliates = [
    ['name' => 'John Marketer', 'email' => 'john@webdaddy.test', 'phone' => '08012345001', 'bank' => 'First Bank'],
    ['name' => 'Sarah Seller', 'email' => 'sarah@webdaddy.test', 'phone' => '08012345002', 'bank' => 'GTBank'],
    ['name' => 'Mike Distributor', 'email' => 'mike@webdaddy.test', 'phone' => '08012345003', 'bank' => 'Access Bank'],
    ['name' => 'Amy Influencer', 'email' => 'amy@webdaddy.test', 'phone' => '08012345004', 'bank' => 'Zenith Bank'],
    ['name' => 'Chris Agent', 'email' => 'chris@webdaddy.test', 'phone' => '08012345005', 'bank' => 'UBA']
];

foreach ($affiliates as $aff) {
    $password = password_hash('Test@123456', PASSWORD_BCRYPT);
    $code = strtoupper(substr(md5($aff['email']), 0, 8));
    
    $sql = "INSERT OR IGNORE INTO affiliate_users (name, email, password, phone, bank_name, affiliate_code, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $db->execute($sql, [
        $aff['name'],
        $aff['email'],
        $password,
        $aff['phone'],
        $aff['bank'],
        $code,
        'active',
        date('Y-m-d H:i:s')
    ]);
}
echo "✅ Created 5 affiliate accounts\n\n";

// VERIFY DATA
echo "📊 VERIFICATION:\n";
$templates = $db->query("SELECT COUNT(*) as count FROM templates")->fetch();
$tools = $db->query("SELECT COUNT(*) as count FROM tools")->fetch();
$affiliates = $db->query("SELECT COUNT(*) as count FROM affiliate_users")->fetch();

echo "✓ Templates: " . $templates['count'] . "\n";
echo "✓ Tools: " . $tools['count'] . "\n";
echo "✓ Affiliates: " . $affiliates['count'] . "\n";
echo "\n✅ TEST DATA GENERATION COMPLETE!\n";
?>
