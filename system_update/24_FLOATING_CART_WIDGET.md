# Floating Cart Widget

## Overview

This document outlines the implementation of a floating cart widget that appears at the bottom-right corner of the screen. The widget ensures users always know what's in their cart and can quickly checkout without needing to find the cart icon in the navigation.

## Problem Statement

**User Feedback:** Not all users realize they need to click the cart icon in the navigation to proceed to checkout. This causes confusion and potentially lost sales.

## Solution

A **persistent floating cart widget** that:
- Shows at the bottom-right corner of the screen
- Displays the number of items in cart
- Shows the total amount
- Provides a quick checkout button
- Is responsive and doesn't obstruct content
- Has smooth animations and premium styling

---

## 1. Design Specifications

### Visual Design

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│                                                         │
│                     PAGE CONTENT                        │
│                                                         │
│                                                         │
│                                                         │
│                                    ┌─────────────────┐  │
│                                    │ 🛒 3 items      │  │
│                                    │ ₦75,000        │  │
│                                    │ [Checkout →]   │  │
│                                    └─────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### States

| State | Appearance |
|-------|------------|
| Empty Cart | Hidden (no need to show) |
| Has Items | Visible with item count, total, checkout button |
| Expanded | Shows mini cart preview (optional) |
| Checkout Page | Hidden (already on checkout) |
| Mobile | Slightly smaller, positioned above other fixed elements |

---

## 2. HTML Structure

```html
<!-- Floating Cart Widget -->
<div id="floating-cart-widget" 
     x-data="floatingCart()"
     x-show="cartCount > 0 && !isCheckoutPage"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     class="floating-cart-widget">
    
    <!-- Main Widget -->
    <div class="cart-widget-main" @click="expanded = !expanded">
        <!-- Cart Icon with Badge -->
        <div class="cart-icon-wrapper">
            <i class="bi bi-bag-fill"></i>
            <span class="cart-badge" x-text="cartCount"></span>
        </div>
        
        <!-- Cart Info -->
        <div class="cart-info">
            <span class="cart-items-text" x-text="`${cartCount} item${cartCount > 1 ? 's' : ''}`"></span>
            <span class="cart-total" x-text="formatCurrency(cartTotal)"></span>
        </div>
        
        <!-- Checkout Button -->
        <a href="/cart-checkout.php" 
           @click.stop
           class="checkout-btn">
            Checkout
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    
    <!-- Expanded Mini Cart (Optional) -->
    <div class="cart-widget-expanded" x-show="expanded" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="mini-cart-header">
            <span class="font-semibold">Your Cart</span>
            <button @click="expanded = false" class="close-btn">
                <i class="bi bi-x"></i>
            </button>
        </div>
        
        <div class="mini-cart-items">
            <template x-for="item in cartItems" :key="item.id">
                <div class="mini-cart-item">
                    <img :src="item.thumbnail" :alt="item.name" class="item-thumb">
                    <div class="item-info">
                        <span class="item-name" x-text="item.name"></span>
                        <span class="item-price" x-text="formatCurrency(item.price)"></span>
                    </div>
                    <button @click.stop="removeItem(item.id)" class="remove-btn">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </template>
        </div>
        
        <div class="mini-cart-footer">
            <div class="flex justify-between mb-3">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-semibold" x-text="formatCurrency(cartTotal)"></span>
            </div>
            <a href="/cart-checkout.php" class="btn btn-primary w-full">
                Proceed to Checkout
            </a>
        </div>
    </div>
</div>
```

---

## 3. CSS Styling

