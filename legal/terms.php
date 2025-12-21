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
    <title>Terms of Service - <?php echo $siteName; ?> | Legal Terms & Conditions</title>
    <meta name="description" content="Terms of Service for <?php echo $siteName; ?>. Read our complete terms and conditions for using our website, templates, tools, and services. Updated December 2025.">
    <meta name="keywords" content="terms of service, terms and conditions, legal terms, service agreement, conditions of use">
    <meta name="author" content="<?php echo $siteName; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo SITE_URL; ?>/legal/terms.php">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Terms of Service - <?php echo $siteName; ?>">
    <meta property="og:description" content="Terms of Service for <?php echo $siteName; ?>. Read our complete terms and conditions.">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/legal/terms.php">
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
