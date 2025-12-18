<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/blog/Blog.php';
require_once __DIR__ . '/../../includes/blog/BlogPost.php';
require_once __DIR__ . '/../../includes/blog/BlogCategory.php';
require_once __DIR__ . '/../../includes/blog/BlogTag.php';
require_once __DIR__ . '/../../includes/blog/BlogBlock.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);

startSecureSession();

$db = getDb();
$blogPost = new BlogPost($db);
$blogCategory = new BlogCategory($db);
$blogTag = new BlogTag($db);
$blogBlock = new BlogBlock($db);

$postId = $_GET['post_id'] ?? null;
$isNew = !$postId;
$post = null;
$blocks = [];

if ($postId) {
    $post = $blogPost->getById($postId);
    if (!$post) {
        header('Location: index.php');
        exit;
    }
    $blocks = $blogBlock->getByPost($postId, false);
}

$categories = $blogCategory->getAll();
$allTags = $blogTag->getAll();
$postTags = $postId ? $blogTag->getByPost($postId) : [];
$postTagIds = array_column($postTags, 'id');

$pageTitle = $isNew ? 'Create New Post' : 'Edit: ' . ($post['title'] ?? 'Untitled');

$blockTypes = [
    'hero_editorial' => ['name' => 'Hero Editorial', 'icon' => '📰', 'desc' => 'Article header with title, image, author'],
    'rich_text' => ['name' => 'Rich Text', 'icon' => '📝', 'desc' => 'Main content with formatted text'],
    'section_divider' => ['name' => 'Section Divider', 'icon' => '➖', 'desc' => 'Visual break between sections'],
    'visual_explanation' => ['name' => 'Visual Explanation', 'icon' => '🖼️', 'desc' => 'Text with image side-by-side'],
    'inline_conversion' => ['name' => 'Inline Conversion', 'icon' => '💰', 'desc' => 'Mid-article CTA block'],
    'internal_authority' => ['name' => 'Internal Authority', 'icon' => '🔗', 'desc' => 'Related posts and links'],
    'faq_seo' => ['name' => 'FAQ SEO', 'icon' => '❓', 'desc' => 'FAQ section with schema markup'],
    'final_conversion' => ['name' => 'Final Conversion', 'icon' => '🎯', 'desc' => 'End-of-article CTA']
];

