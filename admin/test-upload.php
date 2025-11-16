<?php
/**
 * Upload Diagnostic Test Page
 * Direct test of upload functionality
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$testResult = '';
$uploadDirs = [
    'uploads/templates/images',
    'uploads/templates/videos',
    'uploads/tools/images',
    'uploads/tools/videos'
];

// Test directory permissions
$dirTests = [];
foreach ($uploadDirs as $dir) {
    $fullPath = dirname(__DIR__) . '/' . $dir;
    $dirTests[] = [
        'dir' => $dir,
        'exists' => is_dir($fullPath),
        'writable' => is_writable($fullPath),
        'permissions' => file_exists($fullPath) ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A'
    ];
}

// Test FFmpeg
$ffmpegTest = shell_exec('which ffmpeg 2>&1');
$ffmpegInstalled = !empty(trim($ffmpegTest));

// Test GD library
$gdTest = extension_loaded('gd');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Diagnostic - WebDaddy Empire</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto p-8">
        <h1 class="text-3xl font-bold mb-6">Upload System Diagnostic</h1>
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">System Requirements</h2>
            <table class="w-full">
                <tr class="border-b">
                    <td class="py-2 font-medium">FFmpeg Installed</td>
                    <td class="py-2"><?php echo $ffmpegInstalled ? '<span class="text-green-600">✓ Yes</span>' : '<span class="text-red-600">✗ No</span>'; ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-medium">GD Library</td>
                    <td class="py-2"><?php echo $gdTest ? '<span class="text-green-600">✓ Enabled</span>' : '<span class="text-red-600">✗ Disabled</span>'; ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-medium">Max Image Size</td>
                    <td class="py-2"><?php echo MAX_IMAGE_SIZE / (1024 * 1024); ?> MB</td>
                </tr>
                <tr>
                    <td class="py-2 font-medium">Max Video Size</td>
                    <td class="py-2"><?php echo MAX_VIDEO_SIZE / (1024 * 1024); ?> MB</td>
                </tr>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Directory Permissions</h2>
            <table class="w-full">
                <thead>
                    <tr class="border-b-2">
                        <th class="text-left py-2">Directory</th>
                        <th class="text-left py-2">Exists</th>
                        <th class="text-left py-2">Writable</th>
                        <th class="text-left py-2">Permissions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dirTests as $test): ?>
                    <tr class="border-b">
                        <td class="py-2 font-mono text-sm"><?php echo $test['dir']; ?></td>
                        <td class="py-2"><?php echo $test['exists'] ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>'; ?></td>
                        <td class="py-2"><?php echo $test['writable'] ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>'; ?></td>
                        <td class="py-2 font-mono"><?php echo $test['permissions']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Test Upload</h2>
            
            <div class="mb-4">
                <label class="block mb-2 font-medium">Upload Type:</label>
                <select id="upload-type" class="border rounded px-3 py-2">
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block mb-2 font-medium">Category:</label>
                <select id="category" class="border rounded px-3 py-2">
                    <option value="templates">Templates</option>
                    <option value="tools">Tools</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block mb-2 font-medium">File:</label>
                <input type="file" id="file-input" class="border rounded px-3 py-2 w-full">
            </div>
            
            <button onclick="testUpload()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Test Upload
            </button>
            
            <div id="result" class="mt-4"></div>
        </div>
    </div>

    <script>
    async function testUpload() {
        const fileInput = document.getElementById('file-input');
        const uploadType = document.getElementById('upload-type').value;
        const category = document.getElementById('category').value;
        const resultDiv = document.getElementById('result');
        
        if (!fileInput.files[0]) {
            resultDiv.innerHTML = '<div class="bg-red-100 text-red-700 p-4 rounded">Please select a file</div>';
            return;
        }
        
        resultDiv.innerHTML = '<div class="bg-blue-100 text-blue-700 p-4 rounded">Uploading...</div>';
        
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('upload_type', uploadType);
        formData.append('category', category);
        
        try {
            const response = await fetch('/api/upload.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                resultDiv.innerHTML = `<div class="bg-red-100 text-red-700 p-4 rounded">
                    <p class="font-bold">JSON Parse Error!</p>
                    <p>Response Status: ${response.status}</p>
                    <pre class="mt-2 text-sm overflow-auto">${responseText}</pre>
                </div>`;
                return;
            }
            
            if (result.success) {
                resultDiv.innerHTML = `<div class="bg-green-100 text-green-700 p-4 rounded">
                    <p class="font-bold">✓ Upload Successful!</p>
                    <p class="mt-2"><strong>URL:</strong> ${result.url}</p>
                    <p><strong>Filename:</strong> ${result.filename}</p>
                    <p><strong>Size:</strong> ${result.size_formatted || result.size}</p>
                    ${result.thumbnails ? '<p><strong>Thumbnails:</strong> ' + JSON.stringify(result.thumbnails).length + ' generated</p>' : ''}
                    ${result.video_data ? '<p><strong>Video processed:</strong> ✓</p>' : ''}
                </div>`;
            } else {
                resultDiv.innerHTML = `<div class="bg-red-100 text-red-700 p-4 rounded">
                    <p class="font-bold">✗ Upload Failed</p>
                    <p class="mt-2"><strong>Error:</strong> ${result.error}</p>
                </div>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<div class="bg-red-100 text-red-700 p-4 rounded">
                <p class="font-bold">Request Error</p>
                <p class="mt-2">${error.message}</p>
            </div>`;
            console.error('Upload error:', error);
        }
    }
    </script>
</body>
</html>
