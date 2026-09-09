# M-Pesa URL Registration Troubleshooting

## 🔴 Error: "Invalid Access Token" (401.003.01)

This error occurs when M-Pesa rejects the access token when registering URLs. Here are the most common causes and solutions:

### Common Causes

#### 1. **ShortCode Mismatch** (Most Common)
**Problem:** The Till/Paybill number doesn't match the Consumer Key/Secret being used.

**Solution:**
- Each Till/Paybill number has its own unique Consumer Key/Secret pair
- Ensure the Till/Paybill number matches the credentials from M-Pesa Developer Portal
- For example: If your Till number is `8556534`, use the Consumer Key/Secret specifically generated for that Till number

**How to verify:**
1. Log into M-Pesa Developer Portal
2. Go to your app
3. Check which ShortCode (Till/Paybill) is associated with your Consumer Key/Secret
4. Ensure the ShortCode in your settings matches exactly

#### 2. **Environment Mismatch**
**Problem:** Using sandbox credentials with production API or vice versa.

**Solution:**
- **Sandbox:** Use `https://sandbox.safaricom.co.ke` with sandbox Consumer Key/Secret
- **Production:** Use `https://api.safaricom.co.ke` with production Consumer Key/Secret

**Check your `.env` file:**
```env
MPESA_ENVIRONMENT=sandbox  # or 'production'
```

**For Sandbox:**
- Consumer Key: Starts with something like `your_sandbox_key`
- Consumer Secret: Your sandbox secret
- Till/Paybill: Test numbers (e.g., `174379`)

**For Production:**
- Consumer Key: Your production key
- Consumer Secret: Your production secret
- Till/Paybill: Your actual business number

#### 3. **Invalid Consumer Key/Secret**
**Problem:** The Consumer Key or Consumer Secret is incorrect or expired.

**Solution:**
1. Log into M-Pesa Developer Portal
2. Regenerate your Consumer Key/Secret if needed
3. Copy them exactly (no extra spaces)
4. Test credentials first using the "Test Credentials" button

#### 4. **Token Expired Between Calls**
**Problem:** Token obtained but expired before use (rare but possible).

**Solution:**
- The code automatically gets a fresh token before each registration
- If this persists, check server time synchronization

### Step-by-Step Debugging

#### Step 1: Verify Credentials Match ShortCode

1. **Check M-Pesa Developer Portal:**
   ```
   Login → Your App → Check ShortCode/Till Number
   ```

2. **Verify in Settings:**
   - Till/Paybill number matches the one in Developer Portal
   - Consumer Key matches the one for that ShortCode
   - Consumer Secret matches

#### Step 2: Check Environment

```bash
# On your server, check .env
cd ~/domains/your-domain.com/public_html
grep MPESA_ENVIRONMENT .env
```

Should be:
- `MPESA_ENVIRONMENT=sandbox` for testing
- `MPESA_ENVIRONMENT=production` for live

#### Step 3: Test Credentials First

Before registering URLs, test your credentials:

1. Go to Settings page
2. Fill in Till/Paybill number and credentials
3. Click "Test Credentials"
4. Should show: "Credentials are valid!"

If test fails, fix credentials first.

#### Step 4: Check Logs

```bash
cd ~/domains/your-domain.com/public_html
tail -f storage/logs/laravel.log
```

Look for:
- OAuth token generation logs
- Register URL API call logs
- Error details

### Common Scenarios

#### Scenario 1: Sandbox Testing
```
Till Number: 174379 (test number)
Consumer Key: [sandbox key from developer portal]
Consumer Secret: [sandbox secret]
Environment: sandbox
```

#### Scenario 2: Production
```
Till Number: 8556534 (your actual number)
Consumer Key: [production key from developer portal]
Consumer Secret: [production secret]
Environment: production
```

### Quick Fix Checklist

- [ ] Till/Paybill number matches Consumer Key/Secret in Developer Portal
- [ ] Environment setting matches credentials (sandbox vs production)
- [ ] Consumer Key copied correctly (no extra spaces)
- [ ] Consumer Secret copied correctly (no extra spaces)
- [ ] "Test Credentials" button works successfully
- [ ] URLs are HTTPS (required by M-Pesa)
- [ ] URLs are publicly accessible (not localhost)

### Still Not Working?

1. **Double-check in M-Pesa Developer Portal:**
   - Which ShortCode is associated with your Consumer Key/Secret?
   - Are you using the correct app (sandbox vs production)?

2. **Try regenerating credentials:**
   - In Developer Portal, regenerate Consumer Key/Secret
   - Update in your settings
   - Test again

3. **Check M-Pesa API Status:**
   - Visit M-Pesa Developer Portal status page
   - Ensure APIs are operational

4. **Contact M-Pesa Support:**
   - If credentials are correct but still failing
   - Provide error code: `401.003.01`
   - Provide your ShortCode and request ID from logs

### Error Code Reference

| Error Code | Meaning | Solution |
|------------|---------|----------|
| `401.003.01` | Invalid Access Token | Check ShortCode matches credentials, verify environment |
| `401.002.01` | Invalid Consumer Key/Secret | Verify credentials are correct |
| `400.002.01` | Invalid ShortCode | Check Till/Paybill number format |

### Example: Correct Setup

**Sandbox Example:**
```env
MPESA_ENVIRONMENT=sandbox
```

Settings:
- Till Number: `174379`
- Consumer Key: `[sandbox key]`
- Consumer Secret: `[sandbox secret]`
- Confirmation URL: `https://your-domain.com/api/webhooks/mpesa/payment`
- Validation URL: `https://your-domain.com/api/webhooks/mpesa/payment`

**Production Example:**
```env
MPESA_ENVIRONMENT=production
```

Settings:
- Till Number: `8556534` (your actual number)
- Consumer Key: `[production key]`
- Consumer Secret: `[production secret]`
- Confirmation URL: `https://your-domain.com/api/webhooks/mpesa/payment`
- Validation URL: `https://your-domain.com/api/webhooks/mpesa/payment`
