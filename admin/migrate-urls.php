<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url_utils.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'Migrate URLs to Relative Paths';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();

$migrated = false;
$stats = [
    'templates_thumbnail' => 0,
    'templates_demo_url' => 0,
    'templates_demo_video' => 0,
    'tools_thumbnail' => 0,
    'total' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    try {
        $db->exec('BEGIN TRANSACTION');
        
        $templatesQuery = "SELECT id, thumbnail_url, demo_url, demo_video_url FROM templates WHERE thumbnail_url IS NOT NULL OR demo_url IS NOT NULL OR demo_video_url IS NOT NULL";
        $templates = $db->query($templatesQuery)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($templates as $template) {
            $updates = [];
            $params = ['id' => $template['id']];
            
            if (!empty($template['thumbnail_url'])) {
                $oldUrl = $template['thumbnail_url'];
                $newUrl = UrlUtils::normalizeUploadUrl($oldUrl);
                
                if ($oldUrl !== $newUrl) {
                    $updates[] = 'thumbnail_url = :thumbnail_url';
                    $params['thumbnail_url'] = $newUrl;
                    $stats['templates_thumbnail']++;
                }
            }
            
            if (!empty($template['demo_url'])) {
                $oldUrl = $template['demo_url'];
                $newUrl = UrlUtils::normalizeUploadUrl($oldUrl);
                
                if ($oldUrl !== $newUrl) {
                    $updates[] = 'demo_url = :demo_url';
                    $params['demo_url'] = $newUrl;
                    $stats['templates_demo_url']++;
                }
            }
            
            if (!empty($template['demo_video_url'])) {
                $oldUrl = $template['demo_video_url'];
                $newUrl = UrlUtils::normalizeUploadUrl($oldUrl);
                
                if ($oldUrl !== $newUrl) {
                    $updates[] = 'demo_video_url = :demo_video_url';
                    $params['demo_video_url'] = $newUrl;
                    $stats['templates_demo_video']++;
                }
            }
            
            if (!empty($updates)) {
                $sql = "UPDATE templates SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
        }
        
        $toolsQuery = "SELECT id, thumbnail_url FROM tools WHERE thumbnail_url IS NOT NULL";
        $tools = $db->query($toolsQuery)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tools as $tool) {
            if (!empty($tool['thumbnail_url'])) {
                $oldUrl = $tool['thumbnail_url'];
                $newUrl = UrlUtils::normalizeUploadUrl($oldUrl);
                
                if ($oldUrl !== $newUrl) {
                    $stmt = $db->prepare("UPDATE tools SET thumbnail_url = :thumbnail_url WHERE id = :id");
                    $stmt->execute([
                        'id' => $tool['id'],
                        'thumbnail_url' => $newUrl
                    ]);
                    $stats['tools_thumbnail']++;
                }
            }
        }
        
        $db->exec('COMMIT');
        
        $stats['total'] = $stats['templates_thumbnail'] + $stats['templates_demo_url'] + $stats['templates_demo_video'] + $stats['tools_thumbnail'];
        $migrated = true;
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        $error = "Migration failed: " . $e->getMessage();
    }
}

$previewQuery = "
    SELECT 
        'template' as type,
        id,
        name,
        thumbnail_url,
        demo_url,
        demo_video_url as video_url
    FROM templates 
    WHERE thumbnail_url LIKE 'http%' OR demo_url LIKE 'http%' OR demo_video_url LIKE 'http%'
    UNION ALL
    SELECT 
        'tool' as type,
        id,
        name,
        thumbnail_url,
        NULL as demo_url,
        NULL as video_url
    FROM tools 
    WHERE thumbnail_url LIKE 'http%'
    LIMIT 20
";

