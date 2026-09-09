# M-Pesa Environment Configuration Check

## ⚠️ Important: Environment Mismatch

If you're getting "Invalid Access Token" error even with correct credentials, check your environment setting!

## Check Your Environment

On your DirectAdmin server:

```bash
cd ~/domains/business.talksasa.com/public_html
grep MPESA_ENVIRONMENT .env
```

## Your Credentials

Based on your credentials:
- **Business Shortcode:** `6520448`
- **Till number:** `8556534`
- **Consumer Key:** `QNU91PaVXZndQZP6ZRDHUDZzErAQBoO3`
- **Consumer Secret:** `MkiSrOQ4nu3BxFiT`

These look like **PRODUCTION** credentials (not sandbox).

## Required .env Setting

Your `.env` file should have:

```env
MPESA_ENVIRONMENT=production
```

**NOT:**
```env
MPESA_ENVIRONMENT=sandbox  # ❌ Wrong if using production credentials
```

## How to Fix

1. **SSH into your server:**
   ```bash
   ssh talksasa@your-server.com
   cd ~/domains/business.talksasa.com/public_html
   ```

2. **Edit .env file:**
   ```bash
   nano .env
   ```

3. **Set environment:**
   ```env
   MPESA_ENVIRONMENT=production
   ```

4. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

5. **Try registering URLs again**

## Verify Environment

After setting, verify it's being used:

```bash
php artisan tinker
```

```php
env('MPESA_ENVIRONMENT', 'sandbox');
// Should return: "production"
exit
```

## Common Issues

### Issue 1: Environment is "sandbox" but using production credentials
**Symptom:** OAuth works (test credentials pass) but URL registration fails
**Fix:** Set `MPESA_ENVIRONMENT=production`

### Issue 2: Environment is "production" but using sandbox credentials
**Symptom:** OAuth fails completely
**Fix:** Set `MPESA_ENVIRONMENT=sandbox` OR use production credentials

### Issue 3: Business Shortcode not filled
**Symptom:** Using Till number (8556534) instead of Business Shortcode (6520448)
**Fix:** Fill in Business Shortcode field: `6520448`

## Quick Test

After fixing environment, test again:

1. Go to Settings
2. Fill in Business Shortcode: `6520448`
3. Click "Test Credentials" - should pass ✅
4. Click "Register URLs" - should work ✅
