            </main>
            
            <!-- Footer -->
            <footer class="bg-white border-t px-4 lg:px-6 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 md:gap-6">
                    <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> WebDaddy Empire. All rights reserved.</p>
                    <div class="flex items-center gap-4">
                        <?php
                        $whatsappNumber = '';
                        $whatsappClean = '';
                        try {
                            $whatsappNumber = getDb()->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_number'")->fetchColumn() ?: '+2349132672126';
                            $whatsappClean = preg_replace('/[^0-9]/', '', $whatsappNumber);
                        } catch (Exception $e) {
                            $whatsappNumber = '+2349132672126';
                            $whatsappClean = '2349132672126';
                        }
                        ?>
                        <a href="https://wa.me/<?= htmlspecialchars($whatsappClean) ?>?text=Hi,%20I%20need%20support%20with%20my%20WebDaddy%20account" target="_blank" 
                           class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">
                            <i class="bi-whatsapp"></i>
                            <span>WhatsApp Support</span>
                        </a>
                        <span class="text-gray-500 text-sm">📱 <a href="tel:<?= htmlspecialchars($whatsappNumber) ?>" class="text-amber-600 hover:underline font-medium"><?= htmlspecialchars($whatsappNumber) ?></a></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <script>
    function notificationBell() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            loaded: false,
            
            toggle() {
                this.open = !this.open;
                if (this.open && !this.loaded) {
                    this.loadNotifications();
                    if (this.unreadCount > 0) {
                        this.markAllRead();
                    }
                }
            },
            
            async loadNotifications() {
                try {
                    const response = await fetch('/api/customer/notifications.php');
                    const data = await response.json();
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                    this.loaded = true;
                } catch (err) {
                    console.error('Failed to load notifications');
                }
            },
            
            async markAllRead() {
                try {
                    await fetch('/api/customer/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_all_read' })
                    });
                    this.unreadCount = 0;
                } catch (err) {
                    console.error('Failed to mark notifications as read');
                }
            }
        };
    }
    </script>
    
    <!-- Global Event Delegation for Template Details -->
    <script>
    document.addEventListener('click', function(e) {
        // Target specifically template details links
        const templateLink = e.target.closest('a[href*="view=templates-details"]');
        
        if (templateLink) {
            const href = templateLink.getAttribute('href');
            if (href && href.includes('view=templates-details')) {
                // If it's a template detail link, we want to ensure it's handled by our JS
                // instead of a full page reload or being blocked by other scripts.
                
                // Extract slug from href
                const urlParams = new URLSearchParams(href.split('?')[1]);
                const slug = urlParams.get('slug');
                
                if (slug) {
                    // Check if the global opening function exists (from cart-and-tools.js)
                    if (typeof window.openTemplateDetails === 'function') {
                        e.preventDefault();
                        e.stopPropagation();
                        window.openTemplateDetails(slug);
                        return false;
                    }
                }
            }
        }
    }, true); // Use capture phase to ensure we catch it before other listeners
    </script>
</body>
</html>