$layoutVariants = ['default', 'split_left', 'split_right', 'wide', 'contained', 'card_grid', 'timeline'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - WebDaddy Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; }
        
        .editor-header { background: white; border-bottom: 1px solid #e1e8ed; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .editor-header h1 { font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .editor-header h1 a { color: #666; text-decoration: none; }
        .editor-header h1 span { color: #ccc; }
        .header-actions { display: flex; gap: 10px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-primary:hover { background: #0052a3; }
        .btn-secondary { background: #e1e8ed; color: #333; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-ghost { background: transparent; color: #666; }
        .btn-ghost:hover { background: #f0f0f0; }
        
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 0; min-height: calc(100vh - 60px); }
        @media (max-width: 1024px) { .editor-layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
        
        .main-content { padding: 30px; background: #f5f7fa; overflow-y: auto; }
        .sidebar { background: white; border-left: 1px solid #e1e8ed; padding: 20px; overflow-y: auto; }
        
        .blocks-container { max-width: 800px; margin: 0 auto; }
        
        .block-item { background: white; border: 1px solid #e1e8ed; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s; }
        .block-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .block-item.dragging { opacity: 0.5; border: 2px dashed #0066cc; }
        
        .block-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: #f9fafb; border-bottom: 1px solid #e1e8ed; border-radius: 8px 8px 0 0; cursor: move; }
        .block-type { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; }
        .block-type-icon { font-size: 18px; }
        .block-actions { display: flex; gap: 5px; }
        .block-actions button { padding: 5px 8px; border: none; background: transparent; cursor: pointer; border-radius: 4px; font-size: 12px; color: #666; transition: all 0.2s; }
        .block-actions button:hover { background: #e1e8ed; }
        .block-actions button.delete:hover { background: #ffe0e6; color: #dc3545; }
        
        .block-preview { padding: 15px; font-size: 14px; color: #666; min-height: 60px; }
        .block-preview-title { font-weight: 600; color: #333; margin-bottom: 5px; }
        .block-preview-content { overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        
        .add-block-area { background: white; border: 2px dashed #ddd; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .add-block-area:hover { border-color: #0066cc; background: #f8fafc; }
        .add-block-area h3 { font-size: 16px; margin-bottom: 5px; color: #333; }
        .add-block-area p { font-size: 13px; color: #999; }
        
        .block-palette { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .block-palette.open { display: flex; }
        .block-palette-content { background: white; border-radius: 12px; padding: 30px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .block-palette-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e1e8ed; }
        .block-palette-header h2 { font-size: 20px; }
        .block-palette-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .block-option { padding: 15px; border: 1px solid #e1e8ed; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .block-option:hover { border-color: #0066cc; background: #f8fafc; }
        .block-option-icon { font-size: 24px; margin-bottom: 8px; }
        .block-option-name { font-weight: 600; margin-bottom: 4px; }
        .block-option-desc { font-size: 12px; color: #666; }
        
        .sidebar-section { margin-bottom: 25px; }
        .sidebar-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #666; margin-bottom: 12px; letter-spacing: 0.5px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,0.1); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-hint { font-size: 11px; color: #999; margin-top: 4px; }
        
        .tag-input-container { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px; border: 1px solid #ddd; border-radius: 6px; min-height: 42px; }
        .tag-badge { background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 12px; font-size: 12px; display: flex; align-items: center; gap: 5px; }
        .tag-badge button { background: none; border: none; cursor: pointer; color: #1565c0; font-weight: bold; padding: 0; }
        .tag-input-container select { border: none; padding: 5px; flex: 1; min-width: 120px; }
        .tag-input-container select:focus { outline: none; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-draft { background: #e2e3e5; color: #666; }
        .status-published { background: #d4edda; color: #155724; }
        .status-scheduled { background: #fff3cd; color: #856404; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        .modal.open { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 100%; max-width: 700px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid #e1e8ed; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #e1e8ed; display: flex; justify-content: flex-end; gap: 10px; }
        
        .empty-blocks { text-align: center; padding: 60px 20px; }
        .empty-blocks h3 { margin-bottom: 10px; color: #666; }
        .empty-blocks p { color: #999; margin-bottom: 20px; }
        
        .loading { opacity: 0.6; pointer-events: none; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 1px solid #e1e8ed; }
        .tab { padding: 10px 16px; border: none; background: transparent; cursor: pointer; font-size: 14px; color: #666; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .tab.active { color: #0066cc; border-bottom-color: #0066cc; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="editor-header">
        <h1>
            <a href="index.php">Blog</a>
            <span>/</span>
            <?php echo htmlspecialchars($isNew ? 'New Post' : $post['title']); ?>
        </h1>
        <div class="header-actions">
            <button type="button" class="btn btn-secondary" onclick="previewPost()">Preview</button>
            <button type="button" class="btn btn-secondary" id="saveDraftBtn" onclick="savePost('draft')">Save Draft</button>
            <button type="button" class="btn btn-success" id="publishBtn" onclick="savePost('published')">Publish</button>
        </div>
    </div>
    
    <div class="editor-layout">
        <div class="main-content">
            <div id="alertContainer"></div>
            
            <div class="blocks-container">
                <!-- Post Title -->
                <div style="margin-bottom: 30px;">
                    <input type="text" id="postTitle" placeholder="Enter post title..." 
                        value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>"
                        style="width: 100%; padding: 15px; font-size: 28px; font-weight: 700; border: none; border-bottom: 2px solid #e1e8ed; background: transparent;">
                </div>
                
                <!-- Blocks List -->
                <div id="blocksList">
                    <?php if (empty($blocks)): ?>
                        <div class="empty-blocks" id="emptyBlocks">
                            <h3>No blocks yet</h3>
                            <p>Start building your post by adding blocks</p>
                            <button class="btn btn-primary" onclick="openBlockPalette()">+ Add First Block</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($blocks as $block): ?>
                            <div class="block-item" data-block-id="<?php echo $block['id']; ?>" data-block-type="<?php echo htmlspecialchars($block['block_type']); ?>" draggable="true">
                                <div class="block-header">
                                    <div class="block-type">
                                        <span class="block-type-icon"><?php echo $blockTypes[$block['block_type']]['icon'] ?? '📦'; ?></span>
                                        <span><?php echo $blockTypes[$block['block_type']]['name'] ?? $block['block_type']; ?></span>
                                    </div>
                                    <div class="block-actions">
                                        <button onclick="editBlock(<?php echo $block['id']; ?>)" title="Edit">✏️</button>
                                        <button onclick="duplicateBlock(<?php echo $block['id']; ?>)" title="Duplicate">📋</button>
                                        <button onclick="moveBlockUp(<?php echo $block['id']; ?>)" title="Move Up">⬆️</button>
                                        <button onclick="moveBlockDown(<?php echo $block['id']; ?>)" title="Move Down">⬇️</button>
                                        <button class="delete" onclick="deleteBlock(<?php echo $block['id']; ?>)" title="Delete">🗑️</button>
                                    </div>
                                </div>
                                <div class="block-preview" onclick="editBlock(<?php echo $block['id']; ?>)">
                                    <?php 
                                    $data = $block['data_payload'];
                                    $preview = '';
                                    switch ($block['block_type']) {
                                        case 'hero_editorial':
                                            $preview = '<div class="block-preview-title">' . htmlspecialchars($data['h1_title'] ?? 'Untitled') . '</div>';
                                            if (!empty($data['subtitle'])) {
                                                $preview .= '<div class="block-preview-content">' . htmlspecialchars($data['subtitle']) . '</div>';
                                            }
                                            break;
                                        case 'rich_text':
                                            $content = strip_tags($data['content'] ?? '');
                                            $preview = '<div class="block-preview-content">' . htmlspecialchars(substr($content, 0, 150)) . '...</div>';
                                            break;
                                        case 'section_divider':
                                            $preview = '<div style="text-align:center;color:#999;">— ' . ucfirst($data['divider_type'] ?? 'line') . ' Divider —</div>';
                                            break;
                                        case 'visual_explanation':
                                            $preview = '<div class="block-preview-title">' . htmlspecialchars($data['heading'] ?? 'Heading') . '</div>';
                                            $preview .= '<div class="block-preview-content">' . htmlspecialchars(strip_tags(substr($data['content'] ?? '', 0, 100))) . '</div>';
                                            break;
                                        case 'inline_conversion':
                                        case 'final_conversion':
                                            $preview = '<div class="block-preview-title">💰 ' . htmlspecialchars($data['headline'] ?? 'CTA Block') . '</div>';
                                            break;
                                        case 'internal_authority':
                                            $preview = '<div class="block-preview-title">🔗 ' . htmlspecialchars($data['heading'] ?? 'Related Content') . '</div>';
                                            break;
                                        case 'faq_seo':
                                            $count = count($data['items'] ?? []);
                                            $preview = '<div class="block-preview-title">❓ ' . htmlspecialchars($data['heading'] ?? 'FAQ') . '</div>';
                                            $preview .= '<div class="block-preview-content">' . $count . ' questions</div>';
                                            break;
                                        default:
                                            $preview = '<div class="block-preview-content">Click to edit</div>';
                                    }
                                    echo $preview;
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Add Block Button -->
                <div class="add-block-area" onclick="openBlockPalette()" <?php echo empty($blocks) ? 'style="display:none;"' : ''; ?> id="addBlockBtn">
                    <h3>+ Add Block</h3>
                    <p>Choose a block type to add to your post</p>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('settings')">Settings</button>
                <button class="tab" onclick="switchTab('seo')">SEO</button>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-content active" id="tab-settings">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Status</div>
                    <span class="status-badge status-<?php echo htmlspecialchars($post['status'] ?? 'draft'); ?>">
                        <?php echo ucfirst($post['status'] ?? 'Draft'); ?>
                    </span>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Post Details</div>
                    
                    <div class="form-group">
                        <label for="postSlug">URL Slug</label>
                        <input type="text" id="postSlug" placeholder="auto-generated" value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="postCategory">Category</label>
                        <select id="postCategory">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tags</label>
                        <div class="tag-input-container" id="tagContainer">
                            <?php foreach ($postTags as $tag): ?>
                                <span class="tag-badge" data-tag-id="<?php echo $tag['id']; ?>">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                    <button onclick="removeTag(<?php echo $tag['id']; ?>)">&times;</button>
                                </span>
                            <?php endforeach; ?>
                            <select id="tagSelect" onchange="addTag(this)">
                                <option value="">+ Add tag</option>
                                <?php foreach ($allTags as $tag): ?>
                                    <?php if (!in_array($tag['id'], $postTagIds)): ?>
                                        <option value="<?php echo $tag['id']; ?>" data-name="<?php echo htmlspecialchars($tag['name']); ?>">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="postExcerpt">Excerpt</label>
                        <textarea id="postExcerpt" placeholder="Brief summary..." rows="3"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Featured Image</div>
                    <div class="form-group">
                        <input type="text" id="featuredImage" placeholder="Image URL" value="<?php echo htmlspecialchars($post['featured_image'] ?? ''); ?>">
                        <div class="form-hint">Enter image URL or upload</div>
                    </div>
                    <div class="form-group">
                        <input type="text" id="featuredImageAlt" placeholder="Alt text for SEO" value="<?php echo htmlspecialchars($post['featured_image_alt'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Author</div>
                    <div class="form-group">
                        <input type="text" id="authorName" placeholder="Author name" value="<?php echo htmlspecialchars($post['author_name'] ?? 'WebDaddy Team'); ?>">
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Publishing</div>
                    <div class="form-group">
                        <label for="publishDate">Publish Date</label>
                        <input type="datetime-local" id="publishDate" value="<?php echo $post['publish_date'] ? date('Y-m-d\TH:i', strtotime($post['publish_date'])) : ''; ?>">
                    </div>
                </div>
            </div>
            
            <!-- SEO Tab -->
            <div class="tab-content" id="tab-seo">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">SEO Settings</div>
                    
                    <div class="form-group">
                        <label for="focusKeyword">Focus Keyword</label>
                        <input type="text" id="focusKeyword" placeholder="Main keyword" value="<?php echo htmlspecialchars($post['focus_keyword'] ?? ''); ?>">
                        <div class="form-hint">Primary keyword for this post</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="metaTitle">Meta Title</label>
                        <input type="text" id="metaTitle" placeholder="SEO title (60 chars)" maxlength="60" value="<?php echo htmlspecialchars($post['meta_title'] ?? ''); ?>">
                        <div class="form-hint"><span id="metaTitleCount">0</span>/60 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="metaDescription">Meta Description</label>
                        <textarea id="metaDescription" placeholder="SEO description (160 chars)" maxlength="160" rows="3"><?php echo htmlspecialchars($post['meta_description'] ?? ''); ?></textarea>
                        <div class="form-hint"><span id="metaDescCount">0</span>/160 characters</div>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Social Sharing</div>
                    
                    <div class="form-group">
                        <label for="ogTitle">OG Title</label>
                        <input type="text" id="ogTitle" placeholder="Facebook/LinkedIn title" value="<?php echo htmlspecialchars($post['og_title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="ogDescription">OG Description</label>
                        <textarea id="ogDescription" placeholder="Social description" rows="2"><?php echo htmlspecialchars($post['og_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="ogImage">OG Image</label>
                        <input type="text" id="ogImage" placeholder="Social share image URL" value="<?php echo htmlspecialchars($post['og_image'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Block Palette Modal -->
    <div class="block-palette" id="blockPalette">
        <div class="block-palette-content">
            <div class="block-palette-header">
                <h2>Add Block</h2>
                <button class="btn btn-ghost" onclick="closeBlockPalette()">&times;</button>
            </div>
            <div class="block-palette-grid">
                <?php foreach ($blockTypes as $type => $info): ?>
                    <div class="block-option" onclick="addBlock('<?php echo $type; ?>')">
                        <div class="block-option-icon"><?php echo $info['icon']; ?></div>
                        <div class="block-option-name"><?php echo $info['name']; ?></div>
                        <div class="block-option-desc"><?php echo $info['desc']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Block Edit Modal -->
    <div class="modal" id="blockEditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Edit Block</h3>
                <button class="btn btn-ghost" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Block form will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveBlock()">Save Block</button>
            </div>
        </div>
    </div>
    
    <script>
        const postId = <?php echo $postId ? $postId : 'null'; ?>;
        let currentBlockId = null;
        let currentBlockType = null;
        let selectedTags = <?php echo json_encode($postTagIds); ?>;
        
        const blockSchemas = {
            hero_editorial: {
                fields: [
                    { name: 'h1_title', label: 'Main Title (H1)', type: 'text', required: true, placeholder: 'Best Business Website in Nigeria 2025' },
                    { name: 'subtitle', label: 'Subtitle', type: 'textarea', placeholder: 'Everything you need to know about building a professional website' },
                    { name: 'featured_image', label: 'Featured Image URL', type: 'text', placeholder: 'https://...' },
                    { name: 'featured_image_alt', label: 'Image Alt Text', type: 'text', placeholder: 'Describe the image' }
                ]
            },
            rich_text: {
                fields: [
                    { name: 'content', label: 'Content (HTML)', type: 'textarea', required: true, rows: 10, placeholder: '<p>Your content here...</p>' }
                ]
            },
            section_divider: {
                fields: [
                    { name: 'divider_type', label: 'Divider Type', type: 'select', options: ['line', 'gradient', 'labeled', 'space'] },
                    { name: 'label_text', label: 'Label Text (for labeled type)', type: 'text', placeholder: 'Step 1' },
                    { name: 'spacing', label: 'Spacing', type: 'select', options: ['small', 'medium', 'large'] }
                ]
            },
            visual_explanation: {
                fields: [
                    { name: 'heading', label: 'Heading', type: 'text', required: true, placeholder: 'Why This Matters' },
                    { name: 'heading_level', label: 'Heading Level', type: 'select', options: ['h2', 'h3'] },
                    { name: 'content', label: 'Content (HTML)', type: 'textarea', required: true, rows: 5 },
                    { name: 'image.url', label: 'Image URL', type: 'text', placeholder: 'https://...' },
                    { name: 'image.alt', label: 'Image Alt', type: 'text' },
                    { name: 'image_position', label: 'Image Position', type: 'select', options: ['left', 'right'] }
                ]
            },
            inline_conversion: {
                fields: [
                    { name: 'headline', label: 'Headline', type: 'text', required: true, placeholder: 'Want this done for you?' },
                    { name: 'subheadline', label: 'Subheadline', type: 'text', placeholder: 'Get your website in 24 hours' },
                    { name: 'style', label: 'Style', type: 'select', options: ['card', 'banner', 'minimal', 'floating'] },
                    { name: 'cta_primary.text', label: 'CTA Button Text', type: 'text', required: true, placeholder: 'View Templates' },
                    { name: 'cta_primary.url', label: 'CTA Button URL', type: 'text', placeholder: '/' }
                ]
            },
            internal_authority: {
                fields: [
                    { name: 'heading', label: 'Heading', type: 'text', required: true, placeholder: 'Related Articles' },
                    { name: 'display_type', label: 'Display Type', type: 'select', options: ['cards', 'list', 'compact'] },
                    { name: 'source', label: 'Source', type: 'select', options: ['auto', 'manual'] }
                ]
            },
            faq_seo: {
                fields: [
                    { name: 'heading', label: 'Section Heading', type: 'text', required: true, placeholder: 'Frequently Asked Questions' },
                    { name: 'heading_level', label: 'Heading Level', type: 'select', options: ['h2', 'h3'] },
                    { name: 'items', label: 'FAQ Items (JSON)', type: 'textarea', rows: 8, placeholder: '[{"question": "...", "answer": "..."}]' },
                    { name: 'style', label: 'Style', type: 'select', options: ['accordion', 'expanded', 'simple'] }
                ]
            },
            final_conversion: {
                fields: [
                    { name: 'headline', label: 'Headline', type: 'text', required: true, placeholder: 'Ready to Get Started?' },
                    { name: 'subheadline', label: 'Subheadline', type: 'text', placeholder: 'Join thousands of happy customers' },
                    { name: 'style', label: 'Style', type: 'select', options: ['hero', 'card', 'split', 'minimal'] },
                    { name: 'cta_config.type', label: 'CTA Type', type: 'select', options: ['whatsapp', 'template_selector', 'custom'] },
                    { name: 'cta_config.custom.button_text', label: 'Button Text', type: 'text', placeholder: 'Get Started' },
                    { name: 'cta_config.custom.url', label: 'Button URL', type: 'text', placeholder: '/' }
                ]
            }
        };
        
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`.tab[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }
        
        function openBlockPalette() {
            document.getElementById('blockPalette').classList.add('open');
        }
        
        function closeBlockPalette() {
            document.getElementById('blockPalette').classList.remove('open');
        }
        
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => container.innerHTML = '', 3000);
        }
        
        async function addBlock(type) {
            closeBlockPalette();
            
            if (!postId) {
                showAlert('Please save the post first before adding blocks', 'error');
                return;
            }
            
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        post_id: postId,
                        block_type: type,
                        data_payload: getDefaultPayload(type)
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.error || 'Failed to add block', 'error');
                }
            } catch (e) {
                showAlert('Error adding block: ' + e.message, 'error');
            }
        }
        
        function getDefaultPayload(type) {
            const defaults = {
                hero_editorial: { h1_title: 'Enter Title', subtitle: '' },
                rich_text: { content: '<p>Enter your content here...</p>' },
                section_divider: { divider_type: 'line', spacing: 'medium' },
                visual_explanation: { heading: 'Heading', heading_level: 'h2', content: '<p>Content</p>', image_position: 'right' },
                inline_conversion: { headline: 'Want this done for you?', style: 'card', cta_primary: { text: 'Learn More', url: '/' } },
                internal_authority: { heading: 'Related Articles', display_type: 'cards', source: 'auto' },
                faq_seo: { heading: 'FAQ', heading_level: 'h2', items: [], style: 'accordion' },
                final_conversion: { headline: 'Get Started Today', style: 'hero', cta_config: { type: 'custom', custom: { button_text: 'Start Now', url: '/' } } }
            };
            return defaults[type] || {};
        }
        
        async function editBlock(blockId) {
            try {
                const response = await fetch(`/admin/api/blog/blocks.php?action=get&id=${blockId}`);
                const data = await response.json();
                
                if (data.success) {
                    currentBlockId = blockId;
                    currentBlockType = data.block.block_type;
                    renderBlockForm(data.block);
                    document.getElementById('blockEditModal').classList.add('open');
                } else {
                    showAlert(data.error || 'Failed to load block', 'error');
                }
            } catch (e) {
                showAlert('Error loading block: ' + e.message, 'error');
            }
        }
        
        function renderBlockForm(block) {
            const schema = blockSchemas[block.block_type];
            if (!schema) {
                document.getElementById('modalBody').innerHTML = '<p>No editor available for this block type</p>';
                return;
            }
            
            document.getElementById('modalTitle').textContent = 'Edit ' + block.block_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            let html = '<form id="blockForm">';
            
            const behaviors = block.behavior_config || {};
            
            html += `
                <div class="form-group">
                    <label for="layout_variant">Layout Variant</label>
                    <select id="layout_variant" name="layout_variant">
                        ${['default', 'split_left', 'split_right', 'wide', 'contained', 'card_grid', 'timeline'].map(v => 
                            `<option value="${v}" ${block.layout_variant === v ? 'selected' : ''}>${v}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Block Behaviors</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px;">
                            <input type="checkbox" name="behavior_sticky" ${behaviors.sticky ? 'checked' : ''}> Sticky
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px;">
                            <input type="checkbox" name="behavior_collapsible" ${behaviors.collapsible ? 'checked' : ''}> Collapsible
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px;">
                            <input type="checkbox" name="behavior_lazy_loaded" ${behaviors.lazy_loaded ? 'checked' : ''}> Lazy Load
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px;">
                            <input type="checkbox" name="behavior_animated" ${behaviors.animated ? 'checked' : ''}> Animated
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px;">
                            <input type="checkbox" name="behavior_cta_aware" ${behaviors.cta_aware ? 'checked' : ''}> CTA Tracking
                        </label>
                    </div>
                </div>
            `;
            
            schema.fields.forEach(field => {
                let value = getNestedValue(block.data_payload, field.name) || '';
                if (typeof value === 'object') value = JSON.stringify(value, null, 2);
                
                html += `<div class="form-group">`;
                html += `<label for="${field.name}">${field.label}${field.required ? ' *' : ''}</label>`;
                
                if (field.type === 'select') {
                    html += `<select id="${field.name}" name="${field.name}">`;
                    field.options.forEach(opt => {
                        html += `<option value="${opt}" ${value === opt ? 'selected' : ''}>${opt}</option>`;
                    });
                    html += `</select>`;
                } else if (field.type === 'textarea') {
                    html += `<textarea id="${field.name}" name="${field.name}" rows="${field.rows || 4}" placeholder="${field.placeholder || ''}">${escapeHtml(value)}</textarea>`;
                } else {
                    html += `<input type="text" id="${field.name}" name="${field.name}" value="${escapeHtml(value)}" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>`;
                }
                
                html += `</div>`;
            });
            
            html += '</form>';
            document.getElementById('modalBody').innerHTML = html;
        }
        
        function getNestedValue(obj, path) {
            return path.split('.').reduce((o, k) => (o || {})[k], obj);
        }
        
        function setNestedValue(obj, path, value) {
            const keys = path.split('.');
            const last = keys.pop();
            const target = keys.reduce((o, k) => o[k] = o[k] || {}, obj);
            target[last] = value;
            return obj;
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        
        async function saveBlock() {
            const form = document.getElementById('blockForm');
            const formData = new FormData(form);
            const schema = blockSchemas[currentBlockType];
            
            let dataPayload = {};
            schema.fields.forEach(field => {
                let value = formData.get(field.name);
                if (field.name === 'items' && value) {
                    try { value = JSON.parse(value); } catch (e) { value = []; }
                }
                setNestedValue(dataPayload, field.name, value);
            });
            
            const behaviorConfig = {
                sticky: formData.get('behavior_sticky') === 'on',
                collapsible: formData.get('behavior_collapsible') === 'on',
                lazy_loaded: formData.get('behavior_lazy_loaded') === 'on',
                animated: formData.get('behavior_animated') === 'on',
                cta_aware: formData.get('behavior_cta_aware') === 'on'
            };
            
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        id: currentBlockId,
                        layout_variant: formData.get('layout_variant'),
                        behavior_config: behaviorConfig,
                        data_payload: dataPayload
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    closeModal();
                    location.reload();
                } else {
                    showAlert(data.error || 'Failed to save block', 'error');
                }
            } catch (e) {
                showAlert('Error saving block: ' + e.message, 'error');
            }
        }
        
        async function deleteBlock(blockId) {
            if (!confirm('Delete this block?')) return;
            
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: blockId })
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.error || 'Failed to delete block', 'error');
                }
            } catch (e) {
                showAlert('Error deleting block: ' + e.message, 'error');
            }
        }
        
        async function duplicateBlock(blockId) {
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'duplicate', id: blockId })
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.error || 'Failed to duplicate block', 'error');
                }
            } catch (e) {
                showAlert('Error duplicating block: ' + e.message, 'error');
            }
        }
        
        async function moveBlockUp(blockId) {
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'move_up', id: blockId })
                });
                
                const data = await response.json();
                if (data.success) location.reload();
            } catch (e) {
                showAlert('Error moving block', 'error');
            }
        }
        
        async function moveBlockDown(blockId) {
            try {
                const response = await fetch('/admin/api/blog/blocks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'move_down', id: blockId })
                });
                
                const data = await response.json();
                if (data.success) location.reload();
            } catch (e) {
                showAlert('Error moving block', 'error');
            }
        }
        
        function closeModal() {
            document.getElementById('blockEditModal').classList.remove('open');
            currentBlockId = null;
            currentBlockType = null;
        }
        
        function addTag(select) {
            const tagId = select.value;
            if (!tagId) return;
            
            const tagName = select.options[select.selectedIndex].dataset.name;
            selectedTags.push(parseInt(tagId));
            
            const badge = document.createElement('span');
            badge.className = 'tag-badge';
            badge.dataset.tagId = tagId;
            badge.innerHTML = `${tagName} <button onclick="removeTag(${tagId})">&times;</button>`;
            
            document.getElementById('tagContainer').insertBefore(badge, select);
            select.options[select.selectedIndex].remove();
            select.value = '';
        }
        
        function removeTag(tagId) {
            selectedTags = selectedTags.filter(id => id !== tagId);
            const badge = document.querySelector(`.tag-badge[data-tag-id="${tagId}"]`);
            if (badge) badge.remove();
        }
        
        async function savePost(status) {
            const title = document.getElementById('postTitle').value.trim();
            if (!title) {
                showAlert('Please enter a post title', 'error');
                return;
            }
            
            const btn = status === 'published' ? document.getElementById('publishBtn') : document.getElementById('saveDraftBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span> Saving...';
            btn.disabled = true;
            
            try {
                const response = await fetch('/admin/api/blog/posts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: postId ? 'update' : 'create',
                        id: postId,
                        title: title,
                        slug: document.getElementById('postSlug').value,
                        excerpt: document.getElementById('postExcerpt').value,
                        category_id: document.getElementById('postCategory').value || null,
                        featured_image: document.getElementById('featuredImage').value,
                        featured_image_alt: document.getElementById('featuredImageAlt').value,
                        author_name: document.getElementById('authorName').value,
                        publish_date: document.getElementById('publishDate').value,
                        status: status,
                        tags: selectedTags,
                        focus_keyword: document.getElementById('focusKeyword').value,
                        meta_title: document.getElementById('metaTitle').value,
                        meta_description: document.getElementById('metaDescription').value,
                        og_title: document.getElementById('ogTitle').value,
                        og_description: document.getElementById('ogDescription').value,
                        og_image: document.getElementById('ogImage').value
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showAlert('Post saved successfully!', 'success');
                    if (!postId && data.post_id) {
                        window.location.href = `editor.php?post_id=${data.post_id}`;
                    } else {
                        location.reload();
                    }
                } else {
                    showAlert(data.error || 'Failed to save post', 'error');
                }
            } catch (e) {
                showAlert('Error saving post: ' + e.message, 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        
        function previewPost() {
            if (postId) {
                const slug = document.getElementById('postSlug').value || 'preview';
                window.open('/blog/' + slug + '/', '_blank');
            } else {
                showAlert('Save the post first to preview', 'error');
            }
        }
        
        // Character counters
        document.getElementById('metaTitle').addEventListener('input', function() {
            document.getElementById('metaTitleCount').textContent = this.value.length;
        });
        document.getElementById('metaDescription').addEventListener('input', function() {
            document.getElementById('metaDescCount').textContent = this.value.length;
        });
        
        // Initialize counters
        document.getElementById('metaTitleCount').textContent = document.getElementById('metaTitle').value.length;
        document.getElementById('metaDescCount').textContent = document.getElementById('metaDescription').value.length;
        
        // Close modals on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBlockPalette();
                closeModal();
            }
        });
        
        // Close modals on backdrop click
        document.getElementById('blockPalette').addEventListener('click', function(e) {
            if (e.target === this) closeBlockPalette();
        });
        document.getElementById('blockEditModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
