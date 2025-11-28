# Paystack Webhook Setup Guide

## Overview
WebDaddy Empire uses a **dual payment verification system** for maximum security:
1. **API Verification** (Primary) - Queries Paystack API after customer completes payment
2. **Webhook Notifications** (Real-time) - Paystack sends instant notifications to your server

Both methods work independently, but webhooks provide real-time processing and enable all security features.

## Step-by-Step Setup

### 1. Get Your Webhook URL
Your webhook endpoint URL is:
```
https://your-domain.com/api/paystack-webhook.php
```

Replace `your-domain.com` with your actual domain name.

### 2. Configure in Paystack Dashboard

1. Log in to [Paystack Dashboard](https://dashboard.paystack.com)
2. Go to **Settings** → **API Keys & Webhooks**
3. Scroll to **Webhooks** section
4. In the **Webhook URL** field, paste your endpoint:
   ```
   https://your-domain.com/api/paystack-webhook.php
   ```
5. Click **Save**

### 3. Test Webhook Delivery

1. Still in Paystack Dashboard, find the **Test Events** section
2. Click **Send a test event**
3. Select **Charge** as the event type
4. Click **Send event**

### 4. Verify in WebDaddy Empire Admin

Once configured, you can monitor webhook activity:

1. Log in to your admin panel
2. Go to **System Monitoring & Health**
3. Look for the **Webhook Security Dashboard** section
4. You should see:
   - Today's webhook count increasing
   - Recent security events being logged
   - Payment success/fail metrics

## Security Features Enabled

Once webhooks are configured, you get:

✅ **IP Whitelisting** - Only Paystack's servers can send webhooks
✅ **Rate Limiting** - Prevents brute force attacks (60 requests/minute)
✅ **HMAC Signature Verification** - Confirms requests are from Paystack
✅ **Real-time Alerts** - Email notifications for suspicious activity
✅ **Security Logging** - Complete audit trail in database
✅ **Payment Reconciliation** - Automatic discrepancy detection

## What Happens When Webhooks Arrive

1. **Security Gate** checks:
   - IP address is from Paystack
   - Request rate is within limits
   - HMAC signature is valid

2. **Payment Processing**:
   - Transaction is verified with Paystack API
   - Order status updated to "paid"
   - Delivery records created automatically
   - Customer receives download links via email
   - Affiliate commission calculated

3. **Monitoring**:
   - Event logged to security database
   - Real-time dashboard updated
   - Alerts sent if anything suspicious detected

## Troubleshooting

### Webhooks not arriving?
- Confirm URL is exactly correct in Paystack dashboard (no typos, no trailing slash)
- Check your domain's DNS is properly configured
- Ensure your server is publicly accessible (not localhost)

### Webhooks arriving but payments not processing?
- Check "Webhook Security Dashboard" for any blocked requests
- Look at "Recent Security Events" to see if requests are being rejected
- Verify Paystack IP address is whitelisted (shouldn't be needed - auto-included)

### Payment verification succeeds but webhook never arrives?
- This is normal - the API verification works independently
- Webhooks may be delayed or disabled in your Paystack settings
- Your payments will still process correctly via API verification

## Manual Testing Without Webhooks

If you can't set up webhooks yet, the system still works:
1. Customer completes Paystack payment
2. API verification triggers automatically
3. Payment is confirmed
4. Delivery begins
5. No webhook needed!

The only difference is webhooks don't provide real-time dashboard updates.

## Webhook Security Configuration

All security settings are in `includes/config.php`:

```php
// Enable/disable IP whitelisting
define('WEBHOOK_IP_WHITELIST_ENABLED', true);

// Rate limiting (requests per minute)
define('WEBHOOK_RATE_LIMIT', 60);

// Email alert throttling
define('SECURITY_EMAIL_THROTTLE_PER_HOUR', 10);
```

## Paystack IP Addresses

If you disable auto-detection, manually add these IP ranges in your firewall:
- 54.182.26.0/24
- 52.221.94.0/24  
- 52.16.234.0/24

(These are Paystack's official webhook servers as of Nov 2024)

## Support

For Paystack webhook issues:
- Paystack Docs: https://paystack.com/docs/api/webhook/
- Paystack Support: support@paystack.com

For WebDaddy Empire webhook issues:
- Check `logs/security.log` for detailed security events
- Review admin panel → System Monitoring for real-time status
