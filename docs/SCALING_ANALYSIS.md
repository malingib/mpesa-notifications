# Scaling Analysis: 1M Payments/Day, 10K Tenants

## Load Profile

### Traffic Metrics
- **Daily**: 1,000,000 payments
- **Hourly Average**: ~41,667 payments/hour
- **Peak Hour (10x)**: ~416,670 payments/hour
- **Per Second (Peak)**: ~116 payments/second
- **Tenants**: 10,000
- **Average per Tenant**: 100 payments/day

### Peak Load Scenarios
- **Business Hours**: 5-10x average load
- **End of Month**: 2-3x average load
- **Flash Sales**: 20-50x average load

---

## Performance Bottlenecks

### 1. Database Queries (Critical)

**Current Issues:**
- 3 separate idempotency checks (transaction_id, receipt_number, request_id)
- Merchant lookup on every payment (no cache)
- Database transaction for every payment
- Global scopes add query overhead
- No connection pooling
- No read replicas

**Impact:**
- ~500ms per payment (3 queries + transaction)
- At 116 payments/sec = 58 seconds of DB time per second
- Database becomes bottleneck

**Solution:**
- Single idempotency check with composite index
- Redis cache for merchant lookups
- Batch inserts for high-volume periods
- Remove global scopes, use explicit queries
- Connection pooling (PgBouncer/ProxySQL)
- Read replicas for reporting

### 2. Queue Processing (Critical)

**Current Issues:**
- Database queue (slow, ~100 jobs/sec)
- Single queue worker
- No queue prioritization
- No batch processing

**Impact:**
- Queue depth grows during peak
- SMS delivery delayed
- Failed jobs pile up

**Solution:**
- Redis queue (1000+ jobs/sec)
- Multiple workers (10-20 workers)
- Priority queues (high-priority SMS)
- Batch job processing

### 3. Webhook Response Time (High)

**Current Issues:**
- 3 database queries before insert
- Merchant lookup (uncached)
- Synchronous audit logging
- Transaction overhead

**Impact:**
- Response time: 200-500ms
- M-Pesa timeout risk (5 seconds)
- Retries increase load

**Solution:**
- Single idempotency check
- Cached merchant lookup
- Async audit logging
- Optimize transaction scope

### 4. Database Write Contention (High)

**Current Issues:**
- All writes to primary database
- No partitioning
- Index contention on inserts
- Foreign key checks

**Impact:**
- Lock contention
- Slow inserts during peak
- Deadlocks possible

**Solution:**
- Database partitioning (by date/tenant)
- Remove unnecessary foreign keys
- Optimize indexes
- Write batching

### 5. Audit Logging (Medium)

**Current Issues:**
- Synchronous audit writes
- Payment history on every change
- SMS attempt logging synchronous

**Impact:**
- Adds 50-100ms per payment
- Database write amplification

**Solution:**
- Async audit logging (queue)
- Batch audit writes
- Separate audit database

### 6. Caching (Medium)

**Current Issues:**
- No merchant caching
- No template caching
- No user settings cache

**Impact:**
- Repeated database queries
- Slow SMS template resolution

**Solution:**
- Redis cache for merchants
- Template caching
- User settings cache
- Cache warming strategy

---

## Database Optimizations

### 1. Indexing Strategy

**Current:**
```sql
-- Separate indexes
INDEX transaction_id
INDEX receipt_number
INDEX request_id
```

**Optimized:**
```sql
-- Composite index for idempotency
UNIQUE INDEX idx_payments_idempotency (transaction_id, receipt_number, request_id)

-- Partitioning index
INDEX idx_payments_partition (user_id, created_at)

-- Covering index for common queries
INDEX idx_payments_tenant_status (user_id, status, created_at) INCLUDE (amount, transaction_id)
```

### 2. Partitioning

**Strategy:** Partition by date (monthly)

```sql
-- Partition payments table by month
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at))
```

**Benefits:**
- Faster queries (smaller partitions)
- Easier archival
- Better index performance

### 3. Read Replicas

**Strategy:**
- Primary: All writes
- Replica 1: Reporting queries
- Replica 2: Analytics queries

**Implementation:**
- Laravel database connections
- Read/write splitting

### 4. Connection Pooling

**Strategy:**
- PgBouncer (PostgreSQL) or ProxySQL (MySQL)
- Pool size: 100-200 connections
- Transaction pooling mode

### 5. Query Optimization

**Idempotency Check:**
```sql
-- Single query instead of 3
SELECT id FROM payments 
WHERE transaction_id = ? 
   OR receipt_number = ? 
   OR request_id = ?
LIMIT 1;
```

