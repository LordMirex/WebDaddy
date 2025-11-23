<?php
$pageTitle = 'System Monitoring & Health';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Fetch monitoring data from API
$healthData = json_decode(file_get_contents('http://localhost:5000/api/monitoring.php?action=health'), true);
$performanceData = json_decode(file_get_contents('http://localhost:5000/api/monitoring.php?action=api_performance'), true);
$cacheData = json_decode(file_get_contents('http://localhost:5000/api/monitoring.php?action=cache_status'), true);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-speedometer2 text-primary-600"></i> System Monitoring & Health
    </h1>
    <p class="text-gray-600 mt-2">Real-time system health, performance metrics, and error tracking</p>
</div>

<!-- Health Status Overview -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">System Status</h6>
                <div class="text-3xl font-bold text-gray-900">
                    <?php
                    $statusColor = 'text-green-600';
                    $statusBg = 'bg-green-100';
                    $statusIcon = 'bi-check-circle-fill';
                    
                    if ($healthData['status'] === 'CRITICAL') {
                        $statusColor = 'text-red-600';
                        $statusBg = 'bg-red-100';
                        $statusIcon = 'bi-exclamation-triangle-fill';
                    } elseif ($healthData['status'] === 'WARNING') {
                        $statusColor = 'text-yellow-600';
                        $statusBg = 'bg-yellow-100';
                        $statusIcon = 'bi-exclamation-circle-fill';
                    }
                    ?>
                    <span class="<?php echo $statusColor; ?>"><?php echo htmlspecialchars($healthData['status'] ?? 'UNKNOWN'); ?></span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-lg <?php echo $statusBg; ?> flex items-center justify-center">
                <i class="bi <?php echo $statusIcon; ?> text-2xl <?php echo $statusColor; ?>"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Errors (Last Hour)</h6>
        <div class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($healthData['metrics']['errors_last_hour'] ?? 0); ?></div>
        <p class="text-xs text-gray-500 mt-2">
            <?php 
            $errorCount = $healthData['metrics']['errors_last_hour'] ?? 0;
            if ($errorCount > 50) echo '⚠️ High error rate';
            elseif ($errorCount > 20) echo '⚠️ Elevated errors';
            else echo '✓ Normal rate';
            ?>
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Requests (Last Hour)</h6>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($healthData['metrics']['requests_last_hour'] ?? 0); ?></div>
        <p class="text-xs text-gray-500 mt-2">API & page requests</p>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Database Status</h6>
        <div class="text-lg font-bold <?php echo ($healthData['metrics']['database'] === 'OK' ? 'text-green-600' : 'text-red-600'); ?>">
            <?php echo htmlspecialchars($healthData['metrics']['database'] ?? 'UNKNOWN'); ?>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <?php echo ($healthData['metrics']['database'] === 'OK' ? '✓ Connected' : '✗ Error'); ?>
        </p>
    </div>
</div>

