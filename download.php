<?php
/**
 * Download Handler
 * Handles individual file downloads and ZIP bundle downloads
 * Phase 3.3: Enhanced with bundle download support
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tool_files.php';

$token = $_GET['token'] ?? '';
$isBundle = isset($_GET['bundle']) && $_GET['bundle'] === '1';

if (empty($token)) {
    http_response_code(400);
    showErrorPage('Invalid Download Link', 'The download link is missing or invalid. Please check your email for the correct link.');
    exit;
}

$db = getDb();

if ($isBundle) {
    $download = getBundleByToken($token);
    
    if (!$download) {
        http_response_code(404);
        showErrorPage('Link Expired', 'This bundle download link has expired or is invalid. Please contact support for a new link.');
        exit;
    }
    
    if ($download['download_count'] >= $download['max_downloads']) {
        http_response_code(403);
        showErrorPage('Download Limit Reached', 'You have reached the maximum number of downloads for this bundle. Please contact support for assistance.');
        exit;
    }
    
    $stmt = $db->prepare("
        UPDATE download_tokens 
        SET download_count = download_count + 1,
            last_downloaded_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$download['id']]);
    
    $filePath = __DIR__ . '/' . $download['zip_path'];
    $fileName = $download['zip_name'];
    $mimeType = 'application/zip';
    
} else {
    $stmt = $db->prepare("
        SELECT dt.*, tf.file_path, tf.file_name, tf.mime_type, tf.file_type
        FROM download_tokens dt
        INNER JOIN tool_files tf ON dt.file_id = tf.id
        WHERE dt.token = ? AND dt.expires_at > datetime('now', '+1 hour') AND (dt.is_bundle = 0 OR dt.is_bundle IS NULL)
    ");
    $stmt->execute([$token]);
    $download = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$download) {
        http_response_code(404);
        showErrorPage('Link Expired', 'This download link has expired or is invalid. Please contact support for a new link.');
        exit;
    }

    if ($download['download_count'] >= $download['max_downloads']) {
        http_response_code(403);
        showErrorPage('Download Limit Reached', 'You have reached the maximum number of downloads for this file. Please contact support for assistance.');
        exit;
    }

    $stmt = $db->prepare("
        UPDATE download_tokens 
        SET download_count = download_count + 1,
            last_downloaded_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$download['id']]);

    $stmt = $db->prepare("UPDATE tool_files SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$download['file_id']]);

    // Handle external links - redirect instead of download
    // Check if file_path is actually an external URL (not just file_type='link')
    // Some local files may have file_type='link' but are stored locally
    $isExternalUrl = preg_match('/^https?:\/\//i', $download['file_path']);
    
    if ($isExternalUrl) {
        header('Location: ' . $download['file_path']);
        exit;
    }

    $filePath = __DIR__ . '/' . $download['file_path'];
    $fileName = $download['file_name'];
    $mimeType = $download['mime_type'];
}

if (!file_exists($filePath)) {
    http_response_code(404);
    showErrorPage('File Not Found', 'The file could not be found on our server. Please contact support for assistance.');
    exit;
}

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Sanitize filename for Content-Disposition header
$safeFileName = preg_replace('/[^\x20-\x7E]/', '', $fileName);
$safeFileName = str_replace(['"', '\\'], '', $safeFileName);
if (empty($safeFileName)) {
    $safeFileName = 'download';
}

// Get file size
$fileSize = filesize($filePath);

// Set security headers to prevent browser warnings
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Set proper content type - use application/octet-stream for binary files
$contentType = $mimeType ?: 'application/octet-stream';
header('Content-Type: ' . $contentType);

// RFC 5987 encoded filename for non-ASCII support
$encodedFileName = rawurlencode($fileName);
header('Content-Disposition: attachment; filename="' . $safeFileName . '"; filename*=UTF-8\'\'' . $encodedFileName);

// Set content length
header('Content-Length: ' . $fileSize);

// Disable caching
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Allow download in iframes
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');

// Send file to browser
if ($fileSize > 0) {
    $handle = fopen($filePath, 'rb');
    if ($handle !== false) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
}
exit;

function showErrorPage($title, $message) {
    $supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'admin@webdaddy.online';
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - WebDaddy Empire</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">' . htmlspecialchars($title) . '</h1>
        <p class="text-gray-600 mb-6">' . htmlspecialchars($message) . '</p>
        <div class="space-y-3">
            <a href="' . $siteUrl . '" class="block w-full py-3 px-4 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-colors" style="background: #6366f1;">
                Return Home
            </a>
            <a href="mailto:' . htmlspecialchars($supportEmail) . '" class="block w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">
                Contact Support
            </a>
        </div>
    </div>
</body>
</html>';
}
