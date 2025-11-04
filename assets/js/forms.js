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
                    b.classList.remove('bg-primary-600', 'text-white', 'shadow-sm');
                    b.classList.add('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');
                });
                
                btn.classList.remove('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');
                btn.classList.add('bg-primary-600', 'text-white', 'shadow-sm');
                
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
