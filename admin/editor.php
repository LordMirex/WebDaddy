<?php
/**
 * Admin Blog Editor
 * Create, edit, and delete blog posts with full functionality
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/blog/BlogPost.php';
require_once __DIR__ . '/../includes/blog/BlogCategory.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
startSecureSession();

// Check admin access
$admin = $_SESSION['admin'] ?? null;
if (!$admin) {
    header('Location: /admin/login.php');
    exit;
}

$db = getDb();
$blogPost = new BlogPost($db);
$blogCategory = new BlogCategory($db);

$action = $_GET['action'] ?? 'list';
$postId = $_GET['id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'list';
    
    if ($action === 'save') {
        $postId = $_POST['post_id'] ?? null;
        
        $postData = [
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'excerpt' => $_POST['excerpt'] ?? '',
            'featured_image' => $_POST['featured_image'] ?? '',
            'featured_image_alt' => $_POST['featured_image_alt'] ?? '',
            'category_id' => $_POST['category_id'] ?? null,
            'author_name' => $_POST['author_name'] ?? 'WebDaddy Team',
            'status' => $_POST['status'] ?? 'draft',
            'publish_date' => $_POST['publish_date'] ?? date('Y-m-d H:i:s'),
            'reading_time_minutes' => (int)($_POST['reading_time_minutes'] ?? 5),
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'focus_keyword' => $_POST['focus_keyword'] ?? ''
        ];
        
        if ($postId) {
            $blogPost->update($postId, $postData);
            $message = 'Post updated successfully!';
        } else {
            $postId = $blogPost->create($postData);
            $message = 'Post created successfully!';
        }
        
        header('Location: /admin/editor.php?action=edit&id=' . $postId . '&message=' . urlencode($message));
        exit;
    } elseif ($action === 'delete') {
        $deleteId = $_POST['post_id'] ?? null;
        if ($deleteId) {
            $blogPost->delete($deleteId);
            header('Location: /admin/editor.php?action=list&message=' . urlencode('Post deleted successfully!'));
            exit;
        }
    }
}

$post = null;
if ($action === 'edit' && $postId) {
    $post = $blogPost->getById($postId);
}

$categories = $blogCategory->getAll();
$allPosts = $blogPost->getPublished(1, 100);
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Editor | Admin</title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet"><script defer src="/assets/js/alpine.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sidebar-nav { transition: all 0.3s ease; }
        .editor-panel { min-height: 100vh; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-navy-dark text-white p-6 overflow-y-auto">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gold"><i class="bi bi-pencil-square mr-2"></i>Blog Editor</h1>
            </div>
            
            <nav class="space-y-2">
                <a href="/admin/editor.php?action=list" class="block px-4 py-2 rounded <?= $action === 'list' ? 'bg-gold text-navy-dark font-bold' : 'hover:bg-gray-700' ?>">
                    <i class="bi bi-list mr-2"></i>All Posts
                </a>
                <a href="/admin/editor.php?action=create" class="block px-4 py-2 rounded hover:bg-gray-700">
                    <i class="bi bi-plus-circle mr-2"></i>New Post
                </a>
            </nav>

            <hr class="my-6 border-gray-700">
            
            <h3 class="text-sm font-bold text-gray-400 mb-3">RECENT POSTS</h3>
            <ul class="space-y-1 text-sm">
                <?php foreach (array_slice($allPosts, 0, 5) as $p): ?>
                <li>
                    <a href="/admin/editor.php?action=edit&id=<?= $p['id'] ?>" class="block px-3 py-1 rounded hover:bg-gray-700 truncate">
                        <?= htmlspecialchars(substr($p['title'], 0, 20)) ?>...
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <hr class="my-6 border-gray-700">
            
            <a href="/admin/" class="block px-4 py-2 rounded hover:bg-gray-700 text-sm">
                <i class="bi bi-arrow-left mr-2"></i>Back to Admin
            </a>
        </div>

        <!-- Main Editor -->
        <div class="flex-1 overflow-y-auto editor-panel">
            <div class="p-8">
                <!-- Header -->
                <div class="mb-6">
                    <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="bi bi-check-circle mr-2"></i><?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <h2 class="text-3xl font-bold">
                        <?= $action === 'list' ? 'Blog Posts' : ($post ? 'Edit: ' . htmlspecialchars($post['title']) : 'Create New Post') ?>
                    </h2>
                </div>

                <!-- LIST VIEW -->
                <?php if ($action === 'list'): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold">Title</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold">Category</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold">Published</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($allPosts as $p): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <a href="/admin/editor.php?action=edit&id=<?= $p['id'] ?>" class="text-blue-600 hover:underline font-medium">
                                            <?= htmlspecialchars(substr($p['title'], 0, 50)) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-bold <?= $p['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><?= date('M d, Y', strtotime($p['publish_date'])) ?></td>
                                    <td class="px-6 py-4 text-sm space-x-2">
                                        <a href="/admin/editor.php?action=edit&id=<?= $p['id'] ?>" class="text-blue-600 hover:underline">Edit</a>
                                        <a href="/blog/<?= htmlspecialchars($p['slug']) ?>/" target="_blank" class="text-gray-600 hover:underline">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CREATE/EDIT VIEW -->
                <?php else: ?>
                <form method="POST" class="max-w-4xl">
                    <input type="hidden" name="action" value="save">
                    <?php if ($post): ?>
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <?php endif; ?>

                    <div class="bg-white rounded-lg shadow p-8 space-y-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Post Title *</label>
                            <input type="text" name="title" required 
                                   value="<?= htmlspecialchars($post['title'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold"
                                   onchange="updateSlug()">
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">URL Slug *</label>
                            <div class="flex gap-2">
                                <span class="flex items-center text-gray-600">/blog/</span>
                                <input type="text" name="slug" id="slug" required 
                                       value="<?= htmlspecialchars($post['slug'] ?? '') ?>"
                                       class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold">
                                <span class="flex items-center text-gray-600">/</span>
                            </div>
                        </div>

                        <!-- Excerpt -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Excerpt (SEO Description)</label>
                            <textarea name="excerpt" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Category</label>
                            <select name="category_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] ?? null) == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Author -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Author Name</label>
                            <input type="text" name="author_name" 
                                   value="<?= htmlspecialchars($post['author_name'] ?? 'WebDaddy Team') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold">
                        </div>

                        <!-- Status & Publish Date -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Status</label>
                                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold">
                                    <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Publish Date</label>
                                <input type="datetime-local" name="publish_date" 
                                       value="<?= $post ? date('Y-m-d\TH:i', strtotime($post['publish_date'])) : date('Y-m-d\TH:i') ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold">
                            </div>
                        </div>

                        <!-- SEO Fields -->
                        <hr>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Meta Title (SEO)</label>
                            <input type="text" name="meta_title" maxlength="60"
                                   value="<?= htmlspecialchars($post['meta_title'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold"
                                   placeholder="60 characters max">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Meta Description (SEO)</label>
                            <textarea name="meta_description" rows="2" maxlength="160" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold" placeholder="160 characters max"><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Focus Keyword (SEO)</label>
                            <input type="text" name="focus_keyword" 
                                   value="<?= htmlspecialchars($post['focus_keyword'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold"
                                   placeholder="Main keyword for this post">
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-4">
                            <button type="submit" class="px-6 py-2 bg-gold text-navy-dark font-bold rounded-lg hover:opacity-90">
                                <i class="bi bi-check-circle mr-2"></i>Save Post
                            </button>
                            <a href="/admin/editor.php?action=list" class="px-6 py-2 bg-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-400">
                                Cancel
                            </a>
                            <?php if ($post): ?>
                            <button type="button" onclick="if(confirm('Delete this post?')) { document.getElementById('deleteForm').submit(); }" 
                                    class="px-6 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 ml-auto">
                                <i class="bi bi-trash mr-2"></i>Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <?php if ($post): ?>
                <form id="deleteForm" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function updateSlug() {
        const title = document.querySelector('input[name="title"]').value;
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        document.getElementById('slug').value = slug;
    }
    </script>
</body>
</html>
