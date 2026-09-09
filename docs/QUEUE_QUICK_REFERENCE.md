# Queue-Based SMS Dispatch - Quick Reference

## Commands

### Retry Failed SMS
```bash
# Retry specific payment
php artisan sms:retry --payment-id=123

# Batch retry (up to 100)
php artisan sms:retry --limit=100

# Force retry (ignore max attempts)
php artisan sms:retry --payment-id=123 --force
```

### Queue Management
```bash
# Process queue
php artisan queue:work --queue=sms

# Monitor queue
php artisan queue:monitor

# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {uuid}

# Clear queue
php artisan queue:clear sms
```

## Configuration

### Environment Variables
```env
# Development
QUEUE_CONNECTION=database

# Production
QUEUE_CONNECTION=redis
REDIS_QUEUE=sms
```

### Queue Names
- `sms` - Primary SMS queue
- `sms-retry` - Retry queue
- `default` - Other jobs

## Retry Strategy

**Backoff Delays:**
- Attempt 1: 60 seconds
- Attempt 2: 300 seconds (5 minutes)
- Attempt 3: 900 seconds (15 minutes)

**Max Attempts:** 3
**Max Retry Window:** 24 hours

## Idempotency

**Protection Layers:**
1. Job uniqueness (`uniqueId()`)
2. WithoutOverlapping middleware
3. Database transaction
4. `sms_sent` flag check

## Monitoring

### Queue Depth
```sql
SELECT COUNT(*) FROM jobs WHERE queue = 'sms';
```

### Failed Jobs
```sql
SELECT COUNT(*) FROM failed_jobs WHERE queue = 'sms';
```

### Pending SMS
```sql
SELECT COUNT(*) FROM payments 
WHERE sms_sent = FALSE 
  AND status = 'completed' 
  AND sms_retry_count < 3;
```

## Worker Setup

### Supervisor Config
```ini
[program:mpesa-sms-worker]
command=php artisan queue:work redis --queue=sms --tries=3 --timeout=60
numprocs=2
```

### Start Workers
```bash
sudo supervisorctl start mpesa-sms-worker:*
```

## Troubleshooting

### Jobs Not Processing
1. Check workers: `supervisorctl status`
2. Test queue: `php artisan queue:work --once`
3. Check logs: `tail -f storage/logs/worker.log`

### Duplicate SMS
- Use database transactions
- Check `sms_sent` flag
- Use job uniqueness

### High Queue Depth
- Add more workers
- Switch to Redis
- Check API rate limits
