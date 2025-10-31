<?php
$pageTitle = 'Email Affiliate';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $affiliateId = (int)($_POST['affiliate_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($affiliateId) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } else {
        // Get affiliate details
        $stmt = $db->prepare("
            SELECT u.name, u.email 
            FROM affiliates a
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) {
            $error = 'Affiliate not found.';
        } else {
            // Send email
            $message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            if (sendCustomEmailToAffiliate($affiliate['name'], $affiliate['email'], $subject, $message)) {
                $success = 'Email sent successfully to ' . htmlspecialchars($affiliate['name']) . '!';
                logActivity('email_sent', "Admin sent email to affiliate: {$affiliate['email']}", getAdminId());
            } else {
                $error = 'Failed to send email. Please check your email configuration.';
            }
        }
    }
}

// Get all affiliates
$affiliates = $db->query("
    SELECT a.id, a.code, u.name, u.email, u.status
    FROM affiliates a
    JOIN users u ON a.user_id = u.id
    ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-envelope"></i> Email Affiliate</h1>
    <p class="text-muted">Send custom email to an affiliate</p>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope-fill"></i> Compose Email</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="affiliate_id" class="form-label">Select Affiliate <span class="text-danger">*</span></label>
                        <select class="form-select" id="affiliate_id" name="affiliate_id" required>
                            <option value="">-- Choose Affiliate --</option>
                            <?php foreach ($affiliates as $aff): ?>
                            <option value="<?php echo $aff['id']; ?>">
                                <?php echo htmlspecialchars($aff['name']); ?> 
                                (<?php echo htmlspecialchars($aff['email']); ?>) 
                                - Code: <?php echo htmlspecialchars($aff['code']); ?>
                                <?php if ($aff['status'] !== 'active'): ?>
                                    [<?php echo strtoupper($aff['status']); ?>]
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Email Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject" name="subject" required placeholder="Enter email subject">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="10" required placeholder="Type your message here..."></textarea>
                        <small class="text-muted">The message will be formatted as HTML automatically.</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Note:</strong> The email will be sent using the professional WebDaddy template with your custom message.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="/admin/affiliates.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Email Preview</h6>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>The email will include:</strong></p>
                <ul class="small">
                    <li>Professional WebDaddy header with logo</li>
                    <li>Personalized greeting with affiliate's name</li>
                    <li>Your custom subject and message</li>
                    <li>Contact information and WhatsApp link</li>
                    <li>Professional footer with links</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
