/**
 * Paystack Payment Integration
 * Handles instant card payment via Paystack
 */

document.addEventListener('DOMContentLoaded', function() {
    const payNowBtn = document.getElementById('pay-now-btn');
    
    if (payNowBtn) {
        payNowBtn.addEventListener('click', initializePayment);
    }
});

async function initializePayment() {
    const payNowBtn = document.getElementById('pay-now-btn');
    payNowBtn.disabled = true;
    payNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Initializing...';
    
    try {
        // Get customer details from form
        const customerData = {
            name: document.getElementById('customer_name')?.value || '',
            email: document.getElementById('customer_email')?.value || '',
            phone: document.getElementById('customer_phone')?.value || '',
            business_name: document.getElementById('business_name')?.value || '',
            affiliate_code: getAffiliateCode()
        };
        
        // Validate required fields
        if (!customerData.name || !customerData.email || !customerData.phone) {
            alert('Please fill all required fields (Name, Email, Phone)');
            resetPayButton();
            return;
        }
        
        // Validate email format
        if (!validateEmail(customerData.email)) {
            alert('Please enter a valid email address');
            resetPayButton();
            return;
        }
        
        // Initialize payment via backend
        const response = await fetch('/api/paystack-initialize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Open Paystack popup
            const handler = PaystackPop.setup({
                key: data.public_key,
                email: customerData.email,
                amount: data.amount * 100, // Convert to kobo
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
                callback: function(response) {
                    verifyPayment(response.reference);
                },
                onClose: function() {
                    resetPayButton();
                }
            });
            
            handler.openIframe();
        } else {
            alert('Error: ' + data.message);
            resetPayButton();
        }
    } catch (error) {
        console.error('Payment initialization error:', error);
        alert('Failed to initialize payment. Please try again.');
        resetPayButton();
    }
}

async function verifyPayment(reference) {
    document.getElementById('pay-now-btn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying payment...';
    
    try {
        // Get CSRF token from page
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            throw new Error('Security token not found');
        }
        
        const response = await fetch('/api/paystack-verify.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                reference: reference,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect to confirmation page
            window.location.href = '/cart-checkout.php?confirmed=' + data.order_id + '&payment=paystack';
        } else {
            alert('Payment verification failed: ' + data.message);
            resetPayButton();
        }
    } catch (error) {
        console.error('Payment verification error:', error);
        alert('Failed to verify payment. Please contact support with reference: ' + reference);
        resetPayButton();
    }
}

function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : null;
}

function resetPayButton() {
    const payNowBtn = document.getElementById('pay-now-btn');
    if (payNowBtn) {
        payNowBtn.disabled = false;
        const amount = payNowBtn.getAttribute('data-amount');
        if (amount) {
            payNowBtn.innerHTML = '💳 Pay ₦' + amount + ' Now';
        } else {
            payNowBtn.innerHTML = '💳 Pay Now';
        }
    }
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function getAffiliateCode() {
    const affiliateInput = document.getElementById('affiliate_code');
    if (affiliateInput && affiliateInput.value) {
        return affiliateInput.value.toUpperCase();
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    const urlAff = urlParams.get('aff');
    if (urlAff) {
        return urlAff.toUpperCase();
    }
    
    const affiliateBanner = document.querySelector('[class*="from-green-"] span');
    if (affiliateBanner && affiliateBanner.textContent.includes('Code:')) {
        const match = affiliateBanner.textContent.match(/Code:\s*([A-Z0-9]+)/);
        if (match) {
            return match[1];
        }
    }
    
    return '';
}
