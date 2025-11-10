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
    
    // Floating Cart Button
    function setupFloatingCart() {
        const existingCart = document.getElementById('floating-cart');
        if (existingCart) return;
        
        const floatingCart = document.createElement('div');
        floatingCart.id = 'floating-cart';
        floatingCart.className = 'fixed bottom-6 right-6 z-40';
        floatingCart.innerHTML = `
            <button onclick="toggleCartDrawer()" class="relative bg-primary-600 hover:bg-primary-700 text-white rounded-full p-4 shadow-2xl transition-all hover:scale-110">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <span id="floating-cart-count" class="hidden absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">0</span>
            </button>
            <div id="floating-cart-total" class="hidden mt-2 bg-white rounded-lg shadow-lg px-3 py-2 text-center">
                <p class="text-xs text-gray-600">Total</p>
                <p class="text-sm font-bold text-primary-600">₦0</p>
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
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Subtotal</span>
                                <span id="cart-subtotal">₦0</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-gray-900">
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
    
    // Add to Cart
    window.addToCartFromModal = async function(toolId, toolName, price) {
        try {
            const params = new URLSearchParams();
            params.set('action', 'add');
            params.set('tool_id', toolId);
            params.set('quantity', 1);
            params.set('price', price);
            
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            
            const data = await response.json();
            
            if (data.success) {
                closeToolModal();
                showNotification(`${toolName} added to cart!`, 'success');
                updateCartBadge();
                
                // Show floating cart total briefly
                const totalEl = document.getElementById('floating-cart-total');
                if (totalEl) {
                    totalEl.classList.remove('hidden');
                    setTimeout(() => totalEl.classList.add('hidden'), 3000);
                }
            } else {
                showNotification(data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Add to cart error:', error);
            showNotification('Failed to add to cart', 'error');
        }
    };
    
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
                
                // Update floating cart total
                const totalEl = document.getElementById('floating-cart-total');
                if (totalEl && count > 0) {
                    totalEl.querySelector('p:last-child').textContent = formatCurrency(total);
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
                displayCartItems(data.items || [], data.total || 0);
            }
        } catch (error) {
            console.error('Failed to load cart:', error);
        }
    }
    
    function displayCartItems(items, total) {
        const container = document.getElementById('cart-items');
        const footer = document.getElementById('cart-footer');
        
        if (items.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">Your cart is empty</p>';
            footer.classList.add('hidden');
            return;
        }
        
        container.innerHTML = items.map(item => `
            <div class="flex items-center gap-4 mb-4 p-3 bg-gray-50 rounded-lg">
                <img src="${escapeHtml(item.thumbnail_url || '/assets/images/placeholder.jpg')}" 
                     alt="${escapeHtml(item.name)}"
                     class="w-16 h-16 object-cover rounded"
                     onerror="this.src='/assets/images/placeholder.jpg'">
                <div class="flex-1">
                    <h4 class="font-semibold text-sm text-gray-900">${escapeHtml(item.name)}</h4>
                    <p class="text-xs text-gray-600">${formatCurrency(item.price)} × ${item.quantity}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="updateCartQuantity(${item.cart_id}, ${item.quantity - 1})" 
                            class="w-6 h-6 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300">-</button>
                    <span class="w-8 text-center font-semibold">${item.quantity}</span>
                    <button onclick="updateCartQuantity(${item.cart_id}, ${item.quantity + 1})" 
                            class="w-6 h-6 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300">+</button>
                </div>
                <button onclick="removeFromCart(${item.cart_id})" 
                        class="text-red-500 hover:text-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `).join('');
        
        document.getElementById('cart-subtotal').textContent = formatCurrency(total);
        document.getElementById('cart-total').textContent = formatCurrency(total);
        footer.classList.remove('hidden');
    }
    
    window.updateCartQuantity = async function(cartId, newQuantity) {
        if (newQuantity < 1) return;
        
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
                loadCartItems();
                updateCartBadge();
            }
        } catch (error) {
            console.error('Failed to update quantity:', error);
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
                loadCartItems();
                updateCartBadge();
                showNotification('Item removed from cart', 'success');
            }
        } catch (error) {
            console.error('Failed to remove item:', error);
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
