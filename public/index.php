<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
handleAffiliateTracking();

// Limit to 10 templates for single-page layout
$allTemplates = getTemplates(true);
$templates = array_slice($allTemplates, 0, 10);
$affiliateCode = getAffiliateCode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Professional Website Templates with Domains</title>
    <meta name="description" content="Professional website templates with domains included. Launch your business online in 24 hours.">
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.jpg" alt="WebDaddy Empire" style="height: 45px; margin-right: 10px;" onerror="this.style.display='none'">
                <span style="color: var(--royal-blue);"><?php echo SITE_NAME; ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#templates">Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#faq">FAQ</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-primary btn-sm px-3" href="/affiliate/">Become an Affiliate</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Compact Hero Section -->
    <header class="hero-section-compact">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-3">Professional Websites. Ready in 24 Hours.</h1>
                    <p class="lead mb-4">Choose a template below, select a domain, and launch your business online today.</p>
                    <div class="d-flex gap-3 justify-content-center align-items-center mb-3">
                        <div class="trust-indicator">
                            <i class="bi bi-check-circle-fill text-warning me-2"></i>
                            <span>Domain Included</span>
                        </div>
                        <div class="trust-indicator">
                            <i class="bi bi-check-circle-fill text-warning me-2"></i>
                            <span>24-Hour Setup</span>
                        </div>
                        <div class="trust-indicator">
                            <i class="bi bi-check-circle-fill text-warning me-2"></i>
                            <span>Full Customization</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- TEMPLATES SECTION - FRONT AND CENTER -->
    <section class="py-5 bg-light" id="templates">
        <div class="container">
            <div class="row mb-4">
                <div class="col-lg-12 text-center">
                    <h2 class="h3 fw-bold mb-2">Choose Your Template</h2>
                    <p class="text-muted">Pick a professionally designed website and get started instantly</p>
                </div>
            </div>

            <?php if (empty($templates)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info text-center p-4">
                        <i class="bi bi-info-circle fs-2 d-block mb-2"></i>
                        <h4>No templates available at the moment</h4>
                        <p>Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>">contact us on WhatsApp</a>.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($templates as $template): 
                    $features = $template['features'] ? explode(',', $template['features']) : [];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card template-card h-100">
                        <div class="template-card-img-wrapper">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($template['name']); ?>"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <?php if ($template['demo_url']): ?>
                            <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-3" 
                                    onclick="openDemo('<?php echo htmlspecialchars($template['demo_url']); ?>', '<?php echo htmlspecialchars($template['name']); ?>')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h3 class="h5 fw-bold mb-0"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($template['category']); ?></span>
                            </div>
                            <p class="card-text text-muted small mb-3">
                                <?php echo htmlspecialchars($template['description'] ?? ''); ?>
                            </p>
                            <?php if (!empty($features) && count($features) > 0): ?>
                            <ul class="list-unstyled small mb-3">
                                <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                <li class="mb-1">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <?php echo htmlspecialchars(trim($feature)); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                                <div>
                                    <small class="text-muted d-block">Starting at</small>
                                    <h4 class="price mb-0 fs-5"><?php echo formatCurrency($template['price']); ?></h4>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="template.php?id=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        Details
                                    </a>
                                    <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn btn-primary btn-sm">
                                        Order Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works - Simplified -->
    <section class="py-5 bg-white" id="how-it-works">
        <div class="container">
            <div class="row mb-4">
                <div class="col-lg-12 text-center">
                    <h2 class="h3 fw-bold mb-2">How It Works</h2>
                    <p class="text-muted">Get your business online in 3 simple steps</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon-simple mb-3">
                            <i class="bi bi-1-circle-fill"></i>
                        </div>
                        <h4 class="h6 fw-bold">Choose Template</h4>
                        <p class="text-muted small">Select the template that fits your business</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon-simple mb-3">
                            <i class="bi bi-2-circle-fill"></i>
                        </div>
                        <h4 class="h6 fw-bold">Pick Domain & Pay</h4>
                        <p class="text-muted small">Select your domain and complete payment via WhatsApp</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon-simple mb-3">
                            <i class="bi bi-3-circle-fill"></i>
                        </div>
                        <h4 class="h6 fw-bold">Launch & Grow</h4>
                        <p class="text-muted small">Receive credentials and customize your site</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section - Condensed -->
    <section class="py-5 bg-light" id="faq">
        <div class="container">
            <div class="row mb-4">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h3 fw-bold mb-2">Frequently Asked Questions</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border mb-3">
                            <h3 class="accordion-header">
                                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What's included in the price?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Complete website template, premium domain name, hosting setup, and full customization access.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border mb-3">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How long does setup take?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Your website will be ready within 24 hours after payment confirmation.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border mb-3">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Can I customize the template?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! You have full control to customize colors, text, images, and content.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border mb-3">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I get support?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We offer support via WhatsApp at <?php echo WHATSAPP_NUMBER; ?>. Our team is ready to help you.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h3 fw-bold mb-3">Ready to Launch Your Website?</h2>
                    <p class="mb-4">Join hundreds of businesses that trust us with their online presence</p>
                    <a href="#templates" class="btn btn-light btn-lg px-5">
                        <i class="bi bi-rocket-takeoff me-2"></i>Get Started Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-simple">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <h5 class="h6 mb-3">
                        <img src="/assets/images/webdaddy-logo.jpg" alt="WebDaddy Empire" style="height: 35px; margin-right: 8px;" onerror="this.style.display='none'">
                        <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-muted small">Professional website templates with domains included. Launch your business online in minutes.</p>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#templates" class="text-muted small text-decoration-none">Templates</a></li>
                        <li class="mb-2"><a href="#how-it-works" class="text-muted small text-decoration-none">How It Works</a></li>
                        <li class="mb-2"><a href="#faq" class="text-muted small text-decoration-none">FAQ</a></li>
                        <li class="mb-2"><a href="/affiliate/" class="text-muted small text-decoration-none">Become an Affiliate</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-bold mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-whatsapp me-2 text-success"></i>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="text-muted small text-decoration-none">
                                <?php echo WHATSAPP_NUMBER; ?>
                            </a>
                        </li>
                        <li class="mb-2"><a href="/admin/login.php" class="text-muted small text-decoration-none">Admin Login</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-bold mb-3">Security</h6>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark"><i class="bi bi-shield-check me-1"></i>Secure</span>
                        <span class="badge bg-light text-dark"><i class="bi bi-lock-fill me-1"></i>SSL</span>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Demo Modal -->
    <div class="modal fade" id="demoModal" tabindex="-1" aria-labelledby="demoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="demoModalLabel">Template Demo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="demoIframe" src="" frameborder="0" style="width:100%;height:100%;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Open demo modal
        function openDemo(url, title) {
            document.getElementById('demoModalLabel').textContent = title + ' - Demo';
            document.getElementById('demoIframe').src = url;
            new bootstrap.Modal(document.getElementById('demoModal')).show();
        }

        // Navbar scroll effect
        const navbar = document.getElementById('mainNav');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
