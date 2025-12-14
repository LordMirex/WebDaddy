<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/termii.php';
require_once __DIR__ . '/../includes/mailer.php';

$balance = getTermiiBalance();
$amount = $balance['balance'] ?? 0;

error_log("Termii Balance Check: NGN {$amount}");

if ($amount < 1000 && !empty(SUPPORT_EMAIL)) {
    sendEmail(
        SUPPORT_EMAIL,
        'ALERT: Low Termii SMS Balance',
        createEmailTemplate(
            'Low SMS Balance Alert',
            "<p>Current Termii balance: <strong>NGN {$amount}</strong></p>
            <p>Please top up soon to avoid OTP delivery failures.</p>",
            'Admin'
        )
    );
    error_log("Low balance alert sent to admin");
}

echo "Balance check completed: NGN {$amount}\n";
