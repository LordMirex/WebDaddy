<?php
// Set error handling BEFORE any other code
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return true;
});

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/access_log.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Ensure output buffering is clean
while (ob_get_level()) { ob_end_clean(); }

$startTime = microtime(true);
$action = $_GET['action'] ?? '';

if ($action === 'get_categories') {
    $view = $_GET['view'] ?? 'templates';
    try {
        $categories = $view === 'templates' ? getTemplateCategories() : getToolTypes();
        echo json_encode(['success' => true, 'categories' => $categories ?: []]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'categories' => []]);
    }
    exit;
}

if ($action === 'load_view') {
    $view = $_GET['view'] ?? 'templates';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $category = isset($_GET['category']) ? trim(urldecode($_GET['category'])) : null;
    $category = ($category === '') ? null : $category;
    $affiliateCode = $_GET['aff'] ?? '';
    
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
        $sqlWhere = "WHERE active = 1";
        $params = [];
        if ($category) {
            $sqlWhere .= " AND category = ?";
            $params[] = $category;
        }
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM templates $sqlWhere");
        $countStmt->execute($params);
        $totalTemplates = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        $totalPages = max(1, ceil($totalTemplates / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("SELECT id, name, category, price, thumbnail_url, demo_url, demo_video_url, preview_youtube, media_type, description, slug, priority_order, created_at FROM templates $sqlWhere ORDER BY CASE WHEN priority_order IS NOT NULL THEN priority_order ELSE 999 END, created_at DESC LIMIT ? OFFSET ?");
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $templateCategories = getTemplateCategories();
        renderTemplatesGrid($templates, $templateCategories, $totalTemplates, $totalPages, $page, $category, $affiliateCode);
    } else {
        $perPage = 18;
        try {
            $allTools = getToolsByType(true, $category, null, null, true);
            if (!is_array($allTools)) {
                $allTools = [];
            }
            usort($allTools, function($a, $b) {
                $aPriority = ($a['priority_order'] !== null && $a['priority_order'] !== '') ? intval($a['priority_order']) : 999;
                $bPriority = ($b['priority_order'] !== null && $b['priority_order'] !== '') ? intval($b['priority_order']) : 999;
                return $aPriority != $bPriority ? $aPriority <=> $bPriority : strtotime($b['created_at'] ?? '0') <=> strtotime($a['created_at'] ?? '0');
            });
            $totalTools = count($allTools);
            $totalPages = max(1, ceil($totalTools / $perPage));
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;
            $tools = array_slice($allTools, $offset, $perPage);
            $toolCategories = getToolTypes();
            renderToolsGrid($tools, $toolCategories, $totalTools, $totalPages, $page, $category, $affiliateCode);
        } catch (Exception $e) {
            error_log("Tools load error: " . $e->getMessage());
            echo '<div class="col-span-full text-center py-12"><p class="text-red-400">Error loading tools. Please refresh.</p></div>';
        }
    }
    
    $html = ob_get_clean();
    $categories = $view === 'templates' ? getTemplateCategories() : getToolTypes();
    
    if (!$html) {
        $html = '<div class="col-span-full text-center py-12"><p class="text-gray-400">No items found.</p></div>';
    }
    
    // Inject fixImagePath function if not already available
    $scriptInject = '<script>
    if (!window.fixImagePath) {
        window.fixImagePath = function(img) {
            if (!img || img.dataset.fixed) return;
            const originalSrc = img.src;
            if (originalSrc.includes("placeholder.jpg")) {
                img.dataset.fixed = "true";
                return;
            }
            const assetsIdx = originalSrc.indexOf("/assets/");
            const uploadsIdx = originalSrc.indexOf("/uploads/");
            if (assetsIdx !== -1 || uploadsIdx !== -1) {
                const rootPath = assetsIdx !== -1 
                    ? originalSrc.substring(assetsIdx) 
                    : originalSrc.substring(uploadsIdx);
                if (new URL(img.src).pathname !== rootPath) {
                    img.src = rootPath;
                    img.dataset.fixed = "true";
                }
            }
        };
    }
    </script>';
    
    $html = $scriptInject . $html;
    $response = [
        'success' => true,
        'html' => $html,
        'view' => $view,
        'page' => (int)$page,
        'categories' => is_array($categories) ? $categories : []
    ];
    echo json_encode($response);
    exit;
}

function renderTemplatesGrid($templates, $templateCategories, $totalTemplates, $totalPages, $page, $currentCategory, $affiliateCode) {
    if (empty($templates)): 
        $whatsappNum = defined('WHATSAPP_NUMBER') ? preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) : '2349132672126';
        $messageText = "Hi! I'm looking for a website template but couldn't find what I need. Can you help me find or create a custom solution?";
        $whatsappUrl = "https://wa.me/{$whatsappNum}?text=" . urlencode($messageText);
    ?>
        <div class="col-span-full">
            <div class="bg-gradient-to-br from-navy via-navy-light to-navy border-2 border-gold/30 rounded-2xl p-8 md:p-12 text-center shadow-lg">
                <div class="mb-6">
                    <span class="text-5xl md:text-6xl inline-block">🎨</span>
                </div>
                <h4 class="text-2xl md:text-3xl font-bold text-white mb-3">No Templates Here Yet</h4>
                <p class="text-gray-300 mb-6 max-w-md mx-auto leading-relaxed">We're working on adding more templates. In the meantime, tell us what you need and we'll create the perfect website for you!</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" 
                       target="_blank"
                       class="inline-flex items-center justify-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Chat on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 mb-10" data-templates-grid>
            <?php foreach ($templates as $idx => $template): ?>
            <div class="template-card group bg-navy-light rounded-lg md:rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1 h-full flex flex-col" x-cloak>
                <div class="relative overflow-hidden h-40 md:h-48 bg-navy">
                    <img <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                         src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($template['name']); ?>"
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         onload="if(window.fixImagePath) fixImagePath(this)"
                         onerror="if(window.fixImagePath) fixImagePath(this); else { this.src='/assets/images/placeholder.jpg'; this.onerror=null; }"
                         decoding="async">
                    <?php 
                    $mediaType = $template['media_type'] ?? 'banner';
                    $hasDemo = !empty($template['demo_url']) || !empty($template['demo_video_url']) || !empty($template['preview_youtube']);
                    $isYoutube = ($mediaType === 'youtube' && !empty($template['preview_youtube']));
                    
                    if ($isYoutube): ?>
                    <button onclick="event.stopPropagation(); openYoutubeModal('<?php echo htmlspecialchars($template['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm z-10">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        Preview
                    </button>
                    <?php elseif ($mediaType === 'video' && !empty($template['demo_video_url'])): ?>
                    <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($template['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                            class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm z-10">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php endif; ?>
                </div>
                <div class="p-3 md:p-4 flex-1 flex flex-col">
                    <div class="flex justify-between items-start mb-1 md:mb-2">
                        <h3 class="text-xs md:text-sm font-bold text-white flex-1 pr-2 line-clamp-1"><?php echo htmlspecialchars($template['name']); ?></h3>
                        <?php if (!empty($template['category'])): ?>
                        <span class="inline-flex items-center px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-gold/20 text-gold shrink-0">
                            <?php echo htmlspecialchars($template['category']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-400 text-[11px] md:text-xs mb-2 md:mb-3 line-clamp-2 min-h-[24px] md:min-h-[32px] flex-1"><?php echo htmlspecialchars($template['short_description'] ?? ''); ?></p>
                    <div class="flex items-center justify-between pt-2 md:pt-3 border-t border-gray-700/50 mt-auto">
                        <div class="flex flex-col">
                            <span class="text-[8px] md:text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                            <span class="text-base md:text-lg font-extrabold text-gold"><?php echo formatCurrency($template['price']); ?></span>
                        </div>
                        <div class="flex gap-1.5">
                            <button onclick="openTemplateDetails('<?php echo htmlspecialchars($template['slug'], ENT_QUOTES); ?>')" 
                                    class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 border border-gray-600 text-[10px] md:text-xs font-semibold rounded-md md:rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-all whitespace-nowrap">
                                Details
                            </button>
                            <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '', this)"
                                    class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 bg-gold text-navy text-[10px] md:text-xs font-bold rounded-md md:rounded-lg hover:bg-yellow-400 transition-all shadow-md shadow-gold/10 whitespace-nowrap">
                                <svg class="w-3 h-3 md:w-3.5 md:h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                Add
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
    if (empty($tools)): 
        $whatsappNum = defined('WHATSAPP_NUMBER') ? preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) : '2349132672126';
        $messageText = "Hi! I'm looking for a digital tool but couldn't find what I need. Can you help me find or create a custom solution?";
        $whatsappUrl = "https://wa.me/{$whatsappNum}?text=" . urlencode($messageText);
    ?>
        <div class="col-span-full">
            <div class="bg-gradient-to-br from-navy via-navy-light to-navy border-2 border-gold/30 rounded-2xl p-8 md:p-12 text-center shadow-lg">
                <div class="mb-6">
                    <span class="text-5xl md:text-6xl inline-block">🛠️</span>
                </div>
                <h4 class="text-2xl md:text-3xl font-bold text-white mb-3">Tools Coming Soon!</h4>
                <p class="text-gray-300 mb-6 max-w-md mx-auto leading-relaxed">We're preparing amazing digital tools for you. Tell us what you need and we'll prioritize building it or find a custom solution!</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" 
                       target="_blank"
                       class="inline-flex items-center justify-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Chat on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 mb-10" data-tools-grid>
            <?php foreach ($tools as $idx => $tool): ?>
            <div class="template-card group bg-navy-light rounded-lg md:rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1 h-full flex flex-col" data-tool-id="<?php echo $tool['id']; ?>" x-cloak>
                <div class="relative overflow-hidden h-40 md:h-48 bg-navy">
                    <img <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                         src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($tool['name']); ?>"
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         onload="if(window.fixImagePath) fixImagePath(this)"
                         onerror="if(window.fixImagePath) fixImagePath(this); else { this.src='/assets/images/placeholder.jpg'; this.onerror=null; }"
                         decoding="async">
                    <?php 
                    $toolMediaType = $tool['media_type'] ?? 'banner';
                    if ($toolMediaType === 'youtube' && !empty($tool['preview_youtube'])): 
                    ?>
                    <button onclick="event.stopPropagation(); openYoutubeModal('<?php echo htmlspecialchars($tool['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm z-10">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php elseif ($toolMediaType === 'video' && !empty($tool['demo_video_url'])): ?>
                    <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                            class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm z-10">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <?php endif; ?>
                    <?php if ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= $tool['low_stock_threshold'] && $tool['stock_quantity'] > 0): ?>
                    <div class="absolute top-2 right-2 px-2 py-1 bg-yellow-500 text-white text-[10px] font-bold rounded">Limited Stock</div>
                    <?php elseif ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= 0): ?>
                    <div class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white text-[10px] font-bold rounded">Out of Stock</div>
                    <?php endif; ?>
                </div>
                <div class="p-3 md:p-4 flex-1 flex flex-col">
                    <div class="flex justify-between items-start mb-1 md:mb-2">
                        <h3 class="text-xs md:text-sm font-bold text-white flex-1 pr-2 line-clamp-1"><?php echo htmlspecialchars($tool['name']); ?></h3>
                        <?php if (!empty($tool['category'])): ?>
                        <span class="inline-flex items-center px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-gold/20 text-gold shrink-0"><?php echo htmlspecialchars($tool['category']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-400 text-[11px] md:text-xs mb-2 md:mb-3 line-clamp-2 min-h-[24px] md:min-h-[32px] flex-1"><?php echo htmlspecialchars($tool['short_description'] ?? ''); ?></p>
                    <div class="flex items-center justify-between pt-2 md:pt-3 border-t border-gray-700/50 mt-auto">
                        <div class="flex flex-col">
                            <span class="text-[8px] md:text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                            <span class="text-base md:text-lg font-extrabold text-gold"><?php echo formatCurrency($tool['price']); ?></span>
                        </div>
                        <div class="flex gap-1.5">
                            <button onclick="openToolModal(<?php echo $tool['id']; ?>)" class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 border border-gray-600 text-[10px] md:text-xs font-semibold rounded-md md:rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-all whitespace-nowrap">Details</button>
                            <button onclick="addToolToCart(<?php echo $tool['id']; ?>, '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>', this)" class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 bg-gold text-navy text-[10px] md:text-xs font-bold rounded-md md:rounded-lg hover:bg-yellow-400 transition-all shadow-md shadow-gold/10 whitespace-nowrap">Add</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php renderPagination($totalPages, $page, 'tools', $currentCategory, $affiliateCode); ?>
    <?php endif;
}

