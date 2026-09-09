# STK Push Not Received on Phone - Troubleshooting

## Issue
STK Push API returns success (`ResponseCode: 0`), but the prompt doesn't appear on the phone.

## Common Causes

### 1. Phone Number Not Registered with M-Pesa
**Problem:** The phone number must be registered with M-Pesa and have an active M-Pesa account.

**Solution:**
- Ensure the phone number is registered with M-Pesa
- The phone must have M-Pesa app installed or be able to receive USSD prompts
- Test with a different phone number that you know is active

### 2. Till Number Not Activated for STK Push
**Problem:** Till numbers must be specifically activated for STK Push in the M-Pesa system.

**Solution:**
- Contact Safaricom/M-Pesa support to activate STK Push for your Till number
- Verify in M-Pesa Developer Portal that STK Push is enabled for your Till number

### 3. Phone Number Format
**Problem:** Phone number must be in correct format: `254XXXXXXXXX` (12 digits starting with 254).

**Current format in logs:** `254707711847` ✅ (Correct)

**Verify:**
- No spaces or special characters
- Exactly 12 digits
- Starts with `254`
- Second digit is `7` or `1` (Kenyan mobile prefix)

### 4. PartyB Field
**For Till Numbers:**
- `BusinessShortCode`: Use Business Shortcode (e.g., `6520448`)
- `PartyB`: Should be Till number (e.g., `8556534`) or Business Shortcode

**For Paybill:**
- `BusinessShortCode`: Use Business Shortcode or Paybill number
- `PartyB`: Same as BusinessShortCode

### 5. Callback URL Not Registered
**Problem:** M-Pesa may not deliver STK Push if callback URLs are not properly registered.

**Solution:**
- Ensure URLs are registered using "Register URLs" feature
- Verify callback URL is publicly accessible
- Check that callback URL matches exactly what's registered

### 6. Till Number Configuration
**Problem:** The Till number might not be properly linked to the Business Shortcode in M-Pesa system.

**Solution:**
- Verify in M-Pesa Developer Portal that Till number is linked to Business Shortcode
- Ensure Consumer Key/Secret are for the correct Business Shortcode
- Contact M-Pesa support if Till number is not showing in your account

## Diagnostic Steps

### Step 1: Verify Phone Number
```bash
# Check phone number format
# Should be: 254712345678 (12 digits, starts with 254)
```

### Step 2: Test with Different Phone
Try with a different phone number that you know receives M-Pesa prompts.

### Step 3: Check M-Pesa Developer Portal
1. Log into M-Pesa Developer Portal
2. Check if STK Push is enabled for your Till number
3. Verify Till number is linked to Business Shortcode

### Step 4: Verify Callback URLs
1. Use "Register URLs" feature in settings
2. Ensure registration is successful
3. Verify callback URL is publicly accessible

### Step 5: Check Logs
Look for:
- `ResponseCode: 0` ✅ (Success)
- `CheckoutRequestID` ✅ (Returned)
- Any error messages in callback logs

## Expected Behavior

**Successful STK Push:**
```
ResponseCode: 0
ResponseDescription: Success. Request accepted for processing
CheckoutRequestID: ws_CO_...
CustomerMessage: Success. Request accepted for processing
```

**Then:**
- User receives M-Pesa prompt on phone within 10-30 seconds
- User enters PIN
- Payment is processed
- Callback is received at CallBackURL

## If Still Not Working

1. **Contact M-Pesa Support:**
   - Provide CheckoutRequestID from logs
   - Ask them to check why STK Push is not being delivered
   - Verify Till number is activated for STK Push

2. **Check Phone Settings:**
   - Ensure phone can receive SMS/USSD
   - Check if M-Pesa app is blocking prompts
   - Try disabling M-Pesa app temporarily

3. **Test with Paybill:**
   - If Paybill STK Push works but Till doesn't, it's likely a Till-specific configuration issue
   - Contact M-Pesa to activate STK Push for Till number

## Code Changes Made

1. ✅ Added `tillNumber` parameter to `initiateStkPush()` method
2. ✅ For Till numbers: `PartyB` now uses Till number (if provided)
3. ✅ Enhanced logging to show PartyB and Till number values
4. ✅ Updated credential test to pass Till number separately

## Testing Checklist

- [ ] Phone number is registered with M-Pesa
- [ ] Phone number format is correct: `254XXXXXXXXX`
- [ ] Till number is activated for STK Push in M-Pesa system
- [ ] Business Shortcode is correct
- [ ] Consumer Key/Secret match Business Shortcode
- [ ] Passkey is correct
- [ ] Callback URLs are registered
- [ ] Test with different phone number
- [ ] Check M-Pesa Developer Portal for Till number status