**Merchant Lookup:**
```sql
-- Cached, but optimized query
SELECT id, user_id, sms_enabled, sms_template_id
FROM merchants
WHERE account_type = ? AND account_number = ? AND is_active = 1
LIMIT 1;
```

---

## Queue Scaling Strategy

### 1. Queue Architecture

**Current:**
- Single `sms` queue
- Database driver
- 1 worker

**Optimized:**
```
sms-high (priority)     → Critical SMS
sms-normal (default)   → Regular SMS
sms-retry              → Failed SMS retries
audit                  → Audit logging
```

### 2. Worker Scaling

**Configuration:**
- **SMS Workers**: 10-20 workers
- **Audit Workers**: 2-4 workers
- **Retry Workers**: 2-4 workers

**Supervisor Config:**
```ini
[program:mpesa-sms-worker]
numprocs=20
command=php artisan queue:work redis --queue=sms-high,sms-normal --tries=3 --timeout=60
```

### 3. Batch Processing

**Strategy:**
- Batch SMS sends (if API supports)
- Batch audit writes
- Batch payment history

### 4. Queue Monitoring

**Metrics:**
- Queue depth
- Processing rate
- Failure rate
- Worker utilization

---

## Caching Opportunities

### 1. Merchant Cache

**Strategy:**
- Cache key: `merchant:{account_type}:{account_number}`
- TTL: 1 hour
- Invalidation: On merchant update

**Impact:**
- Reduces DB queries by 99%
- Response time: 500ms → 50ms

### 2. Template Cache

**Strategy:**
- Cache key: `template:{user_id}:{merchant_id}`
- TTL: 24 hours
- Invalidation: On template update

### 3. User Settings Cache

**Strategy:**
- Cache key: `user:{user_id}:settings`
- TTL: 1 hour
- Invalidation: On settings update

### 4. Cache Warming

**Strategy:**
- Pre-load active merchants
- Pre-load templates
- Background refresh

---

## Failure Scenarios & Mitigations

### 1. Database Failure

**Scenario:** Primary database down

**Mitigation:**
- Failover to replica (automatic)
- Queue jobs for retry
- Circuit breaker pattern

### 2. Redis Failure

**Scenario:** Redis cache/queue down

**Mitigation:**
- Fallback to database queue
- Disable caching (degraded performance)
- Alert and manual intervention

### 3. Queue Backlog

**Scenario:** Queue depth > 100,000

**Mitigation:**
- Auto-scale workers
- Priority queue processing
- Alert and manual scaling

### 4. SMS API Failure

**Scenario:** Talksasa API down

**Mitigation:**
- Retry with exponential backoff
- Dead-letter queue
- Alert and manual retry

### 5. High Duplicate Rate

**Scenario:** M-Pesa sends duplicates

**Mitigation:**
- Optimized idempotency check
- Cache recent transaction IDs
- Rate limiting

### 6. Peak Load

**Scenario:** 10x normal load

**Mitigation:**
- Auto-scaling workers
- Queue prioritization
- Rate limiting
- Degraded mode (skip non-critical features)

---

## Architecture Refactoring Plan

### Phase 1: Critical Optimizations
1. ✅ Single idempotency check
2. ✅ Merchant caching
3. ✅ Redis queue migration
4. ✅ Worker scaling

### Phase 2: Database Optimizations
1. ✅ Composite indexes
2. ✅ Partitioning
3. ✅ Read replicas
4. ✅ Connection pooling

### Phase 3: Advanced Features
1. ✅ Async audit logging
2. ✅ Batch processing
3. ✅ Monitoring and alerts
4. ✅ Auto-scaling

---

## Performance Targets

### Webhook Response
- **Current**: 200-500ms
- **Target**: < 100ms (p95)
- **Peak**: < 200ms (p99)

### Queue Processing
- **Current**: 100 jobs/sec
- **Target**: 1000+ jobs/sec
- **Latency**: < 5 seconds (p95)

### Database Queries
- **Current**: 3 queries per payment
- **Target**: 1 query per payment
- **Response**: < 10ms (p95)

### SMS Delivery
- **Current**: 30-60 seconds
- **Target**: < 10 seconds (p95)
- **Reliability**: 99.9%

---

## Monitoring & Alerts

### Key Metrics
- Webhook response time
- Queue depth
- Database query time
- SMS success rate
- Error rate
- Worker utilization

### Alert Thresholds
- Queue depth > 10,000
- Response time > 500ms (p95)
- Error rate > 1%
- Database connections > 80%
- Worker utilization > 90%
