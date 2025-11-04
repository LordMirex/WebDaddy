<?php
require_once __DIR__ . '/includes/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="error-page" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-5">
                            <div class="mb-4">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 5rem; color: #dc3545;"></i>
                            </div>
                            <h1 class="display-1 fw-bold" style="color: var(--royal-blue);">404</h1>
                            <h2 class="mb-3">Page Not Found</h2>
                            <p class="text-muted mb-4">
                                The page you're looking for doesn't exist or has been moved.
                            </p>
                            
                            <div class="d-grid gap-2 d-md-block">
                                <a href="/" class="btn btn-primary btn-lg me-2">
                                    <i class="bi bi-house-fill"></i> Go to Homepage
                                </a>
                                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-arrow-left"></i> Go Back
                                </a>
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-muted small mb-2">Need help? Browse our templates:</p>
                                <a href="/#templates" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-grid"></i> View Templates
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
