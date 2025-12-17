<?php
$pageTitle = 'Marketing Tools';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAffiliate();

$affiliateInfo = getAffiliateInfo();
if (!$affiliateInfo) {
    logoutAffiliate();
    header('Location: /affiliate/login.php');
    exit;
}

$affiliateCode = $affiliateInfo['code'];
$referralLink = SITE_URL . '/?aff=' . $affiliateCode;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center space-x-3 mb-2">
        <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-purple-800 rounded-lg flex items-center justify-center shadow-lg">
            <i class="bi bi-box-seam text-2xl text-gold"></i>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Marketing Tools</h1>
            <p class="text-gray-600">Resources to help you promote and earn commissions</p>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="bg-gradient-to-r from-primary-600 to-primary-800 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h3 class="text-xl font-bold mb-2">Your Referral Link</h3>
            <p class="text-white/90 text-sm">Share this link to earn <?php echo AFFILIATE_COMMISSION_RATE * 100; ?>% commission on every sale</p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center px-4 py-2 rounded-lg bg-white/20 text-white font-mono font-bold text-sm">
                <?php echo $affiliateCode; ?>
            </span>
            <span class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white font-semibold text-sm">
                <?php echo AFFILIATE_COMMISSION_RATE * 100; ?>% Commission
            </span>
        </div>
    </div>
</div>

<!-- Referral Link Variants -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-link-45deg text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Your Referral Links</h5>
        </div>
    </div>
    
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Main Referral Link -->
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="bi bi-house-door mr-1"></i> Main Landing Page
                </label>
                <div x-data="{ copied: false }" class="flex gap-2">
                    <input type="text" 
                           value="<?php echo htmlspecialchars($referralLink); ?>" 
                           readonly
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm font-mono">
                    <button @click="navigator.clipboard.writeText('<?php echo htmlspecialchars($referralLink); ?>').then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors shrink-0">
                        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">Links to the homepage with your affiliate code</p>
            </div>
            
            <!-- Registration Link -->
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="bi bi-person-plus mr-1"></i> Affiliate Registration
                </label>
                <div x-data="{ copied: false }" class="flex gap-2">
                    <input type="text" 
                           value="<?php echo SITE_URL; ?>/affiliate/register.php?ref=<?php echo $affiliateCode; ?>" 
                           readonly
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm font-mono">
                    <button @click="navigator.clipboard.writeText('<?php echo SITE_URL; ?>/affiliate/register.php?ref=<?php echo $affiliateCode; ?>').then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors shrink-0">
                        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">Invite others to become affiliates</p>
            </div>
        </div>
    </div>
</div>

