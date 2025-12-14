# Index Page User Profile Integration

## Overview

This document outlines how to display logged-in user information on the index page (landing page) and throughout the public-facing site. When a user is logged in, they should see their profile/account link instead of a generic "Sign In" link, creating a personalized and seamless experience.

## Goals

1. **Personalization** - Logged-in users feel recognized
2. **Easy Access** - Quick navigation to dashboard
3. **Seamless Experience** - No jarring transitions between public and private areas
4. **Non-Intrusive** - Doesn't disrupt the shopping experience

---

## 1. User State Detection

### PHP Session Check

```php
// includes/customer_session_check.php
<?php
/**
 * Get current customer info for public pages
 * Returns null if not logged in (no redirect)
 */
function getPublicPageCustomer() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    
    // Check if customer session exists
    if (empty($_SESSION['customer_id'])) {
        return null;
    }
    
    $db = getDb();
    $stmt = $db->prepare("
        SELECT id, email, username, full_name, avatar_url
        FROM customers 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Session exists but customer not found - clear session
        unset($_SESSION['customer_id']);
        return null;
    }
    
    return $customer;
}

/**
 * Get customer display name
 */
function getCustomerDisplayName($customer) {
    if (!$customer) return null;
    
    if (!empty($customer['full_name'])) {
        $parts = explode(' ', $customer['full_name']);
        return $parts[0]; // First name only
    }
    
    if (!empty($customer['username'])) {
        return $customer['username'];
    }
    
    // Fallback to email prefix
    return explode('@', $customer['email'])[0];
}

/**
 * Get customer initials for avatar
 */
function getCustomerInitials($customer) {
    if (!$customer) return '?';
    
    if (!empty($customer['full_name'])) {
        $parts = explode(' ', $customer['full_name']);
        if (count($parts) >= 2) {
            return strtoupper($parts[0][0] . $parts[1][0]);
        }
        return strtoupper($parts[0][0]);
    }
    
    if (!empty($customer['username'])) {
        return strtoupper($customer['username'][0]);
    }
    
    return strtoupper($customer['email'][0]);
}
```

### JavaScript Session Check

```javascript
/**
 * Check customer session via API
 * For dynamic updates without page reload
 */
async function checkCustomerSession() {
    try {
        const response = await fetch('/api/customer/session.php');
        const data = await response.json();
        
        if (data.success && data.customer) {
            return {
                id: data.customer.id,
                name: data.customer.display_name,
                email: data.customer.email,
                initials: data.customer.initials,
                avatar: data.customer.avatar_url
            };
        }
        
        return null;
    } catch (error) {
        console.error('Session check failed:', error);
        return null;
    }
}
```

### Session API Endpoint

```php
// api/customer/session.php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_session_check.php';

startSecureSession();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$customer = getPublicPageCustomer();

if ($customer) {
    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'display_name' => getCustomerDisplayName($customer),
            'initials' => getCustomerInitials($customer),
            'avatar_url' => $customer['avatar_url']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'customer' => null
    ]);
}
```

---

## 2. Navigation Integration

### Desktop Navigation

```php
<!-- In index.php navigation section -->
<?php
require_once __DIR__ . '/includes/customer_session_check.php';
$currentCustomer = getPublicPageCustomer();
?>

<nav class="hidden md:flex items-center space-x-6">
    <a href="#templates" class="nav-link">Templates</a>
    <a href="#tools" class="nav-link">Tools</a>
    <a href="#about" class="nav-link">About</a>
    
    <!-- User Account Section -->
    <div class="user-nav-section">
        <?php if ($currentCustomer): ?>
            <!-- Logged In User -->
            <div class="user-dropdown" x-data="{ open: false }">
                <button @click="open = !open" 
                        @click.outside="open = false"
                        class="user-trigger">
                    <div class="user-avatar">
                        <?php if (!empty($currentCustomer['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($currentCustomer['avatar_url']) ?>" 
                                 alt="Profile">
                        <?php else: ?>
                            <span><?= getCustomerInitials($currentCustomer) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="user-name">
                        <?= htmlspecialchars(getCustomerDisplayName($currentCustomer)) ?>
                    </span>
                    <i class="bi bi-chevron-down text-xs"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="user-dropdown-menu">
                    <div class="dropdown-header">
                        <span class="text-sm font-medium"><?= htmlspecialchars(getCustomerDisplayName($currentCustomer)) ?></span>
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($currentCustomer['email']) ?></span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="/user/" class="dropdown-item">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                    <a href="/user/orders.php" class="dropdown-item">
                        <i class="bi bi-bag"></i> My Orders
                    </a>
                    <a href="/user/downloads.php" class="dropdown-item">
                        <i class="bi bi-download"></i> Downloads
                    </a>
                    <a href="/user/profile.php" class="dropdown-item">
                        <i class="bi bi-person"></i> Profile Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/user/logout.php" class="dropdown-item text-red-600">
                        <i class="bi bi-box-arrow-right"></i> Sign Out
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Not Logged In -->
            <a href="/user/login.php" class="login-link">
                <i class="bi bi-person"></i>
                <span>Sign In</span>
            </a>
        <?php endif; ?>
    </div>
</nav>
```

