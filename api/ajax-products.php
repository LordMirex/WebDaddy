<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'load_view') {
    $view = $_GET['view'] ?? 'templates';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $category = $_GET['category'] ?? null;
    $affiliateCode = $_GET['aff'] ?? '';
    
    // Add HTTP caching and gzip compression
    header('Cache-Control: public, max-age=300');
    header('Vary: Accept-Encoding');
    if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
    
    if ($view === 'templates') {
        $perPage = 9;
        $db = getDb();
        
        // Use SQL LIMIT instead of fetching all templates
        $sqlWhere = "WHERE active = 1";
        $params = [];
        if ($category) {
            $sqlWhere .= " AND category = ?";
            $params[] = $category;
        }
        
        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM templates $sqlWhere");
        $countStmt->execute($params);
        $totalTemplates = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Fetch only needed fields and only for this page
        $totalPages = max(1, ceil($totalTemplates / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        $stmt = $db->prepare("SELECT id, name, category, price, thumbnail_url, demo_url FROM templates $sqlWhere ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get categories (cached in memory during request)
        $catStmt = $db->prepare("SELECT DISTINCT category FROM templates WHERE active = 1 ORDER BY category ASC");
        $catStmt->execute();
        $templateCategories = array_column($catStmt->fetchAll(PDO::FETCH_ASSOC), 'category');
        
        // Render templates grid
        renderTemplatesGrid($templates, $templateCategories, $totalTemplates, $totalPages, $page, $category, $affiliateCode);
    } else {
        $perPage = 18;
        $totalTools = getToolsCount(true, $category, true);
        $totalPages = max(1, ceil($totalTools / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $tools = getTools(true, $category, $perPage, $offset, true);
        $toolCategories = getToolCategories();
        
        // Render tools grid
        renderToolsGrid($tools, $toolCategories, $totalTools, $totalPages, $page, $category, $affiliateCode);
    }
    
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'view' => $view,
        'page' => $page
    ]);
    exit;
}

function renderTemplatesGrid($templates, $templateCategories, $totalTemplates, $totalPages, $page, $currentCategory, $affiliateCode) {
    // Category filter for templates
    if (!empty($templateCategories)): ?>
    <div class="mb-6 max-w-4xl mx-auto">
        <div class="relative">
            <select id="templates-category-filter" 
                    class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all appearance-none bg-white text-gray-900 font-medium cursor-pointer">
                <option value="" <?php echo empty($currentCategory) ? 'selected' : ''; ?>>
                    All Categories
                </option>
                <?php foreach ($templateCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($currentCategory === $cat) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>
    <?php endif;
    
    if (empty($templates)): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 class="text-xl font-bold text-gray-900 mb-2">No templates available</h4>
            <p class="text-gray-600 mb-0">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px;">
            <?php foreach ($templates as $template): ?>
            <div class="group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                <div class="relative overflow-hidden h-48 bg-gray-100">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($template['name']); ?>"
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                    <?php 
                    $hasDemo = !empty($template['demo_url']) || !empty($template['demo_video_url']);
                    if ($hasDemo):
                        $demoUrl = !empty($template['demo_video_url']) ? $template['demo_video_url'] : $template['demo_url'];
                        $hasVideoExtension = preg_match('/\.(mp4|webm|mov|avi)$/i', $demoUrl);
                        $isVideo = $hasVideoExtension;
                    ?>
                    <?php if ($isVideo): ?>
                    <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Video
                    </button>
                    <?php else: ?>
                    <button onclick="event.stopPropagation(); openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                        Preview
                    </button>
                    <?php endif; ?>
                    <?php if ($isVideo): ?>
                    <button onclick="openVideoModal('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            data-video-trigger
                            data-video-url="<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>"
                            data-video-title="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>"
                            class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Watch Demo
                        </span>
                    </button>
                    <?php else: ?>
                    <button onclick="openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')" 
                            class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Click to Preview
                        </span>
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-base font-bold text-gray-900 flex-1 pr-2"><?php echo htmlspecialchars($template['name']); ?></h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 shrink-0">
                            <?php echo htmlspecialchars($template['category']); ?>
                        </span>
                    </div>
                    <p class="text-gray-600 text-xs mb-3 line-clamp-2"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                            <span class="text-lg font-extrabold text-primary-600"><?php echo formatCurrency($template['price']); ?></span>
                        </div>
                        <div class="flex gap-2">
                            <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                               class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                                Details
                            </a>
                            <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '', this)" 
                               class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php renderPagination($totalPages, $page, 'templates', $currentCategory, $affiliateCode); ?>
    <?php endif;
}

function renderToolsGrid($tools, $toolCategories, $totalTools, $totalPages, $page, $currentCategory, $affiliateCode) {
    // Category filter for tools
    if (!empty($toolCategories)): ?>
    <div class="mb-6 max-w-4xl mx-auto">
        <div class="relative">
            <select id="tools-category-filter" 
                    class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all appearance-none bg-white text-gray-900 font-medium cursor-pointer">
                <option value="" <?php echo empty($currentCategory) ? 'selected' : ''; ?>>
                    All Categories
                </option>
                <?php foreach ($toolCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($currentCategory === $cat) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>
    <?php endif;
    
    if (empty($tools)): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 class="text-xl font-bold text-gray-900 mb-2">No tools available</h4>
            <p class="text-gray-600 mb-0">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; margin-bottom: 40px;">
            <?php foreach ($tools as $tool): ?>
            <div class="tool-card group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1" 
                 data-tool-id="<?php echo $tool['id']; ?>">
                <div class="relative overflow-hidden h-40 bg-gray-100">
                    <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($tool['name']); ?>"
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                    <?php if (!empty($tool['demo_video_url'])): ?>
                    <button onclick="openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            class="absolute top-2 left-2 px-3 py-1.5 bg-black/70 hover:bg-black/90 text-white text-xs font-bold rounded-full flex items-center gap-1 transition-all shadow-lg">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Video
                    </button>
                    <?php endif; ?>
                    <?php if ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= $tool['low_stock_threshold'] && $tool['stock_quantity'] > 0): ?>
                    <div class="absolute top-2 right-2 px-2 py-1 bg-yellow-500 text-white text-xs font-bold rounded">
                        Limited Stock
                    </div>
                    <?php elseif ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= 0): ?>
                    <div class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white text-xs font-bold rounded">
                        Out of Stock
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-sm font-bold text-gray-900 flex-1 pr-2"><?php echo htmlspecialchars($tool['name']); ?></h3>
                        <?php if (!empty($tool['category'])): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 shrink-0">
                            <?php echo htmlspecialchars($tool['category']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tool['short_description'])): ?>
                    <p class="text-gray-600 text-xs mb-3 line-clamp-2"><?php echo htmlspecialchars($tool['short_description']); ?></p>
                    <?php endif; ?>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                            <span class="text-lg font-extrabold text-primary-600"><?php echo formatCurrency($tool['price']); ?></span>
                        </div>
                        <button data-tool-id="<?php echo $tool['id']; ?>" 
                                class="tool-preview-btn inline-flex items-center justify-center px-4 py-2 border-2 border-primary-600 text-xs font-semibold rounded-lg text-primary-600 bg-white hover:bg-primary-50 transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Preview
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php renderPagination($totalPages, $page, 'tools', $currentCategory, $affiliateCode); ?>
    <?php endif;
}

function renderPagination($totalPages, $page, $view, $category, $affiliateCode) {
    if ($totalPages <= 1) return;
    ?>
    <div class="mt-12 flex justify-center">
        <nav class="flex items-center gap-2">
            <?php if ($page > 1): ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo ($page - 1); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Previous
            </a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               class="<?php echo $i === $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-medium border border-gray-300 rounded-lg transition-colors">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo ($page + 1); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Next
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="mt-4 text-center">
        <p class="text-sm text-gray-600">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </p>
    </div>
    <?php
}
