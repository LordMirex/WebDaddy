/**
 * Form Loading States and Validation
 * WebDaddy Empire - Enhanced User Experience
 */

class FormHandler {
    constructor() {
        this.initializeAllForms();
        this.initializeValidation();
    }

    initializeAllForms() {
        const forms = document.querySelectorAll('form[data-loading]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const hasValidation = form.hasAttribute('data-validate');
                
                if (!hasValidation) {
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        this.showLoading(submitBtn);
                    }
                }
            });
        });
    }

    showLoading(button) {
        if (!button) return;
        
        button.setAttribute('data-original-text', button.innerHTML);
        button.disabled = true;
        button.classList.add('loading');
        
        const spinner = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>';
        const loadingText = button.getAttribute('data-loading-text') || 'Processing...';
        
        button.innerHTML = spinner + loadingText;
    }

    hideLoading(button) {
        if (!button) return;
        
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
        button.disabled = false;
        button.classList.remove('loading');
    }

    initializeValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => {
                    if (input.classList.contains('is-invalid')) {
                        this.validateField(input);
                    }
                });
            });

            form.addEventListener('submit', (e) => {
                const isValid = this.validateForm(form);
                
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        this.hideLoading(submitBtn);
                    }
                    
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (form.hasAttribute('data-loading')) {
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        this.showLoading(submitBtn);
                    }
                }
            });
        });
    }

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea, select');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required.';
        } else if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address.';
            }
        } else if (field.type === 'tel' && value) {
            const phoneRegex = /^[\d\s\-\+\(\)]{10,}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number.';
            }
        } else if (field.type === 'url' && value) {
            try {
                new URL(value);
            } catch {
                isValid = false;
                errorMessage = 'Please enter a valid URL.';
            }
        } else if (field.type === 'number' && value) {
            const min = field.getAttribute('min');
            const max = field.getAttribute('max');
            const numValue = parseFloat(value);
            
            if (isNaN(numValue)) {
                isValid = false;
                errorMessage = 'Please enter a valid number.';
            } else if (min !== null && numValue < parseFloat(min)) {
                isValid = false;
                errorMessage = `Value must be at least ${min}.`;
            } else if (max !== null && numValue > parseFloat(max)) {
                isValid = false;
                errorMessage = `Value must be at most ${max}.`;
            }
        } else if (field.getAttribute('minlength') && value.length < parseInt(field.getAttribute('minlength'))) {
            isValid = false;
            errorMessage = `Must be at least ${field.getAttribute('minlength')} characters.`;
        } else if (field.getAttribute('maxlength') && value.length > parseInt(field.getAttribute('maxlength'))) {
            isValid = false;
            errorMessage = `Must be at most ${field.getAttribute('maxlength')} characters.`;
        } else if (field.getAttribute('pattern') && value) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (!pattern.test(value)) {
                isValid = false;
                errorMessage = field.getAttribute('data-pattern-message') || 'Invalid format.';
            }
        }

        if (field.type === 'password' && field.name === 'confirm_password') {
            const passwordField = field.form.querySelector('input[name="new_password"], input[name="password"]');
            if (passwordField && value !== passwordField.value) {
                isValid = false;
                errorMessage = 'Passwords do not match.';
            }
        }

        this.setFieldValidation(field, isValid, errorMessage);
        return isValid;
    }

    setFieldValidation(field, isValid, errorMessage) {
        const feedbackElement = field.nextElementSibling;
        
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
                feedbackElement.textContent = '';
            }
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            
            if (feedbackElement && feedbackElement.classList.contains('invalid-feedback')) {
                feedbackElement.textContent = errorMessage;
            } else {
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = errorMessage;
                field.parentNode.insertBefore(feedback, field.nextSibling);
            }
        }
    }
}

class TemplateFilter {
    constructor() {
        this.templates = [];
        this.currentCategory = 'all';
        
        this.initializeFilter();
    }

    initializeFilter() {
        const templateGrid = document.querySelector('[data-templates-grid]');
        if (!templateGrid) return;

        this.templates = Array.from(templateGrid.querySelectorAll('[data-template]')).map(el => ({
            element: el,
            category: el.getAttribute('data-template-category') || ''
        }));

        this.setupCategoryFilter();
    }

    setupCategoryFilter() {
        const categoryButtons = document.querySelectorAll('[data-category-filter]');
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                categoryButtons.forEach(b => {
                    b.classList.remove('bg-gold', 'text-navy', 'shadow-sm');
                    b.classList.add('bg-transparent', 'text-gray-300', 'border', 'border-gray-600');
                });
                
                btn.classList.remove('bg-transparent', 'text-gray-300', 'border', 'border-gray-600');
                btn.classList.add('bg-gold', 'text-navy', 'shadow-sm');
                
                this.currentCategory = btn.getAttribute('data-category-filter');
                this.filterTemplates();
            });
        });
    }

    filterTemplates() {
        this.templates.forEach(template => {
            const matchesCategory = this.currentCategory === 'all' || 
                template.category.toLowerCase() === this.currentCategory.toLowerCase();
            
            template.element.style.display = matchesCategory ? '' : 'none';
        });
    }
}

