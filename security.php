<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
startSecureSession();
handleAffiliateTracking();

$activeNav = 'security';
$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();

$pageTitle = 'Security & Trust - ' . SITE_NAME;
$pageDescription = 'Learn about our security measures, data protection, and trust certifications at WebDaddy Empire.';
$pageUrl = SITE_URL . '/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        document.documentElement.classList.add('dark');
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            primary: { 50: '#FDF9ED', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26' },
                            navy: { DEFAULT: '#0f172a', light: '#1e293b' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-navy">
    <?php include 'includes/layout/header.php'; ?>
    
    <section class="bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Security & Trust</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Your data and payments are protected by industry-leading security measures.</p>
        </div>
    </section>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid md:grid-cols-2 gap-12 mb-16">
            <div>
                <h2 class="text-3xl font-bold text-white mb-8">Security Measures</h2>
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-2">SSL Encryption</h3>
                            <p class="text-gray-300">All data transmitted between your browser and our servers is encrypted using 256-bit SSL/TLS encryption.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-2">Secure Payments</h3>
                            <p class="text-gray-300">All payments are processed through Paystack, PCI-DSS Level 1 certified payment processors.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-2">Data Protection</h3>
                            <p class="text-gray-300">Customer data is encrypted, regularly backed up, and protected with multi-layer security protocols.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-2">Privacy Policy</h3>
                            <p class="text-gray-300">We respect your privacy. Your personal information is never sold or shared with third parties. <a href="/legal/privacy.php" class="text-primary-400 hover:text-primary-300">Read our privacy policy</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <h2 class="text-3xl font-bold text-white mb-8">Trust & Certifications</h2>
                <div class="space-y-6">
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Secure Transactions</h3>
                        <p class="text-gray-300 text-sm">All transactions are processed securely and your payment information is never stored on our servers.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Data Compliance</h3>
                        <p class="text-gray-300 text-sm">We comply with international data protection regulations and regularly update our security practices.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Transparent Operations</h3>
                        <p class="text-gray-300 text-sm">We believe in transparency. Our terms of service and policies are clear and easily accessible.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Money-Back Guarantee</h3>
                        <p class="text-gray-300 text-sm">Your satisfaction is guaranteed. If you're not happy, we offer a 30-day refund with no questions asked.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CTA -->
        <section class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 text-center">
            <h2 class="text-3xl font-bold text-navy mb-4">Questions About Security?</h2>
            <p class="text-navy/90 mb-6">Contact our team for more information about our security practices.</p>
            <a href="/contact.php" class="inline-flex items-center gap-2 px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">Contact Us</a>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
