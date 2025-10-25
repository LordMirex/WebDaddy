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
    $error = 'Sorry, no domains are currently available for this template. Please check back later.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Template Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Template Marketplace</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/login.php">Admin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../affiliate/login.php">Affiliate</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <img src="<?php echo htmlspecialchars($template['preview_image']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($template['name']); ?>"
                         onerror="this.src='https://via.placeholder.com/400x300?text=<?php echo urlencode($template['name']); ?>'">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($template['description']); ?></p>
                        <h4 class="text-primary"><?php echo formatCurrency($template['price']); ?></h4>
                        
                        <?php if (!empty($template['features'])): 
                            $features = json_decode($template['features'], true);
                            if ($features):
                        ?>
                        <hr>
                        <h5>Features:</h5>
                        <ul class="list-unstyled">
                            <?php foreach ($features as $feature): ?>
                            <li><i class="bi bi-check-circle-fill text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; endif; ?>
                        
                        <?php if (!empty($template['demo_url'])): ?>
                        <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                           class="btn btn-outline-primary btn-sm" 
                           target="_blank">View Demo</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Complete Your Order</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($error)): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="customer_name" 
                                       name="customer_name" 
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" 
                                       class="form-control" 
                                       id="customer_email" 
                                       name="customer_email" 
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" 
                                       required>
                                <small class="form-text text-muted">We'll send your login credentials here</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">WhatsApp Number <span class="text-danger">*</span></label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="customer_phone" 
                                       name="customer_phone" 
                                       value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" 
                                       placeholder="+234..." 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="business_name" 
                                       name="business_name" 
                                       value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="chosen_domain" class="form-label">Choose Your Domain <span class="text-danger">*</span></label>
                                <select class="form-select" id="chosen_domain" name="chosen_domain" required>
                                    <option value="">Select a domain...</option>
                                    <?php foreach ($availableDomains as $domain): ?>
                                    <option value="<?php echo $domain['id']; ?>" 
                                            <?php echo (isset($_POST['chosen_domain']) && $_POST['chosen_domain'] == $domain['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($domain['domain_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><?php echo count($availableDomains); ?> domain(s) available</small>
                            </div>
                            
                            <?php if (!empty($customFields)): ?>
                            <hr>
                            <h5>Additional Information</h5>
                            <?php foreach ($customFields as $field): ?>
                            <div class="mb-3">
                                <label for="custom_<?php echo htmlspecialchars($field['name']); ?>" class="form-label">
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
                                <small class="form-text text-muted"><?php echo htmlspecialchars($field['description']); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="bg-light p-3 rounded mb-3">
                                <h5>Order Summary</h5>
                                <div class="d-flex justify-content-between">
                                    <span>Template:</span>
                                    <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <span>Price:</span>
                                    <strong class="text-primary"><?php echo formatCurrency($template['price']); ?></strong>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Next Steps:</strong><br>
                                1. Click "Continue to WhatsApp" below<br>
                                2. You'll be redirected to WhatsApp with your order details pre-filled<br>
                                3. Send the message to complete your order<br>
                                4. Make payment via bank transfer (details will be provided)<br>
                                5. Receive your website login credentials via email within 24 hours of payment confirmation
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-whatsapp"></i> Continue to WhatsApp
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                        
                        <div class="mt-3 text-center">
                            <a href="index.php" class="btn btn-link">← Back to Templates</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Template Marketplace. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
