                </div>
        </main>
    </div>

<!-- WhatsApp Floating Button -->
<a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>?text=Hello%2C%20I%20need%20help%20with%20my%20affiliate%20account" 
   target="_blank"
   class="fixed bottom-6 right-6 w-16 h-16 bg-green-500 hover:bg-green-600 text-white rounded-full shadow-2xl flex items-center justify-center text-3xl z-50 transition-all hover:scale-110 animate-pulse"
   title="Contact Admin on WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>

<style>
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    50% { box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); }
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
</body>
</html>
