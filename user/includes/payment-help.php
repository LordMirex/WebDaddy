<?php
/**
 * Payment Methods Documentation & Fallbacks
 * Handles payment method selection and documentation
 */

function getPaymentMethodHelp($paymentMethod = 'auto') {
    if ($paymentMethod === 'auto' || $paymentMethod === 'automatic') {
        return [
            'title' => 'Pay with Card (Paystack)',
            'icon' => 'bi-credit-card',
            'description' => 'Fast, secure payment with your debit or credit card. Processed instantly.',
            'processing_time' => 'Instant',
            'fallback' => 'If card payment fails, you can use bank transfer instead.'
        ];
    }
    
    if ($paymentMethod === 'manual') {
        return [
            'title' => 'Bank Transfer',
            'icon' => 'bi-building',
            'description' => 'Transfer money to our bank account. We verify and process within 1-24 hours.',
            'processing_time' => '1-24 hours',
            'fallback' => 'Email or WhatsApp us your payment proof for instant verification.'
        ];
    }
}

function getPaystackStatus() {
    /**
     * IMPORTANT NOTE FOR SHARED HOSTING:
     * 
     * Paystack webhooks may NOT work on shared hosting because:
     * 1. Your shared host may block external API calls
     * 2. Your domain may not be publicly accessible
     * 3. Paystack needs to call your webhook endpoint
     * 
     * SOLUTION:
     * - Bank transfer is the RELIABLE payment method for shared hosting
     * - Paystack card payments are supported for verification via manual webhook calls
     * - For production, consider upgrading to a VPS or using a payment gateway that doesn't rely on webhooks
     */
    
    return [
        'available' => true,
        'note' => 'Card payment via Paystack is available. If it fails, use bank transfer (100% reliable on all hosting).',
        'on_shared_hosting' => 'Bank transfer is the most reliable method on shared hosting'
    ];
}
?>
