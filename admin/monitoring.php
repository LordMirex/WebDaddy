<?php
$pageTitle = 'System Monitoring & Health';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

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
                <div id="status-badge" class="text-3xl font-bold text-gray-900">
                    <div class="animate-pulse">Loading...</div>
                </div>
            </div>
            <div id="status-icon" class="w-16 h-16 rounded-lg bg-gray-100 flex items-center justify-center">
                <i class="bi bi-hourglass-split animate-spin text-2xl text-gray-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Errors (Last Hour)</h6>
        <div id="errors-count" class="text-3xl font-bold text-gray-900 animate-pulse">-</div>
        <p id="errors-status" class="text-xs text-gray-500 mt-2">-</p>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Requests (Last Hour)</h6>
        <div id="requests-count" class="text-3xl font-bold text-gray-900 animate-pulse">-</div>
        <p class="text-xs text-gray-500 mt-2">API & page requests</p>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
        <h6 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Database Status</h6>
        <div id="db-status" class="text-lg font-bold text-gray-600 animate-pulse">-</div>
        <p id="db-indicator" class="text-xs text-gray-500 mt-2">-</p>
    </div>
</div>

<!-- Webhook Security Dashboard -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-shield-lock-fill text-green-600"></i> Webhook Security Dashboard
        </h5>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h6 class="text-xs font-semibold text-blue-600 uppercase mb-1">Today's Webhooks</h6>
                <div id="webhook-count" class="text-2xl font-bold text-blue-900">-</div>
            </div>
            <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                <h6 class="text-xs font-semibold text-red-600 uppercase mb-1">Blocked Requests</h6>
                <div id="blocked-count" class="text-2xl font-bold text-red-900">-</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h6 class="text-xs font-semibold text-green-600 uppercase mb-1">Successful Payments</h6>
                <div id="success-payments" class="text-2xl font-bold text-green-900">-</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <h6 class="text-xs font-semibold text-yellow-600 uppercase mb-1">Failed Payments</h6>
                <div id="failed-payments" class="text-2xl font-bold text-yellow-900">-</div>
            </div>
        </div>
        
        <h6 class="font-bold text-gray-700 mb-3">Recent Security Events</h6>
        <div id="security-events" class="max-h-64 overflow-y-auto">
            <p class="text-gray-500 text-center py-4">
                <i class="bi bi-hourglass-split animate-spin inline-block mr-2"></i> Loading...
            </p>
        </div>
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
                <div id="perf-avg" class="text-2xl font-bold text-gray-900 animate-pulse">-</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Fastest</label>
                    <div id="perf-fast" class="text-lg font-bold text-green-600 animate-pulse">-</div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Slowest</label>
                    <div id="perf-slow" class="text-lg font-bold text-red-600 animate-pulse">-</div>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-200">
                <label class="text-xs font-semibold text-gray-600 uppercase">Total Requests</label>
                <div id="perf-total" class="text-xl font-bold text-gray-900 animate-pulse">-</div>
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
                <div id="cache-status" class="text-2xl font-bold text-gray-900 animate-pulse">-</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Files Cached</label>
                    <div id="cache-files" class="text-lg font-bold text-gray-900 animate-pulse">-</div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Size</label>
                    <div id="cache-size" class="text-lg font-bold text-gray-900 animate-pulse">-</div>
                </div>
            </div>
            <div class="pt-3 border-t border-gray-200">
                <label class="text-xs font-semibold text-gray-600 uppercase">Writable</label>
                <div id="cache-writable" class="text-sm animate-pulse">-</div>
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
                <div id="system-time" class="text-sm text-gray-900 font-mono animate-pulse">-</div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Uptime</label>
                <div id="system-uptime" class="text-sm text-gray-900 animate-pulse">-</div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase mb-1">Cache Directory</label>
                <div id="system-cache-dir" class="text-xs text-gray-600 font-mono truncate animate-pulse">-</div>
            </div>
            <button onclick="location.reload()" class="w-full mt-4 px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition-colors text-sm">
                <i class="bi bi-arrow-clockwise"></i> Refresh Now
            </button>
        </div>
    </div>
</div>

<!-- Top API Endpoints Performance -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h5 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-diagram-3 text-primary-600"></i> Top API Endpoints
        </h5>
    </div>
    <div id="endpoints-container" class="p-6 text-center text-gray-500">
        <i class="bi bi-hourglass-split animate-spin inline-block mr-2"></i> Loading performance data...
    </div>
