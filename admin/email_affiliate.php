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

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-envelope text-primary-600"></i> Email Affiliate
    </h1>
    <p class="text-gray-600 mt-2">Send custom email to an affiliate</p>
</div>

<div class="max-w-4xl mx-auto">
    <?php if ($success): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
        <div class="flex items-center gap-3">
            <i class="bi bi-check-circle text-xl"></i>
            <span><?php echo $success; ?></span>
        </div>
        <button @click="show = false" class="text-green-700 hover:text-green-900">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
        <div class="flex items-center gap-3">
            <i class="bi bi-exclamation-triangle text-xl"></i>
            <span><?php echo $error; ?></span>
        </div>
        <button @click="show = false" class="text-red-700 hover:text-red-900">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-envelope-fill text-primary-600"></i> Compose Email
            </h5>
        </div>
        <div class="p-6">
            <form method="POST" action="">
                <div class="mb-5">
                    <label for="affiliate_id" class="block text-sm font-semibold text-gray-700 mb-2">Select Affiliate <span class="text-red-600">*</span></label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" id="affiliate_id" name="affiliate_id" required>
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
                
                <div class="mb-5">
                    <label for="subject" class="block text-sm font-semibold text-gray-700 mb-2">Email Subject <span class="text-red-600">*</span></label>
                    <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" id="subject" name="subject" required placeholder="Enter email subject">
                </div>
                
                <div class="mb-5">
                    <label for="message" class="block text-sm font-semibold text-gray-700 mb-2">Message <span class="text-red-600">*</span></label>
                    <div id="editor" style="min-height: 300px; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem;"></div>
                    <textarea id="message" name="message" style="display:none;"></textarea>
                    <small class="text-gray-500 text-sm">Use the editor toolbar to format your message with headings, bold, lists, links, etc.</small>
                </div>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg mb-6">
                    <i class="bi bi-info-circle"></i> <strong>Note:</strong> The email will be sent using the professional WebDaddy template with your custom message.
                </div>
                
                <div class="flex flex-col md:flex-row gap-3 md:justify-end">
                    <a href="/admin/affiliates.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors text-center">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-send"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md border border-gray-100 mt-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h6 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-lightbulb text-yellow-500"></i> Email Template Features
            </h6>
        </div>
        <div class="p-6">
            <p class="mb-3 font-semibold text-gray-900">Your email will include:</p>
            <ul class="space-y-2 text-sm text-gray-700 mb-4">
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Professional Header:</strong> Royal blue gradient with crown icon and WebDaddy branding</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Personalized Greeting:</strong> Affiliate's name prominently displayed</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Your Custom Content:</strong> Beautifully formatted with gold accent border</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Call-to-Action:</strong> Button linking to affiliate dashboard</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Quick Tip Section:</strong> Helpful reminder about sharing referral links</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Contact Information:</strong> WhatsApp support link</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="bi bi-check-circle text-green-600 mt-0.5"></i>
                    <span><strong>Professional Footer:</strong> Links to Home, Affiliate Portal, and Support</span>
                </li>
            </ul>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-lg">
                <div class="flex items-start gap-3">
                    <i class="bi bi-palette text-xl mt-0.5"></i>
                    <div>
                        <strong class="font-semibold">Formatting Tips:</strong>
                        <ul class="text-sm mt-2 space-y-1">
                            <li>• Use <strong>headings</strong> to organize content</li>
                            <li>• Use <strong>bold</strong> and <em>italic</em> for emphasis</li>
                            <li>• Create bullet points or numbered lists for clarity</li>
                            <li>• Add links to important resources</li>
                        </ul>
                    </div>
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
