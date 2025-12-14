<?php
$isCheckoutPage = strpos($_SERVER['REQUEST_URI'], 'cart-checkout') !== false;
$cartCount = getCartCount();
$cartTotals = getCartTotal();
$cartTotal = $cartTotals['total'] ?? 0;

if ($isCheckoutPage || $cartCount <= 0) return;
?>
<style>
.floating-cart-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1000;
    font-family: inherit;
}
.cart-widget-main {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
    border-radius: 16px;
    box-shadow: 0 10px 25px -5px rgba(30, 64, 175, 0.4), 0 4px 10px -5px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.2s ease;
}
.cart-widget-main:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px -5px rgba(30, 64, 175, 0.5), 0 6px 15px -5px rgba(0, 0, 0, 0.15);
}
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
}
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
.checkout-btn-float {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: white;
    color: #1e3a8a;
    font-weight: 600;
    font-size: 0.9375rem;
    border-radius: 10px;
    transition: all 0.15s ease;
    text-decoration: none;
}
.checkout-btn-float:hover {
    background: #f0f9ff;
    transform: translateX(2px);
    color: #1e3a8a;
    text-decoration: none;
}
@media (max-width: 640px) {
    .floating-cart-widget {
        bottom: 16px;
        right: 16px;
        left: auto;
    }
    .cart-widget-main {
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
    .checkout-btn-float {
        padding: 8px 14px;
        font-size: 0.875rem;
    }
}
</style>

<div id="floating-cart-widget" class="floating-cart-widget">
    <div class="cart-widget-main">
        <div class="cart-icon-wrapper">
            <i class="bi bi-bag-fill"></i>
            <span class="cart-badge" id="fcw-count"><?= $cartCount ?></span>
        </div>
        
        <div class="cart-info">
            <span class="cart-items-text" id="fcw-text"><?= $cartCount ?> item<?= $cartCount > 1 ? 's' : '' ?></span>
            <span class="cart-total" id="fcw-total">₦<?= number_format($cartTotal) ?></span>
        </div>
        
        <a href="/cart-checkout.php" class="checkout-btn-float">
            Checkout
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<script>
(function() {
    const widget = document.getElementById('floating-cart-widget');
    const countEl = document.getElementById('fcw-count');
    const textEl = document.getElementById('fcw-text');
    const totalEl = document.getElementById('fcw-total');
    
    function updateWidget(count, total) {
        if (count <= 0) {
            widget.style.display = 'none';
            return;
        }
        widget.style.display = 'block';
        countEl.textContent = count;
        textEl.textContent = count + ' item' + (count > 1 ? 's' : '');
        totalEl.textContent = '₦' + Number(total).toLocaleString();
    }
    
    window.addEventListener('cart-updated', async () => {
        try {
            const response = await fetch('/api/cart-data.php');
            const data = await response.json();
            if (data.success) {
                updateWidget(data.count || 0, data.total || 0);
            }
        } catch (e) {}
    });
})();
</script>
