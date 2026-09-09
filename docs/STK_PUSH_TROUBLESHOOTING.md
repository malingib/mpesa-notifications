# STK Push Troubleshooting Guide

## 🔴 Error: "Invalid Access Token" (404.001.03)

All STK Push attempts are failing with this error. Here are the root causes and solutions:

### Issue 1: Environment Mismatch (PRIMARY ISSUE)

**Problem:** The system is using **sandbox** API (`https://sandbox.safaricom.co.ke`) but you have **production** credentials.

**Evidence from logs:**
```
"environment":"sandbox"
"base_url":"https://sandbox.safaricom.co.ke"
"STK Push: Using SANDBOX API"
```

**Solution:**
1. Ensure `.env` has:
   ```env
   MPESA_ENVIRONMENT=production
   ```

2. Clear and rebuild config cache:
   ```bash
   cd ~/domains/business.talksasa.com/public_html
   php artisan config:clear
   php artisan config:cache
   ```

3. Verify the change:
   ```bash
   php artisan tinker
   >>> env('MPESA_ENVIRONMENT')
   # Should output: "production"
   ```

### Issue 2: BusinessShortCode Mismatch

**Problem:** The BusinessShortCode used in STK Push doesn't match the Consumer Key/Secret.

**How to verify:**
1. Log into M-Pesa Developer Portal
2. Check which ShortCode (Till/Paybill) is associated with your Consumer Key/Secret
3. Ensure the ShortCode in your settings matches exactly

**Common mistakes:**
- Using Till number (`8556534`) with Consumer Key/Secret tied to Business Shortcode (`6520448`)
- Using Business Shortcode (`6520448`) with Consumer Key/Secret tied to Till number (`8556534`)

**Solution:**
- Use the **Business Shortcode** field in settings for STK Push
- Ensure the Business Shortcode matches the Consumer Key/Secret from M-Pesa Developer Portal

### Issue 3: Till Numbers and Passkey

**For Till Numbers:**
- Till numbers typically **don't require** a passkey for STK Push
- Password = `Base64(BusinessShortCode + "" + Timestamp)` (empty passkey)

**For Paybill:**
- Paybill **requires** a passkey
- Password = `Base64(BusinessShortCode + Passkey + Timestamp)`

**Current behavior:**
- The system correctly handles Till numbers (passkey can be empty)
- The system correctly handles Paybill (passkey required)

### Issue 4: Token Scope

**Problem:** OAuth token obtained successfully, but STK Push fails.

**Possible causes:**
1. Token from sandbox cannot be used with production endpoints
2. Token doesn't have STK Push scope/permissions
3. BusinessShortCode in STK Push request doesn't match token's scope

**Solution:**
- Ensure environment matches (production credentials → production API)
- Ensure BusinessShortCode matches Consumer Key/Secret

## Quick Diagnostic Steps

### Step 1: Check Environment
```bash
cd ~/domains/business.talksasa.com/public_html
cat .env | grep MPESA_ENVIRONMENT
# Should show: MPESA_ENVIRONMENT=production
```

### Step 2: Clear Config Cache
```bash
php artisan config:clear
php artisan config:cache
```

### Step 3: Verify Config
```bash
php artisan tinker
>>> config('app.env')
>>> env('MPESA_ENVIRONMENT')
```

### Step 4: Test OAuth Only
Use "Test Credentials" without STK Push to verify OAuth works.

### Step 5: Test STK Push
After fixing environment, test STK Push again.

## Expected Behavior After Fix

**Before fix:**
```
"environment":"sandbox"
"base_url":"https://sandbox.safaricom.co.ke"
"STK Push client error" {"status":404,"errorCode":"404.001.03"}
```

**After fix:**
```
"environment":"production"
"base_url":"https://api.safaricom.co.ke"
"STK Push initiated successfully"
```

## Common Error Codes

- **404.001.03**: Invalid Access Token
  - Usually means environment mismatch or BusinessShortCode mismatch
  
- **401.003.01**: Invalid Access Token (for URL registration)
  - Same causes as above

- **403**: Forbidden
  - Usually means Consumer Key/Secret are invalid or don't have permissions

## Testing Checklist

- [ ] `.env` has `MPESA_ENVIRONMENT=production`
- [ ] Config cache cleared: `php artisan config:clear && php artisan config:cache`
- [ ] Business Shortcode matches Consumer Key/Secret
- [ ] For Paybill: Passkey is provided
- [ ] For Till: Passkey can be empty
- [ ] OAuth test succeeds
- [ ] STK Push test succeeds

## Still Not Working?

1. **Check M-Pesa Developer Portal:**
   - Verify Consumer Key/Secret are active
   - Verify ShortCode is associated with the credentials
   - Check if STK Push is enabled for your app

2. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "STK Push"
   ```

3. **Verify Request Payload:**
   - Check logs for `STK Push request payload`
   - Verify BusinessShortCode matches Consumer Key/Secret
   - Verify Password is correctly generated
