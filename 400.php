<?php
require_once __DIR__ . '/includes/config.php';
http_response_code(400);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bad Request - <?php echo SITE_NAME; ?></title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet"><script defer src="/assets/js/alpine.min.js"></script>
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
        <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 text-center">
            <div class="mb-6">
                <svg class="w-24 h-24 mx-auto text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            
            <h1 class="text-8xl font-extrabold text-blue-900 mb-2">400</h1>
            <h2 class="text-2xl font-bold text-white mb-3">Bad Request</h2>
            <p class="text-gray-300 mb-6">
                The request could not be understood by the server. Please check your input.
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
