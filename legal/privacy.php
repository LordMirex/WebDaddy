<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$siteName = SITE_NAME;
$lastUpdated = 'December 2025';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - <?php echo $siteName; ?> | Data Protection & Security</title>
    <meta name="description" content="Privacy Policy for <?php echo $siteName; ?>. Learn how we collect, use, and protect your personal data. GDPR compliant. Updated December 2025.">
    <meta name="keywords" content="privacy policy, data protection, GDPR, personal data, security, website privacy">
    <meta name="author" content="<?php echo $siteName; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo SITE_URL; ?>/legal/privacy.php">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Privacy Policy - <?php echo $siteName; ?>">
    <meta property="og:description" content="Privacy Policy for <?php echo $siteName; ?>. Learn how we collect, use, and protect your personal data.">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/legal/privacy.php">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
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
    <nav class="bg-navy border-b border-navy-light/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="/" class="flex items-center" aria-label="<?= $siteName ?> Home">
                        <img src="/assets/images/webdaddy-logo.png" alt="<?= $siteName ?>" class="h-12 mr-3" loading="eager" decoding="async">
                        <span class="text-xl font-bold text-white hidden sm:inline"><?= $siteName ?></span>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-6">
                    <a href="/blog/" class="inline-block border-b-2 font-medium transition-colors py-4 text-gray-300 border-transparent hover:text-gold" style="background: none !important;">Blog</a>
                    <a href="/about.php" class="inline-block border-b-2 font-medium transition-colors py-4 text-gray-300 border-transparent hover:text-gold" style="background: none !important;">About</a>
                    <a href="/contact.php" class="inline-block border-b-2 font-medium transition-colors py-4 text-gray-300 border-transparent hover:text-gold" style="background: none !important;">Company</a>
                    <a href="/user/login.php" class="inline-flex items-center border-b-2 border-transparent text-gray-300 hover:text-gold font-medium transition-colors py-4">
                        <svg class="w-5 h-5 mr-1.5" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                        </svg>
                        Login
                    </a>
                    <a href="#" onclick="toggleCartDrawer(); return false;" class="relative inline-flex items-center justify-center text-gray-300 hover:text-gold font-medium transition-colors py-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </a>
                    <a href="/affiliate/register.php" class="btn-gold-shine inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg text-navy transition-all">
                        Become an Affiliate
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center">
                    <button id="legal-menu-btn" class="text-gray-300 hover:text-gold focus:outline-none" aria-label="Toggle menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation Menu -->
        <div id="legal-mobile-menu" class="md:hidden bg-navy border-t border-navy-light/50" style="display: none;">
            <div class="px-2 pt-2 pb-4 space-y-1">
                <a href="/blog/" class="block px-4 py-3 rounded-lg text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold font-medium transition-all">Blog</a>
                <a href="/about.php" class="block px-4 py-3 rounded-lg text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold font-medium transition-all">About</a>
                <a href="/contact.php" class="block px-4 py-3 rounded-lg text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold font-medium transition-all">Company</a>
                <a href="/user/login.php" class="flex items-center px-4 py-3 rounded-lg text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold font-medium transition-all">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                        <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                    </svg>
                    Login
                </a>
                <a href="/affiliate/register.php" class="btn-gold-shine block px-4 py-3 rounded-lg text-navy font-semibold text-center transition-all mt-2">Become an Affiliate</a>
            </div>
        </div>
    </nav>
    
    <script>
        document.getElementById('legal-menu-btn').addEventListener('click', function() {
            const menu = document.getElementById('legal-mobile-menu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });
    </script>

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
