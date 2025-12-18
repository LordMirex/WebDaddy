<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
header('Pragma: no-cache', false);
header('Expires: 0', false);

startSecureSession();
handleAffiliateTracking();

$activeNav = 'contact';
$affiliateCode = getAffiliateCode();
$whatsappNumber = WHATSAPP_NUMBER;

// Meta tags
$pageTitle = 'Contact ' . SITE_NAME . ' - Get Help & Support';
$pageDescription = 'Need help? Contact WebDaddy Empire for support, inquiries, or partnership opportunities. Reach us via WhatsApp, email, or contact form.';
$pageKeywords = 'contact us, support, customer service, help, inquiries';
$pageUrl = SITE_URL . '/contact.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDescription; ?>">
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ContactPage",
        "name": "Contact <?php echo SITE_NAME; ?>",
        "url": "<?php echo $pageUrl; ?>",
        "organization": {
            "@type": "Organization",
            "name": "<?php echo SITE_NAME; ?>",
            "url": "<?php echo SITE_URL; ?>",
            "contactPoint": {
                "@type": "ContactPoint",
                "contactType": "Customer Service",
                "telephone": "<?php echo htmlspecialchars($whatsappNumber); ?>",
                "availableLanguage": "en"
            }
        }
    }
    </script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                            display: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                        },
                        colors: {
                            primary: { 50: '#FDF9ED', 100: '#FAF0D4', 200: '#F5E1A8', 300: '#EFCF72', 400: '#E8BB45', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26', 800: '#7D6320', 900: '#604B18' },
                            gold: { DEFAULT: '#D4AF37', 50: '#FDF9ED', 100: '#FAF0D4', 200: '#F5E1A8', 300: '#EFCF72', 400: '#E8BB45', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26', 800: '#7D6320', 900: '#604B18' },
                            navy: { DEFAULT: '#0f172a', dark: '#0a1929', light: '#1e293b' }
                        }
                    }
                }
            }
        }
        document.documentElement.classList.add('dark');
    </script>
</head>
<body class="bg-navy dark:bg-slate-900">
    <?php include 'includes/layout/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Get In Touch</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Have questions? We're here to help. Reach out to us through any of these channels.</p>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid md:grid-cols-2 gap-12">
            <!-- Contact Methods -->
            <div>
                <h2 class="text-3xl font-bold text-white mb-8">Contact Methods</h2>
                
                <div class="space-y-6 mb-12">
                    <!-- WhatsApp -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-green-500 transition-colors">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-2">WhatsApp</h3>
                                <p class="text-gray-400 mb-4">Get instant support via WhatsApp. We respond within minutes during business hours.</p>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsappNumber); ?>?text=Hi%20WebDaddy%20Empire%2C%20I%20need%20help" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                    Chat on WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-2">Email</h3>
                                <p class="text-gray-400 mb-4">Send us an email and we'll get back to you within 24 hours.</p>
                                <a href="mailto:support@webdaddyempire.com" class="text-primary-400 hover:text-primary-300 font-medium">support@webdaddyempire.com</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Live Chat -->
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-blue-500 transition-colors">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-2">Live Chat</h3>
                                <p class="text-gray-400 mb-4">Available during business hours for instant answers to your questions.</p>
                                <button onclick="alert('Live chat coming soon!')" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                    Start Chat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Business Hours -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Business Hours</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex justify-between"><span>Monday - Friday:</span> <span class="font-medium">8:00 AM - 6:00 PM WAT</span></li>
                        <li class="flex justify-between"><span>Saturday:</span> <span class="font-medium">10:00 AM - 4:00 PM WAT</span></li>
                        <li class="flex justify-between"><span>Sunday:</span> <span class="font-medium">Emergency Support Only</span></li>
                    </ul>
                </div>
            </div>
            
            <!-- Quick Links / FAQ Preview -->
            <div>
                <h2 class="text-3xl font-bold text-white mb-8">Common Questions</h2>
                
                <div class="space-y-4">
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-primary-500 transition-colors">
                        <h3 class="font-semibold text-white mb-2">How quickly can I get my template?</h3>
                        <p class="text-gray-400 text-sm">Instantly! After purchase, you'll receive access to download your template immediately.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-primary-500 transition-colors">
                        <h3 class="font-semibold text-white mb-2">Do you offer customization services?</h3>
                        <p class="text-gray-400 text-sm">Yes! Contact us via WhatsApp to discuss custom setup and customization options.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-primary-500 transition-colors">
                        <h3 class="font-semibold text-white mb-2">What payment methods do you accept?</h3>
                        <p class="text-gray-400 text-sm">We accept Paystack, bank transfers, and other secure payment methods.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-primary-500 transition-colors">
                        <h3 class="font-semibold text-white mb-2">Do you provide refunds?</h3>
                        <p class="text-gray-400 text-sm">Yes, we offer a 30-day money-back guarantee on all purchases.</p>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-primary-500 transition-colors">
                        <h3 class="font-semibold text-white mb-2">Is technical support included?</h3>
                        <p class="text-gray-400 text-sm">Absolutely! We provide free technical support for all our products.</p>
                    </div>
                    
                    <a href="/#faq" class="inline-block w-full text-center px-4 py-3 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">
                        View All FAQs
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
