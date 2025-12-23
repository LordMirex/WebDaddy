<?php
$pageTitle = 'Blog Posts';

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';
require_once __DIR__ . '/../../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../../includes/blog/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();
requireAdmin();

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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Blog Posts</h1>
            <p class="text-gray-600 mt-1">Manage your blog content</p>
        </div>
        <a href="editor.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2">
            <i class="bi bi-plus-lg"></i>
            New Post
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg p-4 border-l-4 border-primary-600">
            <div class="text-xs text-gray-500 uppercase font-semibold">Total</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $allCount; ?></div>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-gray-400">
            <div class="text-xs text-gray-500 uppercase font-semibold">Drafts</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $draftCount; ?></div>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-green-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Published</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $publishedCount; ?></div>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-yellow-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Scheduled</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $scheduledCount; ?></div>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-red-500">
            <div class="text-xs text-gray-500 uppercase font-semibold">Archived</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo $archivedCount; ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg p-6 shadow-sm">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="all">All Statuses</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select name="sort" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>Newest</option>
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title</option>
                        <option value="views" <?php echo $sortBy === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" placeholder="Title or excerpt..." value="<?php echo htmlspecialchars($search); ?>" class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors">Filter</button>
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors">Reset</a>
            </div>
        </form>
    </div>

    <!-- Posts Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if (empty($posts)): ?>
            <div class="p-12 text-center">
                <i class="bi bi-inbox text-4xl text-gray-300 block mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">No posts found</h3>
                <p class="text-gray-600 mb-4">Try adjusting your filters or create a new post to get started.</p>
                <a href="editor.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i>
                    Create New Post
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Views</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($posts as $post): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate max-w-xs" title="<?php echo htmlspecialchars($post['title']); ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php if ($post['category_name']): ?>
                                        <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium">
                                            <?php echo htmlspecialchars($post['category_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php
                                        switch($post['status']) {
                                            case 'draft': $postStatusColor = 'bg-gray-200 text-gray-700'; break;
                                            case 'published': $postStatusColor = 'bg-green-100 text-green-700'; break;
                                            case 'scheduled': $postStatusColor = 'bg-yellow-100 text-yellow-700'; break;
                                            case 'archived': $postStatusColor = 'bg-red-100 text-red-700'; break;
                                            default: $postStatusColor = 'bg-gray-100 text-gray-700';
                                        }
                                    ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?php echo $postStatusColor; ?>">
                                        <?php echo ucfirst(htmlspecialchars($post['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($post['view_count']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <a href="editor.php?post_id=<?php echo $post['id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">Edit</a>
                                    <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>/" target="_blank" class="text-gray-600 hover:text-gray-700 font-medium">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">« First</a>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">‹ Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-3 py-1 rounded bg-primary-600 text-white font-medium"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">Next ›</a>
                        <a href="?page=<?php echo $totalPages; ?>&status=<?php echo htmlspecialchars($status); ?>&category=<?php echo htmlspecialchars($category); ?>&search=<?php echo htmlspecialchars($search); ?>&sort=<?php echo htmlspecialchars($sortBy); ?>" class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-50">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