<!-- Performance Metrics -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h5 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="bi bi-lightning-charge-fill text-yellow-500"></i> API Performance
        </h5>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Avg Response Time</label>
                <div class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($performanceData['avg_response_time'] ?? 'N/A'); ?></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Fastest</label>
                    <div class="text-lg font-bold text-green-600"><?php echo htmlspecialchars($performanceData['fastest_response'] ?? 'N/A'); ?></div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Slowest</label>
                    <div class="text-lg font-bold text-red-600"><?php echo htmlspecialchars($performanceData['slowest_response'] ?? 'N/A'); ?></div>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-200">
                <label class="text-xs font-semibold text-gray-600 uppercase">Total Requests</label>
                <div class="text-xl font-bold text-gray-900"><?php echo number_format($performanceData['total_requests'] ?? 0); ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h5 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="bi bi-memory text-blue-500"></i> Cache Status
        </h5>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Cache Status</label>
                <div class="text-2xl font-bold <?php echo ($cacheData['cache_enabled'] ? 'text-green-600' : 'text-red-600'); ?>">
                    <?php echo ($cacheData['cache_enabled'] ? 'ENABLED' : 'DISABLED'); ?>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Files Cached</label>
                    <div class="text-lg font-bold text-gray-900"><?php echo number_format($cacheData['cache_files'] ?? 0); ?></div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Size</label>
                    <div class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($cacheData['total_cache_size'] ?? '0KB'); ?></div>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-200">
                <label class="text-xs font-semibold text-gray-600 uppercase">Writable</label>
                <div class="text-sm">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo ($cacheData['writable'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo ($cacheData['writable'] ? '✓ Yes' : '✗ No'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h5 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="bi bi-info-circle text-cyan-500"></i> System Info
        </h5>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Last Check</label>
                <div class="text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($healthData['timestamp'] ?? 'Unknown'); ?></div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Uptime</label>
                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($healthData['metrics']['uptime_check'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Cache Directory</label>
                <div class="text-xs text-gray-600 font-mono truncate"><?php echo htmlspecialchars($cacheData['cache_directory'] ?? 'N/A'); ?></div>
            </div>
            <button onclick="location.reload()" class="w-full mt-4 px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition-colors text-sm">
                <i class="bi bi-arrow-clockwise"></i> Refresh Now
            </button>
        </div>
    </div>
</div>

<!-- Top API Endpoints Performance -->
<?php if (!empty($performanceData['endpoints'])): ?>
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-diagram-3 text-primary-600"></i> Top API Endpoints
        </h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Endpoint</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Calls</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Avg Time</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Min/Max</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-700">Errors</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach (array_slice($performanceData['endpoints'], 0, 15) as $endpoint => $stats): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-3 px-4 font-mono text-xs text-gray-900"><?php echo htmlspecialchars($endpoint); ?></td>
                    <td class="py-3 px-4 text-center text-gray-900"><?php echo number_format($stats['calls'] ?? 0); ?></td>
                    <td class="py-3 px-4 text-center text-gray-900"><?php echo round($stats['avg_time'] ?? 0, 2); ?>ms</td>
                    <td class="py-3 px-4 text-center text-gray-600 text-xs">
                        <span class="text-green-600"><?php echo round($stats['min_time'] ?? 0, 2); ?></span> / 
                        <span class="text-red-600"><?php echo round($stats['max_time'] ?? 0, 2); ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo ($stats['errors'] > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'); ?>">
                            <?php echo $stats['errors'] > 0 ? htmlspecialchars($stats['errors']) : '0'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Error Logs (if any) -->
<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-red-600"></i> Recent System Errors
        </h5>
    </div>
    <div id="error-logs" class="p-6">
        <p class="text-gray-500 text-center py-8">
            <i class="bi bi-hourglass-split animate-spin inline-block mr-2"></i> Loading error logs...
        </p>
    </div>
</div>

<script>
// Auto-load and display error logs
async function loadErrors() {
    try {
        const response = await fetch('/api/monitoring.php?action=errors&limit=20');
        const data = await response.json();
        
        const container = document.getElementById('error-logs');
        
        if (!data.success || data.error_count === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 py-8">✓ No recent errors - system running smoothly!</p>';
            return;
        }
        
        let html = '<div class="space-y-2 max-h-96 overflow-y-auto">';
        data.errors.forEach(error => {
            const isCritical = error.toLowerCase().includes('critical') || error.toLowerCase().includes('fatal');
            html += `
                <div class="p-3 bg-gray-50 rounded border-l-4 ${isCritical ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50'}">
                    <small class="font-mono text-xs ${isCritical ? 'text-red-800' : 'text-yellow-800'}">${escapeHtml(error)}</small>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        document.getElementById('error-logs').innerHTML = '<p class="text-red-600 py-4">Failed to load error logs</p>';
    }
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, m => map[m]);
}

loadErrors();

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>