function renderPagination($totalPages, $page, $view, $category, $affiliateCode) {
    if ($totalPages <= 1) return; ?>
    <div class="mt-6 md:mt-12 flex flex-col items-center gap-2 md:gap-4">
        <nav class="flex items-center gap-1 md:gap-2">
            <?php
            $params = ['view' => $view];
            if ($affiliateCode) $params['aff'] = $affiliateCode;
            if ($category) $params['category'] = $category;
            
            if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($params, ['page' => $page - 1])); ?>#products" 
               class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                <svg class="w-3 h-3 md:w-4 md:h-4 mr-1 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Prev
            </a>
            <?php endif;
            
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($params, ['page' => $i])); ?>#products" 
               class="<?php echo $i === $page ? 'bg-gold text-navy font-bold' : 'bg-navy-light text-gray-300 border border-gray-700 hover:bg-gold/20 hover:text-gold'; ?> inline-flex items-center justify-center w-8 h-8 md:w-10 md:h-10 text-xs md:text-sm font-semibold rounded-md md:rounded-lg transition-all">
                <?php echo $i; ?>
            </a>
            <?php endfor;
            
            if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($params, ['page' => $page + 1])); ?>#products" 
               class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                Next
                <svg class="w-3 h-3 md:w-4 md:h-4 ml-1 md:ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php endif; ?>
        </nav>
    </div>
<?php }
