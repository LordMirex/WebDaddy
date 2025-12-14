<?php
$isCheckoutPage = strpos($_SERVER['REQUEST_URI'], 'cart-checkout') !== false;
$cartCount = getCartCount();
$cartTotals = getCartTotal();
$cartTotal = $cartTotals['total'] ?? 0;

if ($isCheckoutPage || $cartCount <= 0) return;
?>

<div id="floating-cart-widget" class="fixed bottom-6 right-6 z-[1000] font-sans sm:bottom-4 sm:right-4">
    <div class="cart-widget-main flex items-center gap-3 px-4 py-3 bg-gradient-to-br from-blue-800 to-blue-900 rounded-2xl shadow-lg cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl sm:px-3.5 sm:py-2.5 sm:rounded-xl"
         style="box-shadow: 0 10px 25px -5px rgba(30, 64, 175, 0.4), 0 4px 10px -5px rgba(0, 0, 0, 0.1);">
        
        <div class="relative flex items-center justify-center w-10 h-10 bg-white/15 rounded-xl text-white text-lg sm:w-9 sm:h-9">
            <i class="bi bi-bag-fill"></i>
            <span id="fcw-count" class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1.5 bg-red-500 text-white text-[11px] font-bold rounded-full flex items-center justify-center border-2 border-blue-900">
                <?= $cartCount ?>
            </span>
        </div>
        
        <div class="flex flex-col text-white">
            <span id="fcw-text" class="text-xs opacity-80">
                <?= $cartCount ?> item<?= $cartCount > 1 ? 's' : '' ?>
            </span>
            <span id="fcw-total" class="text-lg font-bold tracking-tight sm:text-base">
                ₦<?= number_format($cartTotal) ?>
            </span>
        </div>
        
        <a href="/cart-checkout.php" 
           class="flex items-center gap-1.5 px-4 py-2.5 bg-white text-blue-900 font-semibold text-sm rounded-lg transition-all duration-150 hover:bg-blue-50 hover:translate-x-0.5 no-underline sm:px-3.5 sm:py-2 sm:text-[13px]">
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
