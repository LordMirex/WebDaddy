<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$db = getDb();

echo "=== AFFILIATES ===\n";
$result = $db->query('SELECT code, status FROM affiliates LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach($result as $aff) {
    echo "- {$aff['code']} ({$aff['status']})\n";
}

echo "\n=== WHATSAPP SETTING ===\n";
$result = $db->query('SELECT setting_value FROM settings WHERE setting_key = \'whatsapp_number\'')->fetch(PDO::FETCH_ASSOC);
echo "WhatsApp Number: " . ($result ? $result['setting_value'] : 'NOT SET') . "\n";
?>
