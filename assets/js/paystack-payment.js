/**
 * Paystack Payment Integration
 * Enhanced with robust retry mechanism and error recovery
 */

const PaymentManager = {
    maxRetries: 3,
    retryDelay: 2000,
    currentPayment: null,
    
    init() {
        const payNowBtn = document.getElementById('pay-now-btn');
        if (payNowBtn) {
            payNowBtn.addEventListener('click', () => this.initializePayment());
        }
        
        this.restorePendingPayment();
    },
    
    savePendingPayment(orderId, reference) {
        try {
            const paymentData = {
                orderId: orderId,
                reference: reference,
                timestamp: Date.now()
            };
            sessionStorage.setItem('pending_payment', JSON.stringify(paymentData));
            this.currentPayment = paymentData;
        } catch (e) {
            console.error('Failed to save payment data:', e);
        }
    },
    
    restorePendingPayment() {
        try {
            const stored = sessionStorage.getItem('pending_payment');
            if (stored) {
                const paymentData = JSON.parse(stored);
                const ageMinutes = (Date.now() - paymentData.timestamp) / 60000;
                if (ageMinutes < 30) {
                    this.currentPayment = paymentData;
                    console.log('Restored pending payment:', paymentData);
                } else {
                    sessionStorage.removeItem('pending_payment');
                }
            }
        } catch (e) {
            console.error('Failed to restore payment data:', e);
        }
    },
    
    clearPendingPayment() {
        try {
            sessionStorage.removeItem('pending_payment');
            this.currentPayment = null;
        } catch (e) {}
    },
    
    async checkPaymentStatus(orderId, reference) {
        try {
            const response = await fetch('/api/check-payment-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, reference: reference })
            });
            return await response.json();
        } catch (error) {
            console.error('Check payment status error:', error);
            return { success: false, status: 'error', message: 'Network error' };
        }
    },
    
    async initializePayment() {
        const payNowBtn = document.getElementById('pay-now-btn');
        payNowBtn.disabled = true;
        payNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Initializing...';
        
        try {
            const customerData = {
                name: document.getElementById('customer_name')?.value || '',
                email: document.getElementById('customer_email')?.value || '',
                phone: document.getElementById('customer_phone')?.value || '',
                business_name: document.getElementById('business_name')?.value || '',
                affiliate_code: this.getAffiliateCode()
            };
            
            if (!customerData.name || !customerData.email || !customerData.phone) {
                alert('Please fill all required fields (Name, Email, Phone)');
                this.resetPayButton();
                return;
            }
            
            if (!this.validateEmail(customerData.email)) {
                alert('Please enter a valid email address');
                this.resetPayButton();
                return;
            }
            
            const response = await fetch('/api/paystack-initialize.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(customerData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.savePendingPayment(data.order_id, data.reference);
                
                const handler = PaystackPop.setup({
                    key: data.public_key,
                    email: customerData.email,
                    amount: data.amount * 100,
                    ref: data.reference,
                    currency: 'NGN',
                    metadata: {
                        order_id: data.order_id,
                        customer_name: customerData.name,
                        custom_fields: [
                            {
                                display_name: "Business Name",
                                variable_name: "business_name",
                                value: customerData.business_name
                            }
                        ]
                    },
                    callback: (response) => {
                        this.verifyPaymentWithRetry(response.reference, data.order_id, 0);
                    },
                    onClose: () => {
                        this.handlePaymentClosed(data.order_id, data.reference);
                    }
                });
                
                handler.openIframe();
            } else {
                alert('Error: ' + data.message);
                this.resetPayButton();
            }
        } catch (error) {
            console.error('Payment initialization error:', error);
            alert('Failed to initialize payment. Please check your internet connection and try again.');
            this.resetPayButton();
        }
    },
    
    async handlePaymentClosed(orderId, reference) {
        this.showOverlay('Checking payment status...', 'Processing');
        
        try {
            const status = await this.checkPaymentStatus(orderId, reference);
            
            if (status.success && status.status === 'paid') {
                this.showSuccessAndRedirect(orderId);
            } else if (status.status === 'pending') {
                this.hideOverlay();
                this.showRecoveryOptions(orderId, reference, 'Payment window closed. Your payment may still be processing.');
            } else {
                this.hideOverlay();
                this.resetPayButton();
            }
        } catch (error) {
            this.hideOverlay();
            this.resetPayButton();
        }
    },
    
    async verifyPaymentWithRetry(reference, orderId, attempt) {
        this.showOverlay('Verifying payment...', 'Processing');
        
        try {
            const csrfToken = this.getCsrfToken();
            if (!csrfToken) {
                throw new Error('Security token not found. Please refresh the page.');
            }
            
            const response = await fetch('/api/paystack-verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    reference: reference,
                    order_id: orderId,
                    csrf_token: csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.clearPendingPayment();
                this.showSuccessAndRedirect(data.order_id);
            } else {
                throw new Error(data.message || 'Verification failed');
            }
        } catch (error) {
            console.error(`Verification attempt ${attempt + 1} failed:`, error);
            
            if (error.message && (error.message.includes('network') || error.name === 'TypeError')) {
                if (attempt < this.maxRetries) {
                    this.updateOverlay(`Connection issue. Retrying... (${attempt + 1}/${this.maxRetries})`);
                    await this.sleep(this.retryDelay * Math.pow(2, attempt));
                    return this.verifyPaymentWithRetry(reference, orderId, attempt + 1);
                }
            }
            
            this.hideOverlay();
            
            const statusCheck = await this.checkPaymentStatus(orderId, reference);
            if (statusCheck.success && statusCheck.status === 'paid') {
                this.clearPendingPayment();
                this.showSuccessAndRedirect(orderId);
                return;
            }
            
            this.showRecoveryOptions(orderId, reference, error.message || 'Payment verification failed');
        }
    },
    
    showRecoveryOptions(orderId, reference, errorMessage) {
        const existingModal = document.getElementById('payment-recovery-modal');
        if (existingModal) existingModal.remove();
        
        const modal = document.createElement('div');
        modal.id = 'payment-recovery-modal';
        modal.className = 'fixed inset-0 bg-black/80 flex items-center justify-center z-[10000] p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 mx-auto mb-4 bg-amber-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Verification Issue</h3>
                    <p class="text-gray-600 text-sm mb-4">Don't worry! Your payment may have been processed. Please try the options below:</p>
                </div>
                
                <div class="space-y-3">
                    <button onclick="PaymentManager.retryVerification('${reference}', ${orderId})" 
                            class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Retry Verification
                    </button>
                    
                    <button onclick="PaymentManager.checkAndRedirect(${orderId}, '${reference}')" 
                            class="w-full py-3 px-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Check Payment Status
                    </button>
                    
                    <a href="/cart-checkout.php?confirmed=${orderId}" 
                       class="block w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors text-center">
                        View Order Status
                    </a>
                    
                    <button onclick="PaymentManager.closeRecoveryModal()" 
                            class="w-full py-2 text-gray-500 hover:text-gray-700 text-sm">
                        Cancel
                    </button>
                </div>
                
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 text-center">
                        Reference: <span class="font-mono">${reference}</span><br>
                        Order: #${orderId}
                    </p>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    },
    
    closeRecoveryModal() {
        const modal = document.getElementById('payment-recovery-modal');
        if (modal) modal.remove();
        this.resetPayButton();
    },
    
    async retryVerification(reference, orderId) {
        const modal = document.getElementById('payment-recovery-modal');
        if (modal) modal.remove();
        
        await this.verifyPaymentWithRetry(reference, orderId, 0);
    },
    
    async checkAndRedirect(orderId, reference) {
        const modal = document.getElementById('payment-recovery-modal');
        if (modal) {
            modal.querySelector('.space-y-3').innerHTML = `
                <div class="text-center py-4">
                    <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-3"></div>
                    <p class="text-gray-600">Checking payment status...</p>
                </div>
            `;
        }
        
        try {
            const status = await this.checkPaymentStatus(orderId, reference);
            
            if (status.success && status.status === 'paid') {
                this.clearPendingPayment();
                if (modal) modal.remove();
                this.showSuccessAndRedirect(orderId);
            } else if (status.status === 'pending') {
                if (modal) modal.remove();
                alert('Your payment is still being processed. Please wait a moment and try again, or contact support with reference: ' + reference);
                this.resetPayButton();
            } else if (status.status === 'failed') {
                if (modal) modal.remove();
                alert('Payment verification failed. Please try again or use a different payment method.');
                this.resetPayButton();
            } else {
                if (modal) modal.remove();
                window.location.href = '/cart-checkout.php?confirmed=' + orderId;
            }
        } catch (error) {
            if (modal) modal.remove();
            alert('Unable to check payment status. Please contact support with reference: ' + reference);
            this.resetPayButton();
        }
    },
    
    showOverlay(message, title = 'Processing') {
        let overlay = document.getElementById('payment-processing-overlay');
        if (overlay) {
            overlay.classList.add('show');
            overlay.classList.remove('success');
            
            const spinnerContainer = overlay.querySelector('.spinner-container');
            if (spinnerContainer) {
                spinnerContainer.innerHTML = `
                    <div class="animated-spinner">
                        <div class="spinner-ring"></div>
                    </div>
                `;
            }
            
            const titleEl = overlay.querySelector('.payment-status-title');
            const messageEl = document.getElementById('payment-processing-message');
            if (titleEl) titleEl.textContent = title;
            if (messageEl) messageEl.textContent = message;
        }
    },
    
    updateOverlay(message) {
        const messageEl = document.getElementById('payment-processing-message');
        if (messageEl) messageEl.textContent = message;
    },
    
    hideOverlay() {
        const overlay = document.getElementById('payment-processing-overlay');
        if (overlay) {
            overlay.classList.remove('show');
        }
    },
    
    showSuccessAndRedirect(orderId) {
        let overlay = document.getElementById('payment-processing-overlay');
        if (overlay) {
            overlay.classList.add('show', 'success');
            
            const spinnerContainer = overlay.querySelector('.spinner-container');
            if (spinnerContainer) {
                spinnerContainer.innerHTML = `
                    <div class="success-checkmark">
                        <div class="success-circle">
                            <span class="checkmark">✓</span>
                        </div>
                    </div>
                `;
            }
            
            const titleEl = overlay.querySelector('.payment-status-title');
            const messageEl = document.getElementById('payment-processing-message');
            if (titleEl) titleEl.textContent = 'Payment Successful!';
            if (messageEl) {
                messageEl.textContent = 'Redirecting to your order...';
                messageEl.classList.add('success-message');
            }
        }
        
        setTimeout(() => {
            window.location.href = '/cart-checkout.php?confirmed=' + orderId + '&payment=paystack';
        }, 1500);
    },
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },
    
    getCsrfToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) return csrfMeta.getAttribute('content');
        
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        return csrfInput ? csrfInput.value : null;
    },
    
    resetPayButton() {
        const payNowBtn = document.getElementById('pay-now-btn');
        if (payNowBtn) {
            payNowBtn.disabled = false;
            const amount = payNowBtn.getAttribute('data-amount');
            payNowBtn.innerHTML = amount ? '💳 Pay ₦' + amount + ' Now' : '💳 Pay Now';
        }
    },
    
    validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    getAffiliateCode() {
        const affiliateInput = document.getElementById('affiliate_code');
        if (affiliateInput?.value) return affiliateInput.value.toUpperCase();
        
        const urlParams = new URLSearchParams(window.location.search);
        const urlAff = urlParams.get('aff');
        if (urlAff) return urlAff.toUpperCase();
        
        const affiliateBanner = document.querySelector('[class*="from-green-"] span');
        if (affiliateBanner?.textContent.includes('Code:')) {
            const match = affiliateBanner.textContent.match(/Code:\s*([A-Z0-9]+)/);
            if (match) return match[1];
        }
        
        return '';
    }
};

document.addEventListener('DOMContentLoaded', () => PaymentManager.init());

async function initializePayment() {
    PaymentManager.initializePayment();
}

async function verifyPayment(reference, order_id) {
    PaymentManager.verifyPaymentWithRetry(reference, order_id, 0);
}

function resetPayButton() {
    PaymentManager.resetPayButton();
}

function validateEmail(email) {
    return PaymentManager.validateEmail(email);
}

function getAffiliateCode() {
    return PaymentManager.getAffiliateCode();
}
