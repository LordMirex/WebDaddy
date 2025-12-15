            </main>
            
            <!-- Footer -->
            <footer class="bg-white border-t px-4 lg:px-6 py-4 text-center text-sm text-gray-500">
                <p>&copy; <?= date('Y') ?> WebDaddy Empire. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <script>
    function notificationBell() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            
            init() {
                this.loadNotifications();
                setInterval(() => this.loadNotifications(), 30000);
            },
            
            toggle() {
                this.open = !this.open;
                if (this.open) {
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
</body>
</html>
