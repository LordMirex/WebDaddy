<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
handleAffiliateTracking();

$templates = getTemplates(true);
$affiliateCode = getAffiliateCode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Professional Website Templates</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#templates">Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/">Admin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/affiliate/">Affiliate</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-3">Professional Website Templates</h1>
                    <p class="lead text-muted mb-4">
                        Get your business online in minutes with our pre-built, fully customizable website templates.
                        Choose your template, select a domain, and start selling today!
                    </p>
                    <?php if ($affiliateCode): ?>
                    <div class="alert alert-success">
                        <strong>Affiliate Code Applied:</strong> <?php echo htmlspecialchars($affiliateCode); ?>
                    </div>
                    <?php endif; ?>
                    <a href="#templates" class="btn btn-primary btn-lg">Browse Templates</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container my-5" id="templates">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-center mb-4">Choose Your Perfect Template</h2>
            </div>
        </div>

        <?php if (empty($templates)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <h4>No templates available at the moment</h4>
                    <p>Please check back later or contact support.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($templates as $template): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card template-card h-100 shadow-sm">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? 'https://via.placeholder.com/400x300'); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($template['name']); ?>">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h5>
                        <p class="card-text text-muted flex-grow-1">
                            <?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 100)); ?>...
                        </p>
                        <div class="mb-3">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($template['category']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="h4 mb-0 text-primary"><?php echo formatCurrency($template['price']); ?></span>
                        </div>
                        <div class="d-grid gap-2">
                            <?php if ($template['demo_url']): ?>
                            <button class="btn btn-outline-primary" 
                                    onclick="openDemo('<?php echo htmlspecialchars($template['demo_url']); ?>', '<?php echo htmlspecialchars($template['name']); ?>')">
                                View Demo
                            </button>
                            <?php endif; ?>
                            <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                               class="btn btn-primary">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <div class="modal fade" id="demoModal" tabindex="-1" aria-labelledby="demoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="demoModalLabel">Template Demo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="demoIframe" src="" frameborder="0" style="width:100%;height:100%;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/admin/" class="text-white text-decoration-none me-3">Admin Login</a>
                    <a href="/affiliate/" class="text-white text-decoration-none">Affiliate Login</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openDemo(url, title) {
            document.getElementById('demoModalLabel').textContent = title + ' - Demo';
            document.getElementById('demoIframe').src = url;
            new bootstrap.Modal(document.getElementById('demoModal')).show();
        }

        document.getElementById('demoModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('demoIframe').src = '';
        });
    </script>
</body>
</html>
