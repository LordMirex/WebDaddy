const CustomerAuth = {
    async checkSession() {
        try {
            const response = await fetch('/api/customer/profile.php', {
                method: 'GET',
                credentials: 'include'
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    return data.customer;
                }
            }
        } catch (e) {
            console.error('CustomerAuth.checkSession error:', e);
        }
        return null;
    },

    async checkEmail(email) {
        try {
            const response = await fetch('/api/customer/check-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ email })
            });
            
            const data = await response.json();
            
            if (response.status === 429) {
                return { success: false, error: data.error || 'Too many requests. Please wait a moment.' };
            }
            
            if (!response.ok && !data.success) {
                return { success: false, error: data.error || 'Server error. Please try again.' };
            }
            
            return data;
        } catch (e) {
            console.error('CustomerAuth.checkEmail error:', e);
            return { success: false, error: 'Connection failed. Please check your internet and try again.' };
        }
    },

    async requestOTP(email, phone = null) {
        try {
            const body = { email };
            if (phone) body.phone = phone;
            
            const response = await fetch('/api/customer/request-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(body)
            });
            
            const data = await response.json();
            
            if (response.status === 429) {
                return { success: false, error: data.error || 'Too many OTP requests. Please wait before trying again.' };
            }
            
            return data;
        } catch (e) {
            console.error('CustomerAuth.requestOTP error:', e);
            return { success: false, error: 'Connection failed. Please check your internet and try again.' };
        }
    },

    async verifyOTP(email, code) {
        try {
            const response = await fetch('/api/customer/verify-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ email, code })
            });
            return await response.json();
        } catch (e) {
            console.error('CustomerAuth.verifyOTP error:', e);
            return { success: false, error: 'Network error. Please try again.' };
        }
    },

    async login(email, password) {
        try {
            const response = await fetch('/api/customer/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ email, password })
            });
            
            if (response.status === 401) {
                alert('Session expired. Please log in again.');
                location.reload();
                return { success: false, error: 'Session expired' };
            }
            
            return await response.json();
        } catch (e) {
            console.error('CustomerAuth.login error:', e);
            return { success: false, error: 'Network error. Please try again.' };
        }
    },

    async logout() {
        try {
            const response = await fetch('/api/customer/logout.php', {
                method: 'POST',
                credentials: 'include'
            });
            return await response.json();
        } catch (e) {
            console.error('CustomerAuth.logout error:', e);
            return { success: false, error: 'Network error. Please try again.' };
        }
    }
};

async function checkCustomerSession() {
    return await CustomerAuth.checkSession();
}

if (typeof window !== 'undefined') {
    window.CustomerAuth = CustomerAuth;
    window.checkCustomerSession = checkCustomerSession;
}