</div>

<!-- Error Logs -->
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
async function loadMonitoringData() {
    try {
        // Load health data
        const healthRes = await fetch('/api/monitoring.php?action=health', { signal: AbortSignal.timeout(5000) });
        const health = await healthRes.json();
        
        if (health.success) {
            updateHealthStatus(health);
        }
        
        // Load performance data
        const perfRes = await fetch('/api/monitoring.php?action=api_performance', { signal: AbortSignal.timeout(5000) });
        const perf = await perfRes.json();
        
        if (perf.success) {
            updatePerformanceData(perf);
        }
        
        // Load cache data
        const cacheRes = await fetch('/api/monitoring.php?action=cache_status', { signal: AbortSignal.timeout(5000) });
        const cache = await cacheRes.json();
        
        if (cache.success) {
            updateCacheData(cache);
        }
        
        // Load error logs
        const errRes = await fetch('/api/monitoring.php?action=errors&limit=20', { signal: AbortSignal.timeout(5000) });
        const errors = await errRes.json();
        
        if (errors.success) {
            updateErrorLogs(errors);
        }
    } catch (error) {
        console.error('Failed to load monitoring data:', error);
        document.getElementById('status-badge').innerHTML = '<span class="text-yellow-600">TIMEOUT</span>';
    }
}

function updateHealthStatus(data) {
    const status = data.status || 'UNKNOWN';
    const metrics = data.metrics || {};
    
    // Update status badge
    const statusBadge = document.getElementById('status-badge');
    const statusIcon = document.getElementById('status-icon');
    let color = 'text-green-600';
    let bgColor = 'bg-green-100';
    let icon = 'bi-check-circle-fill';
    
    if (status === 'CRITICAL') {
        color = 'text-red-600';
        bgColor = 'bg-red-100';
        icon = 'bi-exclamation-triangle-fill';
    } else if (status === 'WARNING') {
        color = 'text-yellow-600';
        bgColor = 'bg-yellow-100';
        icon = 'bi-exclamation-circle-fill';
    }
    
    statusBadge.innerHTML = `<span class="${color}">${status}</span>`;
    statusIcon.innerHTML = `<i class="bi ${icon} text-2xl ${color}"></i>`;
    statusIcon.className = `w-16 h-16 rounded-lg ${bgColor} flex items-center justify-center`;
    
    // Update metrics
    const errors = metrics.errors_last_hour || 0;
    document.getElementById('errors-count').textContent = errors;
    document.getElementById('errors-status').textContent = errors > 50 ? '⚠️ High error rate' : (errors > 20 ? '⚠️ Elevated errors' : '✓ Normal rate');
    
    document.getElementById('requests-count').textContent = (metrics.requests_last_hour || 0).toLocaleString();
    
    const dbStatus = metrics.database || 'UNKNOWN';
    const dbColor = dbStatus === 'OK' ? 'text-green-600' : 'text-red-600';
    document.getElementById('db-status').innerHTML = `<span class="${dbColor}">${dbStatus}</span>`;
    document.getElementById('db-indicator').textContent = dbStatus === 'OK' ? '✓ Connected' : '✗ Error';
    
    document.getElementById('system-time').textContent = data.timestamp || 'Unknown';
    document.getElementById('system-uptime').textContent = metrics.uptime_check || 'OK';
}