```css
/* Floating Cart Widget - Premium Style */
.floating-cart-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1000;
    font-family: var(--font-primary);
}

/* Ensure it doesn't block content at the very bottom */
@media (max-height: 600px) {
    .floating-cart-widget {
        bottom: 12px;
    }
}

/* Main Widget Container */
.cart-widget-main {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
    border-radius: 16px;
    box-shadow: 
        0 10px 25px -5px rgba(30, 64, 175, 0.4),
        0 4px 10px -5px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cart-widget-main:hover {
    transform: translateY(-2px);
    box-shadow: 
        0 15px 30px -5px rgba(30, 64, 175, 0.5),
        0 6px 15px -5px rgba(0, 0, 0, 0.15);
}

/* Cart Icon */
.cart-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    color: white;
    font-size: 1.125rem;
}

.cart-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: #ef4444;
    color: white;
    font-size: 0.6875rem;
    font-weight: 700;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #1e3a8a;
    animation: badgePop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes badgePop {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* Cart Info */
.cart-info {
    display: flex;
    flex-direction: column;
    color: white;
}

.cart-items-text {
    font-size: 0.75rem;
    opacity: 0.8;
}

.cart-total {
    font-size: 1.125rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

/* Checkout Button */
.checkout-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: white;
    color: #1e3a8a;
    font-weight: 600;
    font-size: 0.9375rem;
    border-radius: 10px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.checkout-btn:hover {
    background: #f0f9ff;
    transform: translateX(2px);
}

.checkout-btn i {
    transition: transform 0.2s ease;
}

.checkout-btn:hover i {
    transform: translateX(3px);
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .floating-cart-widget {
        bottom: 16px;
        right: 16px;
        left: 16px;
    }
    
    .cart-widget-main {
        width: 100%;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: 14px;
    }
    
    .cart-icon-wrapper {
        width: 36px;
        height: 36px;
    }
    
    .cart-total {
        font-size: 1rem;
    }
    
    .checkout-btn {
        padding: 8px 14px;
        font-size: 0.875rem;
    }
}

/* Expanded Mini Cart */
.cart-widget-expanded {
    position: absolute;
    bottom: calc(100% + 12px);
    right: 0;
    width: 320px;
    max-height: 400px;
    background: white;
    border-radius: 16px;
    box-shadow: 
        0 20px 40px -10px rgba(0, 0, 0, 0.15),
        0 0 0 1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

@media (max-width: 640px) {
    .cart-widget-expanded {
        width: calc(100vw - 32px);
        right: 0;
        left: 0;
    }
}

.mini-cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #e5e5e5;
}

.mini-cart-header .close-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: #737373;
    transition: all 0.2s ease;
}

.mini-cart-header .close-btn:hover {
    background: #f5f5f5;
    color: #171717;
}

.mini-cart-items {
    max-height: 220px;
    overflow-y: auto;
    padding: 8px;
}

.mini-cart-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 10px;
    transition: background 0.2s ease;
}

.mini-cart-item:hover {
    background: #fafafa;
}

.mini-cart-item .item-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 8px;
    background: #f5f5f5;
}

.mini-cart-item .item-info {
    flex: 1;
    min-width: 0;
}

.mini-cart-item .item-name {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #171717;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mini-cart-item .item-price {
    font-size: 0.8125rem;
    color: #1e40af;
    font-weight: 600;
}

.mini-cart-item .remove-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: #a3a3a3;
    transition: all 0.2s ease;
}

.mini-cart-item .remove-btn:hover {
    background: #fee2e2;
    color: #ef4444;
}

.mini-cart-footer {
    padding: 16px;
    border-top: 1px solid #e5e5e5;
    background: #fafafa;
}

/* Pulse animation for attention */
@keyframes cartPulse {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(30, 64, 175, 0.4); }
    50% { box-shadow: 0 10px 25px -5px rgba(30, 64, 175, 0.6); }
}

.cart-widget-main.pulse {
    animation: cartPulse 2s infinite;
}
```

---

## 4. JavaScript Implementation

```javascript
/**
 * Floating Cart Widget Alpine.js Component
 */
function floatingCart() {
    return {
        cartCount: 0,
        cartTotal: 0,
        cartItems: [],
        expanded: false,
        isCheckoutPage: window.location.pathname.includes('cart-checkout'),
        
        init() {
            // Load cart data
            this.loadCart();
            
            // Listen for cart updates
            window.addEventListener('cart-updated', () => {
                this.loadCart();
                this.pulseWidget();
            });
            
            // Check if on checkout page
            this.isCheckoutPage = window.location.pathname.includes('cart-checkout');
            
            // Close expanded view when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#floating-cart-widget')) {
                    this.expanded = false;
                }
            });
        },
        
        async loadCart() {
            try {
                const response = await fetch('/api/cart.php');
                const data = await response.json();
                
                if (data.success) {
                    this.cartCount = data.count || 0;
                    this.cartTotal = data.total || 0;
                    this.cartItems = data.items || [];
                }
            } catch (error) {
                console.error('Failed to load cart:', error);
            }
        },
        
        async removeItem(itemId) {
            try {
                const response = await fetch('/api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'remove', item_id: itemId })
                });
                
                if (response.ok) {
                    this.loadCart();
                    window.dispatchEvent(new CustomEvent('cart-updated'));
                }
            } catch (error) {
                console.error('Failed to remove item:', error);
            }
        },
        
        pulseWidget() {
            const widget = document.querySelector('.cart-widget-main');
            if (widget) {
                widget.classList.add('pulse');
                setTimeout(() => widget.classList.remove('pulse'), 2000);
            }
        },
        
        formatCurrency(amount) {
            return '₦' + Number(amount).toLocaleString();
        }
    };
}
```

