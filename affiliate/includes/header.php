<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title><?php echo $pageTitle ?? 'Affiliate Portal'; ?> - <?php echo SITE_NAME; ?></title>
    
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
    <!-- Top Navigation Bar -->
    <nav class="bg-gradient-to-r from-primary-900 via-primary-800 to-primary-900 text-white shadow-lg sticky top-0 z-40">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Mobile Menu Button & Logo -->
                <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-white hover:text-gold transition-colors p-2 rounded-lg hover:bg-primary-800 flex-shrink-0">
                        <i class="bi bi-list text-xl sm:text-2xl"></i>
                    </button>
                    <a href="/affiliate/" class="flex items-center space-x-2 group min-w-0">
                        <i class="bi bi-cash-stack text-xl sm:text-2xl text-gold group-hover:scale-110 transition-transform flex-shrink-0"></i>
                        <span class="text-base sm:text-xl font-bold text-white group-hover:text-gold transition-colors truncate"><?php echo SITE_NAME; ?> <span class="text-gold text-sm sm:text-xl">Affiliate</span></span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="flex items-center space-x-2 sm:space-x-4 flex-shrink-0">
                    <!-- View Site Link -->
                    <a href="/" target="_blank" class="hidden md:flex items-center space-x-2 px-3 sm:px-4 py-2 rounded-lg hover:bg-primary-800 transition-all group">
                        <i class="bi bi-box-arrow-up-right text-sm group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium text-sm">View Site</span>
                    </a>
                    
                    <!-- Support Link -->
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER ?? ''); ?>?text=Hi%2C%20I%20need%20support%20with%20my%20affiliate%20account" 
                       target="_blank" 
                       class="hidden md:flex items-center space-x-2 px-3 sm:px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 transition-all group">
                        <i class="bi bi-whatsapp text-sm group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium text-sm">Support</span>
                    </a>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 px-4 py-2 rounded-lg hover:bg-primary-800 transition-all group">
                            <i class="bi bi-person-circle text-xl group-hover:text-gold transition-colors"></i>
                            <span class="font-medium hidden sm:inline"><?php echo htmlspecialchars(getAffiliateName()); ?></span>
                            <i class="bi bi-chevron-down text-xs transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
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
                            <a href="/affiliate/logout.php" class="flex items-center space-x-3 px-4 py-2 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <i class="bi bi-box-arrow-right text-lg"></i>
                                <span class="font-medium">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen">
        <!-- Sidebar - Desktop: Always visible, Mobile: Slide-in drawer -->
        <aside 
            x-bind:class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
            class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out lg:translate-x-0 z-50 shadow-xl lg:shadow-none mt-[57px] lg:mt-0 -translate-x-full">
            
            <!-- Close button for mobile -->
            <div class="lg:hidden flex justify-end p-4">
                <button @click="sidebarOpen = false" class="text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="px-3 py-4 space-y-1">
                <a href="/affiliate/" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all group <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-speedometer2 text-lg <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Dashboard</span>
                </a>

                <a href="/affiliate/earnings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all group <?php echo basename($_SERVER['PHP_SELF']) == 'earnings.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-currency-dollar text-lg <?php echo basename($_SERVER['PHP_SELF']) == 'earnings.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Earnings History</span>
                </a>

                <a href="/affiliate/withdrawals.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all group <?php echo basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-wallet2 text-lg <?php echo basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Withdrawals</span>
                </a>

                <a href="/affiliate/tools.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all group <?php echo basename($_SERVER['PHP_SELF']) == 'tools.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-box-seam text-lg <?php echo basename($_SERVER['PHP_SELF']) == 'tools.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Marketing Tools</span>
                </a>

                <a href="/affiliate/settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all group <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-md' : 'text-gray-700 hover:bg-primary-50 hover:text-primary-700'; ?>">
                    <i class="bi bi-gear text-lg <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-gold' : 'group-hover:text-primary-600'; ?>"></i>
                    <span class="font-semibold">Settings</span>
                </a>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-3"></div>

                <!-- Logout - Mobile only (desktop has it in dropdown) -->
                <a href="/affiliate/logout.php" class="lg:hidden flex items-center space-x-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-all group">
                    <i class="bi bi-box-arrow-right text-lg"></i>
                    <span class="font-semibold">Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Overlay for mobile sidebar -->
        <div x-show="sidebarOpen" 
             @click="sidebarOpen = false"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900 bg-opacity-50 lg:hidden z-40"
             style="display: none;">
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 lg:ml-0 overflow-x-hidden">
            <div class="p-4 md:p-6 lg:p-8">