function updatePerformanceData(data) {
    document.getElementById('perf-avg').textContent = data.avg_response_time || 'N/A';
    document.getElementById('perf-fast').textContent = data.fastest_response || 'N/A';
    document.getElementById('perf-slow').textContent = data.slowest_response || 'N/A';
    document.getElementById('perf-total').textContent = (data.total_requests || 0).toLocaleString();
    
    // Build endpoints table
    const endpoints = data.endpoints || {};
    let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-50 border-b border-gray-200">';
    html += '<th class="text-left py-3 px-4 font-semibold text-gray-700">Endpoint</th>';
    html += '<th class="text-center py-3 px-4 font-semibold text-gray-700">Calls</th>';
    html += '<th class="text-center py-3 px-4 font-semibold text-gray-700">Avg Time</th>';
    html += '<th class="text-center py-3 px-4 font-semibold text-gray-700">Min/Max</th>';
    html += '<th class="text-center py-3 px-4 font-semibold text-gray-700">Errors</th></tr></thead><tbody class="divide-y divide-gray-200">';
    
    Object.entries(endpoints).slice(0, 15).forEach(([endpoint, stats]) => {
        html += `<tr class="hover:bg-gray-50"><td class="py-3 px-4 font-mono text-xs">${escapeHtml(endpoint)}</td>`;
        html += `<td class="py-3 px-4 text-center">${(stats.calls || 0).toLocaleString()}</td>`;
        html += `<td class="py-3 px-4 text-center">${(stats.avg_time || 0).toFixed(2)}ms</td>`;
        html += `<td class="py-3 px-4 text-center text-xs"><span class="text-green-600">${(stats.min_time || 0).toFixed(2)}</span> / <span class="text-red-600">${(stats.max_time || 0).toFixed(2)}</span></td>`;
        const errorClass = (stats.errors || 0) > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
        html += `<td class="py-3 px-4 text-center"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${errorClass}">${stats.errors || 0}</span></td></tr>`;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('endpoints-container').innerHTML = html;
}

function updateCacheData(data) {
    const status = data.cache_enabled ? 'ENABLED' : 'DISABLED';
    const color = data.cache_enabled ? 'text-green-600' : 'text-red-600';
    document.getElementById('cache-status').innerHTML = `<span class="${color}">${status}</span>`;
    document.getElementById('cache-files').textContent = (data.cache_files || 0).toLocaleString();
    document.getElementById('cache-size').textContent = data.total_cache_size || '0KB';
    
    const writeBg = data.writable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    document.getElementById('cache-writable').innerHTML = `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${writeBg}">${data.writable ? '✓ Yes' : '✗ No'}</span>`;
    
    document.getElementById('system-cache-dir').textContent = data.cache_directory || 'N/A';
}

function updateErrorLogs(data) {
    const container = document.getElementById('error-logs');
    
    if (!data.errors || data.errors.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">✓ No recent errors - system running smoothly!</p>';
        return;
    }
    
    let html = '<div class="space-y-2 max-h-96 overflow-y-auto">';
    data.errors.forEach(error => {
        const isCritical = error.toLowerCase().includes('critical') || error.toLowerCase().includes('fatal');
        const borderClass = isCritical ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50';
        const textClass = isCritical ? 'text-red-800' : 'text-yellow-800';
        html += `<div class="p-3 ${borderClass} rounded border-l-4"><small class="font-mono text-xs ${textClass}">${escapeHtml(error)}</small></div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Load webhook security stats
async function loadWebhookSecurityStats() {
    try {
        const res = await fetch('/api/monitoring.php?action=webhook_security', { signal: AbortSignal.timeout(5000) });
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('webhook-count').textContent = data.today_webhooks || 0;
            document.getElementById('blocked-count').textContent = data.blocked_today || 0;
            document.getElementById('success-payments').textContent = data.successful_payments || 0;
            document.getElementById('failed-payments').textContent = data.failed_payments || 0;
            
            // Update security events
            const container = document.getElementById('security-events');
            const events = data.recent_events || [];
            
            if (events.length === 0) {
                container.innerHTML = '<p class="text-center text-green-600 py-4"><i class="bi bi-shield-check"></i> No security incidents - system secure!</p>';
            } else {
                let html = '<div class="space-y-2">';
                events.forEach(event => {
                    const isBlocked = event.event_type.includes('blocked') || event.event_type.includes('invalid') || event.event_type.includes('exceeded');
                    const bgClass = isBlocked ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200';
                    const iconClass = isBlocked ? 'text-red-600 bi-shield-x' : 'text-blue-600 bi-info-circle';
                    html += `<div class="flex items-start gap-3 p-3 rounded-lg border ${bgClass}">
                        <i class="bi ${iconClass} mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-semibold text-sm text-gray-900">${escapeHtml(event.event_type.replace(/_/g, ' ').toUpperCase())}</div>
                            <div class="text-xs text-gray-600">IP: ${escapeHtml(event.ip_address || 'Unknown')}</div>
                            ${event.details ? `<div class="text-xs text-gray-500 mt-1">${escapeHtml(event.details)}</div>` : ''}
                            <div class="text-xs text-gray-400 mt-1">${escapeHtml(event.created_at || '')}</div>
                        </div>
                    </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            }
        }
    } catch (error) {
        console.error('Failed to load webhook security stats:', error);
    }
}

// Load on page load
loadMonitoringData();
loadWebhookSecurityStats();

// Auto-refresh every 30 seconds
setInterval(loadMonitoringData, 30000);
setInterval(loadWebhookSecurityStats, 30000);
</script>
