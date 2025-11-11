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
                window.location.reload();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });
        
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            window.location.reload();
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
                                            <button onclick="addTemplateToCart(${template.id}, '${escapeHtml(template.name)}', this)" 
                                               class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors whitespace-nowrap">
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
                    ${tools.map(tool => `
                        <div class="tool-card group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 cursor-pointer" 
                             data-tool-id="${tool.id}">
                            <div class="relative overflow-hidden h-40 bg-gray-100">
                                <img src="${escapeHtml(tool.thumbnail_url || '/assets/images/placeholder.jpg')}"
                                     alt="${escapeHtml(tool.name)}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
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
                                    <button onclick="event.stopPropagation(); addToCartFromModal(${tool.id}, '${escapeHtml(tool.name)}', ${tool.price})" 
                                            class="add-to-cart-btn inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            contentArea.innerHTML = html;
            setupToolPopup();
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
        
        // Reset category when switching from tools to templates
        if (view === 'templates' && category) {
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
                
                // Re-attach event listeners
                setupToolPopup();
                setupPaginationHandlers();
                setupCategoryFilters();
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
        document.querySelectorAll('a[href*="page="]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const page = parseInt(url.searchParams.get('page')) || 1;
                const category = url.searchParams.get('category') || '';
                switchView(currentView, page, category);
            });
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
    
    // Tool Popup Modal
    function setupToolPopup() {
        document.querySelectorAll('.tool-card, [data-tool-id]').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function(e) {
                // Don't open modal if clicking Add to Cart button
                if (e.target.closest('.add-to-cart-btn')) return;
                
                const toolId = this.dataset.toolId || this.querySelector('[data-tool-id]')?.dataset.toolId;
                if (toolId) {
                    openToolModal(toolId);
                }
            });
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
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" onclick="closeToolModal()"></div>
                
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-6 pt-5 pb-4">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">${escapeHtml(tool.name)}</h3>
                            <button onclick="closeToolModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-4">
                            <img src="${escapeHtml(tool.thumbnail_url || '/assets/images/placeholder.jpg')}" 
                                 alt="${escapeHtml(tool.name)}"
                                 class="w-full h-64 object-cover rounded-lg"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                        </div>
                        
                        ${tool.category ? `<span class="inline-block px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full mb-3">${escapeHtml(tool.category)}</span>` : ''}
                        
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Description</h4>
                            <p class="text-gray-700">${escapeHtml(tool.description || tool.short_description || 'No description available')}</p>
                        </div>
                        
                        ${tool.features ? `
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Features</h4>
                            <ul class="list-disc list-inside space-y-1 text-gray-700">
                                ${tool.features.split(',').map(f => `<li>${escapeHtml(f.trim())}</li>`).join('')}
                            </ul>
                        </div>
                        ` : ''}
                        
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <div>
                                <p class="text-sm text-gray-600">Price</p>
                                <p class="text-3xl font-bold text-primary-600">${formatCurrency(tool.price)}</p>
                            </div>
                            <button onclick="addToCartFromModal(${tool.id}, '${escapeHtml(tool.name)}', ${tool.price})" 
                                    class="add-to-cart-btn inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                Add to Cart
                            </button>
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
        floatingCart.className = 'fixed bottom-6 right-6 z-40';
        floatingCart.innerHTML = `
            <div class="relative">
                <button onclick="toggleCartDrawer()" class="relative bg-primary-600 hover:bg-primary-700 text-white rounded-full p-4 shadow-2xl transition-all hover:scale-110 group">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span id="floating-cart-count" class="hidden absolute -top-1 -right-1 bg-gray-900 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center border-2 border-white">0</span>
                </button>
                
                <!-- Animated Total Amount Badge - positioned to the right side -->
                <div id="floating-cart-total-badge" class="hidden absolute -top-2 -right-2 translate-x-full bg-gray-900 text-white px-2 py-1 rounded-md shadow-lg transition-all duration-300 opacity-0 whitespace-nowrap ml-2">
                    <div id="floating-cart-total-amount" class="text-xs font-semibold">₦0</div>
                </div>
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
        const totalBadge = document.getElementById('floating-cart-total-badge');
        
        if (floatingCart) {
            floatingCart.classList.add('animate-bounce');
            setTimeout(() => floatingCart.classList.remove('animate-bounce'), 500);
        }
        
        // Animate the total badge
        if (totalBadge && !totalBadge.classList.contains('hidden')) {
            totalBadge.style.transform = 'scale(1.2)';
            setTimeout(() => {
                totalBadge.style.transform = 'scale(1)';
            }, 300);
        }
    }
    
    async function updateCartBadge() {
        try {
            const response = await fetch('/api/cart.php?action=get');
            const data = await response.json();
            
            if (data.success) {
                const count = data.count || 0;
                const total = data.total || 0;
                
                // Update all cart badges
                updateBadgeElement('cart-count', count);
                updateBadgeElement('cart-count-mobile', count);
                updateBadgeElement('floating-cart-count', count);
                
                // Update floating cart total amount
                const totalAmountEl = document.getElementById('floating-cart-total-amount');
                const totalBadgeEl = document.getElementById('floating-cart-total-badge');
                
                if (totalAmountEl) {
                    totalAmountEl.textContent = formatCurrency(total);
                }
                
                // Show/hide total badge based on cart count
                if (totalBadgeEl) {
                    if (count > 0) {
                        totalBadgeEl.classList.remove('hidden');
                        setTimeout(() => {
                            totalBadgeEl.classList.remove('opacity-0');
                            totalBadgeEl.classList.add('opacity-100');
                        }, 10);
                    } else {
                        totalBadgeEl.classList.remove('opacity-100');
                        totalBadgeEl.classList.add('opacity-0');
                        setTimeout(() => {
                            totalBadgeEl.classList.add('hidden');
                        }, 300);
                    }
                }
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
