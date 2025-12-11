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
    <title>Privacy Policy - <?php echo $siteName; ?></title>
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
        <h1 class="text-3xl font-bold mb-2">Privacy Policy</h1>
        <p class="text-gray-400 mb-8">Last updated: <?php echo $lastUpdated; ?></p>

        <div class="prose prose-invert max-w-none space-y-6 text-gray-300">
            <section>
                <h2 class="text-xl font-semibold text-white mb-3">1. Information We Collect</h2>
                <p>We collect information you provide directly to us, including:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Contact information (name, email address, phone number)</li>
                    <li>Payment information (processed securely through Paystack)</li>
                    <li>Communication preferences and history</li>
                    <li>Information about your use of our services</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">2. How We Use Your Information</h2>
                <p>We use the information we collect to:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Process your orders and deliver products/services</li>
                    <li>Send you important updates about your purchases</li>
                    <li>Provide customer support via WhatsApp and email</li>
                    <li>Improve our products and services</li>
                    <li>Comply with legal obligations</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">3. Information Sharing</h2>
                <p>We do not sell your personal information. We may share your information with:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Payment processors (Paystack) to complete transactions</li>
                    <li>Service providers who assist in our operations</li>
                    <li>Legal authorities when required by law</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">4. Data Security</h2>
                <p>We implement appropriate security measures to protect your personal information. Payment data is handled securely by Paystack and we never store your full card details on our servers.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">5. Your Rights</h2>
                <p>You have the right to:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Access your personal data</li>
                    <li>Request correction of inaccurate data</li>
                    <li>Request deletion of your data</li>
                    <li>Opt out of marketing communications</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-white mb-3">6. Contact Us</h2>
                <p>For privacy-related inquiries, contact us via WhatsApp at <?php echo WHATSAPP_NUMBER; ?> or visit our main website.</p>
            </section>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-700/50">
            <a href="/" class="text-gold hover:underline">&larr; Back to Home</a>
        </div>
    </main>
</body>
</html>
