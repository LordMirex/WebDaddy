<?php
/**
 * User Create New Support Ticket
 */
require_once __DIR__ . '/includes/auth.php';
$customer = requireCustomer();

$page = 'support';
$pageTitle = 'New Support Ticket';

$successMessage = '';
$errorMessage = '';
$errors = [];

$orders = getCustomerOrders($customer['id'], 50);

$preSelectedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $category = $_POST['category'] ?? 'general';
    $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $message = trim($_POST['message'] ?? '');
    
    if (empty($subject)) {
        $errors['subject'] = 'Subject is required.';
    } elseif (strlen($subject) < 5) {
        $errors['subject'] = 'Subject must be at least 5 characters.';
    } elseif (strlen($subject) > 200) {
        $errors['subject'] = 'Subject must not exceed 200 characters.';
    }
    
    $validCategories = ['general', 'order', 'delivery', 'refund', 'technical', 'account'];
    if (!in_array($category, $validCategories)) {
        $errors['category'] = 'Please select a valid category.';
    }
    
    if ($orderId) {
        $orderValid = false;
        foreach ($orders as $order) {
            if ($order['id'] == $orderId) {
                $orderValid = true;
                break;
            }
        }
        if (!$orderValid) {
            $errors['order_id'] = 'Invalid order selected.';
            $orderId = null;
        }
    }
    
    if (empty($message)) {
        $errors['message'] = 'Message is required.';
    } elseif (strlen($message) < 20) {
        $errors['message'] = 'Please provide more details (at least 20 characters).';
    }
    
    if (empty($errors)) {
        $ticketId = createCustomerTicket([
            'customer_id' => $customer['id'],
            'order_id' => $orderId,
            'subject' => $subject,
            'message' => $message,
            'category' => $category
        ]);
        
        if ($ticketId) {
            header("Location: /user/ticket.php?id=$ticketId&created=1");
            exit;
        } else {
            $errorMessage = 'Failed to create ticket. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="/user/support.php" class="text-amber-600 hover:text-amber-700 inline-flex items-center text-sm font-medium">
            <i class="bi-arrow-left mr-2"></i>Back to Tickets
        </a>
    </div>
    
    <?php if ($errorMessage): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start space-x-3">
        <i class="bi-exclamation-circle-fill text-red-600 text-xl"></i>
        <div>
            <p class="text-red-800 font-medium"><?= htmlspecialchars($errorMessage) ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-4 border-b">
                    <h2 class="font-bold text-gray-900">Create Support Ticket</h2>
                    <p class="text-sm text-gray-500 mt-1">Describe your issue and we'll get back to you as soon as possible.</p>
                </div>
                
                <form method="POST" class="p-4 space-y-4">
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="subject" name="subject" required
                               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                               class="w-full px-4 py-2 border <?= isset($errors['subject']) ? 'border-red-500' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               placeholder="Brief description of your issue">
                        <?php if (isset($errors['subject'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $errors['subject'] ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select id="category" name="category" required
                                    class="w-full px-4 py-2 border <?= isset($errors['category']) ? 'border-red-500' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                <option value="general" <?= ($_POST['category'] ?? '') === 'general' ? 'selected' : '' ?>>General Inquiry</option>
                                <option value="order" <?= ($_POST['category'] ?? '') === 'order' ? 'selected' : '' ?>>Order Issue</option>
                                <option value="delivery" <?= ($_POST['category'] ?? '') === 'delivery' ? 'selected' : '' ?>>Delivery Problem</option>
                                <option value="refund" <?= ($_POST['category'] ?? '') === 'refund' ? 'selected' : '' ?>>Refund Request</option>
                                <option value="technical" <?= ($_POST['category'] ?? '') === 'technical' ? 'selected' : '' ?>>Technical Support</option>
                                <option value="account" <?= ($_POST['category'] ?? '') === 'account' ? 'selected' : '' ?>>Account Help</option>
                            </select>
                            <?php if (isset($errors['category'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= $errors['category'] ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="order_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Related Order (Optional)
                            </label>
                            <select id="order_id" name="order_id"
                                    class="w-full px-4 py-2 border <?= isset($errors['order_id']) ? 'border-red-500' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                <option value="">No related order</option>
                                <?php foreach ($orders as $order): ?>
                                <option value="<?= $order['id'] ?>" 
                                        <?= (($_POST['order_id'] ?? $preSelectedOrderId) == $order['id']) ? 'selected' : '' ?>>
                                    Order #<?= $order['id'] ?> - <?= date('M j, Y', strtotime($order['created_at'])) ?> (<?= ucfirst($order['status']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['order_id'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= $errors['order_id'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea id="message" name="message" rows="6" required minlength="20"
                                  class="w-full px-4 py-3 border <?= isset($errors['message']) ? 'border-red-500' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                  placeholder="Please describe your issue in detail. Include any relevant information such as error messages, steps you've taken, etc."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $errors['message'] ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pt-4 flex items-center justify-end gap-3">
                        <a href="/user/support.php" class="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 transition inline-flex items-center">
                            <i class="bi-send mr-2"></i>Submit Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <h3 class="font-bold text-gray-900 mb-4">Tips for Quick Resolution</h3>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <i class="bi-check-circle text-green-500 mt-0.5"></i>
                        <span>Be specific about your issue</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi-check-circle text-green-500 mt-0.5"></i>
                        <span>Include any error messages you see</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi-check-circle text-green-500 mt-0.5"></i>
                        <span>Link relevant orders if applicable</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi-check-circle text-green-500 mt-0.5"></i>
                        <span>Choose the right category</span>
                    </li>
                </ul>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <i class="bi-clock text-amber-600 text-xl"></i>
                    <div>
                        <h4 class="font-semibold text-amber-800">Response Time</h4>
                        <p class="text-sm text-amber-700 mt-1">We aim to respond to all tickets within 24 hours during business days.</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <i class="bi-question-circle text-blue-600 text-xl"></i>
                    <div>
                        <h4 class="font-semibold text-blue-800">Common Questions</h4>
                        <p class="text-sm text-blue-700 mt-1">Check your order details page for download links, delivery status, and credentials.</p>
                        <a href="/user/orders.php" class="text-sm text-blue-700 underline hover:no-underline mt-2 inline-block">
                            View My Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
