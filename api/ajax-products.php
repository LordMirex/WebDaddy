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
        $perPage = 18;
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
    <div style="margin-bottom: 24px; max-width: 36rem; margin-left: auto; margin-right: auto;">
        <div style="position: relative;">
            <select id="templates-category-filter" 
                    style="width: 100%; padding: 12px 16px 12px 44px; border: 2px solid #d1d5db; border-radius: 8px; appearance: none; background: white; color: #111827; font-weight: 500; cursor: pointer; font-size: 14px;">
                <option value="" <?php echo empty($currentCategory) ? 'selected' : ''; ?>>
                    All Categories
                </option>
                <?php foreach ($templateCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($currentCategory === $cat) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <svg style="width: 20px; height: 20px; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg style="width: 20px; height: 20px; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>
    <?php endif;
    
    if (empty($templates)): ?>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px; padding: 48px; text-align: center;">
            <svg style="width: 64px; height: 64px; margin: 0 auto 16px; color: #60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 style="font-size: 20px; font-weight: bold; color: #111827; margin-bottom: 8px;">No templates available</h4>
            <p style="color: #4b5563; margin: 0;">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px;">
            <?php foreach ($templates as $template): ?>
            <div style="background: white; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                <div style="position: relative; overflow: hidden; height: 192px; background: #f3f4f6;">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($template['name']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;"
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
                            style="position: absolute; top: 8px; right: 8px; padding: 6px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; cursor: pointer; z-index: 10; transition: background 0.2s;">
                        <svg style="width: 16px; height: 16px; display: inline; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Video
                    </button>
                    <?php else: ?>
                    <button onclick="event.stopPropagation(); openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; right: 8px; padding: 6px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; cursor: pointer; z-index: 10; transition: background 0.2s;">
                        Preview
                    </button>
                    <?php endif; ?>
                    <?php if ($isVideo): ?>
                    <button onclick="openVideoModal('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            data-video-trigger
                            data-video-url="<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>"
                            data-video-title="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>"
                            style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s; border: none; cursor: pointer; padding: 0;">
                        <span style="display: inline-flex; align-items: center; padding: 12px 16px; background: white; color: #111827; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <svg style="width: 20px; height: 20px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Watch Demo
                        </span>
                    </button>
                    <?php else: ?>
                    <button onclick="openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')" 
                            style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s; border: none; cursor: pointer; padding: 0;">
                        <span style="display: inline-flex; align-items: center; padding: 12px 16px; background: white; color: #111827; border-radius: 8px; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <svg style="width: 20px; height: 20px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Click to Preview
                        </span>
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div style="padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #111827; flex: 1; padding-right: 8px;"><?php echo htmlspecialchars($template['name']); ?></h3>
                        <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #dbeafe; color: #1e40af; white-space: nowrap;">
                            <?php echo htmlspecialchars($template['category']); ?>
                        </span>
                    </div>
                    <p style="color: #4b5563; font-size: 12px; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Price</span>
                            <span style="font-size: 18px; font-weight: 800; color: #2563eb;"><?php echo formatCurrency($template['price']); ?></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                               style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border: 1px solid #d1d5db; font-size: 12px; font-weight: 500; border-radius: 6px; color: #374151; background: white; cursor: pointer; white-space: nowrap; text-decoration: none; transition: background 0.2s;">
                                Details
                            </a>
                            <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '', this)" 
                               style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border: none; font-size: 12px; font-weight: 500; border-radius: 6px; color: white; background: #2563eb; cursor: pointer; white-space: nowrap; transition: background 0.2s;">
                                <svg style="width: 14px; height: 14px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <div style="margin-bottom: 24px; max-width: 36rem; margin-left: auto; margin-right: auto;">
        <div style="position: relative;">
            <select id="tools-category-filter" 
                    style="width: 100%; padding: 12px 16px 12px 44px; border: 2px solid #d1d5db; border-radius: 8px; appearance: none; background: white; color: #111827; font-weight: 500; cursor: pointer; font-size: 14px;">
                <option value="" <?php echo empty($currentCategory) ? 'selected' : ''; ?>>
                    All Categories
                </option>
                <?php foreach ($toolCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($currentCategory === $cat) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <svg style="width: 20px; height: 20px; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg style="width: 20px; height: 20px; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>
    <?php endif;
    
    if (empty($tools)): ?>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px; padding: 48px; text-align: center;">
            <svg style="width: 64px; height: 64px; margin: 0 auto 16px; color: #60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 style="font-size: 20px; font-weight: bold; color: #111827; margin-bottom: 8px;">No tools available</h4>
            <p style="color: #4b5563; margin: 0;">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; margin-bottom: 40px;">
            <?php foreach ($tools as $tool): ?>
            <div style="background: white; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb; transition: all 0.3s ease;" data-tool-id="<?php echo $tool['id']; ?>">
                <div style="position: relative; overflow: hidden; height: 160px; background: #f3f4f6;">
                    <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($tool['name']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                    <?php if (!empty($tool['demo_video_url'])): ?>
                    <button onclick="openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; left: 8px; padding: 8px 12px; background: rgba(0,0,0,0.7); color: white; font-size: 12px; font-weight: bold; border-radius: 20px; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;">
                        <svg style="width: 14px; height: 14px;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Video
                    </button>
                    <?php endif; ?>
                    <?php if ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= $tool['low_stock_threshold'] && $tool['stock_quantity'] > 0): ?>
                    <div style="position: absolute; top: 8px; right: 8px; padding: 4px 8px; background: #eab308; color: white; font-size: 12px; font-weight: bold; border-radius: 4px;">
                        Limited Stock
                    </div>
                    <?php elseif ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= 0): ?>
                    <div style="position: absolute; top: 8px; right: 8px; padding: 4px 8px; background: #ef4444; color: white; font-size: 12px; font-weight: bold; border-radius: 4px;">
                        Out of Stock
                    </div>
                    <?php endif; ?>
                </div>
                <div style="padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h3 style="font-size: 14px; font-weight: bold; color: #111827; flex: 1; padding-right: 8px;"><?php echo htmlspecialchars($tool['name']); ?></h3>
                        <?php if (!empty($tool['category'])): ?>
                        <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #dcfce7; color: #166534; white-space: nowrap;">
                            <?php echo htmlspecialchars($tool['category']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tool['short_description'])): ?>
                    <p style="color: #4b5563; font-size: 12px; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($tool['short_description']); ?></p>
                    <?php endif; ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Price</span>
                            <span style="font-size: 18px; font-weight: 800; color: #2563eb;"><?php echo formatCurrency($tool['price']); ?></span>
                        </div>
                        <button data-tool-id="<?php echo $tool['id']; ?>" 
                                class="tool-preview-btn"
                                style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border: 2px solid #2563eb; font-size: 12px; font-weight: 600; border-radius: 8px; color: #2563eb; background: white; cursor: pointer; white-space: nowrap; transition: all 0.2s;">
                            <svg style="width: 14px; height: 14px; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <div style="margin-top: 48px; display: flex; justify-content: center;">
        <nav style="display: flex; align-items: center; gap: 8px;">
            <?php if ($page > 1): ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo ($page - 1); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               style="display: inline-flex; align-items: center; padding: 8px 16px; font-size: 14px; font-weight: 500; color: #374151; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; text-decoration: none; transition: background 0.2s;">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
               style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; font-size: 14px; font-weight: 500; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all 0.2s; <?php echo $i === $page ? 'background: #2563eb; color: white; border-color: #2563eb;' : 'background: white; color: #374151;'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo ($page + 1); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               style="display: inline-flex; align-items: center; padding: 8px 16px; font-size: 14px; font-weight: 500; color: #374151; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; text-decoration: none; transition: background 0.2s;">
                Next
                <svg style="width: 16px; height: 16px; margin-left: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <div style="margin-top: 16px; text-align: center;">
        <p style="font-size: 14px; color: #4b5563; margin: 0;">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </p>
    </div>
    <?php
}
