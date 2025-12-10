<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/access_log.php';

header('Content-Type: application/json');

$startTime = microtime(true);
$action = $_GET['action'] ?? '';

if ($action === 'load_view') {
    $view = $_GET['view'] ?? 'templates';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $category = $_GET['category'] ?? null;
    $affiliateCode = $_GET['aff'] ?? '';
    
    // Try cache for templates list
    if ($view === 'templates') {
        $cacheKey = 'templates_list_' . $page . '_' . ($category ?? 'all');
        $cachedResponse = ProductCache::get($cacheKey);
        if ($cachedResponse !== null) {
            echo json_encode($cachedResponse);
            $duration = (microtime(true) - $startTime) * 1000;
            logApiAccess('/api/ajax-products.php?action=load_view&view=templates&page=' . $page, 'GET', 200, $duration);
            rotateAccessLogs();
            exit;
        }
    }
    
    // Try cache for tools list
    if ($view === 'tools') {
        $cacheKey = 'tools_list_ajax_' . $page . '_' . ($category ?? 'all');
        $cachedResponse = ProductCache::get($cacheKey);
        if ($cachedResponse !== null) {
            echo json_encode($cachedResponse);
            $duration = (microtime(true) - $startTime) * 1000;
            logApiAccess('/api/ajax-products.php?action=load_view&view=tools&page=' . $page, 'GET', 200, $duration);
            rotateAccessLogs();
            exit;
        }
    }
    
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
        
        $stmt = $db->prepare("SELECT id, name, category, price, thumbnail_url, demo_url, demo_video_url, preview_youtube, media_type, description, slug, priority_order, created_at FROM templates $sqlWhere ORDER BY CASE WHEN priority_order IS NOT NULL THEN priority_order ELSE 999 END, created_at DESC LIMIT ? OFFSET ?");
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
        $allTools = getTools(true, $category, null, null, true);
        
        // Sort by priority first (null=999), then by date
        usort($allTools, function($a, $b) {
            $aPriority = ($a['priority_order'] !== null && $a['priority_order'] !== '') ? intval($a['priority_order']) : 999;
            $bPriority = ($b['priority_order'] !== null && $b['priority_order'] !== '') ? intval($b['priority_order']) : 999;
            if ($aPriority != $bPriority) {
                return $aPriority <=> $bPriority;
            }
            $aDate = strtotime($a['created_at'] ?? '0');
            $bDate = strtotime($b['created_at'] ?? '0');
            return $bDate <=> $aDate;
        });
        
        $totalTools = count($allTools);
        $totalPages = max(1, ceil($totalTools / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $tools = array_slice($allTools, $offset, $perPage);
        $toolCategories = getToolCategories();
        
        // Render tools grid
        renderToolsGrid($tools, $toolCategories, $totalTools, $totalPages, $page, $category, $affiliateCode);
    }
    
    $html = ob_get_clean();
    
    $response = [
        'success' => true,
        'html' => $html,
        'view' => $view,
        'page' => $page
    ];
    
    // Cache the response
    if ($view === 'templates') {
        $cacheKey = 'templates_list_' . $page . '_' . ($category ?? 'all');
        ProductCache::set($cacheKey, $response);
    } elseif ($view === 'tools') {
        $cacheKey = 'tools_list_ajax_' . $page . '_' . ($category ?? 'all');
        ProductCache::set($cacheKey, $response);
    }
    
    echo json_encode($response);
    
    // Log API access
    $duration = (microtime(true) - $startTime) * 1000;
    logApiAccess('/api/ajax-products.php?action=load_view&view=' . $view . '&page=' . $page, 'GET', 200, $duration);
    rotateAccessLogs();
    exit;
}

function renderTemplatesGrid($templates, $templateCategories, $totalTemplates, $totalPages, $page, $currentCategory, $affiliateCode) {
    if (empty($templates)): ?>
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 16px; padding: 48px; text-align: center;">
            <svg style="width: 64px; height: 64px; margin: 0 auto 16px; color: #60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 style="font-size: 20px; font-weight: bold; color: #ffffff; margin-bottom: 8px;">No templates available</h4>
            <p style="color: #d1d5db; margin: 0;">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-6 mb-10" data-templates-grid>
            <?php foreach ($templates as $idx => $template): ?>
            <div style="background: #1f2937; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #374151; transition: all 0.3s ease;">
                <div style="position: relative; overflow: hidden; height: 192px; background: #111827;">
                    <img <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                         src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($template['name']); ?>"
                         width="1280" height="720"
                         style="width: 100%; height: 100%; object-fit: cover; transition: all 0.3s ease;"
                         onerror="this.src='/assets/images/placeholder.jpg'"
                         decoding="async">
                    <?php 
                    $mediaType = $template['media_type'] ?? 'banner';
                    $hasDemo = !empty($template['demo_url']) || !empty($template['demo_video_url']) || !empty($template['preview_youtube']);
                    $isYoutube = ($mediaType === 'youtube' && !empty($template['preview_youtube']));
                    $isVideo = ($mediaType === 'video' && !empty($template['demo_video_url']));
                    $isDemoUrl = ($mediaType === 'demo_url' && !empty($template['demo_url']));
                    
                    if ($hasDemo):
                    ?>
                    <?php if ($isYoutube): ?>
                    <button onclick="event.stopPropagation(); openYoutubeModal('<?php echo htmlspecialchars($template['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; right: 8px; padding: 6px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; cursor: pointer; z-index: 10; transition: background 0.2s;">
                        <svg style="width: 16px; height: 16px; display: inline; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php elseif ($isVideo): ?>
                    <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($template['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; right: 8px; padding: 6px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; cursor: pointer; z-index: 10; transition: background 0.2s;">
                        <svg style="width: 16px; height: 16px; display: inline; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php elseif ($isDemoUrl): ?>
                    <button onclick="event.stopPropagation(); openDemoFullscreen('<?php echo htmlspecialchars($template['demo_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; right: 8px; padding: 6px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: 600; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; cursor: pointer; z-index: 10; transition: background 0.2s;">
                        <svg style="width: 16px; height: 16px; display: inline; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div style="padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h3 style="font-size: 16px; font-weight: bold; color: #ffffff; flex: 1; padding-right: 8px;"><?php echo htmlspecialchars($template['name']); ?></h3>
                        <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #1e3a8a; color: #93c5fd; white-space: nowrap;">
                            <?php echo htmlspecialchars($template['category']); ?>
                        </span>
                    </div>
                    <p style="color: #d1d5db; font-size: 12px; margin-bottom: 12px; min-height: 32px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #374151;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Price</span>
                            <span style="font-size: 18px; font-weight: 800; color: #2563eb;"><?php echo formatCurrency($template['price']); ?></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                               style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border: 1px solid #4b5563; font-size: 12px; font-weight: 500; border-radius: 6px; color: #d1d5db; background: #1f2937; cursor: pointer; white-space: nowrap; text-decoration: none; transition: background 0.2s;">
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
    if (empty($tools)): ?>
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 16px; padding: 48px; text-align: center;">
            <svg style="width: 64px; height: 64px; margin: 0 auto 16px; color: #60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h4 style="font-size: 20px; font-weight: bold; color: #ffffff; margin-bottom: 8px;">No tools available</h4>
            <p style="color: #d1d5db; margin: 0;">Please check back later or contact us on WhatsApp.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-6 mb-10" data-tools-grid>
            <?php foreach ($tools as $idx => $tool): ?>
            <div style="background: #1f2937; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #374151; transition: all 0.3s ease;" data-tool-id="<?php echo $tool['id']; ?>">
                <div style="position: relative; overflow: hidden; height: 160px; background: #111827;">
                    <img <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                         src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($tool['name']); ?>"
                         width="1280" height="720"
                         style="width: 100%; height: 100%; object-fit: cover; transition: all 0.3s ease;"
                         onerror="this.src='/assets/images/placeholder.jpg'"
                         decoding="async">
                    <?php 
                    $toolMediaType = $tool['media_type'] ?? 'banner';
                    if ($toolMediaType === 'youtube' && !empty($tool['preview_youtube'])): 
                    ?>
                    <button onclick="openYoutubeModal('<?php echo htmlspecialchars($tool['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; left: 8px; padding: 8px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: bold; border-radius: 20px; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php elseif ($toolMediaType === 'video' && !empty($tool['demo_video_url'])): ?>
                    <button onclick="openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            style="position: absolute; top: 8px; left: 8px; padding: 8px 12px; background: #2563eb; color: white; font-size: 12px; font-weight: bold; border-radius: 20px; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
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
                        <h3 style="font-size: 14px; font-weight: bold; color: #ffffff; flex: 1; padding-right: 8px;"><?php echo htmlspecialchars($tool['name']); ?></h3>
                        <?php if (!empty($tool['category'])): ?>
                        <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #064e3b; color: #86efac; white-space: nowrap;">
                            <?php echo htmlspecialchars($tool['category']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #d1d5db; font-size: 12px; margin-bottom: 12px; min-height: 32px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($tool['short_description'] ?? ''); ?></p>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #374151;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Price</span>
                            <span style="font-size: 18px; font-weight: 800; color: #2563eb;"><?php echo formatCurrency($tool['price']); ?></span>
                        </div>
                        <button data-tool-id="<?php echo $tool['id']; ?>" 
                                class="tool-preview-btn"
                                style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border: 2px solid #2563eb; font-size: 12px; font-weight: 600; border-radius: 8px; color: #2563eb; background: #1f2937; cursor: pointer; white-space: nowrap; transition: all 0.2s;">
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
               style="display: inline-flex; align-items: center; padding: 8px 16px; font-size: 14px; font-weight: 500; color: #d1d5db; background: #1f2937; border: 1px solid #4b5563; border-radius: 8px; cursor: pointer; text-decoration: none; transition: background 0.2s;">
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
               style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; font-size: 14px; font-weight: 500; border: 1px solid #4b5563; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all 0.2s; <?php echo $i === $page ? 'background: #2563eb; color: white; border-color: #2563eb;' : 'background: #1f2937; color: #d1d5db;'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?view=<?php echo $view; ?>&page=<?php echo ($page + 1); ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
               style="display: inline-flex; align-items: center; padding: 8px 16px; font-size: 14px; font-weight: 500; color: #d1d5db; background: #1f2937; border: 1px solid #4b5563; border-radius: 8px; cursor: pointer; text-decoration: none; transition: background 0.2s;">
                Next
                <svg style="width: 16px; height: 16px; margin-left: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <div style="margin-top: 16px; text-align: center;">
        <p style="font-size: 14px; color: #d1d5db; margin: 0;">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </p>
    </div>
    <?php
}
