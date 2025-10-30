<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
handleAffiliateTracking();

$templateId = (int)($_GET['template'] ?? 0);
$template = getTemplateById($templateId);

if (!$template) {
    header('Location: /');
    exit;
}

$availableDomains = getAvailableDomains($templateId);

$customFields = !empty($template['custom_fields']) ? json_decode($template['custom_fields'], true) : [];

$errors = [];
$success = false;

$affiliateDiscountRate = 0.20; // 20% discount
$affiliateDiscountPercent = (int)($affiliateDiscountRate * 100);
$originalPrice = $template['price'];

$affiliateCode = getAffiliateCode();
$affiliateData = null;
$hasAffiliate = false;

if (!empty($affiliateCode)) {
    $affiliateData = getAffiliateByCode($affiliateCode);
    if ($affiliateData) {
        $hasAffiliate = true;
    } else {
        $affiliateCode = null;
    }
}

$submittedAffiliateCode = isset($_POST['affiliate_code']) ? trim($_POST['affiliate_code']) : '';
if (!empty($submittedAffiliateCode) && function_exists('sanitizeInput')) {
    $submittedAffiliateCode = sanitizeInput($submittedAffiliateCode);
}

$affiliateInvalid = false;

if (!empty($submittedAffiliateCode) && !$hasAffiliate) {
    $lookupAffiliate = getAffiliateByCode($submittedAffiliateCode);
    if ($lookupAffiliate) {
        $affiliateData = $lookupAffiliate;
        $affiliateCode = $submittedAffiliateCode;
        $hasAffiliate = true;
        $_SESSION['affiliate_code'] = $affiliateCode;
        setcookie(
            'affiliate_code',
            $affiliateCode,
            time() + ((defined('AFFILIATE_COOKIE_DAYS') ? AFFILIATE_COOKIE_DAYS : 30) * 86400),
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
    } else {
        $affiliateInvalid = true;
    }
}

$discountedPrice = $originalPrice;
$discountAmount = 0;

if ($hasAffiliate) {
    $discountedPrice = round($originalPrice * (1 - $affiliateDiscountRate), 2);
    $discountAmount = max(0, $originalPrice - $discountedPrice);
}

$isApplyAffiliate = isset($_POST['apply_affiliate']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isApplyAffiliate && $affiliateInvalid) {
    $errors[] = 'Affiliate code not found. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isApplyAffiliate) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    
    if (empty($customerName)) {
        $errors[] = 'Please enter your full name';
    }
    
    if (empty($customerPhone)) {
        $errors[] = 'Please enter your WhatsApp number';
    }
    
    if (empty($errors)) {
        $payableAmount = $discountedPrice;
        $activeAffiliateCode = $hasAffiliate ? $affiliateCode : null;
        
        $message = "Hello! I would like to order:\n\n";
        $message .= "Template: " . $template['name'] . "\n";
        if ($hasAffiliate) {
            $message .= "Original Price: " . formatCurrency($originalPrice) . "\n";
            $message .= "Affiliate Discount ({$affiliateDiscountPercent}%): -" . formatCurrency($discountAmount) . "\n";
            $message .= "Price to Pay: " . formatCurrency($payableAmount) . "\n";
            $message .= "Affiliate Code: " . $activeAffiliateCode . "\n\n";
        } else {
            $message .= "Price: " . formatCurrency($payableAmount) . "\n\n";
        }
        $message .= "Customer Details:\n";
        $message .= "Name: " . $customerName . "\n";
        $message .= "WhatsApp: " . $customerPhone . "\n";
        
        $orderData = [
            'template_id' => $templateId,
            'chosen_domain_id' => null, // No domain selected yet
            'customer_name' => $customerName,
            'customer_email' => '', // Empty since we don't collect email
            'customer_phone' => $customerPhone,
            'business_name' => '', // Empty since we don't collect business name
            'custom_fields' => null,
            'affiliate_code' => $activeAffiliateCode,
            'session_id' => session_id(),
            'message_text' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'discounted_price' => $payableAmount,
            'discount_amount' => $discountAmount,
            'affiliate_discount_rate' => $hasAffiliate ? $affiliateDiscountRate : 0
        ];
        
        $orderId = createPendingOrder($orderData);
        
        if ($orderId && empty($errors)) {
            logActivity('order_initiated', 'Order #' . $orderId . ' for template ' . $template['name']);
            
            // Add order_id to orderData for WhatsApp message
            $orderData['order_id'] = $orderId;
            
            // Build WhatsApp link directly with the custom message
            $whatsappNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126'));
            $encodedMessage = rawurlencode($message);
            $whatsappLink = "https://wa.me/" . $whatsappNumber . "?text=" . $encodedMessage;
            
            // Use JavaScript redirect for better WhatsApp compatibility
            echo "<script>
                window.location.href = '" . addslashes($whatsappLink) . "';
            </script>";
            exit;
        }
    }
}

$pageTitle = 'Order ' . htmlspecialchars($template['name']);
$features = $template['features'] ? explode(',', $template['features']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" style="height: 50px; margin-right: 12px;">
                <span style="color: var(--royal-blue);"><?php echo SITE_NAME; ?></span>
            </a>
            <div class="ms-auto">
                <a href="/" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
    </nav>

    <section class="hero-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-5 fw-800 mb-3 text-white">Complete Your Order</h1>
                    <p class="lead text-white-80">You're just one step away from launching your website</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-6">
        <div class="row g-4">
            <div class="col-lg-8">
                <?php if (!empty($error)): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4 rounded-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <hr>
                    <p class="mb-0">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" class="alert-link fw-600" target="_blank">
                            <i class="bi bi-whatsapp me-2"></i>Contact us on WhatsApp
                        </a> for custom domain options
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4 rounded-3">
                    <h5 class="alert-heading fw-700"><i class="bi bi-exclamation-circle me-2"></i>Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (empty($error)): ?>
                    <form method="POST" action="" id="orderForm">
                    <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
                        <div class="card-body p-5">
                            <div class="d-flex align-items-center mb-4">
                                <span class="step-badge me-3">1</span>
                                <h3 class="h4 fw-800 mb-0">Your Information</h3>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-12 col-md-6">
                                    <label for="customer_name" class="form-label fw-700">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           id="customer_name" 
                                           name="customer_name" 
                                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" 
                                           required
                                           placeholder="John Doe">
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label for="customer_phone" class="form-label fw-700">
                                        WhatsApp Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" 
                                           class="form-control form-control-lg" 
                                           id="customer_phone" 
                                           name="customer_phone" 
                                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" 
                                           required
                                           placeholder="+234...">
                                    <small class="text-muted">For order updates and support</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
                        <div class="card-body p-5">
                            <div class="d-flex align-items-center mb-4">
                                <span class="step-badge me-3">2</span>
                                <div>
                                    <h3 class="h4 fw-800 mb-1">Affiliate Bonus</h3>
                                    <p class="mb-0 text-muted small">Unlock a 20% discount instantly when you use a valid affiliate code.</p>
                                </div>
                            </div>

                            <?php if ($hasAffiliate): ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                                    <div>
                                        <h5 class="fw-700 mb-1">Affiliate code applied!</h5>
                                        <p class="mb-0 text-success">You're saving <strong><?php echo formatCurrency($discountAmount); ?></strong> today.</p>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-700">Affiliate Code</label>
                                    <input type="text" class="form-control form-control-lg" value="<?php echo htmlspecialchars($affiliateCode); ?>" readonly>
                                    <small class="text-muted">Affiliate bonuses are applied automatically.</small>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="affiliate_code" class="form-label fw-700">Affiliate Code (optional)</label>
                                    <div class="input-group input-group-lg">
                                        <input type="text" 
                                               class="form-control <?php echo !empty($submittedAffiliateCode) && empty($affiliateData) ? 'is-invalid' : ''; ?>" 
                                               id="affiliate_code" 
                                               name="affiliate_code" 
                                               value="<?php echo htmlspecialchars($submittedAffiliateCode); ?>" 
                                               placeholder="Enter affiliate code">
                                        <button class="btn btn-outline-primary" type="button" name="apply_affiliate" value="1">
                                            Apply & Save 20%
                                        </button>
                                    </div>
                                    <small class="text-muted">Know someone who referred you? Use their code and save.</small>
                                    <?php if (!empty($submittedAffiliateCode) && empty($affiliateData)): ?>
                                        <div class="invalid-feedback d-block">Affiliate code not found. Please check and try again.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card border-0 bg-primary bg-opacity-10 rounded-3 mb-4 overflow-hidden">
                        <div class="card-body p-5">
                            <h5 class="fw-800 mb-3"><i class="bi bi-info-circle me-2"></i>What Happens Next?</h5>
                            <ol class="mb-0 ps-3">
                                <li class="mb-2">Click <strong>"Continue to WhatsApp"</strong> below</li>
                                <li class="mb-2">You'll be redirected to WhatsApp with your order details pre-filled</li>
                                <li class="mb-2">Send the message to our team</li>
                                <li class="mb-2">Make payment via bank transfer (details will be provided)</li>
                                <li>Receive your website login credentials within <strong>24 hours</strong></li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-3 mb-4">
                        <button type="submit" class="btn btn-success btn-lg py-3 fw-800" id="submitBtn">
                            <i class="bi bi-whatsapp me-2"></i>
                            <span class="d-none d-md-inline">Continue to WhatsApp</span>
                            <span class="d-md-none">Order Now</span>
                        </button>
                        <a href="template.php?id=<?php echo $template['id']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Template Details
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-3 position-sticky overflow-hidden" style="top: 100px;">
                    <div class="card-body p-5">
                        <h5 class="fw-800 mb-4">Order Summary</h5>
                        
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($template['name']); ?>" 
                                 class="img-fluid rounded-2 mb-3"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <h5 class="fw-700"><?php echo htmlspecialchars($template['name']); ?></h5>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($template['category']); ?></span>
                        </div>
                        
                        <?php if (!empty($features)): ?>
                        <div class="mb-4">
                            <h6 class="fw-700 mb-3">Includes:</h6>
                            <ul class="list-unstyled small">
                                <?php foreach (array_slice($features, 0, 4) as $feature): ?>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <?php echo htmlspecialchars(trim($feature)); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Template Price:</span>
                            <strong><?php echo formatCurrency($originalPrice); ?></strong>
                        </div>

                        <?php if ($hasAffiliate): ?>
                            <div class="d-flex justify-content-between mb-3 text-success">
                                <span>Affiliate Discount (<?php echo $affiliateDiscountPercent; ?>%):</span>
                                <strong>-<?php echo formatCurrency($discountAmount); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-3">
                        
                        <div class="d-flex justify-content-between align-items-center mb-0">
                            <h5 class="fw-800 mb-0">You Pay:</h5>
                            <h4 class="text-primary fw-800 mb-0"><?php echo formatCurrency($discountedPrice); ?></h4>
                        </div>
                        <?php if ($hasAffiliate): ?>
                            <small class="text-success d-block text-end">Savings applied!</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-custom">
        <div class="container text-center">
            <p class="mb-0 small">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('orderForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            }
            // Don't prevent default - let the form submit
        });
        
        // Affiliate code input handling
        const affiliateInput = document.getElementById('affiliate_code');
        const applyBtn = document.querySelector('button[name="apply_affiliate"]');
        const affiliateSection = document.querySelector('.card-body:has(.alert)');
        
        if (affiliateInput) {
            // Convert to uppercase as user types
            affiliateInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
            
            // Handle affiliate code application
            if (applyBtn) {
                applyBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const code = affiliateInput.value.trim().toUpperCase();
                    if (!code) {
                        showAffiliateMessage('Please enter an affiliate code', 'danger');
                        return;
                    }
                    
                    // Show loading state
                    applyBtn.disabled = true;
                    applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Applying...';
                    
                    // Send AJAX request to apply affiliate code
                    fetch('/apply_affiliate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'affiliate_code=' + encodeURIComponent(code)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message and reload to show updated pricing
                            showAffiliateMessage('Affiliate code applied successfully! Updating prices...', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showAffiliateMessage(data.message, 'danger');
                            applyBtn.disabled = false;
                            applyBtn.innerHTML = 'Apply & Save 20%';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAffiliateMessage('Network error. Please try again.', 'danger');
                        applyBtn.disabled = false;
                        applyBtn.innerHTML = 'Apply & Save 20%';
                    });
                });
            }
        }
        
        function showAffiliateMessage(message, type) {
            // Remove existing message
            const existingMsg = document.querySelector('.affiliate-message');
            if (existingMsg) {
                existingMsg.remove();
            }
            
            // Create new message
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} affiliate-message mt-2`;
            alertDiv.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}`;
            
            // Insert after the input group
            const inputGroup = document.querySelector('.input-group');
            if (inputGroup && inputGroup.parentNode) {
                inputGroup.parentNode.insertBefore(alertDiv, inputGroup.nextSibling);
                
                // Auto-hide success messages after 3 seconds
                if (type === 'success') {
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                }
            }
        }
        
        // Mobile WhatsApp optimization
        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            const whatsappBtn = document.getElementById('submitBtn');
            if (whatsappBtn) {
                whatsappBtn.setAttribute('data-mobile', 'true');
            }
        }
    </script>
</body>
</html>
