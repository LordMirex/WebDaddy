<?php
$pageTitle = 'Blog Tags';

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogTag.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();
requireAdmin();

$db = getDb();
$blogTag = new BlogTag($db);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        
        if ($name) {
            try {
                $blogTag->create(['name' => $name]);
                $message = 'Tag created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error creating tag: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Tag name is required.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            try {
                $blogTag->delete($id);
                $message = 'Tag deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting tag: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get tags with post count
$tags = $blogTag->getWithPostCount();
usort($tags, function($a, $b) {
    return $b['post_count'] - $a['post_count'];
});

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Blog Tags</h1>
        <p class="text-gray-600 mt-1">Organize your blog posts with tags</p>
    </div>

    <?php if ($message): ?>
        <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> flex items-start gap-3">
            <i class="bi <?php echo $messageType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> text-xl flex-shrink-0 mt-0.5"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-3 border-b border-gray-200">Add New Tag</h2>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tag Name *</label>
                        <input type="text" name="name" placeholder="e.g., WordPress, SEO" required autofocus class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <p class="text-xs text-gray-500 mt-1">Used for post classification</p>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium">
                        Create Tag
                    </button>
                </form>
            </div>
        </div>

        <!-- List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">All Tags</h2>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-primary-600"><?php echo count($tags); ?></div>
                        <div class="text-xs text-gray-500">Total Tags</div>
                    </div>
                </div>
                
                <?php if (empty($tags)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-3xl text-gray-300 block mb-3"></i>
                        <p class="text-gray-600">No tags yet. Create your first one using the form.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($tags as $tag): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:shadow-sm transition-all group">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($tag['name']); ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5">/blog/tag/<?php echo htmlspecialchars($tag['slug']); ?>/</div>
                                </div>
                                <div class="flex items-center gap-3 ml-4">
                                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                        <?php echo (int)$tag['post_count']; ?> post<?php echo $tag['post_count'] !== 1 ? 's' : ''; ?>
                                    </span>
                                    <a href="#" onclick="if(confirm('Delete this tag? It will be removed from all posts.')) { const form = document.createElement('form'); form.method='POST'; form.innerHTML='<input type=hidden name=action value=delete><input type=hidden name=id value=<?php echo $tag['id']; ?>>'; document.body.appendChild(form); form.submit(); } return false;" class="text-red-600 hover:text-red-700 text-sm font-medium">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
