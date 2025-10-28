<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
handleAffiliateTracking();

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$templateId) {
    header('Location: /');
    exit;
}

$template = getTemplateById($templateId);
if (!$template) {
    header('Location: /');
    exit;
}

$availableDomains = getAvailableDomains($templateId);
$affiliateCode = getAffiliateCode();
$features = $template['features'] ? explode(',', $template['features']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($template['name']); ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($template['description']); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.jpg" alt="WebDaddy Empire" style="height: 50px; margin-right: 12px;">
                <span style="color: var(--royal-blue);"><?php echo SITE_NAME; ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-600" href="/">
                            <i class="bi bi-arrow-left me-1"></i>Back to Templates
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-primary px-3 py-2 mb-3 fw-600"><?php echo htmlspecialchars($template['category']); ?></span>
                    <h1 class="display-4 fw-800 mb-3 text-white"><?php echo htmlspecialchars($template['name']); ?></h1>
                    <p class="lead text-white-80 mb-4"><?php echo htmlspecialchars($template['description']); ?></p>
                    <div class="d-flex gap-4 align-items-center">
                        <div>
                            <div class="small text-white-70">Starting at</div>
                            <h2 class="h1 fw-800 mb-0 text-white"><?php echo formatCurrency($template['price']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-6">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="mb-5">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                         alt="<?php echo htmlspecialchars($template['name']); ?>" 
                         class="img-fluid rounded-3"
                         style="box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <?php if ($template['demo_url']): ?>
                <div class="card border-0 shadow-sm mb-5 overflow-hidden rounded-3">
                    <div class="card-header bg-light border-0 p-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-700">
                            <i class="bi bi-eye me-2"></i>Live Preview
                        </h5>
                        <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-primary">
                            Open in New Tab
                            <i class="bi bi-box-arrow-up-right ms-2"></i>
                        </a>
                    </div>
                    <div style="height: 600px;">
                        <iframe src="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                                frameborder="0" 
                                style="width: 100%; height: 100%;">
                        </iframe>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($features)): ?>
                <section class="mb-6">
                    <h2 class="h3 fw-800 mb-4">What's Included</h2>
                    <div class="row g-4">
                        <?php foreach ($features as $feature): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-badge me-3">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                </div>
                                <div>
                                    <h5 class="fw-600 mb-0"><?php echo htmlspecialchars(trim($feature)); ?></h5>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="mb-6">
                    <h2 class="h3 fw-800 mb-4">What You Get</h2>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="benefit-card">
                                <i class="bi bi-globe2 text-primary"></i>
                                <h5 class="fw-700">Premium Domain</h5>
                                <p class="text-muted small mb-0">Choose from <?php echo count($availableDomains); ?> available premium domain names</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="benefit-card">
                                <i class="bi bi-lightning-charge-fill text-primary"></i>
                                <h5 class="fw-700">Fast Setup</h5>
                                <p class="text-muted small mb-0">Your website will be ready within 24 hours of payment</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="benefit-card">
                                <i class="bi bi-palette-fill text-primary"></i>
                                <h5 class="fw-700">Full Customization</h5>
                                <p class="text-muted small mb-0">Complete control to customize colors, text, and images</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="benefit-card">
                                <i class="bi bi-headset text-primary"></i>
                                <h5 class="fw-700">24/7 Support</h5>
                                <p class="text-muted small mb-0">Get help anytime via WhatsApp support</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <div class="card border-0 shadow-lg rounded-3 overflow-hidden">
                        <div class="card-body p-5">
                            <div class="text-center mb-5 pb-4 border-bottom">
                                <div class="text-muted small fw-600 mb-2">Price</div>
                                <h2 class="display-5 fw-800 text-primary mb-0"><?php echo formatCurrency($template['price']); ?></h2>
                            </div>

                            <div class="mb-5">
                                <h5 class="fw-700 mb-3">Available Domains</h5>
                                <?php if (count($availableDomains) > 0): ?>
                                <div class="domain-list">
                                    <?php foreach (array_slice($availableDomains, 0, 5) as $domain): ?>
                                    <div class="domain-item">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <span class="fw-600"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (count($availableDomains) > 5): ?>
                                    <div class="text-muted small mt-3">
                                        +<?php echo count($availableDomains) - 5; ?> more domains available
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mb-0 small">
                                    No domains currently available. Contact us for custom domains.
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-3 mb-4">
                                <a href="/order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                   class="btn btn-primary btn-lg py-3 fw-700">
                                    <i class="bi bi-cart-plus me-2"></i>Order Now
                                </a>
                                <?php if ($template['demo_url']): ?>
                                <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                                   target="_blank" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-eye me-2"></i>View Live Demo
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="trust-list">
                                <div class="trust-item">
                                    <i class="bi bi-shield-check text-success"></i>
                                    <span class="small">Secure Payment</span>
                                </div>
                                <div class="trust-item">
                                    <i class="bi bi-headset text-success"></i>
                                    <span class="small">24/7 Support</span>
                                </div>
                                <div class="trust-item">
                                    <i class="bi bi-arrow-clockwise text-success"></i>
                                    <span class="small">Free Updates</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light rounded-3 mt-4 overflow-hidden">
                        <div class="card-body p-4">
                            <h6 class="fw-700 mb-3">
                                <i class="bi bi-whatsapp text-success me-2"></i>Need Help?
                            </h6>
                            <p class="small text-muted mb-3">Have questions? Contact us on WhatsApp</p>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" 
                               class="btn btn-success btn-sm w-100"
                               target="_blank">
                                <i class="bi bi-whatsapp me-2"></i>Chat with Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="py-6 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h3 fw-800 mb-3">Ready to Get Started?</h2>
                    <p class="text-muted mb-4">Join hundreds of businesses using our templates</p>
                    <a href="/order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                       class="btn btn-primary btn-lg px-5 fw-700">
                        Order This Template Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-custom">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 small">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/" class="text-white-50 text-decoration-none small">Home</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
