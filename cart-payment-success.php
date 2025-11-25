<?php
/**
 * Automatic Payment Success Page
 * Shows order details and available products after successful Paystack payment
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/delivery.php';

startSecureSession();

// Get order ID from URL
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$orderId) {
    header('Location: /');
    exit;
}

$db = getDb();

// Get order details
$stmt = $db->prepare("
    SELECT 
        po.*, 
        d.domain_name
    FROM pending_orders po
    LEFT JOIN domains d ON po.chosen_domain_id = d.id
    WHERE po.id = ? AND po.status = 'paid'
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: /');
    exit;
}

// Get order items with product details
$stmt = $db->prepare("
    SELECT 
        oi.product_id,
        oi.product_type,
        COALESCE(t.name, tl.name) as product_name,
        COALESCE(t.description, tl.description) as description,
        COALESCE(t.image, tl.image) as image
    FROM order_items oi
    LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
    LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
    WHERE oi.pending_order_id = ?
    GROUP BY oi.product_id, oi.product_type
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get delivery files
$stmt = $db->prepare("
    SELECT * FROM deliveries 
    WHERE pending_order_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$orderId]);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - WebDaddy Empire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --success: #10b981;
            --dark: #1f2937;
            --light: #f9fafb;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .container {
            max-width: 900px;
        }
        
        .success-header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }
        
        .success-header h1 {
            color: var(--dark);
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        
        .success-header p {
            color: #6b7280;
            margin: 0;
            font-size: 16px;
        }
        
        .order-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border: 1px solid var(--light);
            border-radius: 8px;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            background: var(--light);
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            color: var(--dark);
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .product-type {
            display: inline-block;
            background: #dbeafe;
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin: 0;
        }
        
        .order-summary {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .file-download {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: transform 0.2s;
        }
        
        .file-download:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .status-badge {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s;
            flex: 1;
            text-align: center;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-secondary-custom {
            background: white;
            color: var(--primary);
            padding: 12px 30px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
        }
        
        .btn-secondary-custom:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header with Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" style="height: 50px;">
            <a href="/" style="color: white; text-decoration: none; margin-left: 15px; font-weight: 600; font-size: 18px;">← Back</a>
        </div>
        
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1>Order Approved!</h1>
            <p>Your payment has been confirmed and your order is ready</p>
            <div style="margin-top: 20px;">
                <strong style="font-size: 18px; color: var(--primary);">Order #<?php echo $orderId; ?></strong>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div class="order-section" style="text-align: center; padding: 20px;">
            <div class="status-badge">✓ PAYMENT APPROVED & PROCESSING</div>
        </div>
        
        <!-- Order Items -->
        <div class="order-section">
            <div class="section-title">
                <span>📦</span>
                <span>Order Items</span>
            </div>
            
            <?php foreach ($orderItems as $item): ?>
            <div class="product-item">
                <?php if ($item['image']): ?>
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-image">
                <?php else: ?>
                    <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #e5e7eb; color: #9ca3af;">
                        <?php echo $item['product_type'] === 'template' ? '🎨' : '⚙️'; ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <h4 class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                    <p class="product-type"><?php echo $item['product_type']; ?></p>
                    <?php if ($item['description']): ?>
                        <p style="color: #6b7280; font-size: 14px; margin-top: 5px;"><?php echo htmlspecialchars(substr($item['description'], 0, 100)) . '...'; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Domain Info if applicable -->
            <?php if ($order['chosen_domain_id']): ?>
            <div class="product-item" style="background: #fef3c7; border-color: #fcd34d;">
                <div style="font-size: 24px;">🌐</div>
                <div class="product-info">
                    <h4 class="product-name"><?php echo htmlspecialchars($order['domain_name'] ?? 'Premium Domain'); ?></h4>
                    <p class="product-type">domain</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Summary -->
        <div class="order-section">
            <div class="section-title">
                <span>💰</span>
                <span>Order Summary</span>
            </div>
            
            <div class="order-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span><?php echo formatCurrency($order['original_price']); ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                <div class="summary-row">
                    <span>Discount:</span>
                    <span style="color: var(--success);">-<?php echo formatCurrency($order['discount_amount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span>Total Paid:</span>
                    <span><?php echo formatCurrency($order['final_amount']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Download Section -->
        <?php if (!empty($deliveries)): ?>
        <div class="order-section">
            <div class="section-title">
                <span>📥</span>
                <span>Downloads Available</span>
            </div>
            
            <p style="color: #6b7280; margin-bottom: 20px;">Your files are ready for download. Links are available below:</p>
            
            <?php foreach ($deliveries as $delivery): ?>
                <?php if ($delivery['file_path']): ?>
                    <a href="/api/download-delivery.php?delivery_id=<?php echo $delivery['id']; ?>" class="file-download">
                        📄 Download <?php echo htmlspecialchars(basename($delivery['file_path'])); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <p style="color: #9ca3af; font-size: 14px; margin-top: 15px;">
                <strong>Note:</strong> Files are available for 30 days. Download them now and keep them safe!
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Next Steps -->
        <div class="order-section">
            <div class="section-title">
                <span>📋</span>
                <span>What's Next?</span>
            </div>
            
            <div style="background: #ecfdf5; border-left: 4px solid var(--success); padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <p style="margin: 0; color: var(--dark); font-weight: 600;">✓ A confirmation email has been sent to <?php echo htmlspecialchars($order['customer_email']); ?></p>
                <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">Check your inbox for download links and setup instructions</p>
            </div>
            
            <?php if ($order['order_type'] === 'template' || strpos(implode(',', array_column($orderItems, 'product_type')), 'template') !== false): ?>
            <div style="background: #dbeafe; border-left: 4px solid var(--primary); padding: 15px; border-radius: 6px;">
                <p style="margin: 0; color: var(--dark); font-weight: 600;">⏱️ Setup in Progress</p>
                <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">Your website will be live within 24 hours. You'll receive an email with your login credentials.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="/" class="btn-primary-custom">Continue Shopping</a>
            <a href="mailto:support@webdaddy.com" class="btn-secondary-custom">Contact Support</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
