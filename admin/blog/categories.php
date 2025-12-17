<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogCategory.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

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

$pageTitle = 'Blog Categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - WebDaddy Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f7fa; color: #333; }
        
        .header { background: white; border-bottom: 1px solid #e1e8ed; padding: 20px; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { color: #666; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-icon { font-weight: bold; font-size: 18px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        
        .form-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-group input,
        .form-group textarea,
        .form-group select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus { 
            outline: none; 
            border-color: #0066cc; 
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }
        
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        .checkbox-group label { margin: 0; }
        
        .form-hint { font-size: 12px; color: #666; margin-top: 5px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-primary:hover { background: #0052a3; }
        .btn-secondary { background: #e1e8ed; color: #333; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 8px 16px; font-size: 12px; }
        
        .button-group { display: flex; gap: 10px; margin-top: 30px; }
        
        .form-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .categories-list { display: flex; flex-direction: column; gap: 12px; }
        .category-item { background: white; border: 1px solid #e1e8ed; border-radius: 8px; padding: 16px; transition: all 0.2s; }
        .category-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .category-item.parent { border-left: 4px solid #0066cc; }
        
        .category-header { display: flex; justify-content: space-between; align-items: start; }
        .category-name { font-weight: 600; color: #333; }
        .category-slug { font-size: 12px; color: #999; margin-top: 3px; }
        .category-meta { display: flex; gap: 15px; margin-top: 10px; font-size: 12px; }
        .category-badge { background: #e3f2fd; color: #1565c0; padding: 4px 8px; border-radius: 4px; }
        .category-status { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #e2e3e5; color: #666; }
        
        .category-actions { display: flex; gap: 8px; }
        .category-actions a { padding: 6px 12px; border-radius: 4px; font-size: 12px; text-decoration: none; transition: all 0.2s; }
        .category-actions a.edit { background: #e3f2fd; color: #1565c0; }
        .category-actions a.edit:hover { background: #bbdefb; }
        .category-actions a.delete { background: #ffe0e6; color: #721c24; }
        .category-actions a.delete:hover { background: #f8d7da; }
        
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state h3 { margin-bottom: 10px; color: #666; }
        .empty-state p { color: #999; margin-bottom: 20px; }
        
        .category-indent { padding-left: 30px; border-left: 2px solid #e1e8ed; margin-left: 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p>Create and manage your blog categories and hierarchies</p>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                <div class="alert-icon"><?php echo $messageType === 'success' ? '✓' : '✕'; ?></div>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Form Section -->
            <div>
                <div class="form-card">
                    <div class="form-title">
                        <?php echo $editingCategory ? 'Edit Category' : 'Create New Category'; ?>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $editingCategory ? 'update' : 'create'; ?>">
                        <?php if ($editingCategory): ?>
                            <input type="hidden" name="id" value="<?php echo $editingCategory['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Category Name *</label>
                            <input type="text" id="name" name="name" placeholder="e.g., Website Design" value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" required>
                            <div class="form-hint">Used for display and URL</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Brief description of this category..."><?php echo htmlspecialchars($editingCategory['description'] ?? ''); ?></textarea>
                            <div class="form-hint">Shown on category archive pages</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_id">Parent Category</label>
                            <select id="parent_id" name="parent_id">
                                <option value="">None (Top-level)</option>
                                <?php foreach ($parentOptions as $cat): ?>
                                    <?php if (!$editingCategory || $cat['id'] !== $editingCategory['id']): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo ($editingCategory && $cat['id'] === $editingCategory['parent_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Create subcategories by selecting a parent</div>
                        </div>
                        
                        <div style="border-top: 1px solid #e1e8ed; padding-top: 20px; margin-top: 20px;">
                            <div class="form-title" style="font-size: 16px; margin: 0 0 15px 0; padding: 0; border: none;">SEO Settings</div>
                            
                            <div class="form-group">
                                <label for="meta_title">Meta Title</label>
                                <input type="text" id="meta_title" name="meta_title" placeholder="For search results (60 chars)" maxlength="60" value="<?php echo htmlspecialchars($editingCategory['meta_title'] ?? ''); ?>">
                                <div class="form-hint">Leave blank to auto-generate</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="meta_description">Meta Description</label>
                                <textarea id="meta_description" name="meta_description" placeholder="For search results (160 chars)" maxlength="160" style="min-height: 60px;"><?php echo htmlspecialchars($editingCategory['meta_description'] ?? ''); ?></textarea>
                                <div class="form-hint">Leave blank to auto-generate</div>
                            </div>
                        </div>
                        
                        <?php if ($editingCategory): ?>
                            <div class="form-group" style="margin-top: 20px; border-top: 1px solid #e1e8ed; padding-top: 20px;">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_active" name="is_active" <?php echo $editingCategory['is_active'] ? 'checked' : ''; ?>>
                                    <label for="is_active">Active (visible in listings)</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editingCategory ? 'Update Category' : 'Create Category'; ?>
                            </button>
                            <?php if ($editingCategory): ?>
                                <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Categories List Section -->
            <div>
                <div class="form-card">
                    <div class="form-title">All Categories (<?php echo count($categories); ?>)</div>
                    
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <h3>No categories yet</h3>
                            <p>Create your first category using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="categories-list">
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-item <?php echo $cat['parent_id'] ? 'category-indent' : 'parent'; ?>">
                                    <div class="category-header">
                                        <div>
                                            <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                                            <div class="category-slug">/blog/category/<?php echo htmlspecialchars($cat['slug']); ?>/</div>
                                            <div class="category-meta">
                                                <span class="category-badge"><?php echo (int)$cat['post_count']; ?> post<?php echo $cat['post_count'] !== 1 ? 's' : ''; ?></span>
                                                <span class="category-status <?php echo $cat['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="category-actions" style="margin-top: 10px;">
                                        <a href="?edit=<?php echo $cat['id']; ?>" class="edit">Edit</a>
                                        <a href="#" class="delete" onclick="if(confirm('Delete this category? Posts will be unassigned.')) { 
                                            const form = document.createElement('form'); 
                                            form.method='POST'; 
                                            form.innerHTML='<input type=hidden name=action value=delete><input type=hidden name=id value=<?php echo $cat['id']; ?>>'; 
                                            document.body.appendChild(form); 
                                            form.submit(); 
                                        } return false;">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
