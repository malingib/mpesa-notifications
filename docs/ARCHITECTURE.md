# Architecture Documentation

## System Overview

The Talksasa Payment Notifications System is a production-grade, multi-tenant Laravel application that processes M-Pesa payment notifications and sends SMS confirmations via the Talksasa Bulk SMS API.

## Core Components

### 1. Multi-Tenancy Layer

**Design**: Shared database, shared schema with application-level isolation

**Key Models**:
- `Tenant`: Represents a client organization
- `PaymentAccount`: Maps Paybill/Till numbers to tenants
- `Payment`: All payments are scoped to tenants

**Isolation Strategy**:
- All queries filter by `tenant_id`
- Middleware enforces tenant context
- Unique constraints prevent cross-tenant data leakage

### 2. Payment Processing Service

**Location**: `App\Services\PaymentProcessingService`

**Responsibilities**:
- Receive and validate M-Pesa webhook payloads
- Perform idempotency checks
- Resolve payment accounts
- Create payment records
- Dispatch SMS jobs

**Idempotency Strategy**:
1. Check `transaction_id` (primary uniqueness)
2. Check `request_id` (duplicate detection)
3. Check `receipt_number` (backup uniqueness)

### 3. SMS Service

**Location**: `App\Services\TalksasaSmsService`

**Responsibilities**:
- Integrate with Talksasa Bulk SMS API
- Build SMS messages from templates
- Handle API errors and retries

**Template System**:
- Per-account custom templates
- Placeholder replacement (`{amount}`, `{receipt}`, etc.)
- Fallback to default template

### 4. Queue System

**Job**: `App\Jobs\SendPaymentSmsJob`

**Features**:
- Async processing for high volume
- Automatic retry (3 attempts)
- Exponential backoff
- Failure tracking

**Queue Configuration**:
- Database driver (default)
- Can migrate to Redis/SQS for scale

### 5. Webhook Handling

**Controller**: `App\Http\Controllers\MpesaWebhookController`

**Endpoints**:
- `/api/webhooks/mpesa/confirmation`: Receives payment confirmations
- `/api/webhooks/mpesa/validation`: Validates payment requests

**Security**:
- Webhook secret validation (optional)
- Request logging
- Error handling with appropriate HTTP codes

## Data Flow

### Payment Notification Flow

```
M-Pesa → Webhook Endpoint → PaymentProcessingService
    ↓
Idempotency Check (TransactionID)
    ↓
Account Resolution (Paybill/Till → Tenant)
    ↓
Payment Record Creation (DB Transaction)
    ↓
SMS Job Dispatch (Queue)
    ↓
Queue Worker → TalksasaSmsService
    ↓
SMS Sent → Payment Updated
```

### Idempotency Flow

```
Incoming Payment
    ↓
Check: transaction_id exists?
    ├─ Yes → Return (already processed)
    └─ No → Continue
    ↓
Check: request_id exists?
    ├─ Yes → Return (duplicate)
    └─ No → Continue
    ↓
Process Payment
```

## Database Design

### Indexes for Performance

**Payments Table**:
- `transaction_id` (UNIQUE) - Primary idempotency
- `receipt_number` (UNIQUE) - Backup idempotency
- `request_id` (INDEX) - Duplicate detection
- `tenant_id + status` (COMPOSITE) - Tenant queries
- `payment_account_id + transaction_time` (COMPOSITE) - Account history
- `sms_sent + status` (COMPOSITE) - SMS retry queries

### Soft Deletes

All main tables use soft deletes for:
- Audit trail
- Data recovery
- Compliance

## Error Handling

### Payment Processing Errors

- **Invalid Payload**: Log and return 200 (prevent M-Pesa retries)
- **Account Not Found**: Log and return 200 (prevent retries)
- **Database Errors**: Return 500 (trigger M-Pesa retry)

### SMS Sending Errors

- **API Failure**: Retry up to 3 times
- **Permanent Failure**: Log error, mark payment with error
- **Network Timeout**: Exponential backoff retry

## Scalability Considerations

### Current Design

- **Database Queues**: Suitable for < 10K transactions/day
- **Single Queue Worker**: Can handle moderate load

### Production Scaling

1. **Redis Queues**: Migrate for higher throughput
2. **Multiple Workers**: Scale horizontally
3. **Queue Prioritization**: Separate queues for SMS
4. **Database Sharding**: If tenant count exceeds 10K
5. **Caching**: Cache tenant/account lookups

## Security Measures

1. **Tenant Isolation**: Enforced at application layer
2. **Webhook Security**: Secret key validation
3. **Input Validation**: All webhook payloads validated
4. **SQL Injection**: Protected by Eloquent ORM
5. **Rate Limiting**: API throttling (60 req/min)

## Monitoring Points

1. **Payment Processing Rate**: Monitor webhook endpoint
2. **SMS Success Rate**: Track `sms_sent` vs total payments
3. **Queue Depth**: Monitor job queue length
4. **Error Rate**: Track failed payments/SMS
5. **Response Times**: Webhook and API latency

## Future Enhancements

1. **Webhook Signature Verification**: Verify M-Pesa signatures
2. **Payment Reconciliation**: Daily reconciliation jobs
3. **SMS Template Management**: Admin UI for templates
4. **Analytics Dashboard**: Payment analytics per tenant
5. **Webhook Retry Queue**: Handle M-Pesa retries better
6. **Multi-Channel Notifications**: Email, Push notifications
