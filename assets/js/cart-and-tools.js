let toolPopupBound = false;

document.addEventListener('DOMContentLoaded', function() {
    const affiliateCode = new URLSearchParams(window.location.search).get('aff') || '';
    let currentView = new URLSearchParams(window.location.search).get('view') || 'templates';
    let currentPage = 1;
    let currentCategory = new URLSearchParams(window.location.search).get('category') || '';
    
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
                    ${templates.map(template => `
                        <div class="group">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                                <div class="relative overflow-hidden h-48 bg-gray-100">
                                    <img src="${escapeHtml(template.thumbnail_url || '/assets/images/placeholder.jpg')}" 
                                         alt="${escapeHtml(template.name)}"
                                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                         onerror="this.src='/assets/images/placeholder.jpg'">
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
                                            <a href="template.php?id=${template.id}${affiliateCode ? '&aff=' + affiliateCode : ''}" 
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
                    `).join('')}
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
                        
                        return `
                        <div class="tool-card group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1" 
                             data-tool-id="${tool.id}">
                            <div class="relative overflow-hidden h-40 bg-gray-100">
                                <img src="${escapeHtml(tool.thumbnail_url || '/assets/images/placeholder.jpg')}"
                                     alt="${escapeHtml(tool.name)}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
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
        
        // Show loading state
        const contentArea = productsSection.querySelector('.max-w-7xl > div:last-child');
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
                
                // Smooth scroll to products section without jumping
                const rect = productsSection.getBoundingClientRect();
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (rect.top + scrollTop > scrollTop) {
                    productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                
                // Re-attach event listeners for AJAX-replaced elements
                // Note: setupPaginationHandlers() uses document.body delegation, so no need to rebind
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
            }
        } catch (error) {
            console.error('Failed to load tool details:', error);
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
                        
                        <!-- Footer with price and CTA -->
                        <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between gap-3">
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
            
            const data = await response.json();
            
            if (data.success) {
                if (window.closeToolModal) closeToolModal();
                showNotification(`${productName} added to cart!`, 'success');
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
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white font-medium`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('opacity-0', 'transition-opacity');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});
