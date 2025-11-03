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
        this.searchQuery = '';
        this.currentPage = 1;
        this.itemsPerPage = 9;
        
        this.initializeFilter();
    }

    initializeFilter() {
        const templateGrid = document.querySelector('[data-templates-grid]');
        if (!templateGrid) return;

        this.templates = Array.from(templateGrid.querySelectorAll('[data-template]')).map(el => ({
            element: el,
            name: el.getAttribute('data-template-name') || '',
            category: el.getAttribute('data-template-category') || '',
            price: parseFloat(el.getAttribute('data-template-price') || '0')
        }));

        this.setupSearchBar();
        this.setupCategoryFilter();
        this.setupPagination();
    }

    setupSearchBar() {
        const searchInput = document.querySelector('[data-template-search]');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase();
            this.currentPage = 1;
            this.filterTemplates();
        });
    }

    setupCategoryFilter() {
        const categoryButtons = document.querySelectorAll('[data-category-filter]');
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                categoryButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                this.currentCategory = btn.getAttribute('data-category-filter');
                this.currentPage = 1;
                this.filterTemplates();
            });
        });
    }

    filterTemplates() {
        const filtered = this.templates.filter(template => {
            const matchesSearch = !this.searchQuery || 
                template.name.toLowerCase().includes(this.searchQuery) ||
                template.category.toLowerCase().includes(this.searchQuery);
            
            const matchesCategory = this.currentCategory === 'all' || 
                template.category.toLowerCase() === this.currentCategory.toLowerCase();

            return matchesSearch && matchesCategory;
        });

        const totalPages = Math.ceil(filtered.length / this.itemsPerPage);
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const paginated = filtered.slice(start, end);

        this.templates.forEach(template => {
            template.element.style.display = 'none';
        });

        paginated.forEach(template => {
            template.element.style.display = '';
        });

        this.updatePagination(filtered.length, totalPages);
        this.updateResultsCount(filtered.length);
    }

    setupPagination() {
        const paginationContainer = document.querySelector('[data-pagination]');
        if (!paginationContainer) return;

        paginationContainer.addEventListener('click', (e) => {
            if (e.target.hasAttribute('data-page')) {
                e.preventDefault();
                this.currentPage = parseInt(e.target.getAttribute('data-page'));
                this.filterTemplates();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    updatePagination(totalItems, totalPages) {
        const paginationContainer = document.querySelector('[data-pagination]');
        if (!paginationContainer || totalPages <= 1) {
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination justify-content-center">';
        
        if (this.currentPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${this.currentPage - 1}">Previous</a></li>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === this.currentPage) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        if (this.currentPage < totalPages) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${this.currentPage + 1}">Next</a></li>`;
        }

        html += '</ul>';
        paginationContainer.innerHTML = html;
    }

    updateResultsCount(count) {
        const resultsElement = document.querySelector('[data-results-count]');
        if (resultsElement) {
            resultsElement.textContent = `${count} template${count !== 1 ? 's' : ''} found`;
        }
    }
}

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
