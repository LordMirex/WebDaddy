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
    (function() {
        // Universal click handler for template details
        function handleTemplateClick(e) {
            const target = e.target.closest('a[href*="view=templates-details"], .btn-template-details, [data-template-id]');
            if (!target) return;
            
            // Skip if it's already being handled or has specific skip markers
            if (target.dataset.navProcessed) return;

            const href = target.getAttribute('href') || '';
            const slug = target.getAttribute('data-slug') || (href.match(/[?&]slug=([^&]+)/) || [])[1];
            
            if (slug) {
                // Try to use the modern popup method first
                if (typeof window.openTemplateDetails === 'function') {
                    e.preventDefault();
                    e.stopPropagation();
                    window.openTemplateDetails(slug);
                    return false;
                }
            }
        }

        // Attach to document with useCapture=true to intercept before smooth-navigation
        document.addEventListener('click', handleTemplateClick, true);
        
        // Mark footer links as processed so smooth-navigation skips them
        window.addEventListener('load', function() {
            document.querySelectorAll('footer a, .footer a').forEach(function(el) {
                el.dataset.navProcessed = 'true';
            });
        });
    })();
    </script>
</body>
</html>