<!-- Social Media Copy -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-chat-square-text text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Social Media Copy</h5>
        </div>
    </div>
    
    <div class="p-6">
        <p class="text-sm text-gray-600 mb-4">Ready-made text for your social media posts</p>
        
        <div class="grid grid-cols-1 gap-4">
            <!-- Copy Template 1 -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" x-data="{ copied: false }">
                <div class="flex justify-between items-start mb-3">
                    <h6 class="font-semibold text-gray-900">🚀 Quick Launch Template</h6>
                    <button @click="navigator.clipboard.writeText(`🚀 Ready to launch your website?\n\nGet a professional website in 24 hours with <?php echo SITE_NAME; ?>!\n\n✅ Professional Design\n✅ Domain Included\n✅ Fast Setup\n✅ 24/7 Support\n\nClick here: <?php echo $referralLink; ?>`).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="px-3 py-1 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
                        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                        <span class="ml-1" x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                    </button>
                </div>
                <div class="text-sm text-gray-700 bg-white rounded p-3 border border-gray-200">
                    <p>🚀 Ready to launch your website?</p>
                    <p class="mt-2">Get a professional website in 24 hours with <?php echo SITE_NAME; ?>!</p>
                    <p class="mt-2">✅ Professional Design<br>✅ Domain Included<br>✅ Fast Setup<br>✅ 24/7 Support</p>
                    <p class="mt-2">Click here: <?php echo $referralLink; ?></p>
                </div>
            </div>
            
            <!-- Copy Template 2 -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" x-data="{ copied: false }">
                <div class="flex justify-between items-start mb-3">
                    <h6 class="font-semibold text-gray-900">💼 Business Template</h6>
                    <button @click="navigator.clipboard.writeText(`Looking to grow your business online?\n\n<?php echo SITE_NAME; ?> offers ready-made websites with:\n\n⚡ 24-hour delivery\n🎨 Professional templates\n🌐 Free domain included\n💰 Affordable pricing\n\nGet <?php echo CUSTOMER_DISCOUNT_RATE * 100; ?>% OFF with my link: <?php echo $referralLink; ?>`).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="px-3 py-1 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
                        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                        <span class="ml-1" x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                    </button>
                </div>
                <div class="text-sm text-gray-700 bg-white rounded p-3 border border-gray-200">
                    <p>Looking to grow your business online?</p>
                    <p class="mt-2"><?php echo SITE_NAME; ?> offers ready-made websites with:</p>
                    <p class="mt-2">⚡ 24-hour delivery<br>🎨 Professional templates<br>🌐 Free domain included<br>💰 Affordable pricing</p>
                    <p class="mt-2">Get <?php echo CUSTOMER_DISCOUNT_RATE * 100; ?>% OFF with my link: <?php echo $referralLink; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Template -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                <i class="bi bi-envelope text-xl text-primary-600"></i>
            </div>
            <h5 class="text-xl font-bold text-gray-900">Email Template</h5>
        </div>
    </div>
    
    <div class="p-6">
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" x-data="{ copied: false }">
            <div class="flex justify-between items-start mb-3">
                <h6 class="font-semibold text-gray-900">Professional Email Template</h6>
                <button @click="navigator.clipboard.writeText(`Subject: Get Your Professional Website in 24 Hours!\n\nHello!\n\nI wanted to share an amazing service I discovered - <?php echo SITE_NAME; ?>.\n\nThey create professional websites in just 24 hours with:\n\n• Ready-made templates for any business\n• Free domain included\n• Professional design\n• 30-day money-back guarantee\n• 24/7 support\n\nI'm using this link to get <?php echo CUSTOMER_DISCOUNT_RATE * 100; ?>% off, and I thought you might find it useful too:\n<?php echo $referralLink; ?>\n\nBest regards`).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                        class="px-3 py-1 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
                    <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                    <span class="ml-1" x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                </button>
            </div>
            <div class="text-sm text-gray-700 bg-white rounded p-4 border border-gray-200">
                <p class="font-semibold">Subject: Get Your Professional Website in 24 Hours!</p>
                <p class="mt-3">Hello!</p>
                <p class="mt-3">I wanted to share an amazing service I discovered - <?php echo SITE_NAME; ?>.</p>
                <p class="mt-3">They create professional websites in just 24 hours with:</p>
                <ul class="mt-2 list-disc list-inside ml-2">
                    <li>Ready-made templates for any business</li>
                    <li>Free domain included</li>
                    <li>Professional design</li>
                    <li>30-day money-back guarantee</li>
                    <li>24/7 support</li>
                </ul>
                <p class="mt-3">I'm using this link to get <?php echo CUSTOMER_DISCOUNT_RATE * 100; ?>% off, and I thought you might find it useful too:</p>
                <p class="mt-2 text-primary-600 font-medium"><?php echo $referralLink; ?></p>
                <p class="mt-3">Best regards</p>
            </div>
        </div>
    </div>
</div>

<!-- Tips & Best Practices -->
<div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
    <div class="flex items-start">
        <i class="bi bi-lightbulb text-blue-600 text-2xl mr-3 mt-0.5"></i>
        <div>
            <h5 class="font-bold text-blue-900 mb-3">💡 Pro Tips for Success</h5>
            <ul class="space-y-2 text-blue-800 text-sm">
                <li class="flex items-start">
                    <i class="bi bi-check2 text-blue-600 mr-2 mt-1 shrink-0"></i>
                    <span><strong>Target the right audience:</strong> Focus on entrepreneurs, small business owners, and startups who need websites</span>
                </li>
                <li class="flex items-start">
                    <i class="bi bi-check2 text-blue-600 mr-2 mt-1 shrink-0"></i>
                    <span><strong>Be genuine:</strong> Share your honest experience and explain how <?php echo SITE_NAME; ?> solves real problems</span>
                </li>
                <li class="flex items-start">
                    <i class="bi bi-check2 text-blue-600 mr-2 mt-1 shrink-0"></i>
                    <span><strong>Use multiple channels:</strong> Share on social media, email, WhatsApp groups, and relevant online communities</span>
                </li>
                <li class="flex items-start">
                    <i class="bi bi-check2 text-blue-600 mr-2 mt-1 shrink-0"></i>
                    <span><strong>Track your results:</strong> Monitor your dashboard to see which promotional methods work best</span>
                </li>
                <li class="flex items-start">
                    <i class="bi bi-check2 text-blue-600 mr-2 mt-1 shrink-0"></i>
                    <span><strong>Highlight the discount:</strong> Always mention the <?php echo CUSTOMER_DISCOUNT_RATE * 100; ?>% discount customers get with your link</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
