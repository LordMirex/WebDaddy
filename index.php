<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
handleAffiliateTracking();

// Get all active templates for filtering
$templates = getTemplates(true);
$affiliateCode = getAffiliateCode();

// Get unique categories
$categories = array_unique(array_column($templates, 'category'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo SITE_NAME; ?> - Professional Website Templates with Domains</title>
    <meta name="description" content="Professional website templates with domains included. Launch your business online in 24 hours.">
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/forms.js" defer></script>
</head>
<body>
    <!-- Navigation -->
    <nav id="mainNav" class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="navbar-logo" onerror="this.style.display='none'">
                <span class="brand-text"><?php echo SITE_NAME; ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#templates">Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#faq">FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-cta" href="/affiliate/register.php">Become an Affiliate</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-50">
                <div class="col-lg-10 mx-auto text-center">
                    <h1 class="display-4 fw-800 mb-3 text-white">Launch Your Business Online</h1>
                    <p class="lead text-white-80 mb-4">Professional website templates with domains included. Get online in 24 hours or less.</p>
                    
                    <!-- Trust Elements -->
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                        <div class="trust-badge">
                            <i class="bi bi-shield-check text-success me-2"></i>
                            <span class="small fw-600">30-Day Money Back</span>
                        </div>
                        <div class="trust-badge">
                            <i class="bi bi-lightning text-warning me-2"></i>
                            <span class="small fw-600">24hr Setup</span>
                        </div>
                        <div class="trust-badge">
                            <i class="bi bi-headset text-primary me-2"></i>
                            <span class="small fw-600">24/7 Support</span>
                        </div>
                    </div>
                    
                    <!-- Success Metrics -->
                    <div class="row justify-content-center text-center mb-4">
                        <div class="col-auto">
                            <div class="metric-item">
                                <div class="metric-number text-white fw-800">500+</div>
                                <div class="metric-label text-white-75 small">Websites Launched</div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="metric-item">
                                <div class="metric-number text-white fw-800">98%</div>
                                <div class="metric-label text-white-75 small">Happy Customers</div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="metric-item">
                                <div class="metric-number text-white fw-800">24hrs</div>
                                <div class="metric-label text-white-75 small">Average Setup</div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="#templates" class="btn btn-light px-4 fw-600">
                        <i class="bi bi-arrow-down me-2"></i>Explore Templates
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Templates Section -->
    <section class="py-6 bg-white" id="templates">
        <div class="container">
            <div class="row mb-4">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h2 fw-800 mb-3">Choose Your Template</h2>
                    <p class="text-muted fs-5">Pick a professionally designed website and get started instantly</p>
                </div>
            </div>

            <!-- Template Filters -->
            <div class="template-filters">
                <div class="row mb-3">
                    <div class="col-lg-6 mx-auto">
                        <div class="template-search">
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   placeholder="Search templates..." 
                                   data-template-search>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="category-filters justify-content-center">
                            <button class="category-filter-btn active" data-category-filter="all">
                                All Categories
                            </button>
                            <?php foreach ($categories as $category): ?>
                            <button class="category-filter-btn" data-category-filter="<?php echo htmlspecialchars(strtolower($category)); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="results-count" data-results-count>
                            <?php echo count($templates); ?> template<?php echo count($templates) !== 1 ? 's' : ''; ?> found
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($templates)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info text-center p-5 rounded-3">
                        <i class="bi bi-info-circle fs-1 d-block mb-3 opacity-75"></i>
                        <h4 class="fw-700">No templates available</h4>
                        <p class="text-muted mb-0">Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="fw-600">contact us on WhatsApp</a>.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-3" data-templates-grid>
                <?php foreach ($templates as $template): ?>
                <div class="col-12 col-sm-6 col-lg-4" 
                     data-template
                     data-template-name="<?php echo htmlspecialchars($template['name']); ?>"
                     data-template-category="<?php echo htmlspecialchars($template['category']); ?>"
                     data-template-price="<?php echo htmlspecialchars($template['price']); ?>">
                    <div class="template-card">
                        <div class="template-card-img">
                            <img data-src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                                 src="/assets/images/placeholder.jpg"
                                 alt="<?php echo htmlspecialchars($template['name']); ?>"
                                 class="lazy"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <?php if ($template['demo_url']): ?>
                            <button class="btn-preview" onclick="openDemo('<?php echo htmlspecialchars($template['demo_url']); ?>', '<?php echo htmlspecialchars($template['name']); ?>')">
                                <i class="bi bi-eye me-1"></i> Preview Demo
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="template-card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h3 class="h6 fw-700 mb-0"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="badge-cat"><?php echo htmlspecialchars($template['category']); ?></span>
                            </div>
                            <p class="text-muted small mb-3 template-desc"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="price-tag"><?php echo formatCurrency($template['price']); ?></div>
                                <div class="btn-group-custom">
                                    <a href="template.php?id=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn-compact btn-outline">
                                        View
                                    </a>
                                    <a href="order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                       class="btn-compact btn-solid">
                                        <i class="bi bi-lightning me-1"></i>Order Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <div class="row mt-4">
                <div class="col-12">
                    <div data-pagination></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-6 bg-light">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h2 fw-800 mb-3">Trusted by Businesses Like Yours</h2>
                    <p class="text-muted fs-5">See what our customers say about launching their online presence</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p class="testimonial-text mb-3">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                        <div class="testimonial-author">
                            <div class="testimonial-name fw-600">Adebayo Johnson</div>
                            <div class="testimonial-title small text-muted">Bella's Kitchen, Lagos</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p class="testimonial-text mb-3">"From zero to online business in one day! The setup was seamless and the support team is incredible."</p>
                        <div class="testimonial-author">
                            <div class="testimonial-name fw-600">Ngozi Okoro</div>
                            <div class="testimonial-title small text-muted">Fashion Boutique, Abuja</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-3">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p class="testimonial-text mb-3">"Professional, fast, and affordable. My law firm website attracts new clients every week. Highly recommended!"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-name fw-600">Barrister Emeka</div>
                            <div class="testimonial-title small text-muted">Legal Services, Port Harcourt</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-6 bg-white" id="how-it-works">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h2 fw-800 mb-3">How It Works</h2>
                    <p class="text-muted fs-5">Get your business online in 3 simple steps</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4 class="fw-700 mb-2">Choose Template</h4>
                        <p class="text-muted small">Browse our collection and select the template that perfectly fits your business needs.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4 class="fw-700 mb-2">Pick Domain & Pay</h4>
                        <p class="text-muted small">Select your preferred domain and complete payment securely via WhatsApp.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4 class="fw-700 mb-2">Launch & Grow</h4>
                        <p class="text-muted small">Receive your login credentials and start customizing your website immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-6 bg-white" id="faq">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="h2 fw-800 mb-3">Frequently Asked Questions</h2>
                    <p class="text-muted fs-5">Everything you need to know</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion accordion-custom" id="faqAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button fw-600" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What's included in the price?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Complete website template, premium domain name, hosting setup, and full customization access. You get everything needed to launch your business online.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-600" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How long does setup take?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Your website will be ready within 24 hours after payment confirmation. We handle all the technical setup so you can focus on your business.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-600" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Can I customize the template?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You have full control to customize colors, text, images, and content. Our team can also help with customization if needed.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed fw-600" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I get support?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We offer 24/7 support via WhatsApp at <?php echo WHATSAPP_NUMBER; ?>. Our team is always ready to help you with any questions or issues.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container">
            <div class="row g-5 mb-5">
                <div class="col-12 col-md-4">
                    <h5 class="fw-700 mb-3">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" style="height: 35px; margin-right: 10px;" onerror="this.style.display='none'">
                        <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-muted small">Professional website templates with domains included. Launch your business online in minutes.</p>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-700 mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#templates" class="text-muted small text-decoration-none">Templates</a></li>
                        <li class="mb-2"><a href="#how-it-works" class="text-muted small text-decoration-none">How It Works</a></li>
                        <li class="mb-2"><a href="#faq" class="text-muted small text-decoration-none">FAQ</a></li>
                        <li class="mb-2"><a href="/affiliate/register.php" class="text-muted small text-decoration-none">Become an Affiliate</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-700 mb-3">Contact</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-whatsapp me-2 text-success"></i>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" class="text-muted small text-decoration-none">
                                <?php echo getSetting('whatsapp_number', '+2349132672126'); ?>
                            </a>
                        </li>
                        <li class="mb-2"><a href="/admin/login.php" class="text-muted small text-decoration-none">Admin Login</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-700 mb-3">Security</h6>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark"><i class="bi bi-shield-check me-1"></i>Secure</span>
                        <span class="badge bg-light text-dark"><i class="bi bi-lock-fill me-1"></i>SSL</span>
                    </div>
                </div>
            </div>
            <hr class="my-4 opacity-25">
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
                    <h5 class="modal-title fw-700" id="demoModalLabel">Template Demo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="demoIframe" src="/placeholder.svg" frameborder="0" style="width:100%;height:100%;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        function openDemo(url, title) {
            document.getElementById('demoModalLabel').textContent = title + ' - Demo';
            document.getElementById('demoIframe').src = url;
            new bootstrap.Modal(document.getElementById('demoModal')).show();
        }

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
