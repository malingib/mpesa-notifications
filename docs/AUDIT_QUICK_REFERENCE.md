# Audit & Logging System - Quick Reference

## Commands

### Retention Policies
```bash
# Run all retention policies
php artisan retention:run

# Archive only
php artisan retention:run --archive

# Anonymize only
php artisan retention:run --anonymize

# Delete only
php artisan retention:run --delete
```

## Services

### Generate Correlation ID
```php
$correlationId = app(CorrelationIdService::class)->generate('PAY');
```

### Record Payment Creation
```php
$auditService->recordCreation($payment, $data, $correlationId);
```

### Record Payment Update
```php
$auditService->recordUpdate($payment, 'status', 'pending', 'completed');
```

### Record SMS Attempt
```php
$attempt = $smsAttemptService->recordAttempt($payment, $phone, $message, $jobId, 1);
$smsAttemptService->markSent($attempt, $response);
```

### Log Audit Event
```php
$auditLogService->logPayment('payment.received', $paymentId, 'Payment received');
$auditLogService->logSms('sms.sent', $paymentId, 'SMS sent');
```

### Anonymize Payment
```php
$anonymizationService->anonymizePayment($payment, 'gdpr_request');
```

## Query Patterns

### Get Payment History
```php
$history = PaymentHistory::where('payment_id', $paymentId)
    ->orderBy('created_at', 'asc')
    ->get();
```

### Get SMS Attempts
```php
$attempts = SmsAttempt::where('payment_id', $paymentId)
    ->orderBy('attempt_number', 'asc')
    ->get();
```

### Get Complete Audit Trail
```php
$correlationId = $payment->correlation_id;

$history = PaymentHistory::where('correlation_id', $correlationId)->get();
$smsAttempts = SmsAttempt::where('correlation_id', $correlationId)->get();
$auditLogs = AuditLog::where('correlation_id', $correlationId)->get();
```

## Retention Policies

### Payment Records
- **Active:** 7 years
- **Archived:** 10 years
- **Deleted:** After 10 years

### SMS Attempts
- **Active:** 2 years
- **Archived:** 5 years
- **Deleted:** After 5 years

### Audit Logs
- **Active:** 1 year
- **Archived:** 3 years
- **Deleted:** After 3 years

## GDPR Anonymization

### Fields Anonymized
- `phone_number` → `[REDACTED]`
- `payer_name` → `[REDACTED]`
- `metadata` → PII fields removed

### Process
1. Anonymize PII fields
2. Record in `data_anonymizations` table
3. Log anonymization operation
4. Delete after retention period

## Correlation IDs

### Format
```
PAY-20240126143045-abc123def456
```

### Usage
- Payment webhook receipt
- Payment creation
- SMS job dispatch
- SMS send attempts
- All audit logs

### Query by Correlation ID
```sql
SELECT * FROM payment_history WHERE correlation_id = ?;
SELECT * FROM sms_attempts WHERE correlation_id = ?;
SELECT * FROM audit_logs WHERE correlation_id = ?;
```

## Tables

1. **payment_history** - Immutable payment changes
2. **sms_attempts** - Every SMS attempt
3. **audit_logs** - System audit trail
4. **data_anonymizations** - GDPR anonymization tracking
