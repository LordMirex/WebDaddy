<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$siteName = SITE_NAME;
$lastUpdated = 'December 2024';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - <?php echo $siteName; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': '#0a1628',
                        'navy-light': '#1a2942',
                        'gold': '#d4af37',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-navy text-white min-h-screen">
    <nav class="bg-navy-light py-4 border-b border-gray-700/50">
        <div class="max-w-4xl mx-auto px-4">
            <a href="/" class="text-xl font-bold flex items-center gap-2">
                <img src="/assets/images/webdaddy-logo.png" alt="<?php echo $siteName; ?>" class="h-8" onerror="this.style.display='none'">
                <?php echo $siteName; ?>
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold mb-2">Terms of Service</h1>
        <p class="text-gray-400 mb-8">Last updated: <?php echo $lastUpdated; ?></p>

        <div class="prose prose-invert max-w-none space-y-6 text-gray-300">
            <section>
                <h2 class="text-xl font-semibold text-white mb-3">1. Acceptance of Terms</h2>
                <p>By accessing and using <?php echo $siteName; ?>, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">2. Services</h2>
                <p>We provide:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Professional website templates</li>
                    <li>Digital tools and software solutions</li>
                    <li>Custom domain setup and configuration</li>
                    <li>Technical support via WhatsApp</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">3. Payments & Delivery</h2>
                <ul class="list-disc pl-6 space-y-2">
                    <li>All payments are processed securely through Paystack</li>
                    <li>Prices are displayed in Nigerian Naira (NGN)</li>
                    <li>Delivery is typically within 24 hours after payment confirmation</li>
                    <li>You will receive delivery confirmation via WhatsApp/email</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">4. Refund Policy</h2>
                <p>Due to the digital nature of our products:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Refunds are considered on a case-by-case basis</li>
                    <li>Requests must be made within 7 days of purchase</li>
                    <li>Contact support via WhatsApp to initiate a refund request</li>
                    <li>Customized products may not be eligible for refunds</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">5. Intellectual Property</h2>
                <p>Upon purchase, you receive a license to use the templates/tools for your business. You may not resell, redistribute, or claim ownership of the original source code.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">6. Affiliate Program</h2>
                <p>Our affiliate program terms:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Earn <?php echo (AFFILIATE_COMMISSION_RATE * 100); ?>% commission on referred sales</li>
                    <li>Commissions are paid after order completion</li>
                    <li>Fraudulent referrals will result in account termination</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">7. Limitation of Liability</h2>
                <p><?php echo $siteName; ?> is not liable for any indirect, incidental, or consequential damages arising from the use of our products or services.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">8. Contact</h2>
                <p>For questions about these terms, contact us via WhatsApp at <?php echo WHATSAPP_NUMBER; ?>.</p>
            </section>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-700/50">
            <a href="/" class="text-gold hover:underline">&larr; Back to Home</a>
        </div>
    </main>
</body>
</html>
