# Payment Webhook Ingestion Layer Design

## Overview

The webhook ingestion layer is responsible for receiving, validating, and normalizing payment notifications from M-Pesa. It acts as the entry point for all payment data, ensuring data integrity and fast response times.

## Architecture Principles

1. **Fast Response**: Webhook endpoint must respond quickly (< 200ms)
2. **Idempotency**: Handle duplicate notifications gracefully
3. **Validation First**: Validate payload before processing
4. **Audit Trail**: Store raw payload for compliance
5. **Normalization**: Convert M-Pesa formats to internal standard
6. **Async Processing**: SMS and heavy operations happen in background

---

## 1. Controller Structure

### PaymentWebhookController

**Responsibilities:**
- Receive webhook requests
- Validate payload structure
- Normalize payload to internal format
- Store payment record
- Dispatch async jobs
- Return fast response

**Location**: `app/Http/Controllers/PaymentWebhookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\DTOs\PaymentDto;
use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentIngestionService $ingestionService
    ) {}

    /**
     * Handle incoming payment notification
     * 
     * M-Pesa sends payment confirmations to this endpoint.
     * Must respond quickly (< 200ms) to prevent retries.
     */
    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            
            // Normalize payload to internal format
            $paymentDto = PaymentDto::fromMpesaPayload($payload);
            
            // Process payment (idempotent, async SMS)
            $payment = $this->ingestionService->ingest($paymentDto);
            
            if ($payment) {
                Log::info('Payment ingested successfully', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $paymentDto->transactionId,
                ]);
                
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                ], 200);
            }
            
            // Payment already processed (duplicate)
            Log::info('Duplicate payment detected', [
                'transaction_id' => $paymentDto->transactionId,
            ]);
            
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Already processed',
            ], 200);
            
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid payment payload', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            
            // Return success to prevent M-Pesa retries for invalid data
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Invalid payload - logged',
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Payment ingestion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);
            
            // Return 500 to trigger M-Pesa retry mechanism
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Processing error',
            ], 500);
        }
    }
}
```

---

## 2. Request Validation

### PaymentWebhookRequest

**Location**: `app/Http/Requests/PaymentWebhookRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Webhooks don't require user authentication
        // Security is handled by WebhookSecurityMiddleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields for all payment types
            'TransactionID' => 'required|string|max:100',
            'Amount' => 'required|numeric|min:0|max:999999999.99',
            'PhoneNumber' => 'required|string|regex:/^254\d{9}$/',
            'TransactionTime' => 'required|string|regex:/^\d{14}$/',
            
            // Paybill-specific fields
            'BusinessShortCode' => 'required_without:TillNumber|string|max:20',
            'BillRefNumber' => 'nullable|string|max:255',
            
            // Till-specific fields
            'TillNumber' => 'required_without:BusinessShortCode|string|max:20',
            
            // Optional but common fields
            'ReceiptNumber' => 'nullable|string|max:100',
            'RequestID' => 'nullable|string|max:100',
            'ConversationID' => 'nullable|string|max:100',
            'FirstName' => 'nullable|string|max:100',
            'MiddleName' => 'nullable|string|max:100',
            'LastName' => 'nullable|string|max:100',
            'TransactionDesc' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'TransactionID.required' => 'TransactionID is required',
            'Amount.required' => 'Amount is required',
            'Amount.numeric' => 'Amount must be a valid number',
            'PhoneNumber.regex' => 'PhoneNumber must be in E.164 format (254XXXXXXXXX)',
            'TransactionTime.regex' => 'TransactionTime must be in format YYYYMMDDHHmmss',
            'BusinessShortCode.required_without' => 'BusinessShortCode or TillNumber is required',
            'TillNumber.required_without' => 'BusinessShortCode or TillNumber is required',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone number format
        if ($this->has('PhoneNumber')) {
            $phone = preg_replace('/[^0-9]/', '', $this->input('PhoneNumber'));
            
            // Convert 07XXXXXXXX to 2547XXXXXXXX
            if (strlen($phone) === 9 && substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
                // Already correct format
            } elseif (strlen($phone) === 9) {
                $phone = '254' . $phone;
            }
            
            $this->merge(['PhoneNumber' => $phone]);
        }
    }
}
```

---

## 3. Normalized Payment DTO

### PaymentDto

**Location**: `app/DTOs/PaymentDto.php`

