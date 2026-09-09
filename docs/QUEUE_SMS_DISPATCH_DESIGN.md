# Queue-Based SMS Dispatch System Design

## Overview

A production-grade queue system for asynchronous SMS dispatch that ensures fast webhook responses, reliable delivery, and proper failure handling.

## Architecture Principles

1. **Fast Webhook Response**: Payment webhook returns immediately (< 200ms)
2. **Async Processing**: All SMS sending happens in background
3. **Idempotent Retries**: No duplicate SMS on retry
4. **Exponential Backoff**: Delayed retries with increasing intervals
5. **Dead-Letter Queue**: Handle permanently failed jobs

---

## 1. Queue Architecture

### Queue Structure

```
Payment Webhook
    ↓
Dispatch SendPaymentSmsJob (immediate)
    ↓
Queue Worker Processes Job
    ↓
Success? → Yes → Mark SMS Sent
    ↓ No
Retry with Backoff? → Yes → Re-queue with delay
    ↓ No
Move to Dead-Letter Queue
```

### Queue Configuration

**Primary Queue**: `sms` (dedicated SMS queue)
**Failed Queue**: `sms-failed` (dead-letter queue)
**Retry Queue**: `sms-retry` (for delayed retries)

---

## 2. Job Classes

### SendPaymentSmsJob

**Responsibilities:**
- Send SMS for a payment
- Handle retries with exponential backoff
- Prevent duplicate SMS
- Log all attempts

**Retry Strategy:**
- Max attempts: 3
- Backoff: 60s, 300s, 900s (1min, 5min, 15min)
- Idempotency: Check `sms_sent` flag before sending

### RetryFailedSmsJob

**Responsibilities:**
- Retry failed SMS from dead-letter queue
- Manual retry trigger
- Batch retry processing

---

## 3. Retry Strategy

### Exponential Backoff

**Formula:**
```
delay = base_delay * (backoff_multiplier ^ attempt_number)
```

**Example:**
- Attempt 1: 60 seconds
- Attempt 2: 300 seconds (5 minutes)
- Attempt 3: 900 seconds (15 minutes)

### Retry Conditions

**Retry:**
- Network errors
- 5xx server errors
- Rate limiting (429)
- Timeout errors

**No Retry:**
- 4xx client errors (except 429)
- Insufficient balance (402)
- Invalid phone number (400)
- Authentication errors (401 after refresh)

---

## 4. Idempotency

### Duplicate Prevention

**Mechanisms:**
1. **Database Flag**: `payments.sms_sent` boolean
2. **Atomic Update**: Use database transaction
3. **Job Deduplication**: Laravel's unique jobs
4. **Check Before Send**: Verify flag before API call

**Flow:**
```
Job Starts
    ↓
Check sms_sent flag → Yes → Skip (already sent)
    ↓ No
Acquire Lock (database transaction)
    ↓
Check flag again → Yes → Skip (race condition)
    ↓ No
Send SMS
    ↓
Mark sms_sent = true (atomic)
```

---

## 5. Dead-Letter Handling

### Failed Job Processing

**When Job Fails Permanently:**
1. Job moved to `failed_jobs` table
2. Payment record updated with error
3. Alert sent to admin (optional)
4. Manual retry available

### Dead-Letter Queue

**Purpose:**
- Store permanently failed jobs
- Manual review and retry
- Analysis of failure patterns

**Structure:**
- `failed_jobs` table (Laravel default)
- Custom `sms_failures` table for tracking

---

## 6. Queue Configuration

### Queue Drivers

**Development**: Database (simple, no setup)
**Production**: Redis (fast, scalable)

### Queue Workers

**Configuration:**
- Multiple workers for high throughput
- Supervisor for process management
- Separate queue for SMS

### Supervisor Configuration

```ini
[program:mpesa-sms-worker]
command=php /path/to/artisan queue:work redis --queue=sms --tries=3 --timeout=60
autostart=true
autorestart=true
numprocs=2
```

---

## 7. Monitoring & Metrics

### Key Metrics

- **Queue Depth**: Jobs waiting in queue
- **Processing Rate**: SMS per minute
- **Success Rate**: Successful vs failed
- **Retry Rate**: Percentage of retries
- **Average Processing Time**: Time per SMS

### Alerts

- High queue depth (> 1000 jobs)
- Low success rate (< 95%)
- High retry rate (> 10%)
- Worker down

---

## 8. Job Deduplication

### Laravel Unique Jobs

**Feature:**
- Prevent duplicate jobs in queue
- Based on job payload
- Automatic cleanup

**Usage:**
```php
SendPaymentSmsJob::dispatch($paymentId)
    ->unique()
    ->onQueue('sms');
```

---

## 9. Delayed Retries

### Implementation

**Strategy:**
- Use `delay()` method for retries
- Exponential backoff calculation
- Respect rate limits

**Example:**
```php
if ($attempt < $maxAttempts) {
    $delay = $this->calculateBackoff($attempt);
    $this->release($delay);
}
```

---

## 10. Failure Handling

### Failed Job Handler

**Responsibilities:**
- Log failure details
- Update payment record
- Send alerts
- Store in dead-letter queue

### Recovery Process

1. **Automatic**: Retry with backoff
2. **Manual**: Admin-triggered retry
3. **Batch**: Retry all failed jobs

---

## 11. Performance Considerations

### Throughput

- **Database Queue**: ~100 SMS/minute
- **Redis Queue**: ~1000+ SMS/minute
- **Multiple Workers**: Linear scaling

### Optimization

- **Batch Processing**: Send multiple SMS in one API call (if supported)
- **Connection Pooling**: Reuse HTTP connections
- **Queue Prioritization**: High-priority jobs first

---

## 12. Testing Strategy

### Unit Tests

- Job execution
- Retry logic
- Idempotency checks
- Error handling

### Integration Tests

- Full queue flow
- Worker processing
- Dead-letter handling

### Load Tests

- High volume processing
- Concurrent workers
- Queue depth handling
