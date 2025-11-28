<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title><?php echo $pageTitle ?? 'Admin Panel'; ?> - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: '#d4af37',
                        navy: '#0f172a'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: false, userMenuOpen: false }">
    <nav class="bg-gradient-to-r from-primary-900 via-primary-800 to-primary-900 text-white shadow-lg fixed top-0 left-0 right-0 z-50">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-white hover:text-gold transition-colors p-2 rounded-lg hover:bg-primary-800 flex-shrink-0">
                        <i class="bi bi-list text-xl sm:text-2xl"></i>
                    </button>
                    <a href="/admin/" class="flex items-center space-x-2 sm:space-x-3 group min-w-0">
                        <img src="/assets/images/webdaddy-logo.png" alt="<?php echo SITE_NAME; ?>" class="h-8 sm:h-10 w-auto flex-shrink-0 group-hover:scale-105 transition-transform">
                        <span class="text-base sm:text-xl font-bold text-white group-hover:text-gold transition-colors truncate"><?php echo SITE_NAME; ?> <span class="text-gold text-sm sm:text-xl">Admin</span></span>
                    </a>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-4 flex-shrink-0">
                    <a href="/" target="_blank" class="hidden md:flex items-center space-x-2 px-3 sm:px-4 py-2 rounded-lg hover:bg-primary-800 transition-all group">
                        <i class="bi bi-box-arrow-up-right text-sm group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium text-sm">View Site</span>
                    </a>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 px-4 py-2 rounded-lg hover:bg-primary-800 transition-all group">
                            <i class="bi bi-person-circle text-xl group-hover:text-gold transition-colors"></i>
                            <span class="font-medium hidden sm:inline"><?php echo htmlspecialchars(getAdminName()); ?></span>
                            <i class="bi bi-chevron-down text-xs transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2"
                             style="display: none;">
                            <a href="/admin/profile.php" class="flex items-center space-x-3 px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-colors">
                                <i class="bi bi-person text-lg"></i>
                                <span class="font-medium">Profile & Settings</span>
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="/admin/logout.php" class="flex items-center space-x-3 px-4 py-2 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <i class="bi bi-box-arrow-right text-lg"></i>
                                <span class="font-medium">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-[61px] lg:pt-0">
        <aside 
            x-bind:class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
            class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out lg:translate-x-0 z-30 shadow-xl lg:shadow-none top-[61px] lg:top-0 -translate-x-full overflow-y-auto">
            
            <div class="lg:hidden flex justify-end p-4">
                <button @click="sidebarOpen = false" class="text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>

            <nav class="px-3 py-3 space-y-0.5">
                <!-- Overview Section -->
                <a href="/admin/" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-speedometer2 <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Dashboard</span>
                </a>

                <a href="/admin/analytics.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-bar-chart-line <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Analytics</span>
                </a>

                <a href="/admin/search_analytics.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'search_analytics.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-search <?php echo basename($_SERVER['PHP_SELF']) == 'search_analytics.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Search Analytics</span>
                </a>

                <a href="/admin/reports.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-graph-up <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Reports</span>
                </a>

                <a href="/admin/monitoring.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'monitoring.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-speedometer2 <?php echo basename($_SERVER['PHP_SELF']) == 'monitoring.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">System Monitoring</span>
                </a>

                <!-- Management Section -->
                <div class="pt-4 pb-2">
                    <div class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Management</div>
                </div>

                <a href="/admin/orders.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-cart <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Orders</span>
                </a>

                <a href="/admin/templates.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-grid <?php echo basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Templates</span>
                </a>

                <a href="/admin/tools.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'tools.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-tools <?php echo basename($_SERVER['PHP_SELF']) == 'tools.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Working Tools</span>
                </a>

                <a href="/admin/domains.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'domains.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-globe <?php echo basename($_SERVER['PHP_SELF']) == 'domains.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Domains</span>
                </a>

                <a href="/admin/affiliates.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'affiliates.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-people <?php echo basename($_SERVER['PHP_SELF']) == 'affiliates.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Affiliates</span>
                </a>

                <a href="/admin/commissions.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-wallet2 <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Commissions</span>
                </a>

                <!-- Payments & Delivery Section -->
                <div class="pt-4 pb-2">
                    <div class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Payments & Delivery</div>
                </div>

                <a href="/admin/deliveries.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'deliveries.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-box-seam <?php echo basename($_SERVER['PHP_SELF']) == 'deliveries.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Deliveries</span>
                </a>

                <a href="/admin/tool-files.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'tool-files.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-file-earmark-zip <?php echo basename($_SERVER['PHP_SELF']) == 'tool-files.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Tool Files</span>
                </a>

                <a href="/admin/payment-logs.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'payment-logs.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-receipt <?php echo basename($_SERVER['PHP_SELF']) == 'payment-logs.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Payment Logs</span>
                </a>

                <a href="/admin/export.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'export.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-download <?php echo basename($_SERVER['PHP_SELF']) == 'export.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Export Data</span>
                </a>

                <a href="/admin/support.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-headset <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Support Tickets</span>
                </a>

                <!-- System Section -->
                <div class="pt-4 pb-2">
                    <div class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">System</div>
                </div>

                <a href="/admin/settings.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-gear <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Settings</span>
                </a>

                <a href="/admin/database.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'database.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-database-gear <?php echo basename($_SERVER['PHP_SELF']) == 'database.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Database</span>
                </a>

                <a href="/admin/activity_logs.php" class="flex items-center space-x-2 px-3 py-2.5 rounded-lg transition-all group text-sm <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-clock-history <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Activity Logs</span>
                </a>

                <!-- Logout for Mobile -->
                <div class="border-t border-gray-200 my-2 lg:hidden"></div>
                <a href="/admin/logout.php" class="lg:hidden flex items-center space-x-2 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 transition-all group text-sm">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="font-semibold">Logout</span>
                </a>
            </nav>
        </aside>

        <div x-show="sidebarOpen" 
             @click="sidebarOpen = false"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900 bg-opacity-50 lg:hidden z-20"
             style="display: none;">
        </div>

        <main class="flex-1 lg:ml-0 overflow-x-hidden">
            <div class="p-4 md:p-6 lg:p-8">
