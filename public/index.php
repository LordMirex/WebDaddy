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
    <title><?php echo SITE_NAME; ?> - Launch Your Business Website in Minutes</title>
    <meta name="description" content="Professional, pre-built website templates with domains included. Get your business online fast with our ready-to-use templates.">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.jpg" alt="WebDaddy Empire" style="height: 50px; margin-right: 10px;">
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

    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center">
                    <?php if ($affiliateCode): ?>
                    <div class="alert alert-light border-0 d-inline-block mb-4" style="background: rgba(255,255,255,0.2); color: white;">
                        <i class="bi bi-gift-fill me-2"></i>
                        <strong>Special Offer:</strong> Affiliate discount applied with code <?php echo htmlspecialchars($affiliateCode); ?>
                    </div>
                    <?php endif; ?>
                    <h1 class="display-3 fw-bold mb-4">Launch Your Business Website in Minutes</h1>
                    <p class="lead mb-5">
                        Skip the hassle of building from scratch. Get a professional, fully-functional website with a premium domain included.
                        Perfect for businesses ready to go online today.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="#templates" class="btn btn-light btn-lg px-5 py-3 fw-bold">
                            <i class="bi bi-grid-3x3-gap me-2"></i>Browse Templates
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg px-5 py-3 fw-semibold">
                            Learn More
                        </a>
                    </div>
                    <div class="mt-5 pt-4">
                        <div class="row g-4 text-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check-circle-fill text-white me-2" style="font-size: 1.5rem;"></i>
                                    <span class="fs-5">Domain Included</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check-circle-fill text-white me-2" style="font-size: 1.5rem;"></i>
                                    <span class="fs-5">Ready in 24 Hours</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check-circle-fill text-white me-2" style="font-size: 1.5rem;"></i>
                                    <span class="fs-5">Full Customization</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="py-5 bg-light" id="how-it-works">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold mb-3">How It Works</h2>
                    <p class="lead text-muted">Get your business online in 3 simple steps</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                                <i class="bi bi-1-circle-fill text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h3 class="h4 fw-bold mb-3">Choose Your Template</h3>
                            <p class="text-muted">Browse our collection of professional templates and select the one that fits your business perfectly.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                                <i class="bi bi-2-circle-fill text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h3 class="h4 fw-bold mb-3">Select Your Domain</h3>
                            <p class="text-muted">Pick from our available premium domains or request a custom domain for your brand.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                                <i class="bi bi-3-circle-fill text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h3 class="h4 fw-bold mb-3">Launch & Customize</h3>
                            <p class="text-muted">Receive your login credentials and start customizing your site with your content, brand colors, and images.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white" id="templates">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-3">Professional Templates</h2>
                    <p class="lead text-muted">Choose from our hand-picked collection of modern, responsive website templates</p>
                </div>
            </div>

            <?php if (empty($templates)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info text-center p-5">
                        <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                        <h4>No templates available at the moment</h4>
                        <p>Please check back later or contact support.</p>
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
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>" 
                                 class="card-img-top" 
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
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h3 class="card-title mb-0"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="badge"><?php echo htmlspecialchars($template['category']); ?></span>
                            </div>
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo htmlspecialchars($template['description'] ?? ''); ?>
                            </p>
                            <?php if (!empty($features) && count($features) > 0): ?>
                            <div class="mb-3">
                                <ul class="list-unstyled small mb-0">
                                    <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                    <li class="mb-1">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <?php echo htmlspecialchars(trim($feature)); ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                                <div>
                                    <small class="text-muted d-block">Starting at</small>
                                    <h4 class="price mb-0"><?php echo formatCurrency($template['price']); ?></h4>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="template.php?id=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        Details
                                    </a>
                                    <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn btn-primary">
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

    <section class="py-5 bg-light" id="features">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-3">Why Choose Us</h2>
                    <p class="lead text-muted">Everything you need to succeed online, in one place</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                            <i class="bi bi-lightning-charge-fill text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold">Fast Setup</h4>
                        <p class="text-muted">Get your website live within 24 hours of payment confirmation</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                            <i class="bi bi-phone-fill text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold">Mobile Ready</h4>
                        <p class="text-muted">All templates are fully responsive and mobile-optimized</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                            <i class="bi bi-shield-check text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold">Secure & Reliable</h4>
                        <p class="text-muted">Built with security best practices and reliable hosting</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                            <i class="bi bi-headset text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="fw-bold">24/7 Support</h4>
                        <p class="text-muted">Get help anytime via WhatsApp or email support</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white" id="faq">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-3">Frequently Asked Questions</h2>
                    <p class="lead text-muted">Everything you need to know about our templates</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h3 class="accordion-header">
                                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What's included in the price?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Each purchase includes the complete website template, a premium domain name, hosting setup, and full access to customize your site. You also receive login credentials and documentation to help you get started.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How long does it take to get my website?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    After payment confirmation, your website will be set up and ready within 24 hours. You'll receive your login credentials via email, and you can start customizing immediately.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Can I customize the template?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Absolutely! You have full control to customize colors, text, images, and content. The template is fully yours to modify as you see fit.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What if I need help?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We offer support via WhatsApp at <?php echo WHATSAPP_NUMBER; ?>. Our team is ready to help you with any questions or technical issues you encounter.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">Ready to Launch Your Website?</h2>
                    <p class="lead mb-4">Join hundreds of businesses that trust us with their online presence</p>
                    <a href="#templates" class="btn btn-light btn-lg px-5 py-3 fw-bold">
                        Get Started Now
                    </a>
                </div>
            </div>
        </div>
    </section>

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

    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-lightning-charge-fill me-2"></i><?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-white-50">Professional website templates with domains included. Launch your business online in minutes.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#templates" class="text-white-50 text-decoration-none">Templates</a></li>
                        <li class="mb-2"><a href="#how-it-works" class="text-white-50 text-decoration-none">How It Works</a></li>
                        <li class="mb-2"><a href="#faq" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li class="mb-2"><a href="/affiliate/" class="text-white-50 text-decoration-none">Become an Affiliate</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Contact</h5>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2">
                            <i class="bi bi-whatsapp me-2"></i>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="text-white-50 text-decoration-none">
                                <?php echo WHATSAPP_NUMBER; ?>
                            </a>
                        </li>
                        <li class="mb-2"><a href="/admin/login.php" class="text-white-50 text-decoration-none">Admin Login</a></li>
                    </ul>
                </div>
            </div>
            <hr class="border-white-50 my-4">
            <div class="row">
                <div class="col-12 text-center text-white-50">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
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

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