### Mobile Navigation

```php
<!-- Mobile Menu -->
<div class="mobile-menu" x-show="mobileMenuOpen" x-cloak>
    <div class="mobile-menu-inner">
        <!-- User Section at Top -->
        <div class="mobile-user-section">
            <?php if ($currentCustomer): ?>
                <div class="mobile-user-header">
                    <div class="user-avatar large">
                        <?php if (!empty($currentCustomer['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($currentCustomer['avatar_url']) ?>" alt="">
                        <?php else: ?>
                            <span><?= getCustomerInitials($currentCustomer) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars(getCustomerDisplayName($currentCustomer)) ?></span>
                        <span class="user-email"><?= htmlspecialchars($currentCustomer['email']) ?></span>
                    </div>
                </div>
                <div class="mobile-user-links">
                    <a href="/user/" class="mobile-link">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                    <a href="/user/orders.php" class="mobile-link">
                        <i class="bi bi-bag"></i> My Orders
                    </a>
                    <a href="/user/downloads.php" class="mobile-link">
                        <i class="bi bi-download"></i> Downloads
                    </a>
                </div>
            <?php else: ?>
                <div class="mobile-login-prompt">
                    <p class="text-gray-600 mb-3">Sign in to track your orders</p>
                    <a href="/user/login.php" class="btn btn-primary w-full">
                        Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mobile-menu-divider"></div>
        
        <!-- Main Links -->
        <nav class="mobile-nav-links">
            <a href="#templates">Templates</a>
            <a href="#tools">Tools</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
        </nav>
        
        <?php if ($currentCustomer): ?>
            <div class="mobile-menu-divider"></div>
            <a href="/user/logout.php" class="mobile-logout-link">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        <?php endif; ?>
    </div>
</div>
```

---

## 3. CSS Styling

```css
/* User Navigation Styles */

/* User Trigger Button */
.user-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px 6px 6px;
    background: transparent;
    border: 1px solid var(--neutral-200);
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-trigger:hover {
    background: var(--neutral-50);
    border-color: var(--neutral-300);
}

/* User Avatar */
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-700) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 600;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar.large {
    width: 48px;
    height: 48px;
    font-size: 1rem;
}

/* User Name */
.user-name {
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--neutral-700);
}

/* Dropdown Menu */
.user-dropdown {
    position: relative;
}

.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 240px;
    background: white;
    border: 1px solid var(--neutral-200);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    z-index: 100;
}

.dropdown-header {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.dropdown-divider {
    height: 1px;
    background: var(--neutral-200);
    margin: 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    font-size: 0.9375rem;
    color: var(--neutral-700);
    transition: all 0.15s ease;
}

.dropdown-item:hover {
    background: var(--neutral-50);
    color: var(--primary-600);
}

.dropdown-item i {
    width: 18px;
    text-align: center;
    color: var(--neutral-400);
}

.dropdown-item:hover i {
    color: var(--primary-500);
}

.dropdown-item.text-red-600 {
    color: #dc2626;
}

.dropdown-item.text-red-600:hover {
    background: #fef2f2;
}

.dropdown-item.text-red-600 i {
    color: #dc2626;
}

/* Login Link (Not Logged In) */
.login-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--neutral-600);
    border: 1px solid var(--neutral-200);
    border-radius: 9999px;
    transition: all 0.2s ease;
}

.login-link:hover {
    color: var(--primary-600);
    border-color: var(--primary-300);
    background: var(--primary-50);
}

/* Mobile User Section */
.mobile-user-section {
    padding: 20px;
    background: var(--neutral-50);
}

.mobile-user-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.mobile-user-header .user-info {
    display: flex;
    flex-direction: column;
}

.mobile-user-header .user-name {
    font-weight: 600;
}

.mobile-user-header .user-email {
    font-size: 0.8125rem;
    color: var(--neutral-500);
}

.mobile-user-links {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.mobile-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    font-size: 0.9375rem;
    color: var(--neutral-700);
    border-radius: 8px;
    transition: background 0.15s ease;
}

.mobile-link:hover {
    background: rgba(255, 255, 255, 0.8);
}

.mobile-link i {
    width: 20px;
    text-align: center;
    color: var(--neutral-400);
}

.mobile-login-prompt {
    text-align: center;
}

.mobile-logout-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    font-size: 0.9375rem;
    color: #dc2626;
}
```