// Instant search functionality
window.TemplateSearch = {
    performSearch(query, affiliateCode = '') {
        const grid = document.querySelector('[data-templates-grid]');
        const resultsMsg = document.querySelector('[data-search-results]');
        
        const url = (!query || query.trim().length === 0) 
            ? '/api/search.php' 
            : '/api/search.php?q=' + encodeURIComponent(query.trim());
        
        return fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateTemplateGrid(data.results, affiliateCode);
                    this.updateResultsMessage(data.results.length, query);
                }
                return data;
            })
            .catch(e => {
                console.error('Search failed:', e);
                throw e;
            });
    },
    
    updateResultsMessage(count, query) {
        const resultsMsg = document.querySelector('[data-search-results]');
        if (!resultsMsg) return;
        
        // Create elements safely to prevent XSS
        const p = document.createElement('p');
        p.className = `text-sm text-${count > 0 ? 'gray-700' : 'yellow-800'}`;
        
        const countSpan = document.createElement('span');
        countSpan.className = `font-semibold ${count > 0 ? 'text-primary-600' : ''}`;
        countSpan.textContent = `${count} result(s)`;
        
        const forText = document.createTextNode(' for "');
        const queryText = document.createTextNode(query);
        const closeQuote = document.createTextNode('"');
        
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'ml-2 text-primary-600 hover:text-primary-700 font-medium focus:outline-none';
        clearButton.textContent = 'Clear search';
        clearButton.setAttribute('data-clear-search', 'true');
        
        p.appendChild(countSpan);
        p.appendChild(forText);
        p.appendChild(queryText);
        p.appendChild(closeQuote);
        p.appendChild(clearButton);
        
        resultsMsg.innerHTML = '';
        resultsMsg.appendChild(p);
    },
    
    updateTemplateGrid(templates, affiliateCode = '') {
        const grid = document.querySelector('[data-templates-grid]');
        if (!grid) return;
        
        if (templates.length === 0) {
            grid.innerHTML = this.getEmptyStateHTML();
            return;
        }
        
        grid.innerHTML = templates.map(template => this.getTemplateHTML(template, affiliateCode)).join('');
    },
    
    getEmptyStateHTML() {
        return `
            <div class="col-span-full bg-navy-light border border-gray-700/50 rounded-2xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gold mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <h4 class="text-xl font-bold text-white mb-2">No templates found</h4>
                <p class="text-gray-200 mb-4">Try searching for: "Business", "E-commerce", "Portfolio", or "Resume"</p>
                <a href="/" class="text-gold hover:text-gold-500 font-medium">View all templates</a>
            </div>
        `;
    },
    
    getTemplateHTML(template, affiliateCode) {
        // Escape HTML to prevent XSS
        const escapeHtml = (str) => {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        };
        
        const affParam = affiliateCode ? `&aff=${escapeHtml(affiliateCode)}` : '';
        const safeName = escapeHtml(template.name || '');
        const safeCategory = escapeHtml(template.category || '');
        const safeDescription = escapeHtml(template.description || 'Professional website template');
        const safeThumbnail = escapeHtml(template.thumbnail_url || '/assets/images/placeholder.jpg');
        const safeId = escapeHtml(template.id || '');
        
        // Use data attributes instead of inline onclick to prevent XSS
        const demoButtons = template.demo_url ? `
            <button class="demo-btn absolute top-2 right-2 px-3 py-1 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full shadow-lg transition-colors z-10"
                    data-demo-url="${escapeHtml(template.demo_url)}"
                    data-demo-name="${safeName}">
                Preview
            </button>
            <button class="demo-btn absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                    data-demo-url="${escapeHtml(template.demo_url)}"
                    data-demo-name="${safeName}">
                <span class="inline-flex items-center px-4 py-2 bg-navy text-white rounded-lg font-medium shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Click to Preview
                </span>
            </button>
        ` : '';
        
        return `
            <div class="group">
                <div class="bg-navy-light rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                    <div class="relative overflow-hidden h-48 bg-navy">
                        <img src="${safeThumbnail}"
                             alt="${safeName}"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                        ${demoButtons}
                    </div>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-base font-bold text-white flex-1 pr-2">${safeName}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold shrink-0">
                                ${safeCategory}
                            </span>
                        </div>
                        <p class="text-sm text-gray-100 mb-3 line-clamp-2">${safeDescription}</p>
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-extrabold text-gold">₦${parseFloat(template.price).toLocaleString()}</span>
                            <button onclick="addTemplateToCart(${safeId}, ${JSON.stringify(template.name)}, this)" 
                               class="inline-flex items-center px-4 py-2 bg-gold hover:bg-gold-500 text-navy text-sm font-semibold rounded-lg transition-colors shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    // Add event delegation for demo buttons created dynamically
    initializeDemoButtons() {
        document.addEventListener('click', (e) => {
            const demoBtn = e.target.closest('.demo-btn');
            if (demoBtn) {
                const demoUrl = demoBtn.dataset.demoUrl;
                const demoName = demoBtn.dataset.demoName;
                if (demoUrl && typeof window.openDemo === 'function') {
                    window.openDemo(demoUrl, demoName);
                }
            }
            
            // Handle clear search button clicks
            const clearBtn = e.target.closest('[data-clear-search]');
            if (clearBtn) {
                e.preventDefault();
                // Get the search input element
                const searchInput = document.querySelector('input[x-model="searchQuery"]');
                if (searchInput) {
                    // Clear the input value
                    searchInput.value = '';
                    // Trigger Alpine.js update by dispatching an input event
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    
                    // Get Alpine component and call clearSearch if available
                    const alpineEl = searchInput.closest('[x-data]');
                    if (alpineEl && window.Alpine) {
                        const alpineData = window.Alpine.$data(alpineEl);
                        if (alpineData && typeof alpineData.clearSearch === 'function') {
                            alpineData.clearSearch();
                        }
                    }
                }
            }
        });
    }
};

class LazyImageLoader {
    constructor() {
        this.initializeLazyLoading();
    }

    initializeLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));
        } else {
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new FormHandler();
    new TemplateFilter();
    new LazyImageLoader();
    
    // Initialize demo button event delegation
    if (window.TemplateSearch) {
        window.TemplateSearch.initializeDemoButtons();
    }
});

window.showAlert = function(message, type = 'info') {
    const alertContainer = document.querySelector('[data-alert-container]') || document.body;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
};
