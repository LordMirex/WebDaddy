<?php
// Security Headers - Protect customer portal
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com https://instant.page https://js.paystack.co https://paystack.com https://cdn.quilljs.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://fonts.googleapis.com https://cdn.quilljs.com https://paystack.com https://*.paystack.com; style-src-elem 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://fonts.googleapis.com https://cdn.quilljs.com https://paystack.com https://*.paystack.com; img-src 'self' data: https:; font-src 'self' https: data: https://cdn.jsdelivr.net; frame-src https://www.youtube.com https://www.youtube-nocookie.com https://checkout.paystack.com https://js.paystack.co https://paystack.com https://*.paystack.com; connect-src 'self' https: wss:");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$page = $page ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';

$pendingOrders = getCustomerOrderCount($customer['id'], 'pending');
$openTickets = getCustomerOpenTickets($customer['id']);

$navItems = [
    ['url' => '/user/', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ['url' => '/user/orders.php', 'icon' => 'bi-bag', 'label' => 'My Orders', 'page' => 'orders', 'badge' => $pendingOrders > 0 ? $pendingOrders : null],
    ['url' => '/user/downloads.php', 'icon' => 'bi-download', 'label' => 'Downloads', 'page' => 'downloads'],
    ['url' => '/user/referral.php', 'icon' => 'bi-gift', 'label' => 'Refer & Earn', 'page' => 'referral'],
    ['url' => '/user/support.php', 'icon' => 'bi-chat-dots', 'label' => 'Support', 'page' => 'support', 'badge' => $openTickets > 0 ? $openTickets : null],
    ['url' => '/user/profile.php', 'icon' => 'bi-person', 'label' => 'Profile', 'page' => 'profile'],
    ['url' => '/user/security.php', 'icon' => 'bi-shield-lock', 'label' => 'Security', 'page' => 'security'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - WebDaddy Empire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/premium.css?v=<?= time() ?>">
    <script defer src="/assets/alpine.csp.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/assets/images/favicon.png" type="image/png">
    <style>
        [x-cloak] { display: none !important; }
        .gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #F4E4A6 50%, #D4AF37 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div x-data="userSidebar" class="flex min-h-screen">
        <!-- Mobile sidebar backdrop -->
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" 
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>
        
        <!-- Sidebar -->
        <aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" 
               class="fixed lg:static inset-y-0 left-0 w-64 bg-slate-900 text-white z-50 transform transition-transform -translate-x-full lg:translate-x-0">
            <div class="p-4 border-b border-slate-700">
                <a href="/" class="flex items-center space-x-3">
                    <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy" class="h-12">
                    <span class="font-bold text-xl">My Account</span>
                </a>
            </div>
            
            <nav class="p-4 space-y-2">
                <?php foreach ($navItems as $item): ?>
                <a href="<?= $item['url'] ?>" 
                   class="flex items-center px-4 py-3 rounded-lg transition-colors <?= $page === $item['page'] ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                    <i class="<?= $item['icon'] ?> text-lg mr-3"></i>
                    <span><?= $item['label'] ?></span>
                    <?php if (!empty($item['badge'])): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                
                <hr class="border-slate-700 my-4">
                
                <a href="/" class="flex items-center px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition-colors">
                    <i class="bi-shop text-lg mr-3"></i>
                    <span>Back to Store</span>
                </a>
                
                <a href="/user/logout.php" class="flex items-center px-4 py-3 rounded-lg text-red-400 hover:bg-slate-800 transition-colors">
                    <i class="bi-box-arrow-right text-lg mr-3"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        
        <!-- Alpine.js CSP User Sidebar -->
        <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('userSidebar', () => ({
                sidebarOpen: false
            }));
        });
        </script>
        
        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="bg-white shadow-sm border-b px-4 lg:px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4 flex-1">
                        <button @click="sidebarOpen = true" class="lg:hidden text-gray-600 hover:text-gray-900">
                            <i class="bi-list text-2xl"></i>
                        </button>
                        <a href="/" class="flex items-center space-x-2 hover:opacity-75 transition flex-shrink-0">
                            <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy" class="h-9 w-9">
                            <span class="text-base sm:text-lg font-bold text-amber-600 whitespace-nowrap">Back to Store</span>
                        </a>
                        <div class="hidden sm:block border-l border-gray-200 pl-4 flex-1">
                            <h1 class="text-lg sm:text-xl font-bold text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
                        </div>
                        <h1 class="text-sm sm:hidden font-bold text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div x-data="notificationBell" class="relative">
                            <button @click="toggle()" class="relative p-2 text-gray-600 hover:text-gray-900">
                                <i class="bi-bell text-xl"></i>
                                <span x-show="unreadCount > 0" x-text="unreadCount" 
                                      class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-semibold rounded-full w-5 h-5 flex items-center justify-center leading-none pt-0.5"></span>
                            </button>
                            
                            <div x-show="open" x-cloak @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-72 sm:w-80 max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-xl border z-50">
                                <div class="p-3 sm:p-4 border-b">
                                    <h3 class="font-bold text-sm sm:text-base">Notifications</h3>
                                </div>
                                <div class="max-h-80 sm:max-h-96 overflow-y-auto">
                                    <template x-for="n in notifications" :key="n.id">
                                        <div class="p-3 sm:p-4 border-b hover:bg-gray-50" :class="{'bg-blue-50': n.priority === 'high'}">
                                            <p class="text-xs sm:text-sm font-semibold break-words" x-text="n.title"></p>
                                            <p class="text-xs sm:text-sm text-gray-600 mt-1 break-words" x-text="n.message"></p>
                                        </div>
                                    </template>
                                    <div x-show="notifications.length === 0" class="p-6 sm:p-8 text-center text-gray-500 text-sm">
                                        No new notifications
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User -->
                        <a href="/user/profile.php" class="flex items-center space-x-2 hover:opacity-75 transition">
                            <div class="w-8 h-8 rounded-full bg-amber-600 flex items-center justify-center text-white font-bold cursor-pointer">
                                <?= strtoupper(substr(getCustomerName(), 0, 1)) ?>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-gray-700"><?= htmlspecialchars(getCustomerName()) ?></span>
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-6">
