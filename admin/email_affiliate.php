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
            // Send email with HTML content (sanitization handled in mailer function)
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
                        <div id="editor" style="min-height: 300px; background: white; border: 1px solid #ced4da; border-radius: 0.375rem;"></div>
                        <textarea id="message" name="message" style="display:none;"></textarea>
                        <small class="text-muted">Use the editor toolbar to format your message with headings, bold, lists, links, etc.</small>
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
                <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Email Template Features</h6>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Your email will include:</strong></p>
                <ul class="small mb-3">
                    <li><strong>Professional Header:</strong> Royal blue gradient with crown icon and WebDaddy branding</li>
                    <li><strong>Personalized Greeting:</strong> Affiliate's name prominently displayed</li>
                    <li><strong>Your Custom Content:</strong> Beautifully formatted with gold accent border</li>
                    <li><strong>Call-to-Action:</strong> Button linking to affiliate dashboard</li>
                    <li><strong>Quick Tip Section:</strong> Helpful reminder about sharing referral links</li>
                    <li><strong>Contact Information:</strong> WhatsApp support link</li>
                    <li><strong>Professional Footer:</strong> Links to Home, Affiliate Portal, and Support</li>
                </ul>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-palette"></i> <strong>Formatting Tips:</strong>
                    <ul class="small mb-0 mt-2">
                        <li>Use <strong>headings</strong> to organize content</li>
                        <li>Use <strong>bold</strong> and <em>italic</em> for emphasis</li>
                        <li>Create bullet points or numbered lists for clarity</li>
                        <li>Add links to important resources</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill editor
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Type your message here...',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, 4, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link'],
                ['clean']
            ]
        }
    });

    // Set editor font styling
    var editorContainer = document.querySelector('#editor .ql-editor');
    if (editorContainer) {
        editorContainer.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
        editorContainer.style.fontSize = '15px';
        editorContainer.style.lineHeight = '1.6';
        editorContainer.style.color = '#374151';
        editorContainer.style.minHeight = '250px';
    }

    // Sync Quill content to hidden textarea before form submission
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            var messageField = document.querySelector('#message');
            messageField.value = quill.root.innerHTML;
            
            // Validate that content exists
            if (quill.getText().trim().length === 0) {
                e.preventDefault();
                alert('Please enter a message before sending.');
                return false;
            }
            
            // Show loading state on submit button
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            }
            
            return true;
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
