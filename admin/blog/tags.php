<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogTag.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

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

// Sort by post count (descending)
usort($tags, function($a, $b) {
    return $b['post_count'] - $a['post_count'];
});

$pageTitle = 'Blog Tags';
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
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-icon { font-weight: bold; font-size: 18px; }
        
        .grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-group input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-group input:focus { 
            outline: none; 
            border-color: #0066cc; 
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }
        
        .form-hint { font-size: 12px; color: #666; margin-top: 5px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #0066cc; color: white; width: 100%; }
        .btn-primary:hover { background: #0052a3; }
        .btn-sm { padding: 6px 12px; font-size: 12px; width: auto; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .card-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .tags-container { display: flex; flex-wrap: wrap; gap: 10px; }
        
        .tag-item { 
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            background: white; 
            border: 1px solid #e1e8ed; 
            border-radius: 8px; 
            padding: 12px 16px;
            transition: all 0.2s;
        }
        .tag-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        
        .tag-info { flex: 1; }
        .tag-name { font-weight: 600; color: #333; }
        .tag-meta { font-size: 12px; color: #999; margin-top: 3px; }
        
        .tag-actions { display: flex; gap: 8px; margin-left: 12px; }
        .tag-actions a { 
            padding: 6px 10px; 
            border-radius: 4px; 
            font-size: 12px; 
            text-decoration: none; 
            transition: all 0.2s;
            background: #ffe0e6;
            color: #721c24;
        }
        .tag-actions a:hover { background: #f8d7da; }
        
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e1e8ed; }
        .stat { text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #0066cc; }
        .stat-label { font-size: 12px; color: #666; margin-top: 3px; }
        
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state h3 { margin-bottom: 10px; color: #666; }
        .empty-state p { color: #999; }
        
        .tag-badge { 
            display: inline-block;
            background: #e3f2fd; 
            color: #1565c0; 
            padding: 4px 10px; 
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tags-list { display: flex; flex-direction: column; gap: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p>Add tags to organize and classify your blog posts</p>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                <div class="alert-icon"><?php echo $messageType === 'success' ? '✓' : '✕'; ?></div>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Create Tag Form -->
            <div>
                <div class="card">
                    <div class="card-title">Add New Tag</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label for="name">Tag Name *</label>
                            <input type="text" id="name" name="name" placeholder="e.g., WordPress, SEO, Ecommerce" required autofocus>
                            <div class="form-hint">Used for post classification</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Create Tag</button>
                    </form>
                </div>
            </div>
            
            <!-- Tags List -->
            <div>
                <div class="card">
                    <div class="stats-header">
                        <div>
                            <div class="card-title" style="margin: 0; padding: 0; border: none;">All Tags</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo count($tags); ?></div>
                            <div class="stat-label">Total Tags</div>
                        </div>
                    </div>
                    
                    <?php if (empty($tags)): ?>
                        <div class="empty-state">
                            <h3>No tags yet</h3>
                            <p>Create your first tag using the form on the left to organize your posts.</p>
                        </div>
                    <?php else: ?>
                        <div class="tags-list">
                            <?php foreach ($tags as $tag): ?>
                                <div class="tag-item">
                                    <div class="tag-info">
                                        <div class="tag-name"><?php echo htmlspecialchars($tag['name']); ?></div>
                                        <div class="tag-meta">
                                            <span style="margin-right: 15px;">/blog/tag/<?php echo htmlspecialchars($tag['slug']); ?>/</span>
                                            <span class="tag-badge"><?php echo (int)$tag['post_count']; ?> post<?php echo $tag['post_count'] !== 1 ? 's' : ''; ?></span>
                                        </div>
                                    </div>
                                    <div class="tag-actions">
                                        <a href="#" onclick="if(confirm('Delete this tag? It will be removed from all posts.')) { 
                                            const form = document.createElement('form'); 
                                            form.method='POST'; 
                                            form.innerHTML='<input type=hidden name=action value=delete><input type=hidden name=id value=<?php echo $tag['id']; ?>>'; 
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
