<?php
$pageTitle = 'Blog Categories';

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();
requireAdmin();

$db = getDb();
$blogCategory = new BlogCategory($db);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        
        if ($name) {
            try {
                $blogCategory->create([
                    'name' => $name,
                    'description' => $description,
                    'parent_id' => $parent_id,
                    'meta_title' => $meta_title,
                    'meta_description' => $meta_description,
                    'is_active' => 1
                ]);
                $message = 'Category created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error creating category: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Category name is required.';
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($name && $id) {
            try {
                $blogCategory->update($id, [
                    'name' => $name,
                    'description' => $description,
                    'parent_id' => $parent_id,
                    'meta_title' => $meta_title,
                    'meta_description' => $meta_description,
                    'is_active' => $is_active
                ]);
                $message = 'Category updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating category: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            try {
                $blogCategory->delete($id);
                $message = 'Category deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting category: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get categories with post count
$categories = $blogCategory->getWithPostCount(false);
$parentOptions = $blogCategory->getAll(false);

$editingId = $_GET['edit'] ?? null;
$editingCategory = $editingId ? $blogCategory->getById($editingId) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Blog Categories</h1>
        <p class="text-gray-600 mt-1">Create and manage your blog categories</p>
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
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-3 border-b border-gray-200">
                    <?php echo $editingCategory ? 'Edit Category' : 'Create New Category'; ?>
                </h2>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="<?php echo $editingCategory ? 'update' : 'create'; ?>">
                    <?php if ($editingCategory): ?>
                        <input type="hidden" name="id" value="<?php echo $editingCategory['id']; ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                        <input type="text" name="name" placeholder="e.g., Website Design" value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" required class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <p class="text-xs text-gray-500 mt-1">Used for display and URL</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" placeholder="Brief description..." class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500" rows="3"><?php echo htmlspecialchars($editingCategory['description'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Shown on category pages</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Parent Category</label>
                        <select name="parent_id" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">None (Top-level)</option>
                            <?php foreach ($parentOptions as $cat): ?>
                                <?php if (!$editingCategory || $cat['id'] !== $editingCategory['id']): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($editingCategory && $cat['id'] === $editingCategory['parent_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-medium text-gray-900 mb-3">SEO Settings</h3>
                        
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Meta Title</label>
                            <input type="text" name="meta_title" placeholder="60 characters" maxlength="60" value="<?php echo htmlspecialchars($editingCategory['meta_title'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                            <textarea name="meta_description" placeholder="160 characters" maxlength="160" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500" rows="2"><?php echo htmlspecialchars($editingCategory['meta_description'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate</p>
                        </div>
                    </div>

                    <?php if ($editingCategory): ?>
                        <div class="border-t border-gray-200 pt-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" <?php echo $editingCategory['is_active'] ? 'checked' : ''; ?> class="rounded">
                                <span class="text-sm font-medium text-gray-700">Active (visible in listings)</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors font-medium">
                            <?php echo $editingCategory ? 'Update' : 'Create'; ?>
                        </button>
                        <?php if ($editingCategory): ?>
                            <a href="categories.php" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors font-medium text-center">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-3 border-b border-gray-200">All Categories (<?php echo count($categories); ?>)</h2>
                
                <?php if (empty($categories)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-3xl text-gray-300 block mb-3"></i>
                        <p class="text-gray-600">No categories yet. Create your first one using the form.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($categories as $cat): ?>
                            <div class="flex items-start justify-between p-4 border border-gray-200 rounded-lg hover:shadow-sm transition-all <?php echo !$cat['parent_id'] ? 'border-l-4 border-l-primary-600' : ''; ?>">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cat['name']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1">/blog/category/<?php echo htmlspecialchars($cat['slug']); ?>/</div>
                                    <div class="flex gap-2 mt-2">
                                        <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                            <?php echo (int)$cat['post_count']; ?> post<?php echo $cat['post_count'] !== 1 ? 's' : ''; ?>
                                        </span>
                                        <span class="inline-block <?php echo $cat['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> px-2 py-1 rounded text-xs font-medium">
                                            <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2 ml-4">
                                    <a href="?edit=<?php echo $cat['id']; ?>" class="text-primary-600 hover:text-primary-700 text-sm font-medium">Edit</a>
                                    <a href="#" onclick="if(confirm('Delete this category? Posts will be unassigned.')) { const form = document.createElement('form'); form.method='POST'; form.innerHTML='<input type=hidden name=action value=delete><input type=hidden name=id value=<?php echo $cat['id']; ?>>'; document.body.appendChild(form); form.submit(); } return false;" class="text-red-600 hover:text-red-700 text-sm font-medium">Delete</a>
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
