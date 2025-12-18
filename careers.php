<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
startSecureSession();
handleAffiliateTracking();

$activeNav = 'careers';
$affiliateCode = getAffiliateCode();

$pageTitle = 'Careers - Join ' . SITE_NAME;
$pageDescription = 'Join our team! We\'re hiring talented individuals to help us serve African entrepreneurs.';
$pageUrl = SITE_URL . '/careers.php';
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
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-navy">
    <?php include 'includes/layout/header.php'; ?>
    
    <section class="bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Join Our Team</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Help us democratize web design for entrepreneurs across Africa.</p>
        </div>
    </section>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <section class="grid md:grid-cols-2 gap-12 mb-16">
            <div>
                <h2 class="text-3xl font-bold text-white mb-6">Why Work With Us?</h2>
                <ul class="space-y-4 text-gray-300">
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Meaningful Impact:</strong> Help thousands of African entrepreneurs build their digital presence and grow their businesses</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Growth Opportunities:</strong> Learn and grow in a fast-paced, innovative environment with mentorship from industry experts</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Remote-First Culture:</strong> Work from anywhere with flexible hours that suit your lifestyle</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Competitive Compensation:</strong> Fair salaries, performance bonuses, and incentive programs</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Passionate Team:</strong> Collaborate with talented professionals who are dedicated to our mission of empowering African entrepreneurs</span>
                    </li>
                </ul>
            </div>
            
            <div>
                <h2 class="text-3xl font-bold text-white mb-6">Current Openings</h2>
                <div class="space-y-4">
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Web Developer</h3>
                        <p class="text-gray-400 text-sm mb-4">Build and maintain our web platforms. Work with modern technologies like PHP, JavaScript, and responsive design. Help create seamless experiences for our users.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Customer Care Specialist</h3>
                        <p class="text-gray-400 text-sm mb-4">Be the voice of our customers. Provide exceptional support via WhatsApp, email, and live chat. Solve problems and ensure customer satisfaction at the highest level.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Content Creator</h3>
                        <p class="text-gray-400 text-sm mb-4">Create compelling blog posts, case studies, tutorials, and marketing content. Tell stories that inspire and educate African entrepreneurs about digital transformation.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Marketing Manager</h3>
                        <p class="text-gray-400 text-sm mb-4">Drive our marketing strategy and campaigns. Reach more African entrepreneurs, build brand awareness, and grow our community through innovative marketing initiatives.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Application Section -->
        <section id="apply" class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12">
            <h2 class="text-3xl font-bold text-navy mb-2 text-center">Interested in Joining Us?</h2>
            <p class="text-navy/90 mb-8 text-center">Submit your resume and tell us which role interests you. We review all applications and contact promising candidates.</p>
            
            <form id="careersForm" class="max-w-2xl mx-auto bg-navy/20 rounded-lg p-6 sm:p-8">
                <div class="space-y-4">
                    <div>
                        <label for="fullName" class="block text-navy font-semibold mb-2">Full Name *</label>
                        <input type="text" id="fullName" name="fullName" required class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors" placeholder="Your full name">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-navy font-semibold mb-2">Email Address *</label>
                        <input type="email" id="email" name="email" required class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors" placeholder="your@email.com">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-navy font-semibold mb-2">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors" placeholder="Your phone number">
                    </div>
                    
                    <div>
                        <label for="position" class="block text-navy font-semibold mb-2">Position Interested In *</label>
                        <select id="position" name="position" required class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors">
                            <option value="">-- Select a position --</option>
                            <option value="Web Developer">Web Developer</option>
                            <option value="Customer Care Specialist">Customer Care Specialist</option>
                            <option value="Content Creator">Content Creator</option>
                            <option value="Marketing Manager">Marketing Manager</option>
                            <option value="Other">Other (please specify in message)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-navy font-semibold mb-2">Message (Tell us about yourself)</label>
                        <textarea id="message" name="message" rows="4" class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors resize-none" placeholder="Why are you interested in joining us? What makes you a great fit?"></textarea>
                    </div>
                    
                    <div>
                        <label for="resumeUrl" class="block text-navy font-semibold mb-2">Resume Link or Google Drive URL *</label>
                        <input type="url" id="resumeUrl" name="resumeUrl" required class="w-full px-4 py-3 rounded-lg bg-navy text-white border border-primary-400/30 focus:border-primary-400 focus:outline-none transition-colors" placeholder="https://drive.google.com/... or your resume link">
                        <p class="text-navy/70 text-xs mt-2">You can share a link to your resume on Google Drive, Dropbox, or your portfolio</p>
                    </div>
                    
                    <button type="submit" class="w-full px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors mt-6">
                        Submit Your Application
                    </button>
                </div>
            </form>
        </section>
        
        <script>
            document.getElementById('careersForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const fullName = document.getElementById('fullName').value;
                const email = document.getElementById('email').value;
                const phone = document.getElementById('phone').value || 'Not provided';
                const position = document.getElementById('position').value;
                const message = document.getElementById('message').value || 'No additional message';
                const resumeUrl = document.getElementById('resumeUrl').value;
                
                const mailtoLink = `mailto:admin@webdaddyempire.com?subject=Career%20Application%20-%20${encodeURIComponent(position)}&body=${encodeURIComponent(
                    `Name: ${fullName}\nEmail: ${email}\nPhone: ${phone}\n\nPosition Applied For: ${position}\n\nResume Link: ${resumeUrl}\n\nMessage:\n${message}`
                )}`;
                
                window.location.href = mailtoLink;
                
                setTimeout(() => {
                    alert('Thank you for your application! Please make sure to send the email. If your email client didn\'t open, you can also reach us at admin@webdaddyempire.com');
                }, 500);
            });
        </script>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
