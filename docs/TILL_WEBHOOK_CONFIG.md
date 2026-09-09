# M-Pesa Till Number Webhook Configuration

## Till Numbers vs Paybill Numbers

### Till Numbers (Buy Goods)
- **Webhook Support**: ✅ Yes, but configuration varies
- **API Type**: Uses M-Pesa Express (STK Push) or Buy Goods API
- **Webhook Configuration**: 
  - May be set per-transaction (in API request)
  - OR configured globally in M-Pesa Developer Portal
  - OR configured in your M-Pesa provider's dashboard

### Paybill Numbers
- **Webhook Support**: ✅ Yes, standard configuration
- **API Type**: Uses Lipa na M-Pesa Online API
- **Webhook Configuration**: 
  - Set in M-Pesa Developer Portal
  - Confirmation URL and Validation URL

## How Till Number Webhooks Work

### Option 1: Per-Transaction Callback (STK Push)
When initiating an STK Push payment, you can specify a callback URL:

```php
// Example STK Push request
{
    "BusinessShortCode": "YOUR_TILL_NUMBER",
    "CallBackURL": "https://your-domain.com/api/webhooks/mpesa/payment",
    "TransactionDesc": "Payment",
    // ... other fields
}
```

### Option 2: Global Webhook Configuration
Some M-Pesa providers allow setting a global webhook URL for all Till number transactions:
- Configured in M-Pesa Developer Portal
- OR in your M-Pesa provider's dashboard (if using third-party provider)

### Option 3: Automatic Webhooks (Some Providers)
Some M-Pesa integration providers automatically send webhooks to a configured URL for all Till number transactions.

## Current System Support

Our system supports **both** Till and Paybill webhooks through the unified endpoint:

**Endpoint**: `POST /api/webhooks/mpesa/payment`

This endpoint automatically detects:
- **Till Number**: From `TillNumber` field in payload
- **Paybill**: From `BusinessShortCode` field in payload

## Configuration Steps

### For Till Numbers:

1. **Check your M-Pesa Provider:**
   - If using Safaricom directly: Configure in Developer Portal
   - If using third-party (Statum, UMS Pay, etc.): Configure in their dashboard

2. **Set Webhook URL:**
   - Use: `https://your-public-domain.com/api/webhooks/mpesa/payment`
   - Must be HTTPS (not HTTP)
   - Must be publicly accessible

3. **For STK Push Payments:**
   - Include `CallBackURL` in your STK Push request
   - Point to: `https://your-public-domain.com/api/webhooks/mpesa/payment`

## Testing

1. **Use ngrok for local testing:**
   ```bash
   ./setup-ngrok.sh
   ```

2. **Configure webhook URL** with ngrok URL

3. **Make a payment** to your Till number

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Important Notes

⚠️ **Till numbers may not support webhooks in all scenarios:**
- Some basic Till numbers only support manual checking
- STK Push (Express) payments usually support callbacks
- Check with your M-Pesa provider for specific capabilities

✅ **Our system is ready:**
- Handles both Till and Paybill webhooks
- Automatically detects account type
- Processes payments correctly

## Troubleshooting

### No webhooks received for Till number:

1. **Verify webhook is configured:**
   - Check M-Pesa Developer Portal
   - Check your M-Pesa provider's dashboard
   - Verify CallBackURL in STK Push requests

2. **Check if Till number supports webhooks:**
   - Contact Safaricom support
   - Check your M-Pesa provider documentation
   - Some basic Till numbers may not support webhooks

3. **Alternative: Polling**
   - If webhooks aren't supported, you may need to poll M-Pesa API
   - Check transaction status periodically
   - This is less efficient but works for all Till numbers

## Recommendation

For production, consider:
1. **Upgrade to Paybill** if webhooks are critical (more reliable webhook support)
2. **Use STK Push** with CallBackURL for Till numbers
3. **Contact Safaricom** to confirm your Till number's webhook capabilities
