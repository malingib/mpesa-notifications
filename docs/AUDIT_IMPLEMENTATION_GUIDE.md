# Audit & Logging System - Implementation Guide

## Overview

Complete implementation of an audit and logging system for financial transactions with immutability, traceability, and GDPR compliance.

## Database Tables

### 1. Payment History (`payment_history`)
- Immutable record of all payment changes
- Tracks field-level changes
- Includes correlation IDs for tracing

### 2. SMS Attempts (`sms_attempts`)
- Every SMS send attempt logged
- Success/failure tracking
- Provider response storage

### 3. Enhanced Audit Logs (`audit_logs`)
- Correlation IDs added
- Severity and category fields
- Session and request tracking

### 4. Data Anonymizations (`data_anonymizations`)
- GDPR anonymization tracking
- Reason and scope recording

## Services

### CorrelationIdService
**Purpose:** Generate and manage correlation IDs

**Usage:**
```php
$correlationId = app(CorrelationIdService::class)->generate('PAY');
// Returns: PAY-20240126143045-abc123def456
```

### PaymentAuditService
**Purpose:** Track all payment changes

**Usage:**
```php
// Record payment creation
$auditService->recordCreation($payment, $data, $correlationId);

// Record field update
$auditService->recordUpdate($payment, 'status', 'pending', 'completed');

// Record SMS sent
$auditService->recordSmsSent($payment);
```

### SmsAttemptService
**Purpose:** Track every SMS attempt

**Usage:**
```php
// Record attempt start
$attempt = $smsAttemptService->recordAttempt($payment, $phone, $message, $jobId, 1);

// Mark as sent
$smsAttemptService->markSent($attempt, $providerResponse);

// Mark as failed
$smsAttemptService->markFailed($attempt, $error, $errorCode);
```

### AuditLogService
**Purpose:** Structured audit logging

**Usage:**
```php
// Log payment event
$auditLogService->logPayment('payment.received', $paymentId, 'Payment received', $metadata);

// Log SMS event
$auditLogService->logSms('sms.sent', $paymentId, 'SMS sent successfully');

// Log system event
$auditLogService->logSystem('system.maintenance', 'System maintenance started');
```

### DataAnonymizationService
**Purpose:** GDPR-safe anonymization

**Usage:**
```php
// Anonymize payment
$anonymizationService->anonymizePayment($payment, 'gdpr_request', 'User requested deletion');
```

### RetentionPolicyService
**Purpose:** Data retention and archival

**Usage:**
```php
// Run all policies
$retentionService->runAllPolicies();

// Archive old payments
$retentionService->archivePayments(7); // 7 years
```

## Integration Points

### Payment Ingestion

**Update `PaymentIngestionService`:**
```php
public function ingest(PaymentDto $dto): Payment
{
    $correlationId = $this->correlationIdService->generate();
    
    $payment = Payment::create([
        'correlation_id' => $correlationId,
        // ... other fields
    ]);
    
    // Record creation in audit
    $this->paymentAuditService->recordCreation($payment, $dto->toArray(), $correlationId);
    
    // Log audit event
    $this->auditLogService->logPayment('payment.received', $payment->id, 'Payment received', [], 'info', $correlationId);
    
    return $payment;
}
```

### SMS Sending

**Update `SendPaymentSmsJob`:**
```php
public function handle(...): void
{
    // Record attempt start
    $attempt = $this->smsAttemptService->recordAttempt(
        $payment,
        $payment->phone_number,
        $message,
        $this->job->getJobId(),
        $this->attempts()
    );
    
    try {
        $success = $smsService->sendSms(...);
        
        if ($success) {
            // Mark attempt as sent
            $this->smsAttemptService->markSent($attempt, $response);
            
            // Record in payment history
            $this->paymentAuditService->recordSmsSent($payment);
        }
    } catch (\Exception $e) {
        // Mark attempt as failed
        $this->smsAttemptService->markFailed($attempt, $e->getMessage(), $e->getCode());
        
        // Record failure
        $this->paymentAuditService->recordSmsFailure($payment, $e->getMessage());
    }
}
```

