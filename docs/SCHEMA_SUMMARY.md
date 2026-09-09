# Database Schema Summary

## Quick Reference

### Tables Overview

| Table | Purpose | Key Fields | Relationships |
|-------|---------|------------|---------------|
| `users` | Tenants/Clients | `id`, `code`, `email` | 1→many merchants, payments, templates |
| `merchants` | Paybill/Till accounts | `id`, `account_type`, `account_number` | many→1 user, 1→many payments |
| `payments` | Payment transactions | `id`, `transaction_id`, `receipt_number` | many→1 user, many→1 merchant, 1→1 SMS |
| `sms_templates` | SMS message templates | `id`, `user_id`, `message` | many→1 user (nullable = global) |
| `sms_notifications` | SMS delivery records | `id`, `payment_id` (unique) | 1→1 payment, many→1 template |
| `api_tokens` | API authentication | `id`, `token` (hashed) | many→1 user |
| `audit_logs` | System audit trail | `id`, `action`, `entity_type` | many→1 user (nullable) |

## Tenant Isolation

**Enforcement Strategy:**
1. **Database Level**: All tenant-scoped tables have `user_id` foreign key
2. **Application Level**: All queries MUST include `WHERE user_id = ?`
3. **Middleware**: TenantMiddleware sets `user_id` from authenticated token/session

**Example Query Pattern:**
```php
// ✅ CORRECT - Tenant isolated
Payment::where('user_id', $currentUser->id)->get();

// ❌ WRONG - No tenant filter
Payment::all();
```

## Idempotency Strategy

**Three-Layer Protection:**

1. **Primary**: `payments.transaction_id` (UNIQUE) - M-Pesa TransactionID
2. **Backup**: `payments.receipt_number` (UNIQUE) - M-Pesa ReceiptNumber  
3. **Detection**: `payments.request_id` (INDEXED) - M-Pesa RequestID

**Processing Flow:**
```
Incoming Payment
    ↓
Check transaction_id exists? → Yes → Skip (already processed)
    ↓ No
Check receipt_number exists? → Yes → Skip (duplicate)
    ↓ No
Check request_id exists? → Yes → Skip (duplicate request)
    ↓ No
Process Payment
```

## Indexing Strategy

### Critical Indexes

**Tenant Isolation:**
- `idx_payments_user_status` - Fast tenant-scoped queries
- `idx_merchants_user` - Tenant merchant lookup
- `idx_sms_user_status` - Tenant SMS queries

**Idempotency:**
- `payments.transaction_id` (UNIQUE) - Primary check
- `payments.receipt_number` (UNIQUE) - Backup check
- `idx_payments_request_id` - Duplicate detection

**Performance:**
- `idx_payments_merchant_time` - Merchant payment history
- `idx_payments_sms_status` - SMS retry queries
- `idx_merchants_account_lookup` - Fast account resolution

## Relationships Diagram

```
┌─────────┐
│  users  │
└────┬────┘
     │
     ├───< (1:many) ───┐
     │                  │
     │                  ▼
     │            ┌──────────────┐
     │            │  merchants   │
     │            └──────┬───────┘
     │                   │
     │                   ├───< (1:many) ───┐
     │                   │                  │
     │                   │                  ▼
     │                   │            ┌──────────┐
     │                   │            │ payments │
     │                   │            └────┬─────┘
     │                   │                 │
     │                   │                 ├───> (1:1) ───┐
     │                   │                 │             │
     │                   │                 │             ▼
     │                   │                 │      ┌──────────────┐
     │                   │                 │      │sms_notifications│
     │                   │                 │      └──────────────┘
     │                   │                 │
     │                   │                 └───> (many:1) ───┐
     │                   │                                 │
     │                   └───> (many:1) ───────────────────┘
     │
     ├───< (1:many) ───┐
     │                  │
     │                  ▼
     │            ┌──────────────┐
     │            │sms_templates │
     │            └──────────────┘
     │
     ├───< (1:many) ───┐
     │                  │
     │                  ▼
     │            ┌──────────────┐
     │            │  api_tokens  │
     │            └──────────────┘
     │
     └───< (1:many) ───┐
                        │
                        ▼
                  ┌──────────────┐
                  │  audit_logs  │
                  └──────────────┘
```

## Migration Order

1. `users` - Base tenant table
2. `sms_templates` - Referenced by merchants
3. `merchants` - References users and templates
4. `payments` - References users and merchants
5. `sms_notifications` - References payments, users, merchants
6. `api_tokens` - References users
7. `audit_logs` - References users
8. Add foreign key: `payments.sms_notification_id` → `sms_notifications.id`

## Key Constraints

### Unique Constraints
- `users.code` - Unique tenant identifier
- `users.email` - Unique login email
- `merchants(account_type, account_number)` - Unique payment accounts
- `payments.transaction_id` - Primary idempotency
- `payments.receipt_number` - Backup idempotency
- `api_tokens.token` - Unique API tokens
- `sms_notifications.payment_id` - One SMS per payment

### Foreign Key Constraints
- All `user_id` fields → `users.id` (CASCADE delete)
- `merchants.user_id` → `users.id`
- `payments.user_id` → `users.id`
- `payments.merchant_id` → `merchants.id`
- `sms_notifications.payment_id` → `payments.id` (UNIQUE)
- `sms_notifications.user_id` → `users.id`
- `sms_notifications.merchant_id` → `merchants.id`
- `payments.sms_notification_id` → `sms_notifications.id` (SET NULL)

## Data Integrity Rules

1. **One Payment = One SMS**: Enforced by `sms_notifications.payment_id` UNIQUE constraint
2. **Account Uniqueness**: (`account_type`, `account_number`) must be unique
3. **Idempotency**: `transaction_id` must be unique (prevents duplicate processing)
4. **Tenant Isolation**: All queries must filter by `user_id`
5. **Soft Deletes**: Used for users, merchants, templates, tokens (preserves audit trail)

## Query Examples

### Tenant-Isolated Payment Query
```sql
SELECT * FROM payments 
WHERE user_id = 1 
  AND status = 'completed'
  AND transaction_time >= '2024-01-01'
ORDER BY transaction_time DESC;
```

### Account Resolution (Cross-Tenant Safe)
```sql
SELECT m.*, u.id as user_id 
FROM merchants m
INNER JOIN users u ON m.user_id = u.id
WHERE m.account_type = 'paybill'
  AND m.account_number = '123456'
  AND m.is_active = TRUE
  AND u.is_active = TRUE
  AND u.deleted_at IS NULL;
```

### SMS Retry Query (Tenant-Scoped)
```sql
SELECT p.* 
FROM payments p
WHERE p.user_id = 1
  AND p.sms_sent = FALSE
  AND p.status = 'completed'
  AND p.sms_retry_count < 3
ORDER BY p.created_at ASC
LIMIT 100;
```

### Idempotency Check
```sql
SELECT COUNT(*) 
FROM payments 
WHERE transaction_id = 'ABC123' 
   OR receipt_number = 'XYZ789';
```

## Performance Considerations

### Expected Table Sizes
- `payments`: High volume (thousands/day) - Consider partitioning by month
- `sms_notifications`: Same as payments
- `audit_logs`: Very high volume - Archive after 1 year
- `users`: Low volume (hundreds)
- `merchants`: Low volume (thousands)

### Partitioning Recommendation
Partition `payments` and `audit_logs` by `transaction_time`/`created_at` (monthly partitions) for tables exceeding 10M rows.
