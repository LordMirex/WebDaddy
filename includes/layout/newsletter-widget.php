<?php
/**
 * Newsletter Widget
 * Lightweight newsletter signup form for footer/sidebar
 * Non-intrusive, can be displayed anywhere
 */
?>
<!-- Newsletter Signup Widget -->
<div id="newsletter-widget" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg p-6 my-6 shadow-lg">
    <h3 class="text-lg font-bold mb-2">
        <i class="bi bi-envelope-heart mr-2"></i>Stay Updated
    </h3>
    <p class="text-sm mb-4 opacity-90">Get weekly tips and exclusive offers delivered to your inbox</p>
    
    <form id="newsletter-form" class="space-y-3">
        <input type="email" 
               name="email" 
               placeholder="Your email address" 
               required
               class="w-full px-4 py-2 rounded text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-white focus:outline-none">
        
        <input type="text" 
               name="name" 
               placeholder="Your name (optional)" 
               class="w-full px-4 py-2 rounded text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-white focus:outline-none">
        
        <button type="submit" 
                class="w-full bg-white text-orange-600 font-bold py-2 rounded hover:bg-gray-100 transition-colors">
            Subscribe Now
        </button>
    </form>
    
    <p class="text-xs mt-3 opacity-75">✓ We respect your privacy. No spam, ever.</p>
</div>

<script>
document.getElementById('newsletter-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'newsletter_subscribe');
    
    try {
        const response = await fetch('/includes/monetization/newsletter.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            form.innerHTML = '<div class="text-center"><p class="font-bold">✓ Welcome aboard!</p><p class="text-sm mt-2">Check your email for a welcome message</p></div>';
            setTimeout(() => form.parentElement.classList.add('opacity-75'), 2000);
        } else {
            alert(result.message || 'Error subscribing');
        }
    } catch (error) {
        console.error('Newsletter signup error:', error);
        alert('Error subscribing. Please try again.');
    }
});
</script>

<style>
#newsletter-widget {
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
