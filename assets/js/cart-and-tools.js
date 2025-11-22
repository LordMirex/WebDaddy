let toolPopupBound = false;

function trackTemplateClick(templateId) {
    if (!templateId) return;
    fetch('/api/analytics.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'track_template_click',
            template_id: templateId
        })
    }).catch(err => console.error('Analytics tracking failed:', err));
}

function trackToolClick(toolId) {
    if (!toolId) return;
    fetch('/api/analytics.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'track_tool_click',
            tool_id: toolId
        })
    }).catch(err => console.error('Analytics tracking failed:', err));
}

document.addEventListener('DOMContentLoaded', function() {
    const affiliateCode = new URLSearchParams(window.location.search).get('aff') || '';
    let currentView = new URLSearchParams(window.location.search).get('view') || 'templates';
    let currentPage = 1;
    let currentCategory = new URLSearchParams(window.location.search).get('category') || '';
    const viewCache = { templates: null, tools: null };
    
    // Initialize
    init();
    
    function init() {
        setupAJAXTabs();
        setupToolPopup();
        setupFloatingCart();
        setupCartDrawer();
        updateCartBadge();
        setupSearch();
        setupCategoryDropdown();
        setupPaginationHandlers();
        setupAnalyticsTracking();
        
        // Pre-cache both views for instant switching
        preCacheBothViews();
    }
    
    // Pre-cache both views on initial load for instant tab switching
    async function preCacheBothViews() {
        const viewToCache = currentView === 'templates' ? 'tools' : 'templates';
        
        try {
            const params = new URLSearchParams();
            params.set('action', 'load_view');
            params.set('view', viewToCache);
            params.set('page', 1);
            if (affiliateCode) params.set('aff', affiliateCode);
            
            const response = await fetch(`/api/ajax-products.php?${params.toString()}`);
            const data = await response.json();
            
            if (data.success && data.html) {
                viewCache[viewToCache] = data.html;
                console.log(`✅ Pre-cached ${viewToCache} view for instant switching`);
            }
        } catch (err) {
            console.log(`ℹ️ Pre-cache for ${viewToCache} skipped - will load on demand`);
        }
    }
    
    function setupAnalyticsTracking() {
        document.body.addEventListener('click', function(e) {
            // Track template link clicks (both old and new URL formats)
            const templateLink = e.target.closest('a[href*="template.php"], a[href^="/"][href*="-"]');
            if (templateLink && !templateLink.href.includes('/api/') && !templateLink.href.includes('/admin/') && !templateLink.href.includes('/affiliate/')) {
                // Extract template ID from data attribute or parse from old URL format
                const templateId = templateLink.dataset.templateId || 
                                  (templateLink.href.includes('?id=') ? parseInt(new URL(templateLink.href).searchParams.get('id')) : null);
                if (templateId) {
                    trackTemplateClick(templateId);
                }
            }
            
            const toolPreviewBtn = e.target.closest('.tool-preview-btn');
            if (toolPreviewBtn) {
                const toolId = parseInt(toolPreviewBtn.dataset.toolId);
                if (toolId) {
                    trackToolClick(toolId);
                }
            }
        });
    }
    
    // Setup search functionality
    function setupSearch() {
        const searchInput = document.getElementById('search-input');
        const clearBtn = document.getElementById('clear-search');
        const loadingIndicator = document.getElementById('search-loading');
        
        if (!searchInput) return;
        
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            
            if (query.length > 0) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
            
            if (searchTimeout) clearTimeout(searchTimeout);
            
            if (query.length === 0) {
                switchView(currentView, 1, currentCategory);
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 500);
        });
        
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            switchView(currentView, 1, currentCategory);
        });
        
        async function performSearch(query) {
            if (!query) return;
            
            loadingIndicator.style.display = 'block';
            
            try {
                const searchType = currentView === 'templates' ? 'template' : 'tool';
                const response = await fetch(`/api/search.php?q=${encodeURIComponent(query)}&type=${searchType}`);
                const data = await response.json();
                
                if (data.success && data.results) {
                    renderSearchResults(data.results);
                } else {
                    renderSearchResults([]);
                }
            } catch (error) {
                console.error('Search error:', error);
                renderSearchResults([]);
            } finally {
                loadingIndicator.style.display = 'none';
            }
        }
        
        function renderSearchResults(results) {
            const contentArea = document.getElementById('products-content-area');
            if (!contentArea) return;
            
            if (results.length === 0) {
                contentArea.innerHTML = `
                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-12 text-center">
                        <svg class="w-16 h-16 mx-auto text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h4 class="text-xl font-bold text-gray-900 mb-2">No results found</h4>
                        <p class="text-gray-600">Try different keywords or <button onclick="document.getElementById('clear-search').click()" class="font-semibold text-primary-600 hover:text-primary-700">clear search</button></p>
                    </div>
                `;
                updateCounterDisplay(0);
                return;
            }
            
            if (currentView === 'templates') {
                renderTemplates(results);
            } else {
                renderTools(results);
            }
            updateCounterDisplay(results.length);
        }
        
        function updateCounterDisplay(count) {
            const counterSelectors = [
                'a[href*="view=templates"] .text-primary-600',
                'a[href*="view=tools"] .text-primary-600'
            ];
            
            const activeSelector = currentView === 'templates' ? counterSelectors[0] : counterSelectors[1];
            const counterElement = document.querySelector(activeSelector);
            
            if (counterElement) {
                counterElement.textContent = count;
            }
        }
        
        function renderTemplates(templates) {
            const contentArea = document.getElementById('products-content-area');
            const html = `
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    ${templates.map(template => {
                        const hasDemo = template.demo_url || template.demo_video_url;
                        const demoUrl = template.demo_video_url || template.demo_url;
                        const isVideo = demoUrl && /\.(mp4|webm|mov|avi)$/i.test(demoUrl);
                        
                        return `
                        <div class="group">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                                <div class="relative overflow-hidden h-48 bg-gray-100">
                                    <img src="${escapeHtml(template.thumbnail_url || '/assets/images/placeholder.jpg')}" 
                                         alt="${escapeHtml(template.name)}"
                                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                         onerror="this.src='/assets/images/placeholder.jpg'">
                                    ${hasDemo ? (isVideo ? `
                                    <button onclick="event.stopPropagation(); openVideoModal('${escapeJsString(demoUrl)}', '${escapeJsString(template.name)}')"
                                            class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Video
                                    </button>
                                    <button onclick="openVideoModal('${escapeJsString(demoUrl)}', '${escapeJsString(template.name)}')"
                                            data-video-trigger
                                            class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Watch Demo
                                        </span>
                                    </button>
                                    ` : `
                                    <button onclick="event.stopPropagation(); openDemoFullscreen('${escapeJsString(demoUrl)}', '${escapeJsString(template.name)}')"
                                            class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                                        Preview
                                    </button>
                                    <button onclick="openDemoFullscreen('${escapeJsString(demoUrl)}', '${escapeJsString(template.name)}')" 
                                            class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Click to Preview
                                        </span>
                                    </button>
                                    `) : ''}
                                </div>
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-base font-bold text-gray-900 flex-1 pr-2">${escapeHtml(template.name)}</h3>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 shrink-0">
                                            ${escapeHtml(template.category || '')}
                                        </span>
                                    </div>
                                    <p class="text-gray-600 text-xs mb-3 line-clamp-2">${escapeHtml((template.description || '').substring(0, 80))}...</p>
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                        <div class="flex flex-col">
                                            <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                                            <span class="text-base font-bold text-primary-600">${formatCurrency(template.price)}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <a href="/${template.slug}${affiliateCode ? '?aff=' + affiliateCode : ''}" 
                                               data-template-id="${template.id}"
                                               class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                                                Details
                                            </a>
                                            <button onclick="addTemplateToCart(${template.id}, '${escapeJsString(template.name)}', this)" 
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
                        </div>
                        `;
                    }).join('')}
                </div>
            `;
            contentArea.innerHTML = html;
        }
        
        function renderTools(tools) {
            const contentArea = document.getElementById('products-content-area');
            const html = `
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    ${tools.map(tool => {
                        const isOutOfStock = tool.stock_unlimited == 0 && tool.stock_quantity <= 0;
                        const isLowStock = tool.stock_unlimited == 0 && tool.stock_quantity <= tool.low_stock_threshold && tool.stock_quantity > 0;
                        const hasVideo = tool.demo_video_url && tool.demo_video_url.trim() !== '';
                        
                        return `
                        <div class="tool-card group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1" 
                             data-tool-id="${tool.id}">
                            <div class="relative overflow-hidden h-40 bg-gray-100">
                                <img src="${escapeHtml(tool.thumbnail_url || '/assets/images/placeholder.jpg')}"
                                     alt="${escapeHtml(tool.name)}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                                ${hasVideo ? `
                                <button onclick="openVideoModal('${escapeJsString(tool.demo_video_url)}', '${escapeJsString(tool.name)}')"
                                        class="absolute top-2 left-2 px-3 py-1.5 bg-black/70 hover:bg-black/90 text-white text-xs font-bold rounded-full flex items-center gap-1 transition-all shadow-lg">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                    Watch Video
                                </button>
                                ` : ''}
                                ${isLowStock ? `
                                <div class="absolute top-2 right-2 px-2 py-1 bg-yellow-500 text-white text-xs font-bold rounded">
                                    Limited Stock
                                </div>
                                ` : isOutOfStock ? `
                                <div class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white text-xs font-bold rounded">
                                    Out of Stock
                                </div>
                                ` : ''}
                            </div>
                            <div class="p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-sm font-bold text-gray-900 flex-1 pr-2">${escapeHtml(tool.name)}</h3>
                                    ${tool.category ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 shrink-0">${escapeHtml(tool.category)}</span>` : ''}
                                </div>
                                ${tool.short_description ? `<p class="text-gray-600 text-xs mb-3 line-clamp-2">${escapeHtml(tool.short_description)}</p>` : ''}
                                <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                                        <span class="text-lg font-extrabold text-primary-600">${formatCurrency(tool.price)}</span>
                                    </div>
                                    <button data-tool-id="${tool.id}" 
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
                        `;
                    }).join('')}
                </div>
            `;
            contentArea.innerHTML = html;
        }
    }
    
    // AJAX Tab Switching
    function setupAJAXTabs() {
        document.querySelectorAll('a[href*="view="]').forEach(link => {
            link.addEventListener('click', function(e) {
                const url = new URL(this.href);
                const view = url.searchParams.get('view');
                
                if (!view || (view !== 'templates' && view !== 'tools')) return;
                
                e.preventDefault();
                switchView(view);
            });
        });
    }
    
    async function switchView(view, page = 1, category = '') {
        const previousView = currentView;
        currentView = view;
        currentPage = page;
        currentCategory = category;
        
        const productsSection = document.querySelector('#products');
        if (!productsSection) return;
        
        // Clear search when switching views
        const searchInput = document.getElementById('search-input');
        const clearBtn = document.getElementById('clear-search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.placeholder = `Search ${view === 'templates' ? 'templates' : 'tools'}...`;
            if (clearBtn) clearBtn.style.display = 'none';
        }
        
        // Reset category when actually switching between views (not when filtering within same view)
        if (previousView !== view) {
            category = '';
            currentCategory = '';
        }
        
        const contentArea = productsSection.querySelector('.max-w-7xl > div:last-child');
        
        // Check cache first - instant load if available
        if (viewCache[view] && !category && page === 1) {
            if (contentArea) {
                contentArea.innerHTML = viewCache[view];
                contentArea.style.opacity = '1';
                contentArea.style.pointerEvents = 'auto';
            }
            updateActiveTab(view);
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('view', view);
            newUrl.searchParams.set('page', page);
            newUrl.searchParams.delete('category');
            if (affiliateCode) newUrl.searchParams.set('aff', affiliateCode);
            newUrl.hash = 'products';
            window.history.pushState({}, '', newUrl);
            
            // Always scroll to products when pagination is used
            if (page > 1) {
                productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            setupCategoryFilters();
            setupCategoryDropdown();
            return;
        }
        
        // Show loading state
        if (contentArea) {
            contentArea.style.opacity = '0.5';
            contentArea.style.pointerEvents = 'none';
        }
        
        // Build URL
        const params = new URLSearchParams();
        params.set('action', 'load_view');
        params.set('view', view);
        params.set('page', page);
        if (category) params.set('category', category);
        if (affiliateCode) params.set('aff', affiliateCode);
        
        try {
            const response = await fetch(`/api/ajax-products.php?${params.toString()}`);
            const data = await response.json();
            
            if (data.success && data.html) {
                // Cache the view HTML (only main page, not filtered/paginated)
                if (!category && page === 1) {
                    viewCache[view] = data.html;
                }
                
                // Update content
                if (contentArea) {
                    contentArea.innerHTML = data.html;
                    contentArea.style.opacity = '1';
                    contentArea.style.pointerEvents = 'auto';
                }
                
                // Update tabs
                updateActiveTab(view);
                
                // Update URL without reload
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('view', view);
                newUrl.searchParams.set('page', page);
                if (category) {
                    newUrl.searchParams.set('category', category);
                } else {
                    newUrl.searchParams.delete('category');
                }
                if (affiliateCode) newUrl.searchParams.set('aff', affiliateCode);
                newUrl.hash = 'products';
                window.history.pushState({}, '', newUrl);
                
                // Smooth scroll to products section - always scroll for pagination
                if (page > 1) {
                    productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                
                // Re-attach event listeners for AJAX-replaced elements
                setupCategoryFilters();
                setupCategoryDropdown();
            }
        } catch (error) {
            console.error('Failed to load view:', error);
            if (contentArea) {
                contentArea.style.opacity = '1';
                contentArea.style.pointerEvents = 'auto';
            }
        }
    }
    
    function updateActiveTab(view) {
        document.querySelectorAll('a[href*="view="]').forEach(tab => {
            const tabUrl = new URL(tab.href, window.location.origin);
            const tabView = tabUrl.searchParams.get('view');
            
            if (tabView === view) {
                tab.classList.add('bg-primary-600', 'text-white', 'shadow-sm');
                tab.classList.remove('text-gray-700', 'hover:bg-gray-50');
            } else {
                tab.classList.remove('bg-primary-600', 'text-white', 'shadow-sm');
                tab.classList.add('text-gray-700', 'hover:bg-gray-50');
            }
        });
    }
    
    function setupPaginationHandlers() {
        // Use event delegation on document body (stable ancestor that never gets replaced)
        if (document.body._paginationHandlerAttached) return;
        document.body._paginationHandlerAttached = true;
        
        document.body.addEventListener('click', function(e) {
            // Only handle clicks within the products section
            if (!e.target.closest('#products')) return;
            
            // Handle both anchor links and button elements with data-page
            const paginationLink = e.target.closest('a[href*="page="]');
            const paginationButton = e.target.closest('[data-page]');
            
            if (paginationLink) {
                e.preventDefault();
                const url = new URL(paginationLink.href);
                const page = parseInt(url.searchParams.get('page')) || 1;
                const category = url.searchParams.get('category') || currentCategory || '';
                switchView(currentView, page, category);
            } else if (paginationButton) {
                e.preventDefault();
                const page = parseInt(paginationButton.dataset.page) || 1;
                const category = currentCategory || '';
                switchView(currentView, page, category);
            }
        });
    }
    
    function setupCategoryFilters() {
        document.querySelectorAll('a[href*="category="]').forEach(link => {
            link.addEventListener('click', function(e) {
                const url = new URL(this.href, window.location.origin);
                const view = url.searchParams.get('view');
                if (view === 'tools') {
                    e.preventDefault();
                    const category = url.searchParams.get('category') || '';
                    switchView('tools', 1, category);
                }
            });
        });
    }
    
    function setupCategoryDropdown() {
        const toolsDropdown = document.getElementById('tools-category-filter');
        const templatesDropdown = document.getElementById('templates-category-filter');
        
        if (toolsDropdown) {
            const newToolsDropdown = toolsDropdown.cloneNode(true);
            toolsDropdown.parentNode.replaceChild(newToolsDropdown, toolsDropdown);
            newToolsDropdown.addEventListener('change', function(e) {
                const category = e.target.value;
                switchView('tools', 1, category);
            });
        }
        
        if (templatesDropdown) {
            const newTemplatesDropdown = templatesDropdown.cloneNode(true);
            templatesDropdown.parentNode.replaceChild(newTemplatesDropdown, templatesDropdown);
            newTemplatesDropdown.addEventListener('change', function(e) {
                const category = e.target.value;
                switchView('templates', 1, category);
            });
        }
    }
    
    // Tool Popup Modal
    function setupToolPopup() {
        if (toolPopupBound) return;
        toolPopupBound = true;

        document.addEventListener('click', (event) => {
            const previewBtn = event.target.closest('.tool-preview-btn');
            if (!previewBtn) return;

            const toolId = previewBtn.dataset.toolId;
            if (toolId) {
                event.preventDefault();
                event.stopPropagation();
                openToolModal(toolId);
            }
        });
    }
    
    async function openToolModal(toolId) {
        try {
            const response = await fetch(`/api/tools.php?action=get_tool&id=${toolId}`);
            const data = await response.json();
            
            if (data.success && data.tool) {
                showToolModal(data.tool);
                
                // Track tool view when preview is clicked on index page
                trackToolViewFromPreview(toolId);
            }
        } catch (error) {
            console.error('Failed to load tool details:', error);
        }
    }
    
    // Track tool view from preview modal
    function trackToolViewFromPreview(toolId) {
        // Get session ID from cookie
        const cookies = document.cookie.split(';');
        let sessionId = null;
        for (let cookie of cookies) {
            if (cookie.trim().startsWith('PHPSESSID=')) {
                sessionId = cookie.trim().substring(10);
                break;
            }
        }
        
        if (sessionId && toolId) {
            fetch('/api/track-view.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tool_id: toolId,
                    source: 'preview_modal'
                })
            }).catch(err => console.error('Error tracking tool view:', err));
        }
    }
    
    function showToolModal(tool) {
        const modal = document.createElement('div');
        modal.id = 'tool-modal';
        modal.className = 'fixed inset-0 z-50 overflow-y-auto';
        
        // Pre-escape values before template literal to prevent injection
        const escapedName = escapeHtml(tool.name);
        const escapedNameForJs = escapeJsString(tool.name);
        const escapedCategory = tool.category ? escapeHtml(tool.category) : '';
        const escapedDescription = tool.description || tool.short_description ? escapeHtml(tool.description || tool.short_description).replace(/\n/g, '<br>') : '';
        const escapedDeliveryTime = tool.delivery_time ? escapeHtml(tool.delivery_time) : '';
        const escapedThumbnail = escapeHtml(tool.thumbnail_url || '/assets/images/placeholder.jpg');
        
        const isOutOfStock = tool.stock_unlimited == 0 && tool.stock_quantity <= 0;
        const isLowStock = tool.stock_unlimited == 0 && tool.stock_quantity <= tool.low_stock_threshold && tool.stock_quantity > 0;
        const stockBadge = isOutOfStock 
            ? '<span class="inline-block px-3 py-1 bg-red-100 text-red-800 text-sm font-semibold rounded-full">Out of Stock</span>'
            : isLowStock 
            ? `<span class="inline-block px-3 py-1 bg-yellow-100 text-yellow-800 text-sm font-semibold rounded-full">Limited Stock (${tool.stock_quantity} left)</span>`
            : tool.stock_unlimited == 0 
            ? `<span class="inline-block px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">${tool.stock_quantity} in stock</span>`
            : '';
        
        modal.innerHTML = `
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" onclick="closeToolModal()"></div>
                
                <div class="relative inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-3xl mx-4">
                    <div class="bg-white">
                        <!-- Header with close button -->
                        <div class="flex justify-between items-start px-4 sm:px-6 pt-4 sm:pt-6 pb-3 sm:pb-4 border-b border-gray-200">
                            <div class="flex-1">
                                <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 mb-2">${escapedName}</h3>
                                <div class="flex gap-2 items-center flex-wrap">
                                    ${escapedCategory ? `<span class="inline-block px-2 py-0.5 sm:px-3 sm:py-1 bg-green-100 text-green-800 text-xs sm:text-sm font-medium rounded-full">${escapedCategory}</span>` : ''}
                                    ${stockBadge}
                                </div>
                            </div>
                            <button onclick="closeToolModal()" class="text-gray-400 hover:text-gray-600 ml-2 sm:ml-4 flex-shrink-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Content -->
                        <div class="px-4 sm:px-6 py-3 sm:py-4 max-h-[60vh] overflow-y-auto">
                            <div class="mb-4 sm:mb-6">
                                <img src="${escapedThumbnail}" 
                                     alt="${escapedName}"
                                     class="w-full h-48 sm:h-64 md:h-72 object-cover rounded-lg sm:rounded-xl shadow-md"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                            </div>
                            
                            ${tool.demo_video_url ? `
                            <div class="mb-4 sm:mb-6">
                                <div class="bg-gray-50 border border-gray-200 rounded-lg sm:rounded-xl overflow-hidden">
                                    <div class="bg-gray-100 border-b border-gray-200 p-3 sm:p-4 flex justify-between items-center">
                                        <h4 class="text-base sm:text-lg font-bold text-gray-900 flex items-center">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Demo Video
                                        </h4>
                                        <button onclick="openVideoModal('${escapeJsString(tool.demo_video_url)}', '${escapedNameForJs}')"
                                                class="inline-flex items-center px-3 py-1.5 sm:px-4 sm:py-2 border border-transparent text-xs sm:text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                            Watch Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            
                            ${escapedDescription ? `
                            <div class="mb-4 sm:mb-6">
                                <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 sm:mb-3 flex items-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    About This Tool
                                </h4>
                                <div class="text-gray-700 text-sm sm:text-base leading-relaxed bg-gray-50 p-3 sm:p-4 rounded-lg border border-gray-200">
                                    ${escapedDescription}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${tool.features ? `
                            <div class="mb-4 sm:mb-6">
                                <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 sm:mb-3 flex items-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Key Features
                                </h4>
                                <ul class="space-y-2">
                                    ${tool.features.split(',').map(f => `
                                        <li class="flex items-start">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span class="text-gray-700 text-sm sm:text-base">${escapeHtml(f.trim())}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                            ` : ''}
                            
                            <!-- Share Section in scrollable content -->
                            <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 sm:p-5 mb-4 sm:mb-6">
                                <div class="text-center mb-4">
                                    <h3 class="text-base font-bold text-gray-900 mb-1">Love this tool?</h3>
                                    <p class="text-xs text-gray-600">Share it with your friends!</p>
                                </div>
                                
                                <!-- Share Buttons -->
                                <div class="flex items-center gap-2 flex-wrap justify-center">
                                    <!-- WhatsApp Share -->
                                    <button onclick="shareToolViaWhatsApp('${escapedNameForJs}', '${tool.slug}')" 
                                            class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all shadow-md hover:shadow-lg text-xs sm:text-sm font-medium">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        <span class="hidden sm:inline">WhatsApp</span>
                                    </button>
                                    
                                    <!-- Facebook Share -->
                                    <button onclick="shareToolViaFacebook('${escapedNameForJs}', '${tool.slug}')" 
                                            class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all shadow-md hover:shadow-lg text-xs sm:text-sm font-medium">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                        </svg>
                                        <span class="hidden sm:inline">Facebook</span>
                                    </button>
                                    
                                    <!-- Twitter/X Share -->
                                    <button onclick="shareToolViaTwitter('${escapedNameForJs}', '${tool.slug}')" 
                                            class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2.5 bg-gray-900 hover:bg-black text-white rounded-lg transition-all shadow-md hover:shadow-lg text-xs sm:text-sm font-medium">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417a9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                        </svg>
                                        <span class="hidden sm:inline">Twitter</span>
                                    </button>
                                    
                                    <!-- Copy Link -->
                                    <button onclick="copyToolShareLink('${tool.slug}')" 
                                            class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-900 rounded-lg transition-all shadow-md hover:shadow-lg text-xs sm:text-sm font-medium">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        <span class="hidden sm:inline">Copy Link</span>
                                    </button>
                                </div>
                            </div>
                            
                            ${escapedDeliveryTime ? `
                            <div class="mb-4 sm:mb-6">
                                <h4 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 sm:mb-3 flex items-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Delivery
                                </h4>
                                <p class="text-sm sm:text-base text-gray-700 bg-blue-50 p-3 rounded-lg border border-blue-200">
                                    <span class="font-semibold text-blue-900">Estimated delivery:</span> ${escapedDeliveryTime}
                                </p>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Footer with price and CTA only -->
                        <div class="px-4 sm:px-6 py-4 sm:py-6 bg-white border-t border-gray-200">
                            <!-- Price and Cart Button -->
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs sm:text-sm text-gray-600 mb-1">Price</p>
                                    <p class="text-xl sm:text-2xl md:text-3xl font-extrabold text-primary-600">${formatCurrency(tool.price)}</p>
                                </div>
                                ${isOutOfStock 
                                    ? `<button disabled class="inline-flex items-center px-4 py-2.5 sm:px-6 sm:py-3 border-2 border-gray-300 text-sm sm:text-base font-semibold rounded-lg sm:rounded-xl text-gray-400 bg-gray-100 cursor-not-allowed">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Out of Stock
                                    </button>`
                                    : `<button onclick="addToCartFromModal(${tool.id}, '${escapedNameForJs}', ${tool.price}); closeToolModal();" 
                                        class="inline-flex items-center px-4 py-2.5 sm:px-6 sm:py-3 text-sm sm:text-base font-semibold rounded-lg sm:rounded-xl text-white bg-primary-600 hover:bg-primary-700 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        Add to Cart
                                    </button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
    }
    
    window.closeToolModal = function() {
        const modal = document.getElementById('tool-modal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = 'auto';
        }
    };
    
    // Open tool modal from share link
    window.openToolModalFromShare = function(tool) {
        showToolModal(tool);
    };
    
    // Copy tool share link to clipboard
    window.copyToolShareLink = async function(slug) {
        if (!slug) return;
        try {
            const shareUrl = window.location.origin + '/?tool=' + slug;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(shareUrl);
            } else {
                const tempInput = document.createElement('input');
                tempInput.value = shareUrl;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
            }
            showNotification('Tool link copied to clipboard!', 'success');
        } catch (err) {
            console.error('Failed to copy link:', err);
            showNotification('Failed to copy link', 'error');
        }
    };
    
    // Share tool via WhatsApp
    window.shareToolViaWhatsApp = function(toolName, toolSlug) {
        const shareUrl = window.location.origin + '/?tool=' + toolSlug;
        const text = `Check out this amazing tool: ${toolName}!\n\n${shareUrl}`;
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
        window.open(whatsappUrl, '_blank', 'width=600,height=400');
    };
    
    // Share tool via Facebook
    window.shareToolViaFacebook = function(toolName, toolSlug) {
        const shareUrl = window.location.origin + '/?tool=' + toolSlug;
        const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
        window.open(facebookUrl, '_blank', 'width=600,height=400');
    };
    
    // Share tool via Twitter
    window.shareToolViaTwitter = function(toolName, toolSlug) {
        const shareUrl = window.location.origin + '/?tool=' + toolSlug;
        const twitterText = `Check out this tool: ${toolName}`;
        const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(twitterText)}&url=${encodeURIComponent(shareUrl)}`;
        window.open(twitterUrl, '_blank', 'width=600,height=400');
    };
    
    // Floating Cart Button (Fixed Position)
    function setupFloatingCart() {
        const existingCart = document.getElementById('floating-cart');
        if (existingCart) return;
        
        const floatingCart = document.createElement('div');
        floatingCart.id = 'floating-cart';
        floatingCart.className = 'fixed top-20 right-4 z-40';
        floatingCart.innerHTML = `
            <div class="relative">
                <button onclick="toggleCartDrawer()" class="relative bg-primary-600 hover:bg-primary-700 text-white rounded-full p-3 shadow-xl transition-all hover:scale-105">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span id="floating-cart-count" class="hidden absolute -top-1 -right-1 bg-red-500 text-white font-bold rounded-full h-5 w-5 flex items-center justify-center border-2 border-white text-xs">0</span>
                </button>
            </div>
        `;
        
        document.body.appendChild(floatingCart);
    }
    
    // Cart Drawer
    function setupCartDrawer() {
        const existingDrawer = document.getElementById('cart-drawer');
        if (existingDrawer) return;
        
        const cartDrawer = document.createElement('div');
        cartDrawer.id = 'cart-drawer';
        cartDrawer.className = 'fixed inset-0 z-50 hidden';
        cartDrawer.innerHTML = `
            <div class="absolute inset-0 bg-gray-900 bg-opacity-50" onclick="toggleCartDrawer()"></div>
            <div class="absolute right-0 top-0 h-full w-full sm:w-96 bg-white shadow-2xl transform translate-x-full transition-transform duration-300" id="cart-drawer-content">
                <div class="h-full flex flex-col">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">Your Cart</h3>
                        <button onclick="toggleCartDrawer()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div id="cart-items" class="flex-1 overflow-y-auto p-4">
                        <p class="text-gray-500 text-center py-8">Your cart is empty</p>
                    </div>
                    
                    <div id="cart-footer" class="hidden border-t border-gray-200 p-4 bg-gray-50">
                        <div class="mb-4" id="cart-totals-container">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Subtotal</span>
                                <span id="cart-subtotal">₦0</span>
                            </div>
                            <div id="cart-discount-row" class="hidden flex justify-between text-sm text-green-600 mb-1">
                                <span>Discount (20%)</span>
                                <span id="cart-discount-amount">-₦0</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-gray-900 pt-2 border-t border-gray-200">
                                <span>Total</span>
                                <span id="cart-total">₦0</span>
                            </div>
                        </div>
                        <button onclick="proceedToCheckout()" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 rounded-lg transition-colors">
                            Proceed to Checkout
                        </button>
                        <button onclick="toggleCartDrawer()" class="w-full mt-2 text-primary-600 hover:text-primary-700 font-medium py-2">
                            Continue Shopping
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(cartDrawer);
    }
    
    window.toggleCartDrawer = function() {
        const drawer = document.getElementById('cart-drawer');
        const content = document.getElementById('cart-drawer-content');
        
        if (drawer.classList.contains('hidden')) {
            drawer.classList.remove('hidden');
            setTimeout(() => content.classList.remove('translate-x-full'), 10);
            loadCartItems();
        } else {
            content.classList.add('translate-x-full');
            setTimeout(() => drawer.classList.add('hidden'), 300);
        }
    };
    
    // Add to Cart (for Tools)
    window.addToCartFromModal = async function(toolId, toolName, price) {
        return await addProductToCart('tool', toolId, toolName, 1);
    };
    
    // Add Template to Cart
    window.addTemplateToCart = async function(templateId, templateName, button) {
        if (button) button.disabled = true;
        const result = await addProductToCart('template', templateId, templateName, 1);
        if (button) button.disabled = false;
        return result;
    };
    
    // Unified Add Product to Cart function
    async function addProductToCart(productType, productId, productName, quantity = 1) {
        try {
            const params = new URLSearchParams();
            params.set('action', 'add');
            params.set('product_type', productType);
            params.set('product_id', productId);
            params.set('quantity', quantity);
            
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            
            if (!response.ok) {
                const text = await response.text();
                let errorMessage = 'Failed to add to cart';
                try {
                    const errorData = JSON.parse(text);
                    errorMessage = errorData.message || errorData.error || errorMessage;
                } catch (e) {
                    console.error('Server returned non-JSON error:', text);
                }
                showNotification(errorMessage, 'error');
                return false;
            }
            
            const data = await response.json();
            
            if (data.success) {
                if (window.closeToolModal) closeToolModal();
                const notificationMessage = productName ? `${productName} added to cart!` : (data.message || 'Added to cart!');
                showNotification(notificationMessage, 'success');
                updateCartBadge();
                
                // Animate cart button
                animateCartBadge();
                
                return true;
            } else {
                showNotification(data.message || data.error || 'Failed to add to cart', 'error');
                return false;
            }
        } catch (error) {
            console.error('Add to cart error:', error);
            showNotification('Failed to add to cart', 'error');
            return false;
        }
    }
    
    function animateCartBadge() {
        const floatingCart = document.getElementById('floating-cart');
        if (floatingCart) {
            floatingCart.classList.add('animate-bounce');
            setTimeout(() => floatingCart.classList.remove('animate-bounce'), 500);
        }
    }
    
    async function updateCartBadge() {
        try {
            const response = await fetch('/api/cart.php?action=get');
            const data = await response.json();
            
            if (data.success) {
                const count = data.count || 0;
                
                // Update all cart badges
                updateBadgeElement('cart-count', count);
                updateBadgeElement('cart-count-mobile', count);
                updateBadgeElement('floating-cart-count', count);
            }
        } catch (error) {
            console.error('Failed to update cart badge:', error);
        }
    }
    
    function updateBadgeElement(id, count) {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = count;
            if (count > 0) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }
    
    async function loadCartItems() {
        try {
            const response = await fetch('/api/cart.php?action=get');
            const data = await response.json();
            
            if (data.success) {
                // Pass the full totals object with discount info
                displayCartItems(data.items || [], data.totals || data.total || 0);
            }
        } catch (error) {
            console.error('Failed to load cart:', error);
        }
    }
    
    function displayCartItems(items, totals) {
        const container = document.getElementById('cart-items');
        const footer = document.getElementById('cart-footer');
        
        if (items.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">Your cart is empty</p>';
            footer.classList.add('hidden');
            return;
        }
        
        container.innerHTML = items.map(item => {
            const itemTotal = (item.price_at_add || item.price) * item.quantity;
            const productType = item.product_type || 'tool';
            const isTemplate = productType === 'template';
            
            return `
            <div class="flex items-center gap-4 mb-4 p-3 bg-gray-50 rounded-lg">
                <img src="${escapeHtml(item.thumbnail_url || '/assets/images/placeholder.jpg')}" 
                     alt="${escapeHtml(item.name)}"
                     class="w-16 h-16 object-cover rounded"
                     onerror="this.src='/assets/images/placeholder.jpg'">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold text-sm text-gray-900 truncate">${escapeHtml(item.name)}</h4>
                        ${isTemplate ? '<span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full">Template</span>' : '<span class="text-xs px-2 py-0.5 bg-green-100 text-green-800 rounded-full">Tool</span>'}
                    </div>
                    <p class="text-xs text-gray-600">${formatCurrency(item.price_at_add || item.price)}${isTemplate ? '' : ' × ' + item.quantity}</p>
                    <p class="text-sm font-semibold text-primary-600">${formatCurrency(itemTotal)}</p>
                </div>
                <div class="flex items-center gap-2">
                    ${!isTemplate ? `
                        <button onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})" 
                                class="w-6 h-6 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300 transition-colors">-</button>
                        <span class="w-8 text-center font-semibold">${item.quantity}</span>
                        <button onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})" 
                                class="w-6 h-6 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300 transition-colors">+</button>
                    ` : ''}
                </div>
                <button onclick="removeFromCart(${item.id})" 
                        class="text-red-500 hover:text-red-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        }).join('');
        
        // Display totals properly with discount if applicable
        const subtotalEl = document.getElementById('cart-subtotal');
        const totalEl = document.getElementById('cart-total');
        const discountRow = document.getElementById('cart-discount-row');
        const discountAmount = document.getElementById('cart-discount-amount');
        
        if (totals && typeof totals === 'object') {
            subtotalEl.textContent = formatCurrency(totals.subtotal || 0);
            
            // Show/hide discount row if applicable
            if (totals.has_discount && totals.discount > 0) {
                if (discountRow && discountAmount) {
                    discountAmount.textContent = '-' + formatCurrency(totals.discount);
                    discountRow.classList.remove('hidden');
                }
            } else {
                if (discountRow) {
                    discountRow.classList.add('hidden');
                }
            }
            
            totalEl.textContent = formatCurrency(totals.total || 0);
        } else {
            // Fallback to simple total
            const total = typeof totals === 'number' ? totals : 0;
            subtotalEl.textContent = formatCurrency(total);
            totalEl.textContent = formatCurrency(total);
            if (discountRow) {
                discountRow.classList.add('hidden');
            }
        }
        
        footer.classList.remove('hidden');
    }
    
    window.updateCartQuantity = async function(cartId, newQuantity) {
        if (newQuantity < 1) {
            // If quantity goes to 0, remove the item
            removeFromCart(cartId);
            return;
        }
        
        try {
            const params = new URLSearchParams();
            params.set('action', 'update');
            params.set('cart_id', cartId);
            params.set('quantity', newQuantity);
            
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reload cart with fresh data from server
                await loadCartItems();
                await updateCartBadge();
            } else {
                showNotification(data.message || 'Failed to update quantity', 'error');
            }
        } catch (error) {
            console.error('Failed to update quantity:', error);
            showNotification('Failed to update quantity', 'error');
        }
    };
    
    window.removeFromCart = async function(cartId) {
        try {
            const params = new URLSearchParams();
            params.set('action', 'remove');
            params.set('cart_id', cartId);
            
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reload cart with fresh data from server
                await loadCartItems();
                await updateCartBadge();
                showNotification('Item removed from cart', 'success');
            } else {
                showNotification(data.message || 'Failed to remove item', 'error');
            }
        } catch (error) {
            console.error('Failed to remove item:', error);
            showNotification('Failed to remove item', 'error');
        }
    };
    
    window.proceedToCheckout = function() {
        const checkoutUrl = `/cart-checkout.php${affiliateCode ? '?aff=' + affiliateCode : ''}`;
        window.location.href = checkoutUrl;
    };
    
    // Utility Functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeJsString(text) {
        if (!text) return '';
        return text.replace(/\\/g, '\\\\')
                   .replace(/'/g, "\\'")
                   .replace(/"/g, '\\"')
                   .replace(/`/g, '\\`')
                   .replace(/\$/g, '\\$')
                   .replace(/\n/g, '\\n')
                   .replace(/\r/g, '\\r');
    }
    
    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-NG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
    
    function showNotification(message, type = 'success') {
        // Create notification container
        const container = document.createElement('div');
        container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2';
        
        // Create notification element
        const notification = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200';
        const textColor = type === 'success' ? 'text-emerald-900' : 'text-red-900';
        const iconColor = type === 'success' ? 'text-emerald-600' : 'text-red-600';
        const shadowColor = type === 'success' ? 'shadow-emerald-100' : 'shadow-red-100';
        
        notification.innerHTML = `
            <div class="animate-slide-in flex items-start gap-3 px-4 py-3 sm:px-5 sm:py-4 rounded-lg border-2 ${bgColor} ${shadowColor} shadow-lg backdrop-blur-sm max-w-sm">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 flex-shrink-0 mt-0.5 ${iconColor}" fill="currentColor" viewBox="0 0 20 20">
                    ${type === 'success' 
                        ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'
                        : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'
                    }
                </svg>
                <div class="flex-1">
                    <p class="text-sm sm:text-base font-semibold ${textColor} leading-tight">${escapeHtml(message)}</p>
                </div>
                <button onclick="this.closest('div').closest('div').remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors pt-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        `;
        
        container.appendChild(notification);
        document.body.appendChild(container);
        
        // Auto-dismiss after 4 seconds
        setTimeout(() => {
            notification.classList.add('opacity-0', 'transition-opacity', 'duration-300');
            setTimeout(() => container.remove(), 300);
        }, 4000);
    }
    
    // Global function for opening demo previews (uses modal for in-page previews)
    window.openDemo = function(demoUrl, templateName) {
        if (!demoUrl) return;
        
        // Check if it's a video file or demo URL
        const isVideo = demoUrl.match(/\.(mp4|webm|mov|avi)$/i);
        
        if (isVideo) {
            // Open video in video modal
            if (typeof window.openVideoModal === 'function') {
                window.openVideoModal(demoUrl, templateName || 'Preview');
            } else {
                console.warn('Video modal not available, opening in new tab');
                window.open(demoUrl, '_blank', 'noopener,noreferrer');
            }
        } else {
            // Open demo URL in fullscreen modal
            if (typeof window.openDemoFullscreen === 'function') {
                window.openDemoFullscreen(demoUrl, templateName || 'Live Preview');
            } else {
                console.warn('Demo modal not available, opening in new tab');
                window.open(demoUrl, '_blank', 'noopener,noreferrer');
            }
        }
    };
});
