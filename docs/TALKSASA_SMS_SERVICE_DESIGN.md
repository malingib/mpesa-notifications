# Talksasa Bulk SMS API Service Design

## Overview

A production-grade service class for integrating with Talksasa Bulk SMS API. Handles authentication, balance checking, retry logic, and comprehensive error handling.

## Service Architecture

### Responsibilities
1. **Authentication**: Token-based API authentication
2. **Balance Checking**: Verify sufficient balance before sending
3. **SMS Sending**: Send SMS with retry logic
4. **Error Handling**: Comprehensive error categorization
5. **Logging**: Log all attempts and outcomes

### Design Principles
- **Single Responsibility**: Only handles SMS API communication
- **Retry Strategy**: Exponential backoff for transient failures
- **Error Recovery**: Distinguish between retryable and permanent errors
- **Observability**: Comprehensive logging for debugging

---

## 1. Service Class Structure

### TalksasaSmsService

**Location**: `app/Services/TalksasaSmsService.php`

**Key Methods:**
- `sendSms()` - Main SMS sending method
- `checkBalance()` - Verify account balance
- `getAccountInfo()` - Get account details
- `authenticate()` - Get/refresh API token

**Dependencies:**
- Guzzle HTTP Client
- Laravel Cache (for token storage)
- Laravel Log

---

## 2. Authentication Strategy

### Token-Based Authentication

**Flow:**
```
Request → Check Cache for Token
    ↓
Token Exists? → Yes → Use Token
    ↓ No
Call /auth/token endpoint
    ↓
Store Token in Cache (with TTL)
    ↓
Use Token for Requests
```

**Token Storage:**
- Cache key: `talksasa_api_token`
- TTL: 1 hour (or as per API response)
- Auto-refresh on 401 errors

---

## 3. Balance Checking

### Pre-Send Balance Check

**Strategy:**
1. Check balance before sending
2. Estimate SMS cost (based on message length)
3. Verify sufficient balance
4. Log warning if balance is low

**Balance Endpoint:**
```
GET /api/v1/account/balance
Headers: Authorization: Bearer {token}
```

---

## 4. Retry Logic

### Exponential Backoff Strategy

**Retry Conditions:**
- Network errors (timeout, connection refused)
- 5xx server errors
- Rate limiting (429)

**No Retry:**
- 4xx client errors (except 429)
- Authentication errors (401) - refresh token first
- Insufficient balance

**Backoff Formula:**
```
delay = base_delay * (2 ^ attempt_number)
```

**Example:**
- Attempt 1: 1 second
- Attempt 2: 2 seconds
- Attempt 3: 4 seconds
- Attempt 4: 8 seconds

---

## 5. Error Handling Strategy

### Error Categories

**Transient Errors** (Retryable):
- Network timeouts
- 500 Internal Server Error
- 502 Bad Gateway
- 503 Service Unavailable
- 429 Too Many Requests

**Permanent Errors** (No Retry):
- 400 Bad Request (invalid payload)
- 401 Unauthorized (after token refresh)
- 402 Payment Required (insufficient balance)
- 404 Not Found (invalid endpoint)
- 422 Unprocessable Entity (validation error)

**Error Response Format:**
```php
throw new TalksasaSmsException(
    message: 'Error message',
    code: 500,
    isRetryable: true,
    previous: $exception
);
```

---

## 6. Logging Strategy

### Log Levels

**Info:**
- SMS sent successfully
- Balance checked
- Token refreshed

**Warning:**
- Low balance
- Retry attempt
- Rate limit hit

**Error:**
- SMS sending failed
- Authentication failed
- Permanent errors

**Debug:**
- Request/response details
- Retry decisions
- Token operations

---

## 7. Testing Strategy

### Mocking the Service

**Approach:**
1. Create interface for service
2. Use Laravel's service container binding
3. Mock HTTP client responses
4. Test retry logic
5. Test error scenarios

**Example Test:**
```php
public function test_sms_sent_successfully()
{
    Http::fake([
        'talksasa.com/api/v1/sms' => Http::response(['status' => 'success'], 200),
    ]);
    
    $service = app(TalksasaSmsService::class);
    $result = $service->sendSms('254712345678', 'Test message');
    
    $this->assertTrue($result);
}
```

---

## 8. Configuration

### Environment Variables

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

---

## 9. Service Interface

### TalksasaSmsServiceInterface

For better testability and extensibility:

```php
interface TalksasaSmsServiceInterface
{
    public function sendSms(string $phoneNumber, string $message): bool;
    public function checkBalance(): float;
    public function getAccountInfo(): array;
}
```

---

## 10. Usage Examples

### Basic Usage
```php
$service = app(TalksasaSmsService::class);
$success = $service->sendSms('254712345678', 'Hello World');
```

### With Error Handling
```php
try {
    $service->sendSms($phone, $message);
} catch (TalksasaSmsException $e) {
    if ($e->isRetryable()) {
        // Queue for retry
    } else {
        // Log permanent error
    }
}
```

---

## 11. Performance Considerations

- **Connection Pooling**: Reuse HTTP connections
- **Token Caching**: Avoid repeated authentication
- **Async Requests**: Consider async for bulk sends
- **Rate Limiting**: Respect API rate limits

---

## 12. Monitoring & Metrics

**Key Metrics:**
- SMS success rate
- Average response time
- Retry count
- Balance levels
- Error rates by type

**Alerts:**
- Low balance threshold
- High error rate
- Authentication failures
- API downtime
