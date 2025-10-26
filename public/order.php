<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
handleAffiliateTracking();

$templateId = isset($_GET['template']) ? (int)$_GET['template'] : 0;

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

if (empty($availableDomains)) {
    $error = 'Sorry, no domains are currently available for this template. Please contact us for custom domains.';
}

$customFields = !empty($template['custom_fields']) ? json_decode($template['custom_fields'], true) : [];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $chosenDomainId = (int)($_POST['chosen_domain'] ?? 0);
    
    if (empty($customerName)) {
        $errors[] = 'Please enter your full name';
    }
    
    if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($customerPhone)) {
        $errors[] = 'Please enter your phone number';
    }
    
    if (empty($businessName)) {
        $errors[] = 'Please enter your business name';
    }
    
    if ($chosenDomainId <= 0) {
        $errors[] = 'Please select a domain';
    } else {
        $domainValid = false;
        foreach ($availableDomains as $domain) {
            if ($domain['id'] == $chosenDomainId) {
                $domainValid = true;
                $chosenDomain = $domain;
                break;
            }
        }
        if (!$domainValid) {
            $errors[] = 'Selected domain is not valid';
        }
    }
    
    $customFieldData = [];
    if (!empty($customFields)) {
        foreach ($customFields as $field) {
            $fieldName = 'custom_' . $field['name'];
            $fieldValue = trim($_POST[$fieldName] ?? '');
            
            if (!empty($field['required']) && empty($fieldValue)) {
                $errors[] = 'Please enter ' . htmlspecialchars($field['label']);
            }
            
            $customFieldData[$field['name']] = $fieldValue;
        }
    }
    
    if (empty($errors)) {
        $db = getDb();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, domain_name FROM domains WHERE id = ? AND status = 'available' AND assigned_order_id IS NULL FOR UPDATE");
            $stmt->execute([$chosenDomainId]);
            $domainCheck = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$domainCheck) {
                $db->rollBack();
                $errors[] = 'Sorry, this domain is no longer available. Please select another domain.';
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = 'An error occurred. Please try again.';
            error_log('Domain availability check failed: ' . $e->getMessage());
        }
    }
    
    if (empty($errors)) {
        $affiliateCode = getAffiliateCode();
        $chosenDomain = $domainCheck;
        
        $message = "Hello! I would like to order:\n\n";
        $message .= "Template: " . $template['name'] . "\n";
        $message .= "Domain: " . $chosenDomain['domain_name'] . "\n";
        $message .= "Price: " . formatCurrency($template['price']) . "\n\n";
        $message .= "Customer Details:\n";
        $message .= "Name: " . $customerName . "\n";
        $message .= "Email: " . $customerEmail . "\n";
        $message .= "Phone: " . $customerPhone . "\n";
        $message .= "Business: " . $businessName . "\n";
        
        if (!empty($customFieldData)) {
            $message .= "\nAdditional Information:\n";
            foreach ($customFields as $field) {
                if (!empty($customFieldData[$field['name']])) {
                    $message .= $field['label'] . ": " . $customFieldData[$field['name']] . "\n";
                }
            }
        }
        
        $orderData = [
            'template_id' => $templateId,
            'chosen_domain_id' => $chosenDomainId,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'business_name' => $businessName,
            'custom_fields' => !empty($customFieldData) ? json_encode($customFieldData) : null,
            'affiliate_code' => $affiliateCode,
            'session_id' => session_id(),
            'message_text' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $orderId = createPendingOrder($orderData);
        
        if ($orderId) {
            try {
                $updateStmt = $db->prepare("UPDATE domains SET assigned_order_id = ? WHERE id = ? AND assigned_order_id IS NULL");
                $updateStmt->execute([$orderId, $chosenDomainId]);
                
                if ($updateStmt->rowCount() === 0) {
                    $db->rollBack();
                    $errors[] = 'Sorry, this domain was just taken by another customer. Please select a different domain.';
                } else {
                    $db->commit();
                }
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('Failed to assign domain to order: ' . $e->getMessage());
                $errors[] = 'An error occurred while processing your order. Please try again.';
            }
        }
        
        if ($orderId && empty($errors)) {
            logActivity('order_initiated', 'Order #' . $orderId . ' for template ' . $template['name']);
            
            $whatsappNumber = preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER);
            $whatsappLink = "https://wa.me/" . $whatsappNumber . "?text=" . urlencode($message);
            
            header('Location: ' . $whatsappLink);
            exit;
        }
        
        if (!empty($errors) && isset($db) && $db->inTransaction()) {
            $db->rollBack();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <style>
        .order-hero {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--navy-blue) 100%);
            padding: 3rem 0 2rem 0;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        .step-indicator {
            background: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content-center;
            font-weight: bold;
            color: var(--primary-color);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <img src="/assets/images/webdaddy-logo.jpg" alt="WebDaddy Empire" style="height: 50px; margin-right: 10px;">
                <span style="color: var(--royal-blue);"><?php echo SITE_NAME; ?></span>
            </a>
            <div class="ms-auto">
                <a href="/" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
    </nav>

    <section class="order-hero text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-5 fw-bold mb-3">Complete Your Order</h1>
                    <p class="lead">You're just one step away from launching your website</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <?php if (!empty($error)): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <hr>
                    <p class="mb-0">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="alert-link" target="_blank">
                            <i class="bi bi-whatsapp me-2"></i>Contact us on WhatsApp
                        </a> for custom domain options
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-circle me-2"></i>Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (empty($error)): ?>
                <form method="POST" action="" id="orderForm">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <span class="step-indicator me-3">1</span>
                                <h3 class="h4 fw-bold mb-0">Your Information</h3>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="customer_name" class="form-label fw-semibold">
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
                                
                                <div class="col-md-6">
                                    <label for="customer_email" class="form-label fw-semibold">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" 
                                           class="form-control form-control-lg" 
                                           id="customer_email" 
                                           name="customer_email" 
                                           value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" 
                                           required
                                           placeholder="john@example.com">
                                    <small class="text-muted">Your login credentials will be sent here</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="customer_phone" class="form-label fw-semibold">
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
                                
                                <div class="col-md-6">
                                    <label for="business_name" class="form-label fw-semibold">
                                        Business Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           id="business_name" 
                                           name="business_name" 
                                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" 
                                           required
                                           placeholder="My Business">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <span class="step-indicator me-3">2</span>
                                <h3 class="h4 fw-bold mb-0">Choose Your Domain</h3>
                            </div>
                            
                            <label for="chosen_domain" class="form-label fw-semibold">
                                Select a Domain <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="chosen_domain" name="chosen_domain" required>
                                <option value="">-- Select your preferred domain --</option>
                                <?php foreach ($availableDomains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" 
                                        <?php echo (isset($_POST['chosen_domain']) && $_POST['chosen_domain'] == $domain['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($domain['domain_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                <?php echo count($availableDomains); ?> premium domain(s) available
                            </small>
                        </div>
                    </div>
                    
                    <?php if (!empty($customFields)): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <span class="step-indicator me-3">3</span>
                                <h3 class="h4 fw-bold mb-0">Additional Information</h3>
                            </div>
                            
                            <div class="row g-3">
                                <?php foreach ($customFields as $field): ?>
                                <div class="col-12">
                                    <label for="custom_<?php echo htmlspecialchars($field['name']); ?>" class="form-label fw-semibold">
                                        <?php echo htmlspecialchars($field['label']); ?>
                                        <?php if (!empty($field['required'])): ?>
                                        <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea class="form-control" 
                                              id="custom_<?php echo htmlspecialchars($field['name']); ?>" 
                                              name="custom_<?php echo htmlspecialchars($field['name']); ?>" 
                                              rows="3" 
                                              <?php echo !empty($field['required']) ? 'required' : ''; ?>><?php echo htmlspecialchars($_POST['custom_' . $field['name']] ?? ''); ?></textarea>
                                    <?php else: ?>
                                    <input type="text" 
                                           class="form-control" 
                                           id="custom_<?php echo htmlspecialchars($field['name']); ?>" 
                                           name="custom_<?php echo htmlspecialchars($field['name']); ?>" 
                                           value="<?php echo htmlspecialchars($_POST['custom_' . $field['name']] ?? ''); ?>" 
                                           <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($field['description'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($field['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>What Happens Next?</h5>
                            <ol class="mb-0 ps-3">
                                <li class="mb-2">Click <strong>"Continue to WhatsApp"</strong> below</li>
                                <li class="mb-2">You'll be redirected to WhatsApp with your order details pre-filled</li>
                                <li class="mb-2">Send the message to our team</li>
                                <li class="mb-2">Make payment via bank transfer (details will be provided)</li>
                                <li>Receive your website login credentials within <strong>24 hours</strong></li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-3 mt-4">
                        <button type="submit" class="btn btn-success btn-lg py-3 fw-bold" id="submitBtn">
                            <i class="bi bi-whatsapp me-2"></i>Continue to WhatsApp
                        </button>
                        <a href="template.php?id=<?php echo $template['id']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Template Details
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm position-sticky" style="top: 100px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Order Summary</h5>
                        
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($template['name']); ?>" 
                                 class="img-fluid rounded mb-3"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <h5 class="fw-bold"><?php echo htmlspecialchars($template['name']); ?></h5>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($template['category']); ?></span>
                        </div>
                        
                        <?php if (!empty($features)): ?>
                        <div class="mb-4">
                            <h6 class="fw-semibold mb-3">Includes:</h6>
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
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Template Price:</span>
                            <strong><?php echo formatCurrency($template['price']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Domain:</span>
                            <strong>Included</strong>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="d-flex justify-content-between align-items-center mb-0">
                            <h5 class="fw-bold mb-0">Total:</h5>
                            <h4 class="text-primary fw-bold mb-0"><?php echo formatCurrency($template['price']); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('orderForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        });
    </script>
</body>
</html>
