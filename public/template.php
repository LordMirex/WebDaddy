<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
handleAffiliateTracking();

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$templateId) {
    header('Location: index.php');
    exit;
}

$template = getTemplateById($templateId);
if (!$template) {
    header('Location: index.php');
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
    
    <style>
        .template-hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 3rem 0;
        }
        .preview-image {
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            height: auto;
        }
        .sticky-sidebar {
            position: sticky;
            top: 100px;
        }
        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.5rem;
            background: var(--gray-100);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <i class="bi bi-lightning-charge-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">
                            <i class="bi bi-arrow-left me-1"></i>Back to Templates
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="template-hero text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white text-primary px-3 py-2 mb-3"><?php echo htmlspecialchars($template['category']); ?></span>
                    <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($template['name']); ?></h1>
                    <p class="lead mb-4"><?php echo htmlspecialchars($template['description']); ?></p>
                    <div class="d-flex gap-3 align-items-center">
                        <div>
                            <div class="small opacity-75">Starting at</div>
                            <h2 class="h1 fw-bold mb-0"><?php echo formatCurrency($template['price']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="mb-5">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                         alt="<?php echo htmlspecialchars($template['name']); ?>" 
                         class="preview-image"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <?php if ($template['demo_url']): ?>
                <div class="card border-0 shadow-sm mb-5 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
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
                </div>
                <?php endif; ?>

                <?php if (!empty($features)): ?>
                <section class="mb-5">
                    <h2 class="h3 fw-bold mb-4">What's Included</h2>
                    <div class="row g-4">
                        <?php foreach ($features as $feature): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <div class="feature-icon flex-shrink-0 me-3">
                                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-semibold mb-1"><?php echo htmlspecialchars(trim($feature)); ?></h5>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="mb-5">
                    <h2 class="h3 fw-bold mb-4">What You Get</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <i class="bi bi-globe2 text-primary fs-3 mb-3 d-block"></i>
                                    <h5 class="fw-bold">Premium Domain</h5>
                                    <p class="text-muted mb-0">Choose from <?php echo count($availableDomains); ?> available premium domain names</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <i class="bi bi-lightning-charge-fill text-primary fs-3 mb-3 d-block"></i>
                                    <h5 class="fw-bold">Fast Setup</h5>
                                    <p class="text-muted mb-0">Your website will be ready within 24 hours of payment</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <i class="bi bi-palette-fill text-primary fs-3 mb-3 d-block"></i>
                                    <h5 class="fw-bold">Full Customization</h5>
                                    <p class="text-muted mb-0">Complete control to customize colors, text, and images</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <i class="bi bi-headset text-primary fs-3 mb-3 d-block"></i>
                                    <h5 class="fw-bold">24/7 Support</h5>
                                    <p class="text-muted mb-0">Get help anytime via WhatsApp support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-4">
                            <div class="text-center mb-4 pb-4 border-bottom">
                                <div class="text-muted small mb-2">Price</div>
                                <h2 class="display-5 fw-bold text-primary mb-0"><?php echo formatCurrency($template['price']); ?></h2>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">Available Domains</h5>
                                <?php if (count($availableDomains) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($availableDomains, 0, 5) as $domain): ?>
                                    <div class="list-group-item px-0 py-2 border-0">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (count($availableDomains) > 5): ?>
                                    <div class="text-muted small mt-2">
                                        +<?php echo count($availableDomains) - 5; ?> more domains available
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <small>No domains currently available. Contact us for custom domains.</small>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                   class="btn btn-primary btn-lg py-3 fw-bold">
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

                            <div class="mt-4 pt-4 border-top">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-shield-check text-success me-2"></i>
                                    <span class="small">Secure Payment</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-headset text-success me-2"></i>
                                    <span class="small">24/7 Support</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-arrow-clockwise text-success me-2"></i>
                                    <span class="small">Free Updates</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">
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

    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h3 fw-bold mb-4">Ready to Get Started?</h2>
                    <p class="text-muted mb-4">Join hundreds of businesses using our templates</p>
                    <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                       class="btn btn-primary btn-lg px-5">
                        Order This Template Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/" class="text-white-50 text-decoration-none">Home</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
