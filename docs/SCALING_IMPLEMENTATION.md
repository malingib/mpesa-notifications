# Scaling Implementation Guide

## Overview

This guide implements optimizations to handle 1M payments/day across 10K tenants.

## Key Optimizations

### 1. Optimized Payment Ingestion

**File**: `app/Services/OptimizedPaymentIngestionService.php`

**Changes:**
- Single idempotency check (composite query)
- Cached merchant lookups (Redis, 1 hour TTL)
- Optimized database transactions
- Async audit logging
- Batch processing support

**Usage:**
```php
// Replace PaymentIngestionService with OptimizedPaymentIngestionService
$service = app(OptimizedPaymentIngestionService::class);
$payment = $service->ingest($dto);
```

### 2. Database Index Optimization

**Migration**: `2024_01_01_000018_optimize_payments_indexes.php`

**New Indexes:**
- `idx_payments_idempotency` - Composite index for fast idempotency checks
- `idx_payments_tenant_status_time` - Tenant-scoped queries
- `idx_payments_sms_retry` - SMS retry queries
- `idx_merchants_account_lookup` - Fast merchant lookups

**Run Migration:**
```bash
php artisan migrate
```

### 3. Optimized SMS Job

**File**: `app/Jobs/OptimizedSendPaymentSmsJob.php`

**Changes:**
- Cached idempotency checks
- Selective field loading
- Async audit logging
- Optimized transaction scope

**Usage:**
```php
// Replace SendPaymentSmsJob with OptimizedSendPaymentSmsJob
OptimizedSendPaymentSmsJob::dispatch($paymentId);
```

### 4. Caching Configuration

**File**: `config/cache.php`

**Setup:**
- Redis as default cache driver
- Connection pooling
- TTL configuration

**Environment:**
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
```

## Deployment Steps

### Phase 1: Database Optimization

1. **Run Index Migration:**
   ```bash
   php artisan migrate
   ```

2. **Verify Indexes:**
   ```sql
   SHOW INDEXES FROM payments;
   SHOW INDEXES FROM merchants;
   ```

### Phase 2: Caching Setup

1. **Install Redis:**
   ```bash
   # Ubuntu/Debian
   sudo apt-get install redis-server
   
   # Start Redis
   sudo systemctl start redis
   ```

2. **Configure Laravel:**
   ```env
   CACHE_DRIVER=redis
   QUEUE_CONNECTION=redis
   ```

3. **Test Cache:**
   ```bash
   php artisan tinker
   Cache::put('test', 'value', 60);
   Cache::get('test');
   ```

### Phase 3: Queue Migration

1. **Install Redis Queue:**
   ```bash
   composer require predis/predis
   ```

2. **Update Config:**
   ```env
   QUEUE_CONNECTION=redis
   ```

3. **Test Queue:**
   ```bash
   php artisan queue:work redis --once
   ```

### Phase 4: Service Migration

1. **Update Service Provider:**
   ```php
   // app/Providers/AppServiceProvider.php
   $this->app->singleton(\App\Services\OptimizedPaymentIngestionService::class);
   ```

2. **Update Controller:**
   ```php
   // app/Http/Controllers/PaymentWebhookController.php
   public function __construct(
       private OptimizedPaymentIngestionService $ingestionService
   ) {}
   ```

3. **Update Job Dispatch:**
   ```php
   // Use OptimizedSendPaymentSmsJob
   OptimizedSendPaymentSmsJob::dispatch($paymentId);
   ```

### Phase 5: Worker Scaling

1. **Update Supervisor Config:**
   ```ini
   [program:mpesa-sms-worker]
   numprocs=20
   command=php artisan queue:work redis --queue=sms-high,sms-normal --tries=3 --timeout=60
   ```

2. **Start Workers:**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start mpesa-sms-worker:*
   ```

## Performance Monitoring

### Key Metrics

1. **Webhook Response Time:**
   ```bash
   # Monitor logs
   tail -f storage/logs/laravel.log | grep "Payment ingested"
   ```

2. **Queue Depth:**
   ```bash
   php artisan queue:monitor
   ```

3. **Database Queries:**
   ```sql
   -- Slow queries
   SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
   ```

4. **Cache Hit Rate:**
   ```bash
   redis-cli INFO stats | grep keyspace
   ```

### Alerts

Set up alerts for:
- Queue depth > 10,000
- Response time > 500ms (p95)
- Error rate > 1%
- Database connections > 80%

## Load Testing

### Test Scenarios

1. **Normal Load:**
   - 1,000 payments/minute
   - Verify response time < 100ms

2. **Peak Load:**
   - 10,000 payments/minute
   - Verify response time < 200ms
   - Monitor queue depth

3. **Sustained Load:**
   - 1M payments/day
   - Monitor for 24 hours
   - Check for memory leaks

### Tools

- **Apache Bench:**
   ```bash
   ab -n 10000 -c 100 http://localhost/api/webhooks/payments
   ```

- **Artillery:**
   ```yaml
   config:
     target: 'http://localhost'
     phases:
       - duration: 60
         arrivalRate: 100
   ```

## Rollback Plan

If issues occur:

1. **Revert Service:**
   ```php
   // Use original PaymentIngestionService
   ```

2. **Revert Queue:**
   ```env
   QUEUE_CONNECTION=database
   ```

3. **Disable Caching:**
   ```env
   CACHE_DRIVER=array
   ```

4. **Scale Down Workers:**
   ```bash
   sudo supervisorctl stop mpesa-sms-worker:*
   ```

## Expected Performance

### Before Optimization
- Webhook Response: 200-500ms
- Queue Processing: 100 jobs/sec
- Database Queries: 3 per payment
- Cache Hit Rate: 0%

### After Optimization
- Webhook Response: < 100ms (p95)
- Queue Processing: 1000+ jobs/sec
- Database Queries: 1 per payment
- Cache Hit Rate: 95%+

## Troubleshooting

### High Response Time

1. Check cache hit rate
2. Verify database indexes
3. Monitor queue depth
4. Check worker utilization

### Queue Backlog

1. Scale up workers
2. Check Redis performance
3. Verify job processing rate
4. Monitor failed jobs

### Database Slowdown

1. Check index usage
2. Monitor slow queries
3. Verify connection pooling
4. Check for lock contention
