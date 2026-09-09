# Talksasa SMS Service - Implementation Guide

## Service Overview

The `TalksasaSmsService` is a production-grade service class for integrating with Talksasa Bulk SMS API. It handles authentication, balance checking, retry logic, and comprehensive error handling.

## Key Features

✅ **Token-Based Authentication** - Automatic token management with caching
✅ **Balance Checking** - Pre-send balance verification
✅ **Retry Logic** - Exponential backoff for transient errors
✅ **Error Handling** - Categorized errors (retryable vs permanent)
✅ **Comprehensive Logging** - Logs all attempts and outcomes

## Configuration

### Environment Variables

Add to `.env`:

```env
TALKSASA_SMS_API_URL=https://api.talksasa.com/v1
TALKSASA_SMS_API_KEY=your_api_key
TALKSASA_SMS_API_SECRET=your_api_secret
TALKSASA_SMS_SENDER_ID=TALKSASA
TALKSASA_SMS_TIMEOUT=30
TALKSASA_SMS_RETRY_ATTEMPTS=3
TALKSASA_SMS_RETRY_DELAY=1
TALKSASA_SMS_LOW_BALANCE_THRESHOLD=100
```

## Usage

### Basic SMS Sending

```php
use App\Services\TalksasaSmsService;

$service = app(TalksasaSmsService::class);

try {
    $success = $service->sendSms('254712345678', 'Hello World!');
    
    if ($success) {
        echo "SMS sent successfully";
    }
} catch (TalksasaSmsException $e) {
    if ($e->isRetryable()) {
        // Queue for retry
        Log::warning('Retryable error', ['error' => $e->getMessage()]);
    } else {
        // Permanent error - log and alert
        Log::error('Permanent error', ['error' => $e->getMessage()]);
    }
}
```

### Check Balance

```php
try {
    $balance = $service->checkBalance();
    echo "Current balance: KES {$balance}";
} catch (TalksasaSmsException $e) {
    echo "Failed to check balance: " . $e->getMessage();
}
```

### Get Account Info

```php
try {
    $info = $service->getAccountInfo();
    print_r($info);
} catch (TalksasaSmsException $e) {
    echo "Failed to get account info: " . $e->getMessage();
}
```

## Error Handling

### Error Categories

**Retryable Errors:**
- Network timeouts
- 500 Internal Server Error
- 502 Bad Gateway
- 503 Service Unavailable
- 429 Too Many Requests

**Permanent Errors:**
- 400 Bad Request
- 401 Unauthorized (after token refresh)
- 402 Payment Required (insufficient balance)
- 404 Not Found
- 422 Unprocessable Entity

### Error Handling Example

```php
try {
    $service->sendSms($phone, $message);
} catch (TalksasaSmsException $e) {
    if ($e->isRetryable()) {
        // Retryable - queue for later
        Log::warning('Retryable SMS error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $e->getContext(),
        ]);
        
        // Optionally queue for retry
        // RetrySmsJob::dispatch($phone, $message)->delay(now()->addMinutes(5));
    } else {
        // Permanent - log and alert
        Log::error('Permanent SMS error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $e->getContext(),
        ]);
        
        // Send alert to admin
        // Notification::send($admin, new SmsErrorAlert($e));
    }
}
```

## Retry Logic

### Exponential Backoff

The service uses exponential backoff for retries:

- **Attempt 1**: 1 second delay
- **Attempt 2**: 2 seconds delay
- **Attempt 3**: 4 seconds delay
- **Attempt 4**: 8 seconds delay

### Retry Configuration

```php
// In config/talksasa.php or .env
'retry_attempts' => 3,  // Maximum retry attempts
'retry_delay' => 1,     // Base delay in seconds
```

## Authentication Flow

1. **Check Cache** - Look for cached token
2. **Use Cached Token** - If valid, use it
3. **Authenticate** - If missing/expired, call `/auth/token`
4. **Cache Token** - Store token with TTL
5. **Use Token** - Include in Authorization header

### Token Caching

- **Cache Key**: `talksasa_api_token`
- **TTL**: 1 hour (or as per API response)
- **Auto-Refresh**: On 401 errors

## Balance Checking

### Pre-Send Check

Before sending SMS, the service:
1. Checks current balance
2. Estimates SMS cost (based on message length)
3. Verifies sufficient balance
4. Logs warning if balance is low

### Cost Estimation

- Standard SMS: 160 characters = 1 SMS
- Long SMS: > 160 characters = multiple SMS
- Cost: 1 KES per SMS (adjust in code)

## Logging

### Log Levels

**Info:**
- SMS send attempt started
- SMS sent successfully
- Balance checked
- Token obtained

**Warning:**
- Low balance
- Retry attempt
- Rate limit hit

**Error:**
- SMS sending failed
- Authentication failed
- Permanent errors

### Log Format

```php
Log::info('SMS sent successfully', [
    'phone_number' => '254****678', // Masked for privacy
    'message_id' => 'MSG123',
    'message_length' => 50,
]);
```

## Testing

### Mocking the Service

```php
use Illuminate\Support\Facades\Http;
use App\Services\TalksasaSmsService;

// Mock successful response
Http::fake([
    'api.talksasa.com/v1/auth/token' => Http::response([
        'token' => 'test_token',
        'expires_in' => 3600,
    ], 200),
    'api.talksasa.com/v1/sms/send' => Http::response([
        'status' => 'success',
        'message_id' => 'MSG123',
    ], 200),
]);

$service = app(TalksasaSmsService::class);
$result = $service->sendSms('254712345678', 'Test');
```

### Test Scenarios

1. **Successful Send** - Mock 200 response
2. **Retry on 500** - Mock 500, then 200
3. **Insufficient Balance** - Mock low balance
4. **Token Refresh** - Mock 401, then new token
5. **Permanent Error** - Mock 400 response

## Integration with Payment System

The service is integrated with the payment notification system:

```php
// In SendPaymentSmsJob
$templateService = app(SmsTemplateService::class);
$smsService = app(TalksasaSmsService::class);

$message = $templateService->getMessageForPayment($payment);
$smsService->sendSms($payment->phone_number, $message);
```

## Monitoring

### Key Metrics

- SMS success rate
- Average response time
- Retry count
- Balance levels
- Error rates by type

### Alerts

- Low balance threshold
- High error rate
- Authentication failures
- API downtime

## Troubleshooting

### Common Issues

**"Failed to authenticate"**
- Check API key and secret
- Verify API URL
- Check network connectivity

**"Insufficient balance"**
- Top up account
- Check balance via `checkBalance()`

**"SMS send failed after retries"**
- Check API status
- Verify phone number format
- Check message content

**"Token not found"**
- Clear cache: `Cache::forget('talksasa_api_token')`
- Check authentication endpoint

## Performance

- **Connection Pooling**: HTTP client reuses connections
- **Token Caching**: Reduces authentication calls
- **Async Ready**: Can be used in queue jobs
- **Timeout Handling**: Configurable request timeout

## Security

- ✅ **Token Security**: Tokens cached securely
- ✅ **Phone Masking**: Phone numbers masked in logs
- ✅ **Error Sanitization**: Sensitive data not logged
- ✅ **HTTPS Only**: All API calls use HTTPS
