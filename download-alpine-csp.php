<?php
/**
 * Download Alpine.js CSP builds to fix Content Security Policy issues
 * Run: php download-alpine-csp.php
 */

$files = [
    'alpine.csp.min.js' => 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.csp.min.js',
    'alpine-collapse.csp.min.js' => 'https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.csp.min.js'
];

$assetsDir = __DIR__ . '/assets';
$downloaded = 0;
$failed = 0;

echo "Downloading Alpine.js CSP builds...\n";
echo str_repeat("=", 50) . "\n\n";

foreach ($files as $filename => $url) {
    $filepath = $assetsDir . '/' . $filename;
    echo "Downloading: $filename\n";
    echo "From: $url\n";
    
    // Use stream context to handle HTTPS
    $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
    $content = @file_get_contents($url, false, $context);
    
    if ($content !== false && strlen($content) > 0) {
        if (file_put_contents($filepath, $content) !== false) {
            $size = strlen($content);
            echo "✓ Saved to $filepath (" . number_format($size) . " bytes)\n";
            $downloaded++;
        } else {
            echo "✗ Failed to write to $filepath\n";
            $failed++;
        }
    } else {
        echo "✗ Failed to download from URL\n";
        $failed++;
    }
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "Results: $downloaded downloaded, $failed failed\n";
echo "\nIf downloads failed, please manually download from:\n";
foreach ($files as $filename => $url) {
    echo "  - $url\n";
    echo "    Save to: ./assets/$filename\n";
}
echo "\nOnce complete, your Alpine.js components will work with QServers CSP policy.\n";
