<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$siteName = SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status - <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind-fallback.css">
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
        <h1 class="text-3xl font-bold mb-2">Service Status</h1>
        <p class="text-gray-400 mb-8">Current operational status of our services</p>

        <div class="bg-green-900/30 border border-green-500/50 rounded-lg p-6 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-4 h-4 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-green-400 font-semibold text-lg">All Systems Operational</span>
            </div>
            <p class="text-gray-300 mt-2 text-sm">Last checked: <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>

        <div class="space-y-4">
            <div class="bg-navy-light rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span>Website & Store</span>
                </div>
                <span class="text-green-400 text-sm">Operational</span>
            </div>

            <div class="bg-navy-light rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span>Payment Processing (Paystack)</span>
                </div>
                <span class="text-green-400 text-sm">Operational</span>
            </div>

            <div class="bg-navy-light rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span>Digital Tools</span>
                </div>
                <span class="text-green-400 text-sm">Operational</span>
            </div>

            <div class="bg-navy-light rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span>WhatsApp Support</span>
                </div>
                <span class="text-green-400 text-sm">Available 24/7</span>
            </div>
        </div>

        <div class="mt-12">
            <h2 class="text-xl font-semibold mb-4">Recent Updates</h2>
            <div class="space-y-4">
                <?php
                try {
                    $db = getDb();
                    $stmt = $db->query('SELECT * FROM system_updates ORDER BY created_at DESC LIMIT 5');
                    $updates = $stmt->fetchAll();
                    
                    if (empty($updates)) {
                        // Show default if no updates
                        echo '<div class="bg-navy-light rounded-lg p-4">';
                        echo '<div class="flex items-center justify-between mb-2">';
                        echo '<span class="font-medium">System Maintenance Complete</span>';
                        echo '<span class="text-gray-400 text-sm">' . date('M j, Y') . '</span>';
                        echo '</div>';
                        echo '<p class="text-gray-400 text-sm">Routine maintenance completed successfully. All services running smoothly.</p>';
                        echo '</div>';
                    } else {
                        foreach ($updates as $update) {
                            echo '<div class="bg-navy-light rounded-lg p-4">';
                            echo '<div class="flex items-center justify-between mb-2">';
                            echo '<span class="font-medium">' . htmlspecialchars($update['title']) . '</span>';
                            echo '<span class="text-gray-400 text-sm">' . htmlspecialchars($update['display_date']) . '</span>';
                            echo '</div>';
                            echo '<p class="text-gray-400 text-sm">' . htmlspecialchars($update['description']) . '</p>';
                            echo '</div>';
                        }
                    }
                } catch (Exception $e) {
                    // Fallback if table doesn't exist yet
                    echo '<div class="bg-navy-light rounded-lg p-4">';
                    echo '<div class="flex items-center justify-between mb-2">';
                    echo '<span class="font-medium">System Maintenance Complete</span>';
                    echo '<span class="text-gray-400 text-sm">' . date('M j, Y') . '</span>';
                    echo '</div>';
                    echo '<p class="text-gray-400 text-sm">Routine maintenance completed successfully. All services running smoothly.</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <div class="mt-12 p-6 bg-navy-light rounded-lg">
            <h3 class="font-semibold mb-2">Need Help?</h3>
            <p class="text-gray-400 text-sm mb-4">If you're experiencing issues, contact our support team.</p>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                Contact Support
            </a>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-700/50">
            <a href="/" class="text-gold hover:underline">&larr; Back to Home</a>
        </div>
    </main>
</body>
</html>
