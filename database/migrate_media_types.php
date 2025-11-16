<?php
require_once __DIR__ . '/../includes/db.php';

echo "🔄 Starting media type migration...\n\n";

try {
    $db = getDb();
    $db->exec('BEGIN TRANSACTION');
    
    echo "1️⃣ Adding media_type column to templates table...\n";
    $db->exec("ALTER TABLE templates ADD COLUMN media_type TEXT DEFAULT 'banner' CHECK(media_type IN ('demo_url', 'banner', 'video'))");
    
    echo "2️⃣ Adding demo_video_url column to templates table...\n";
    $db->exec("ALTER TABLE templates ADD COLUMN demo_video_url TEXT");
    
    echo "3️⃣ Classifying existing templates...\n";
    $templates = $db->query("SELECT id, demo_url FROM templates")->fetchAll(PDO::FETCH_ASSOC);
    
    $classified = [
        'demo_url' => 0,
        'video' => 0,
        'banner' => 0
    ];
    
    foreach ($templates as $template) {
        $demo_url = trim($template['demo_url'] ?? '');
        $mediaType = 'banner';
        $demoVideoUrl = null;
        $clearDemoUrl = false;
        
        if (!empty($demo_url)) {
            $parsed = parse_url($demo_url);
            $path = $parsed['path'] ?? $demo_url;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            if (in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'mkv'])) {
                $mediaType = 'video';
                $demoVideoUrl = $demo_url;
                $clearDemoUrl = true;
                $classified['video']++;
            } elseif (preg_match('/^https?:\/\//i', $demo_url)) {
                $mediaType = 'demo_url';
                $classified['demo_url']++;
            } else {
                $classified['banner']++;
            }
        } else {
            $classified['banner']++;
        }
        
        $stmt = $db->prepare("UPDATE templates SET media_type = :media_type, demo_video_url = :demo_video_url, demo_url = :demo_url WHERE id = :id");
        $stmt->execute([
            ':media_type' => $mediaType,
            ':demo_video_url' => $demoVideoUrl,
            ':demo_url' => $clearDemoUrl ? null : $demo_url,
            ':id' => $template['id']
        ]);
    }
    
    echo "   ✅ Classified " . $classified['demo_url'] . " templates as demo_url\n";
    echo "   ✅ Classified " . $classified['video'] . " templates as video\n";
    echo "   ✅ Classified " . $classified['banner'] . " templates as banner\n\n";
    
    echo "4️⃣ Checking tools table for demo/video fields...\n";
    $toolsSchema = $db->query("PRAGMA table_info(tools)")->fetchAll(PDO::FETCH_ASSOC);
    $hasDemo = false;
    $hasVideo = false;
    
    foreach ($toolsSchema as $column) {
        if ($column['name'] === 'demo_url') $hasDemo = true;
        if ($column['name'] === 'video_url' || $column['name'] === 'demo_video_url') $hasVideo = true;
    }
    
    if ($hasDemo || $hasVideo) {
        echo "   ⚠️  Found demo/video fields in tools table\n";
        echo "   ℹ️  Note: These fields will be ignored by updated admin forms\n";
        echo "   ℹ️  Consider manual cleanup if needed\n\n";
    } else {
        echo "   ✅ Tools table is clean (no demo/video fields)\n\n";
    }
    
    echo "5️⃣ Creating index on media_type...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_templates_media_type ON templates(media_type)");
    
    $db->exec('COMMIT');
    
    echo "\n✅ Migration completed successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Summary:\n";
    echo "  • Added media_type column to templates\n";
    echo "  • Added demo_video_url column to templates\n";
    echo "  • Classified existing templates based on demo_url content\n";
    echo "  • Created index on media_type\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
} catch (Exception $e) {
    $db->exec('ROLLBACK');
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
