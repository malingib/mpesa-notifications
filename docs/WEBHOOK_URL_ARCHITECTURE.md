# Webhook URL Architecture - Multi-Tenant Design

## Current Implementation: Shared URL Approach ✅

### How It Works

**All users register the SAME webhook URL with M-Pesa:**
```
https://your-domain.com/api/webhooks/mpesa/payment
```

### Why This Works

1. **M-Pesa Includes Account Info in Payload**
   - Every webhook from M-Pesa includes the `TillNumber` or `BusinessShortCode` in the payload
   - Example payload:
     ```json
     {
       "TransactionID": "ABC123",
       "Amount": 100,
       "TillNumber": "8556534",  // ← This identifies which user
       "PhoneNumber": "254712345678",
       ...
     }
     ```

2. **System Routes Based on Account Number**
   - Webhook controller extracts `TillNumber` or `BusinessShortCode` from payload
   - Looks up merchant in database: `MerchantRepository::findByAccount()`
   - Merchant record has `user_id` field → routes to correct tenant
   - Payment is saved with correct `user_id`

3. **M-Pesa Registers URLs Per ShortCode**
   - When User A registers URL for Till `12345`, M-Pesa associates that URL with Till `12345`
   - When User B registers URL for Till `67890`, M-Pesa associates that URL with Till `67890`
   - Both can use the same URL because M-Pesa includes the Till number in each webhook

### Current Flow

```
User A (Till 12345) → Registers: https://domain.com/api/webhooks/mpesa/payment
User B (Till 67890) → Registers: https://domain.com/api/webhooks/mpesa/payment

Payment to Till 12345:
  ↓
M-Pesa sends webhook to: https://domain.com/api/webhooks/mpesa/payment
  ↓
Payload includes: {"TillNumber": "12345", ...}
  ↓
System looks up merchant with TillNumber = "12345"
  ↓
Finds User A's merchant → Routes payment to User A ✅

Payment to Till 67890:
  ↓
M-Pesa sends webhook to: https://domain.com/api/webhooks/mpesa/payment
  ↓
Payload includes: {"TillNumber": "67890", ...}
  ↓
System looks up merchant with TillNumber = "67890"
  ↓
Finds User B's merchant → Routes payment to User B ✅
```

## Alternative: Unique URLs Per User

### Option 1: URL Parameters
```
https://domain.com/api/webhooks/mpesa/payment?user_id=123
https://domain.com/api/webhooks/mpesa/payment?user_id=456
```

**Pros:**
- Explicit user identification in URL
- Easier debugging (can see user in URL)

**Cons:**
- M-Pesa may strip query parameters
- More complex URL management
- Not necessary (M-Pesa provides account number)

### Option 2: Subdomain Routing
```
https://user123.domain.com/api/webhooks/mpesa/payment
https://user456.domain.com/api/webhooks/mpesa/payment
```

**Pros:**
- Very explicit routing
- Can use DNS/wildcard subdomains

**Cons:**
- Complex DNS setup
- Requires wildcard SSL certificates
- Overkill for this use case

### Option 3: Path-Based Routing
```
https://domain.com/api/webhooks/mpesa/payment/user/123
https://domain.com/api/webhooks/mpesa/payment/user/456
```

**Pros:**
- Explicit routing
- No DNS changes needed

**Cons:**
- Each user needs different URL
- More complex registration process
- Not necessary (current approach works)

## Recommendation: Keep Shared URL ✅

**The current shared URL approach is:**
- ✅ **Standard practice** - Most multi-tenant systems use this
- ✅ **Simpler** - One URL to maintain and monitor
- ✅ **Scalable** - Works for thousands of users
- ✅ **Reliable** - M-Pesa always includes account number
- ✅ **Secure** - No user IDs exposed in URLs

## Current Code Implementation

### Webhook Controller
```php
// app/Http/Controllers/PaymentWebhookController.php
public function handle(PaymentWebhookRequest $request)
{
    // Extract account from payload
    $paymentDto = PaymentDto::fromMpesaPayload($payload);
    // $paymentDto->accountNumber = "8556534" (from TillNumber)
    
    // Route to correct user via merchant lookup
    $payment = $this->ingestionService->ingest($paymentDto);
    // Payment is saved with correct user_id
}
```

### Merchant Repository
```php
// app/Repositories/MerchantRepository.php
public function findByAccount(string $accountType, string $accountNumber): ?Merchant
{
    return Merchant::withoutGlobalScopes()
        ->where('account_type', $accountType)
        ->where('account_number', $accountNumber)
        ->where('is_active', true)
        ->with('user')  // ← Links to correct user
        ->first();
}
```

## Summary

**Answer: Users will have SIMILAR URLs (same URL for all users)**

- All users register: `https://your-domain.com/api/webhooks/mpesa/payment`
- System routes payments based on `TillNumber`/`BusinessShortCode` in payload
- Each payment is correctly assigned to the right user via merchant lookup
- This is the standard, scalable approach for multi-tenant webhook systems

## If You Want Unique URLs

If you prefer unique URLs per user for any reason, we can implement:
1. URL parameter approach: `?user_id=123`
2. Path-based routing: `/api/webhooks/mpesa/payment/user/{id}`
3. Subdomain routing: `user123.domain.com`

However, **the current shared URL approach is recommended** and follows industry best practices.