$needsMigration = $db->query($previewQuery)->fetchAll(PDO::FETCH_ASSOC);
$totalNeedsMigration = count($needsMigration);
?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            <i class="bi bi-arrow-repeat mr-2"></i>
            Migrate URLs to Relative Paths
        </h1>
        <p class="text-gray-600">Convert absolute URLs to relative paths for environment portability</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <i class="bi bi-exclamation-triangle text-red-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($migrated): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-6 mb-6">
            <div class="flex items-start">
                <i class="bi bi-check-circle text-green-500 text-2xl mr-3"></i>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Migration Completed Successfully!</h3>
                    <div class="text-sm text-green-700 space-y-1">
                        <p><strong>Total URLs Updated:</strong> <?php echo $stats['total']; ?></p>
                        <ul class="list-disc list-inside ml-4 mt-2">
                            <li>Template Banners: <?php echo $stats['templates_thumbnail']; ?></li>
                            <li>Template Demo URLs: <?php echo $stats['templates_demo_url']; ?></li>
                            <li>Template Videos: <?php echo $stats['templates_demo_video']; ?></li>
                            <li>Tool Banners: <?php echo $stats['tools_thumbnail']; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-6">
        <div class="flex">
            <i class="bi bi-info-circle text-blue-500 text-xl mr-3"></i>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-blue-900 mb-2">About This Migration</h3>
                <div class="text-sm text-blue-700 space-y-2">
                    <p><strong>Problem:</strong> When you upload files, the system was storing absolute URLs (like <code>https://old-domain.com/uploads/...</code>). When you move to a new environment, these URLs break.</p>
                    <p><strong>Solution:</strong> This migration converts all uploaded file URLs to relative paths (like <code>/uploads/...</code>) that work in any environment.</p>
                    <p><strong>What it does:</strong></p>
                    <ul class="list-disc list-inside ml-4">
                        <li>Converts template banners from absolute to relative URLs</li>
                        <li>Converts template demo videos from absolute to relative URLs</li>
                        <li>Converts tool banners from absolute to relative URLs</li>
                        <li>Leaves external URLs (placeholder.com, YouTube, etc.) unchanged</li>
                    </ul>
                    <p class="mt-2"><strong>Safe:</strong> This operation is wrapped in a database transaction and can be safely run multiple times.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totalNeedsMigration > 0): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="bi bi-exclamation-circle text-yellow-600 mr-2"></i>
                Found <?php echo $totalNeedsMigration; ?> URL(s) Needing Migration
            </h2>
            
            <div class="mb-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current URL</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($needsMigration as $item): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $item['type'] === 'template' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo ucfirst($item['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div class="max-w-md truncate" title="<?php echo htmlspecialchars($item['thumbnail_url']); ?>">
                                            <?php echo htmlspecialchars($item['thumbnail_url']); ?>
                                        </div>
                                        <?php if (!empty($item['demo_url']) && strpos($item['demo_url'], 'http') === 0): ?>
                                            <div class="max-w-md truncate text-xs text-gray-500 mt-1" title="<?php echo htmlspecialchars($item['demo_url']); ?>">
                                                Demo URL: <?php echo htmlspecialchars($item['demo_url']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['video_url']) && strpos($item['video_url'], 'http') === 0): ?>
                                            <div class="max-w-md truncate text-xs text-gray-500 mt-1" title="<?php echo htmlspecialchars($item['video_url']); ?>">
                                                Video: <?php echo htmlspecialchars($item['video_url']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" onsubmit="return confirm('Are you sure you want to migrate all URLs? This will update the database.');">
                <button type="submit" name="migrate" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                    <i class="bi bi-arrow-repeat mr-2"></i>
                    Migrate <?php echo $totalNeedsMigration; ?> URL(s) Now
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-6">
            <div class="flex">
                <i class="bi bi-check-circle text-green-500 text-xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-green-900 mb-1">All URLs are Already Relative!</h3>
                    <p class="text-sm text-green-700">No migration needed. All your upload URLs are using relative paths.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-6">
        <a href="/admin/templates.php" class="inline-flex items-center text-primary-600 hover:text-primary-700 font-medium">
            <i class="bi bi-arrow-left mr-2"></i>
            Back to Templates
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