---

## 5. PHP Integration

### API Endpoint Update

```php
// api/cart.php - Add endpoint for floating cart
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();

header('Content-Type: application/json');

// GET - Fetch cart data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cartItems = getCart();
    $totals = getCartTotal();
    
    $items = [];
    foreach ($cartItems as $item) {
        $items[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'price' => $item['price'],
            'thumbnail' => $item['thumbnail_url'] ?? '/assets/placeholder.png',
            'type' => $item['type']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($cartItems),
        'total' => $totals['total'] ?? 0,
        'items' => $items
    ]);
    exit;
}

// POST - Cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'remove':
            $itemId = $input['item_id'] ?? null;
            if ($itemId) {
                removeFromCart($itemId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Item ID required']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}
```

### Include in Pages

```php
// In index.php, template.php, etc. - before closing </body>
<?php include __DIR__ . '/includes/floating-cart-widget.php'; ?>
```

### Widget Include File

```php
<!-- includes/floating-cart-widget.php -->
<?php
// Don't show on checkout page
$isCheckoutPage = strpos($_SERVER['REQUEST_URI'], 'cart-checkout') !== false;
$cartCount = getCartCount();
?>

<?php if (!$isCheckoutPage): ?>
<div id="floating-cart-widget" 
     x-data="floatingCart()"
     x-show="cartCount > 0"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-cloak
     class="floating-cart-widget">
    
    <div class="cart-widget-main">
        <div class="cart-icon-wrapper">
            <i class="bi bi-bag-fill"></i>
            <span class="cart-badge" x-text="cartCount"><?= $cartCount ?></span>
        </div>
        
        <div class="cart-info">
            <span class="cart-items-text" x-text="`${cartCount} item${cartCount > 1 ? 's' : ''}`">
                <?= $cartCount ?> item<?= $cartCount > 1 ? 's' : '' ?>
            </span>
            <span class="cart-total" x-text="formatCurrency(cartTotal)">
                ₦<?= number_format(getCartTotal()['total'] ?? 0) ?>
            </span>
        </div>
        
        <a href="/cart-checkout.php" class="checkout-btn">
            Checkout
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<script>
<?php include __DIR__ . '/../assets/js/floating-cart.js'; ?>
</script>
<?php endif; ?>
```

---

## 6. Accessibility

```html
<!-- Accessible version -->
<div id="floating-cart-widget" 
     role="complementary"
     aria-label="Shopping cart">
    
    <div class="cart-widget-main" 
         role="button"
         tabindex="0"
         aria-expanded="false"
         aria-controls="mini-cart-dropdown"
         @keydown.enter="expanded = !expanded"
         @keydown.escape="expanded = false">
        
        <div class="cart-icon-wrapper" aria-hidden="true">
            <i class="bi bi-bag-fill"></i>
            <span class="cart-badge" x-text="cartCount"></span>
        </div>
        
        <div class="cart-info">
            <span class="sr-only">Cart contains</span>
            <span x-text="`${cartCount} items`"></span>
            <span class="sr-only">Total:</span>
            <span x-text="formatCurrency(cartTotal)"></span>
        </div>
        
        <a href="/cart-checkout.php" 
           class="checkout-btn"
           aria-label="Go to checkout">
            Checkout
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>

<style>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
```

---

## 7. Implementation Checklist

### Phase 1: Core Widget
- [ ] Create HTML structure
- [ ] Add CSS styling
- [ ] Implement Alpine.js component
- [ ] Create API endpoint

### Phase 2: Integration
- [ ] Add widget include to index.php
- [ ] Add widget include to template.php
- [ ] Hide on checkout page
- [ ] Test cart synchronization

### Phase 3: Polish
- [ ] Add animations
- [ ] Implement pulse effect on add
- [ ] Mobile responsiveness
- [ ] Accessibility audit

### Phase 4: Optional Features
- [ ] Mini cart expansion
- [ ] Remove item functionality
- [ ] Item thumbnail display
- [ ] Discount code preview

---

## Related Documents

- [03_CHECKOUT_FLOW.md](./03_CHECKOUT_FLOW.md) - Checkout process
- [09_FRONTEND_CHANGES.md](./09_FRONTEND_CHANGES.md) - Frontend structure
- [23_UI_UX_PREMIUM_UPGRADE.md](./23_UI_UX_PREMIUM_UPGRADE.md) - Premium design
