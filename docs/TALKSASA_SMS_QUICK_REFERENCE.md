# Talksasa SMS Service - Quick Reference

## Service Class

**Location**: `app/Services/TalksasaSmsService.php`

**Key Methods:**
- `sendSms(string $phoneNumber, string $message, array $options = []): bool`
- `checkBalance(): float`
- `getAccountInfo(): array`
- `validateConfiguration(): bool`

## Quick Start

### 1. Configure Credentials

```env
TALKSASA_SMS_API_URL=https://api.talksasa.com/v1
TALKSASA_SMS_API_KEY=your_api_key
TALKSASA_SMS_API_SECRET=your_api_secret
TALKSASA_SMS_SENDER_ID=TALKSASA
```

### 2. Send SMS

```php
use App\Services\TalksasaSmsService;

$service = app(TalksasaSmsService::class);
$service->sendSms('254712345678', 'Hello World!');
```

### 3. Check Balance

```php
$balance = $service->checkBalance();
echo "Balance: KES {$balance}";
```

## Error Handling

```php
use App\Exceptions\TalksasaSmsException;

try {
    $service->sendSms($phone, $message);
} catch (TalksasaSmsException $e) {
    if ($e->isRetryable()) {
        // Retryable error
        Log::warning('Retryable', ['error' => $e->getMessage()]);
    } else {
        // Permanent error
        Log::error('Permanent', ['error' => $e->getMessage()]);
    }
}
```

## Testing

### Mock HTTP Responses

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.talksasa.com/v1/auth/token' => Http::response([
        'token' => 'test_token',
        'expires_in' => 3600,
    ], 200),
    'api.talksasa.com/v1/sms/send' => Http::response([
        'status' => 'success',
    ], 200),
]);
```

## API Endpoints Used

- `POST /auth/token` - Get authentication token
- `GET /account/balance` - Check account balance
- `GET /account/info` - Get account information
- `POST /sms/send` - Send SMS

## Retry Behavior

**Retries on:**
- Network errors
- 500, 502, 503 errors
- 429 rate limiting

**No retry on:**
- 400 Bad Request
- 401 Unauthorized (after token refresh)
- 402 Insufficient Balance
- 404 Not Found

## Logging

All operations are logged:
- SMS attempts (info)
- Success (info)
- Retries (warning)
- Errors (error)

Check logs: `storage/logs/laravel.log`