## End-to-End Tracing

### Query Complete Payment Journey

```php
// Get payment with all events
$correlationId = $payment->correlation_id;

// Payment history
$history = PaymentHistory::where('correlation_id', $correlationId)->get();

// SMS attempts
$smsAttempts = SmsAttempt::where('correlation_id', $correlationId)->get();

// Audit logs
$auditLogs = AuditLog::where('correlation_id', $correlationId)->get();

// Combine and sort by timestamp
$allEvents = collect()
    ->merge($history->map(fn($h) => ['type' => 'history', 'data' => $h]))
    ->merge($smsAttempts->map(fn($s) => ['type' => 'sms', 'data' => $s]))
    ->merge($auditLogs->map(fn($a) => ['type' => 'audit', 'data' => $a]))
    ->sortBy('data.created_at');
```

## GDPR Compliance

### Anonymization Process

1. **Identify PII Fields:**
   - `phone_number`
   - `payer_name`
   - `metadata` (PII fields)

2. **Anonymize:**
   ```php
   $anonymizationService->anonymizePayment($payment, 'gdpr_request');
   ```

3. **Track:**
   - Record in `data_anonymizations` table
   - Log anonymization operation

### Retention Policies

**Run via Command:**
```bash
# Run all policies
php artisan retention:run

# Archive only
php artisan retention:run --archive

# Anonymize only
php artisan retention:run --anonymize

# Delete only
php artisan retention:run --delete
```

**Schedule (in `app/Console/Kernel.php`):**
```php
$schedule->command('retention:run')
    ->monthly()
    ->withoutOverlapping();
```

## Query Patterns

### Get Payment Timeline

```sql
SELECT 
    'payment_history' as source,
    created_at,
    action,
    field_name,
    old_value,
    new_value
FROM payment_history
WHERE payment_id = ?
ORDER BY created_at;
```

### Get SMS Attempt History

```sql
SELECT 
    attempt_number,
    status,
    error_message,
    sent_at,
    created_at
FROM sms_attempts
WHERE payment_id = ?
ORDER BY attempt_number, created_at;
```

### Get Complete Audit Trail

```sql
-- Payment history
SELECT 'history' as type, created_at, action, description
FROM payment_history
WHERE correlation_id = ?

UNION ALL

-- SMS attempts
SELECT 'sms' as type, created_at, status as action, error_message as description
FROM sms_attempts
WHERE correlation_id = ?

UNION ALL

-- Audit logs
SELECT 'audit' as type, created_at, action, description
FROM audit_logs
WHERE correlation_id = ?

ORDER BY created_at;
```

## Monitoring

### Key Metrics

- **Payment Changes:** Count by action type
- **SMS Success Rate:** Successful vs failed attempts
- **Anonymization Count:** GDPR requests processed
- **Retention Compliance:** Data archived/deleted

### Alerts

- High failure rate
- Anonymization requests pending
- Retention policy failures
- Audit log errors

## Best Practices

1. ✅ **Always generate correlation ID** for new payments
2. ✅ **Record all changes** in payment history
3. ✅ **Track every SMS attempt** separately
4. ✅ **Log all significant events** in audit logs
5. ✅ **Anonymize before deletion** for GDPR
6. ✅ **Run retention policies** regularly
7. ✅ **Monitor audit logs** for anomalies

## Summary

✅ **Immutable Records** - Payment history tracks all changes
✅ **SMS History** - Every attempt logged separately
✅ **End-to-End Tracing** - Correlation IDs across all events
✅ **Complete Audit Trail** - Who did what and when
✅ **GDPR Compliance** - Safe soft deletion with anonymization
✅ **Retention Policies** - Automatic archival and deletion
