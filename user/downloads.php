<?php
/**
 * User Downloads Page
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'downloads';
$pageTitle = 'Downloads';

$downloads = getCustomerDownloads($customer['id']);

$groupedDownloads = [];
foreach ($downloads as $download) {
    $orderId = $download['order_id'];
    if (!isset($groupedDownloads[$orderId])) {
        $groupedDownloads[$orderId] = [
            'order_id' => $orderId,
            'order_date' => $download['order_date'],
            'tools' => []
        ];
    }
    
    $toolId = $download['tool_name'];
    if (!isset($groupedDownloads[$orderId]['tools'][$toolId])) {
        $groupedDownloads[$orderId]['tools'][$toolId] = [
            'name' => $download['tool_name'],
            'thumbnail' => $download['tool_thumbnail'],
            'files' => []
        ];
    }
    
    $groupedDownloads[$orderId]['tools'][$toolId]['files'][] = $download;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-gray-500">Access your purchased digital products</p>
        </div>
    </div>

    <?php if (empty($downloads)): ?>
    <div class="bg-white rounded-xl shadow-sm border p-8 text-center">
        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="bi-download text-purple-600 text-2xl"></i>
        </div>
        <h3 class="font-bold text-gray-900 mb-2">No Downloads Available</h3>
        <p class="text-gray-500 mb-4">You haven't purchased any downloadable products yet.</p>
        <a href="/" class="inline-block px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
            Browse Products
        </a>
    </div>
    <?php else: ?>
    
    <div class="space-y-6">
        <?php foreach ($groupedDownloads as $orderGroup): ?>
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-900">Order #<?= $orderGroup['order_id'] ?></h3>
                    <p class="text-sm text-gray-500"><?= date('M j, Y', strtotime($orderGroup['order_date'])) ?></p>
                </div>
                <a href="/user/order-detail.php?id=<?= $orderGroup['order_id'] ?>" class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                    View Order <i class="bi-chevron-right"></i>
                </a>
            </div>
            
            <div class="divide-y">
                <?php foreach ($orderGroup['tools'] as $tool): ?>
                <div class="p-4">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                            <img src="<?= htmlspecialchars(!empty($tool['thumbnail']) ? $tool['thumbnail'] : '/assets/images/placeholder.jpg') ?>" alt="" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='/assets/images/placeholder.jpg';">
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($tool['name']) ?></h4>
                            <p class="text-sm text-gray-500"><?= count($tool['files']) ?> file<?= count($tool['files']) != 1 ? 's' : '' ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <?php foreach ($tool['files'] as $file): ?>
                        <?php
                            $isExpired = strtotime($file['expires_at']) < time();
                            $canDownload = !$isExpired;
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center border">
                                    <i class="bi-file-earmark text-gray-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($file['file_name']) ?></p>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                        <?php if ($file['file_size']): ?>
                                        <span><i class="bi-hdd mr-1"></i><?= formatFileSize($file['file_size']) ?></span>
                                        <?php endif; ?>
                                        <span class="text-gray-500">
                                            <i class="bi-infinity mr-1"></i>Unlimited downloads
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex-shrink-0">
                                <?php if ($canDownload): ?>
                                <a href="/download.php?token=<?= urlencode($file['token']) ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition">
                                    <i class="bi-download mr-2"></i>Download
                                </a>
                                <?php elseif ($isExpired): ?>
                                <span class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-500 text-sm font-medium rounded-lg cursor-not-allowed">
                                    <i class="bi-x-circle mr-2"></i>Expired
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-500 text-sm font-medium rounded-lg cursor-not-allowed">
                                    <i class="bi-x-circle mr-2"></i>Limit Reached
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <i class="bi-info-circle text-blue-600 text-xl"></i>
            <div>
                <h4 class="font-semibold text-blue-800">Unlimited Downloads</h4>
                <p class="text-sm text-blue-700 mt-1">You have unlimited downloads for all your purchased files. Files expire on the date shown above, but until then you can download them as many times as you need.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