```php
<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Payment Data Transfer Object
 * 
 * Normalized representation of payment data.
 * Converts M-Pesa payloads to internal format.
 */
class PaymentDto
{
    public function __construct(
        public readonly string $transactionId,
        public readonly ?string $receiptNumber,
        public readonly ?string $requestId,
        public readonly ?string $conversationId,
        public readonly string $accountType, // 'paybill' or 'till'
        public readonly string $accountNumber,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $phoneNumber,
        public readonly ?string $payerName,
        public readonly Carbon $transactionTime,
        public readonly string $status, // 'completed', 'pending', etc.
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly array $rawPayload, // Full M-Pesa payload for audit
    ) {}

    /**
     * Create PaymentDto from M-Pesa payload
     */
    public static function fromMpesaPayload(array $payload): self
    {
        // Determine account type
        $accountType = isset($payload['BillRefNumber']) || isset($payload['BusinessShortCode'])
            ? 'paybill'
            : 'till';
        
        // Extract account number
        $accountNumber = $accountType === 'paybill'
            ? ($payload['BusinessShortCode'] ?? '')
            : ($payload['TillNumber'] ?? '');
        
        // Parse transaction time
        $transactionTime = self::parseTransactionTime($payload['TransactionTime'] ?? '');
        
        // Build payer name
        $payerName = trim(
            ($payload['FirstName'] ?? '') . ' ' .
            ($payload['MiddleName'] ?? '') . ' ' .
            ($payload['LastName'] ?? '')
        ) ?: null;
        
        return new self(
            transactionId: $payload['TransactionID'],
            receiptNumber: $payload['ReceiptNumber'] ?? null,
            requestId: $payload['RequestID'] ?? null,
            conversationId: $payload['ConversationID'] ?? null,
            accountType: $accountType,
            accountNumber: $accountNumber,
            amount: (float) $payload['Amount'],
            currency: 'KES', // M-Pesa always uses KES
            phoneNumber: $payload['PhoneNumber'],
            payerName: $payerName,
            transactionTime: $transactionTime,
            status: 'completed', // M-Pesa only sends completed payments
            description: $payload['TransactionDesc'] ?? null,
            reference: $payload['BillRefNumber'] ?? null,
            rawPayload: $payload,
        );
    }

    /**
     * Parse M-Pesa transaction time format (YYYYMMDDHHmmss)
     */
    private static function parseTransactionTime(string $timeString): Carbon
    {
        if (strlen($timeString) === 14) {
            $year = substr($timeString, 0, 4);
            $month = substr($timeString, 4, 2);
            $day = substr($timeString, 6, 2);
            $hour = substr($timeString, 8, 2);
            $minute = substr($timeString, 10, 2);
            $second = substr($timeString, 12, 2);
            
            return Carbon::create(
                $year, $month, $day, $hour, $minute, $second,
                'Africa/Nairobi'
            );
        }
        
        return Carbon::now('Africa/Nairobi');
    }

    /**
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'receipt_number' => $this->receiptNumber,
            'request_id' => $this->requestId,
            'conversation_id' => $this->conversationId,
            'account_type' => $this->accountType,
            'account_number' => $this->accountNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'phone_number' => $this->phoneNumber,
            'payer_name' => $this->payerName,
            'transaction_time' => $this->transactionTime->toDateTimeString(),
            'status' => $this->status,
            'description' => $this->description,
            'reference' => $this->reference,
            'metadata' => $this->rawPayload,
        ];
    }
}
```

---

## 4. Payment Ingestion Service

### PaymentIngestionService

**Location**: `app/Services/PaymentIngestionService.php`

```php
<?php

namespace App\Services;

use App\DTOs\PaymentDto;
use App\Models\Payment;
use App\Models\Merchant;
use App\Jobs\SendPaymentSmsJob;
use App\Repositories\PaymentRepository;
use App\Repositories\MerchantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Ingestion Service
 * 
 * Handles payment ingestion with idempotency and async processing.
 */
class PaymentIngestionService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private MerchantRepository $merchantRepository
    ) {}

    /**
     * Ingest payment notification
     * 
     * @return Payment|null Returns Payment if created, null if duplicate
     */
    public function ingest(PaymentDto $dto): ?Payment
    {
        // Idempotency check
        $existingPayment = $this->paymentRepository->findByTransactionId($dto->transactionId);
        if ($existingPayment) {
            Log::info('Duplicate payment detected', [
                'transaction_id' => $dto->transactionId,
                'existing_payment_id' => $existingPayment->id,
            ]);
            return null;
        }

        // Check duplicate by request ID
        if ($dto->requestId) {
            $duplicateByRequest = $this->paymentRepository->findByRequestId($dto->requestId);
            if ($duplicateByRequest) {
                Log::warning('Duplicate payment by request ID', [
                    'request_id' => $dto->requestId,
                    'existing_payment_id' => $duplicateByRequest->id,
                ]);
                return null;
            }
        }

        // Find merchant account
        $merchant = $this->merchantRepository->findByAccount(
            $dto->accountType,
            $dto->accountNumber
        );

        if (!$merchant || !$merchant->isActive()) {
            throw new \InvalidArgumentException(
                "Merchant account not found or inactive: {$dto->accountType} {$dto->accountNumber}"
            );
        }

        // Create payment in transaction
        return DB::transaction(function () use ($dto, $merchant) {
            $payment = Payment::create([
                'user_id' => $merchant->user_id,
                'merchant_id' => $merchant->id,
                'transaction_id' => $dto->transactionId,
                'receipt_number' => $dto->receiptNumber,
                'request_id' => $dto->requestId,
                'conversation_id' => $dto->conversationId,
                'account_type' => $dto->accountType,
                'account_number' => $dto->accountNumber,
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'phone_number' => $dto->phoneNumber,
                'payer_name' => $dto->payerName,
                'transaction_time' => $dto->transactionTime,
                'status' => $dto->status,
                'description' => $dto->description,
                'reference' => $dto->reference,
                'metadata' => $dto->rawPayload, // Store full payload
                'sms_sent' => false,
            ]);

            // Dispatch SMS job asynchronously
            SendPaymentSmsJob::dispatch($payment->id);

            Log::info('Payment ingested', [
                'payment_id' => $payment->id,
                'transaction_id' => $dto->transactionId,
                'merchant_id' => $merchant->id,
                'user_id' => $merchant->user_id,
            ]);

            return $payment;
        });
    }
}
```

