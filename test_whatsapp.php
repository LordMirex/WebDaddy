<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$db = getDb();
$result = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_number'")->fetch(PDO::FETCH_ASSOC);
echo "WhatsApp Number: " . ($result ? $result['setting_value'] : 'NOT SET') . "\n";

// Test WhatsApp link generation
$message = "Test message";
$number = preg_replace('/[^0-9]/', '', $result['setting_value'] ?? '+2349132672126');
$encodedMessage = rawurlencode($message);
$link = "https://wa.me/" . $number . "?text=" . $encodedMessage;
echo "WhatsApp Link: " . $link . "\n";
?>
