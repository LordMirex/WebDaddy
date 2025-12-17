<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
startSecureSession();

$pageTitle = 'Page Not Found | WebDaddy Blog';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="The page you're looking for could not be found.">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        h1 {
            font-size: 5rem;
            margin: 0;
            font-weight: bold;
            color: #d4af37;
        }
        h2 {
            font-size: 1.5rem;
            margin: 1rem 0;
            color: #e0e0e0;
        }
        p {
            font-size: 1rem;
            color: #b0b0b0;
            margin: 1rem 0;
        }
        a {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.8rem 2rem;
            background-color: #d4af37;
            color: #1a1a2e;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        a:hover {
            background-color: #f0d655;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The blog post or page you're looking for doesn't exist or has been moved.</p>
        <a href="/blog/">← Back to Blog</a>
    </div>
</body>
</html>
