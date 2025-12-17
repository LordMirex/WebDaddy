<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';
require_once __DIR__ . '/../../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../../includes/blog/helpers.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$blogPost = new BlogPost($db);
$blogCategory = new BlogCategory($db);

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'date';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// Build query
$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if ($category !== 'all') {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(p.title LIKE ? OR p.excerpt LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Build order by
$orderBy = 'p.created_at DESC';
switch ($sortBy) {
    case 'title':
        $orderBy = 'p.title ASC';
        break;
    case 'views':
        $orderBy = 'p.view_count DESC';
        break;
    case 'date':
    default:
        $orderBy = 'p.created_at DESC';
        break;
}

// Get total count
$countSql = "SELECT COUNT(*) FROM blog_posts p WHERE " . (empty($where) ? "1=1" : implode(" AND ", $where));
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

// Get posts
$offset = ($page - 1) * $perPage;
$sql = "
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM blog_posts p
    LEFT JOIN blog_categories c ON p.category_id = c.id
    WHERE " . (empty($where) ? "1=1" : implode(" AND ", $where)) . "
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quick stats
$allCount = $blogPost->getAllCount();
$draftCount = $blogPost->getAllCount('draft');
$publishedCount = $blogPost->getAllCount('published');
$scheduledCount = $blogPost->getAllCount('scheduled');
$archivedCount = $blogPost->getAllCount('archived');

// Get categories for filter
$categories = $blogCategory->getAll();

$pageTitle = 'Blog Posts';
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
        .header { background: white; border-bottom: 1px solid #e1e8ed; padding: 20px; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #0066cc; }
        .stat-card.draft { border-left-color: #999; }
        .stat-card.published { border-left-color: #28a745; }
        .stat-card.scheduled { border-left-color: #ffc107; }
        .stat-card.archived { border-left-color: #dc3545; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .stat-value { font-size: 28px; font-weight: bold; color: #0066cc; margin-top: 5px; }
        
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; }
        .filter-group input,
        .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .filter-group input:focus,
        .filter-group select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,0.1); }
        
        .btn { padding: 10px 16px; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-primary:hover { background: #0052a3; }
        .btn-secondary { background: #e1e8ed; color: #333; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-sm { padding: 6px 10px; font-size: 12px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .table-container { background: white; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f5f7fa; }
        th { padding: 15px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; }
        td { padding: 15px; border-top: 1px solid #e1e8ed; }
        tbody tr:hover { background: #f9fafb; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-draft { background: #e2e3e5; color: #666; }
        .status-published { background: #d4edda; color: #155724; }
        .status-scheduled { background: #fff3cd; color: #856404; }
        .status-archived { background: #f8d7da; color: #721c24; }
        
        .title-cell { font-weight: 500; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .category-badge { background: #e3f2fd; color: #1565c0; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .category-badge { display: inline-block; }
        
        .actions { display: flex; gap: 8px; }
        .actions a { padding: 6px 10px; border-radius: 4px; background: #e1e8ed; color: #333; text-decoration: none; font-size: 12px; transition: all 0.2s; }
        .actions a:hover { background: #cbd5e0; }
        .actions a.delete { background: #ffe0e6; color: #721c24; }
        .actions a.delete:hover { background: #f8d7da; }
        
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state h3 { margin-bottom: 10px; }
        .empty-state p { color: #666; margin-bottom: 20px; }
        
        .pagination-container { padding: 20px; text-align: center; }
        .pagination { display: flex; gap: 8px; justify-content: center; }
        .pagination a, .pagination span { padding: 8px 12px; border-radius: 4px; background: #e1e8ed; color: #333; text-decoration: none; }
        .pagination a:hover { background: #cbd5e0; }
        .pagination .active { background: #0066cc; color: white; }
        
        .bulk-actions { display: flex; gap: 10px; align-items: center; padding: 15px; background: #f9fafb; border-bottom: 1px solid #e1e8ed; }
        .bulk-actions input[type="checkbox"] { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p>Manage your blog posts, categories, and content</p>
    </div>
    
    <div class="container">
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Posts</div>
                <div class="stat-value"><?php echo $allCount; ?></div>
            </div>
            <div class="stat-card draft">
                <div class="stat-label">Drafts</div>
                <div class="stat-value"><?php echo $draftCount; ?></div>
            </div>
            <div class="stat-card published">
                <div class="stat-label">Published</div>
                <div class="stat-value"><?php echo $publishedCount; ?></div>
            </div>
            <div class="stat-card scheduled">
                <div class="stat-label">Scheduled</div>
                <div class="stat-value"><?php echo $scheduledCount; ?></div>
            </div>
            <div class="stat-card archived">
                <div class="stat-label">Archived</div>
                <div class="stat-value"><?php echo $archivedCount; ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; width: 100%;">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="all">All Statuses</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Title or excerpt..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort">
                        <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                        <option value="views" <?php echo $sortBy === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="index.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>
        
        <!-- Posts Table -->
        <div class="table-container">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <h3>No posts found</h3>
                    <p>Try adjusting your filters or create a new post to get started.</p>
                    <a href="editor.php" class="btn btn-primary">Create New Post</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35%;">Title</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 12%;">Status</th>
                            <th style="width: 12%;">Views</th>
                            <th style="width: 15%;">Date</th>
                            <th style="width: 11%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <div class="title-cell" title="<?php echo htmlspecialchars($post['title']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($post['category_name']): ?>
                                        <span class="category-badge"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($post['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($post['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($post['view_count']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="editor.php?post_id=<?php echo $post['id']; ?>">Edit</a>
                                        <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>/" target="_blank">View</a>
                                        <a href="#" class="delete" onclick="if(confirm('Delete this post?')) location.href='#delete-post-<?php echo $post['id']; ?>'; return false;">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>">« First</a>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>">‹ Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>">Next ›</a>
                        <a href="?page=<?php echo $totalPages; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>">Last »</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
