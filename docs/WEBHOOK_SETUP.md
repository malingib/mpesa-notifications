# M-Pesa Webhook Setup Guide

## The Problem

When you test locally with `./test-webhook.sh`, it works because the request comes from your machine. But when M-Pesa tries to send a webhook, it can't reach `localhost:8000` because that's only accessible on your local machine.

## Solution: Make Your Server Publicly Accessible

### Option 1: Use ngrok (For Testing/Development)

**ngrok** creates a public URL that tunnels to your localhost.

1. **Install ngrok:**
   ```bash
   # Download from https://ngrok.com/download
   # Or install via package manager
   ```

2. **Start ngrok tunnel:**
   ```bash
   ngrok http 8000
   ```

3. **Copy the public URL** (e.g., `https://abc123.ngrok.io`)

4. **Configure M-Pesa webhook URL:**
   - Go to your M-Pesa Developer Portal
   - Set **Confirmation URL** to: `https://abc123.ngrok.io/api/webhooks/mpesa/payment`
   - Set **Validation URL** to: `https://abc123.ngrok.io/api/webhooks/mpesa/validation` (optional)

5. **Important:** ngrok URLs change each time you restart (unless you have a paid plan with fixed domain)

### Option 2: Deploy to Public Server (For Production)

Deploy your application to a server with a public IP/domain:

1. **Deploy to server** (AWS, DigitalOcean, etc.)
2. **Set up domain** (e.g., `api.yourdomain.com`)
3. **Configure M-Pesa webhook URL:**
   - **Confirmation URL**: `https://api.yourdomain.com/api/webhooks/mpesa/payment`
   - **Validation URL**: `https://api.yourdomain.com/api/webhooks/mpesa/validation`

## M-Pesa Webhook Configuration

### Where to Configure

1. **M-Pesa Developer Portal** (https://developer.safaricom.co.ke)
2. Navigate to your app settings
3. Set the webhook URLs:
   - **Confirmation URL**: Where M-Pesa sends payment notifications
   - **Validation URL**: Where M-Pesa validates before sending (optional)

### Webhook Endpoints

- **Payment Webhook**: `POST /api/webhooks/mpesa/payment`
- **Confirmation (Legacy)**: `POST /api/webhooks/mpesa/confirmation`
- **Validation (Legacy)**: `POST /api/webhooks/mpesa/validation`

### Expected Response Format

M-Pesa expects this response format:

```json
{
    "ResultCode": 0,
    "ResultDesc": "Accepted"
}
```

Our endpoint returns this automatically.

## Testing

1. **Test locally first:**
   ```bash
   ./test-webhook.sh
   ```

2. **Set up ngrok:**
   ```bash
   ngrok http 8000
   ```

3. **Update M-Pesa webhook URL** with ngrok URL

4. **Make a real payment** to your Till number

5. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Troubleshooting

### No webhooks received

- ✅ Check M-Pesa webhook URL is set correctly
- ✅ Verify URL is publicly accessible (not localhost)
- ✅ Check server firewall allows incoming connections
- ✅ Verify SSL certificate is valid (HTTPS required)
- ✅ Check M-Pesa Developer Portal for webhook delivery status

### Webhooks received but payment not processed

- ✅ Check logs for errors
- ✅ Verify merchant exists in database
- ✅ Ensure Till number matches exactly
- ✅ Check queue worker is running

## Security

- Webhooks don't require authentication (M-Pesa doesn't send tokens)
- Consider adding IP whitelist for M-Pesa IPs (optional)
- Webhook secret can be configured in `.env` for additional security
