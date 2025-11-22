<?php
require_once __DIR__ . '/includes/config.php';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Forbidden - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-700 to-blue-500 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <div class="mb-6">
                <svg class="w-24 h-24 mx-auto text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 2.526a6 6 0 008.367 8.364m1.497-3.036a.75.75 0 10-1.06-1.061 3 3 0 11-4.243 4.243.75.75 0 101.06 1.06A4.5 4.5 0 1014.974 11.853z" clip-rule="evenodd"/>
                </svg>
            </div>
            
            <h1 class="text-8xl font-extrabold text-blue-900 mb-2">403</h1>
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Access Forbidden</h2>
            <p class="text-gray-600 mb-6">
                You don't have permission to access this resource.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-3 justify-center mb-6">
                <a href="/" class="inline-flex items-center justify-center px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    Go to Homepage
                </a>
            </div>
        </div>
    </div>
</body>
</html>
