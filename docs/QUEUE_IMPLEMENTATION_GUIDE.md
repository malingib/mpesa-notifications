# Queue-Based SMS Dispatch - Implementation Guide

## Overview

Complete implementation of a queue-based SMS dispatch system that ensures fast webhook responses, reliable delivery, and proper failure handling.

## Architecture

### Flow Diagram

```
Payment Webhook
    ↓ (immediate response)
SendPaymentSmsJob::dispatch($paymentId)
    ↓
Queue: 'sms'
    ↓
Queue Worker Processes
    ↓
Check Idempotency (sms_sent flag)
    ↓
Send SMS via TalksasaSmsService
    ↓
Success? → Yes → Mark sms_sent = true
    ↓ No
Retryable Error? → Yes → Re-queue with delay
    ↓ No
Move to failed_jobs (dead-letter)
```

## Job Classes

### 1. SendPaymentSmsJob

**Location**: `app/Jobs/SendPaymentSmsJob.php`

**Features:**
- ✅ Idempotent SMS sending (database transaction)
- ✅ Exponential backoff retries (60s, 300s, 900s)
- ✅ Rules evaluation before sending
- ✅ Comprehensive error handling
- ✅ Dead-letter handling

**Retry Strategy:**
- Max attempts: 3
- Backoff delays: [60, 300, 900] seconds
- Timeout: 24 hours max retry window

**Idempotency:**
```php
DB::transaction(function () {
    $payment->refresh(); // Get latest state
    if ($payment->sms_sent) {
        return; // Already sent
    }
    // Send SMS and mark as sent atomically
});
```

### 2. RetryFailedSmsJob

**Location**: `app/Jobs/RetryFailedSmsJob.php`

**Features:**
- Single payment retry
- Batch retry for failed payments
- Respects max retry count

## Queue Configuration

### Queue Setup

**Development (Database Queue):**
```env
QUEUE_CONNECTION=database
QUEUE_QUEUE=default
```

**Production (Redis Queue):**
```env
QUEUE_CONNECTION=redis
REDIS_QUEUE=sms
```

### Queue Names

- `sms` - Primary SMS queue
- `sms-retry` - Retry queue
- `default` - Other jobs

## Worker Configuration

### Supervisor Setup

Create `/etc/supervisor/conf.d/mpesa-sms-worker.conf`:

```ini
[program:mpesa-sms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=sms --tries=3 --timeout=60 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

### Start Workers

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mpesa-sms-worker:*
```

## Retry Strategy

### Exponential Backoff

**Delays:**
- Attempt 1: 60 seconds (1 minute)
- Attempt 2: 300 seconds (5 minutes)
- Attempt 3: 900 seconds (15 minutes)

**Implementation:**
```php
public array $backoff = [60, 300, 900];
```

### Retry Conditions

**Retries:**
- Network errors
- 5xx server errors
- Rate limiting (429)
- Timeout errors

**No Retry:**
- 4xx client errors (except 429)
- Insufficient balance
- Invalid phone number
- Authentication errors (after refresh)

## Idempotency Implementation

### Database Transaction

```php
DB::transaction(function () use ($payment) {
    // Reload to get latest state
    $payment->refresh();
    
    // Check flag
    if ($payment->sms_sent) {
        return; // Already sent
    }
    
    // Send SMS
    $smsService->sendSms(...);
    
    // Mark as sent (atomic)
    $payment->update(['sms_sent' => true]);
});
```

### Job Uniqueness

```php
public function __construct(int $paymentId)
{
    $this->uniqueId = 'sms_payment_' . $paymentId;
}
```

## Dead-Letter Handling

### Failed Jobs Table

Laravel automatically stores failed jobs in `failed_jobs` table:
- `uuid` - Unique job identifier
- `connection` - Queue connection
- `queue` - Queue name
- `payload` - Job data
- `exception` - Error details
- `failed_at` - Failure timestamp

### Manual Retry

```bash
# Retry specific failed job
php artisan queue:retry {uuid}

# Retry all failed jobs
php artisan queue:retry all

# Retry failed SMS
php artisan sms:retry --payment-id=123
```

### Batch Retry Command

```bash
# Retry up to 100 failed payments
php artisan sms:retry --limit=100

# Retry specific payment
php artisan sms:retry --payment-id=123
```

## Monitoring

### Queue Depth

```bash
# Check queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed
```

### Database Queries

```sql
-- Check queue depth
SELECT COUNT(*) FROM jobs WHERE queue = 'sms';

-- Check failed jobs
SELECT COUNT(*) FROM failed_jobs WHERE queue = 'sms';

-- Check pending SMS
SELECT COUNT(*) FROM payments 
WHERE sms_sent = FALSE 
  AND status = 'completed' 
  AND sms_retry_count < 3;
```

### Logs

Monitor worker logs:
```bash
tail -f storage/logs/worker.log
tail -f storage/logs/laravel.log | grep SMS
```

## Performance Tuning

### Worker Scaling

**Single Worker:**
- ~100 SMS/minute (database queue)
- ~1000 SMS/minute (Redis queue)

**Multiple Workers:**
- Linear scaling
- 2 workers = 2x throughput
- 4 workers = 4x throughput

### Queue Prioritization

```php
// High priority
SendPaymentSmsJob::dispatch($id)->onQueue('sms-high');

// Normal priority
SendPaymentSmsJob::dispatch($id)->onQueue('sms');
```

### Batch Processing

If Talksasa API supports batch sending:
```php
// Send multiple SMS in one API call
$smsService->sendBatch([
    ['phone' => '254...', 'message' => '...'],
    ['phone' => '254...', 'message' => '...'],
]);
```

## Testing

### Mock Queue

```php
Queue::fake();

SendPaymentSmsJob::dispatch($paymentId);

Queue::assertPushed(SendPaymentSmsJob::class, function ($job) use ($paymentId) {
    return $job->paymentId === $paymentId;
});
```

### Test Retry Logic

```php
// Mock SMS service to fail first 2 times, succeed on 3rd
$smsService = Mockery::mock(TalksasaSmsService::class);
$smsService->shouldReceive('sendSms')
    ->times(3)
    ->andReturn(false, false, true);

$job = new SendPaymentSmsJob($paymentId);
$job->handle($smsService, $templateService);
```

## Troubleshooting

### Jobs Not Processing

1. **Check workers running:**
   ```bash
   supervisorctl status
   ```

2. **Check queue connection:**
   ```bash
   php artisan queue:work --once
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/worker.log
   ```

### Duplicate SMS

**Causes:**
- Race condition in idempotency check
- Multiple workers processing same job
- Job retried after success

**Solution:**
- Use database transactions
- Check `sms_sent` flag before sending
- Use unique job IDs

### High Queue Depth

**Symptoms:**
- Jobs piling up
- Slow SMS delivery

**Solutions:**
- Add more workers
- Switch to Redis queue
- Optimize SMS service
- Check API rate limits

## Best Practices

1. **Always use transactions** for idempotency
2. **Check flags before sending** to prevent duplicates
3. **Log all attempts** for debugging
4. **Monitor queue depth** regularly
5. **Set up alerts** for failures
6. **Use Redis** in production
7. **Scale workers** based on load
8. **Test retry logic** thoroughly

## Summary

✅ **Fast Webhook Response** - Jobs dispatched immediately
✅ **Async Processing** - All SMS in background
✅ **Idempotent Retries** - No duplicate SMS
✅ **Exponential Backoff** - Smart retry delays
✅ **Dead-Letter Handling** - Failed jobs tracked
✅ **Comprehensive Logging** - Full audit trail
