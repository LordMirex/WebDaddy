<?php
/**
 * User Dashboard Home
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'dashboard';
$pageTitle = 'Dashboard';

$orderCount = getCustomerOrderCount($customer['id']);
$pendingDeliveries = getCustomerPendingDeliveries($customer['id']);
$openTickets = getCustomerOpenTickets($customer['id']);
$recentOrders = getCustomerOrders($customer['id'], 5);

$profileComplete = !empty($customer['whatsapp_number']);

$db = getDb();
$announcements = $db->query("
    SELECT * FROM user_announcements 
    WHERE is_active = 1 
    AND (customer_id IS NULL OR customer_id = " . intval($customer['id']) . ")
    AND (expires_at IS NULL OR datetime(expires_at) > datetime('now'))
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-amber-600 to-amber-500 rounded-xl p-6 text-white">
        <h2 class="text-2xl font-bold mb-2">Welcome back, <?= htmlspecialchars(getCustomerName()) ?>!</h2>
        <p class="opacity-90">Manage your orders, downloads, and account settings from here.</p>
    </div>
    
    <?php if (!empty($announcements)): ?>
    <!-- Announcements Section -->
    <div class="space-y-3">
        <?php foreach ($announcements as $ann): 
            $typeStyles = [
                'info' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'icon' => 'bi-info-circle-fill', 'iconColor' => 'text-blue-600', 'textColor' => 'text-blue-800'],
                'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'icon' => 'bi-check-circle-fill', 'iconColor' => 'text-green-600', 'textColor' => 'text-green-800'],
                'warning' => ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'icon' => 'bi-exclamation-triangle-fill', 'iconColor' => 'text-yellow-600', 'textColor' => 'text-yellow-800'],
                'danger' => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'icon' => 'bi-exclamation-circle-fill', 'iconColor' => 'text-red-600', 'textColor' => 'text-red-800']
            ];
            $style = $typeStyles[$ann['type']] ?? $typeStyles['info'];
        ?>
        <div class="<?= $style['bg'] ?> border <?= $style['border'] ?> rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="bi <?= $style['icon'] ?> <?= $style['iconColor'] ?> text-xl mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="font-semibold <?= $style['textColor'] ?>"><?= htmlspecialchars($ann['title']) ?></h3>
                    <div class="text-sm <?= $style['textColor'] ?> mt-1 opacity-90 announcement-content"><?= $ann['message'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$profileComplete): ?>
    <!-- Profile Incomplete Alert -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-start space-x-3">
        <i class="bi-exclamation-triangle-fill text-yellow-600 text-xl mt-0.5"></i>
        <div>
            <h3 class="font-semibold text-yellow-800">Complete Your Profile</h3>
            <p class="text-sm text-yellow-700 mt-1">Add your WhatsApp number for faster checkout and better support.</p>
            <a href="/user/profile.php" class="inline-block mt-2 text-sm font-medium text-yellow-800 underline hover:no-underline">Complete Profile &rarr;</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Orders</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $orderCount ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="bi-bag text-blue-600 text-xl"></i>
                </div>
            </div>
            <a href="/user/orders.php" class="text-sm text-blue-600 hover:underline mt-3 inline-block">View all orders &rarr;</a>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Deliveries</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $pendingDeliveries ?></p>
                </div>
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                    <i class="bi-truck text-amber-600 text-xl"></i>
                </div>
            </div>
            <a href="/user/orders.php?status=paid" class="text-sm text-amber-600 hover:underline mt-3 inline-block">Track deliveries &rarr;</a>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Open Tickets</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $openTickets ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="bi-chat-dots text-green-600 text-xl"></i>
                </div>
            </div>
            <a href="/user/support.php" class="text-sm text-green-600 hover:underline mt-3 inline-block">View tickets &rarr;</a>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-bold text-gray-900">Recent Orders</h3>
            <a href="/user/orders.php" class="text-sm text-amber-600 hover:underline">View all</a>
        </div>
        
        <?php if (empty($recentOrders)): ?>
        <div class="p-8 text-center">
            <i class="bi-bag text-gray-300 text-4xl mb-3"></i>
            <p class="text-gray-500">No orders yet</p>
            <a href="/" class="inline-block mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">Browse Products</a>
        </div>
        <?php else: ?>
        <div class="divide-y">
            <?php foreach ($recentOrders as $order): ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-900">Order #<?= $order['id'] ?></p>
                        <p class="text-sm text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?> &middot; <?= $order['item_count'] ?> item(s)</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            <?php
                            switch ($order['status']) {
                                case 'paid': echo 'bg-blue-100 text-blue-700'; break;
                                case 'completed': echo 'bg-green-100 text-green-700'; break;
                                case 'pending': echo 'bg-yellow-100 text-yellow-700'; break;
                                case 'cancelled': echo 'bg-red-100 text-red-700'; break;
                                default: echo 'bg-gray-100 text-gray-700';
                            }
                            ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                        <a href="/user/order-detail.php?id=<?= $order['id'] ?>" class="text-amber-600 hover:text-amber-700">
                            <i class="bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/user/downloads.php" class="bg-white rounded-xl shadow-sm border p-4 text-center hover:shadow-md transition group">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                <i class="bi-download text-purple-600 text-xl"></i>
            </div>
            <p class="font-medium text-gray-900">Downloads</p>
        </a>
        
        <a href="/user/new-ticket.php" class="bg-white rounded-xl shadow-sm border p-4 text-center hover:shadow-md transition group">
            <div class="w-12 h-12 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                <i class="bi-plus-circle text-teal-600 text-xl"></i>
            </div>
            <p class="font-medium text-gray-900">New Ticket</p>
        </a>
        
        <a href="/user/profile.php" class="bg-white rounded-xl shadow-sm border p-4 text-center hover:shadow-md transition group">
            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                <i class="bi-person text-indigo-600 text-xl"></i>
            </div>
            <p class="font-medium text-gray-900">Edit Profile</p>
        </a>
        
        <a href="/" class="bg-white rounded-xl shadow-sm border p-4 text-center hover:shadow-md transition group">
            <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition">
                <i class="bi-shop text-rose-600 text-xl"></i>
            </div>
            <p class="font-medium text-gray-900">Shop More</p>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