---

## 5. Security Considerations

### 5.1 Webhook Authentication

**Option 1: IP Whitelist**
```php
// In WebhookSecurityMiddleware
$allowedIps = [
    '196.201.214.0/24', // M-Pesa IP range
    '196.201.215.0/24',
];

if (!in_array($request->ip(), $allowedIps)) {
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

**Option 2: Secret Key Validation**
```php
// In WebhookSecurityMiddleware
$providedSecret = $request->header('X-Webhook-Secret');
$expectedSecret = config('mpesa.webhook_secret');

if ($providedSecret !== $expectedSecret) {
    Log::warning('Invalid webhook secret', ['ip' => $request->ip()]);
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

**Option 3: Signature Verification**
```php
// Verify M-Pesa signature if provided
$signature = $request->header('X-Mpesa-Signature');
$payload = $request->getContent();
$expectedSignature = hash_hmac('sha256', $payload, config('mpesa.webhook_secret'));

if (!hash_equals($expectedSignature, $signature)) {
    return response()->json(['error' => 'Invalid signature'], 401);
}
```

### 5.2 Rate Limiting

```php
// In routes/api.php
Route::post('/webhooks/mpesa/payment')
    ->middleware('throttle:100,1'); // 100 requests per minute
```

### 5.3 Input Sanitization

- All inputs validated via FormRequest
- Phone numbers normalized to E.164
- Amounts validated (min/max)
- Strings length-limited
- SQL injection prevented by Eloquent

### 5.4 Error Handling

- **Invalid Payload**: Return 200 (prevent retries)
- **Processing Error**: Return 500 (trigger retry)
- **Duplicate**: Return 200 (already processed)
- **Account Not Found**: Return 200 (log for review)

### 5.5 Logging & Monitoring

```php
// Log all webhook attempts
Log::info('Webhook received', [
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'payload_keys' => array_keys($request->all()),
]);

// Log failures
Log::error('Webhook processing failed', [
    'error' => $e->getMessage(),
    'payload' => $request->all(),
]);
```

---

## 6. Route Configuration

```php
// routes/api.php
Route::prefix('webhooks/mpesa')->group(function () {
    Route::post('/payment', [PaymentWebhookController::class, 'handle'])
        ->name('webhooks.mpesa.payment')
        ->middleware(['webhook.security', 'throttle:100,1']);
});
```

---

## 7. Response Format

### Success Response
```json
{
    "ResultCode": 0,
    "ResultDesc": "Accepted"
}
```

### Error Response (for retry)
```json
{
    "ResultCode": 1,
    "ResultDesc": "Processing error"
}
```

**Note**: M-Pesa expects `ResultCode: 0` for success. Non-zero codes trigger retries.

---

## 8. Performance Targets

- **Response Time**: < 200ms (excluding network)
- **Database Queries**: < 5 queries per request
- **Memory Usage**: < 10MB per request
- **Throughput**: 100+ requests/second

---

## 9. Testing Considerations

### Unit Tests
- DTO normalization
- Validation rules
- Time parsing

### Integration Tests
- Full webhook flow
- Idempotency handling
- Error scenarios

### Load Tests
- High volume ingestion
- Concurrent requests
- Database performance

---

## Summary

**Key Components:**
1. ✅ **PaymentWebhookController** - Single endpoint, fast response
2. ✅ **PaymentWebhookRequest** - Comprehensive validation
3. ✅ **PaymentDto** - Normalized data format
4. ✅ **PaymentIngestionService** - Business logic, idempotency
5. ✅ **Security Middleware** - Authentication, rate limiting
6. ✅ **Async Processing** - SMS sent via queue

**Design Principles:**
- Fast response times
- Idempotent processing
- Comprehensive validation
- Full audit trail
- Secure by default