---

## 4. Welcome Message Component (Optional)

### Personalized Greeting

```html
<!-- Welcome Banner for Logged-In Users -->
<?php if ($currentCustomer): ?>
<div class="welcome-banner" x-data="{ dismissed: false }" x-show="!dismissed">
    <div class="container mx-auto px-6">
        <div class="welcome-content">
            <span class="welcome-text">
                Welcome back, <strong><?= htmlspecialchars(getCustomerDisplayName($currentCustomer)) ?></strong>! 
                Ready to find your next template?
            </span>
            <div class="welcome-actions">
                <a href="/user/orders.php" class="welcome-link">
                    <i class="bi bi-bag-check"></i> View Orders
                </a>
                <button @click="dismissed = true" class="welcome-dismiss">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.welcome-banner {
    background: linear-gradient(90deg, var(--primary-600) 0%, var(--primary-700) 100%);
    color: white;
    padding: 10px 0;
}

.welcome-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.welcome-text {
    font-size: 0.9375rem;
}

.welcome-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-link {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.9);
    transition: color 0.15s ease;
}

.welcome-link:hover {
    color: white;
}

.welcome-dismiss {
    padding: 4px;
    color: rgba(255, 255, 255, 0.7);
    transition: color 0.15s ease;
}

.welcome-dismiss:hover {
    color: white;
}

@media (max-width: 640px) {
    .welcome-content {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
}
</style>
```

---

## 5. Alpine.js Dynamic Component

```javascript
/**
 * User navigation component
 * For pages that need dynamic user state updates
 */
function userNav() {
    return {
        customer: null,
        dropdownOpen: false,
        loading: true,
        
        async init() {
            await this.loadCustomer();
            this.loading = false;
        },
        
        async loadCustomer() {
            this.customer = await checkCustomerSession();
        },
        
        getInitials() {
            if (!this.customer?.name) return '?';
            const parts = this.customer.name.split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return parts[0][0].toUpperCase();
        },
        
        getDisplayName() {
            if (!this.customer?.name) return 'User';
            return this.customer.name.split(' ')[0];
        },
        
        async logout() {
            try {
                await fetch('/user/logout.php', { method: 'POST' });
                this.customer = null;
                window.location.reload();
            } catch (error) {
                console.error('Logout failed:', error);
            }
        }
    };
}
```

---

## 6. Implementation Checklist

### Phase 1: Backend
- [ ] Create customer_session_check.php
- [ ] Create session API endpoint
- [ ] Test session detection

### Phase 2: Desktop Navigation
- [ ] Add user dropdown to index.php
- [ ] Add to template.php
- [ ] Style dropdown menu
- [ ] Test logged-in state

### Phase 3: Mobile Navigation
- [ ] Add user section to mobile menu
- [ ] Style mobile user display
- [ ] Test responsive behavior

### Phase 4: Polish
- [ ] Add welcome banner (optional)
- [ ] Animate dropdown
- [ ] Test logout flow
- [ ] Accessibility check

---

## Related Documents

- [02_CUSTOMER_AUTH.md](./02_CUSTOMER_AUTH.md) - Authentication system
- [04_USER_DASHBOARD.md](./04_USER_DASHBOARD.md) - Dashboard
- [09_FRONTEND_CHANGES.md](./09_FRONTEND_CHANGES.md) - Frontend updates
- [23_UI_UX_PREMIUM_UPGRADE.md](./23_UI_UX_PREMIUM_UPGRADE.md) - Design system
- [24_FLOATING_CART_WIDGET.md](./24_FLOATING_CART_WIDGET.md) - Cart widget
