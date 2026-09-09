# Database Schema Design - Multi-Tenant Payment Notification System

## Overview

This document describes a normalized MySQL database schema for a multi-tenant payment notification system. The schema enforces strict tenant isolation, supports idempotent payment processing, and tracks all operations for audit purposes.

---

## 1. Core Tables

### 1.1 `users` (Tenants/Clients)

Represents Talksasa clients who own payment accounts.

```sql
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT 'Client/Company name',
    `email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Login email',
    `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique tenant identifier (e.g., CLIENT001)',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Account status',
    `settings` JSON NULL COMMENT 'Tenant-specific settings (timezone, preferences, etc.)',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    
    INDEX `idx_users_code` (`code`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `name`: Client/company name
- `email`: Unique login email
- `password`: Bcrypt hashed password
- `code`: Unique tenant identifier for API access
- `is_active`: Account activation status
- `settings`: JSON for tenant-specific configurations
- `deleted_at`: Soft delete timestamp

---

### 1.2 `merchants` (Payment Accounts)

Maps Paybill and Till numbers to users. Each merchant account belongs to exactly one user.

```sql
CREATE TABLE `merchants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Foreign key to users',
    `account_type` ENUM('paybill', 'till') NOT NULL COMMENT 'Type of payment account',
    `account_number` VARCHAR(20) NOT NULL COMMENT 'Paybill or Till number',
    `account_name` VARCHAR(255) NULL COMMENT 'Display name for the account',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Account status',
    `sms_template_id` BIGINT UNSIGNED NULL COMMENT 'Default SMS template for this merchant',
    `webhook_url` VARCHAR(500) NULL COMMENT 'Optional custom webhook URL',
    `metadata` JSON NULL COMMENT 'Additional merchant-specific data',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    
    UNIQUE KEY `uk_merchants_account` (`account_type`, `account_number`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sms_template_id`) REFERENCES `sms_templates`(`id`) ON DELETE SET NULL,
    INDEX `idx_merchants_user` (`user_id`, `is_active`),
    INDEX `idx_merchants_account_lookup` (`account_type`, `account_number`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `user_id`: Owner of this payment account (tenant isolation)
- `account_type`: 'paybill' or 'till'
- `account_number`: The actual Paybill/Till number
- `account_name`: Human-readable name
- `is_active`: Account activation status
- `sms_template_id`: Default template for SMS notifications
- `webhook_url`: Optional custom webhook endpoint
- `metadata`: Additional JSON data

**Constraints:**
- Unique constraint on (`account_type`, `account_number`) - prevents duplicate accounts
- Foreign key to `users` with CASCADE delete
- Foreign key to `sms_templates` with SET NULL on delete

---

### 1.3 `payments` (Payment Transactions)

Stores all incoming payment transactions with idempotency support.

```sql
CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant isolation - denormalized for performance',
    `merchant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Foreign key to merchants',
    
    -- Idempotency Fields (M-Pesa Identifiers)
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'M-Pesa TransactionID (primary idempotency key)',
    `receipt_number` VARCHAR(100) NULL UNIQUE COMMENT 'M-Pesa ReceiptNumber (backup idempotency key)',
    `request_id` VARCHAR(100) NULL COMMENT 'M-Pesa RequestID (duplicate detection)',
    `conversation_id` VARCHAR(100) NULL COMMENT 'M-Pesa ConversationID',
    
    -- Payment Details
    `account_type` ENUM('paybill', 'till') NOT NULL COMMENT 'Denormalized for quick lookup',
    `account_number` VARCHAR(20) NOT NULL COMMENT 'Denormalized account number',
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Payment amount',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'KES' COMMENT 'Currency code',
    `phone_number` VARCHAR(20) NOT NULL COMMENT 'Payer phone number (E.164 format)',
    `payer_name` VARCHAR(255) NULL COMMENT 'Payer full name',
    
    -- Transaction Metadata
    `transaction_time` TIMESTAMP NOT NULL COMMENT 'When payment occurred (M-Pesa timestamp)',
    `status` ENUM('pending', 'completed', 'failed', 'cancelled', 'reversed') NOT NULL DEFAULT 'pending',
    `description` TEXT NULL COMMENT 'Transaction description',
    `reference` VARCHAR(255) NULL COMMENT 'Customer reference (BillRefNumber)',
    `metadata` JSON NOT NULL COMMENT 'Full M-Pesa payload for audit trail',
    
    -- SMS Notification Tracking
    `sms_sent` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'SMS delivery status',
    `sms_sent_at` TIMESTAMP NULL COMMENT 'When SMS was sent',
    `sms_notification_id` BIGINT UNSIGNED NULL COMMENT 'Link to sms_notifications table',
    `sms_error` TEXT NULL COMMENT 'SMS sending error message',
    `sms_retry_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of SMS retry attempts',
    
    -- Timestamps
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    
    -- Foreign Keys
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sms_notification_id`) REFERENCES `sms_notifications`(`id`) ON DELETE SET NULL,
    
    -- Indexes for Performance
    INDEX `idx_payments_user_status` (`user_id`, `status`),
    INDEX `idx_payments_merchant_time` (`merchant_id`, `transaction_time`),
    INDEX `idx_payments_transaction_time` (`transaction_time`),
    INDEX `idx_payments_sms_status` (`sms_sent`, `status`, `sms_retry_count`),
    INDEX `idx_payments_request_id` (`request_id`),
    INDEX `idx_payments_receipt` (`receipt_number`),
    INDEX `idx_payments_account_lookup` (`account_type`, `account_number`, `transaction_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `user_id`: Denormalized tenant ID for fast filtering (tenant isolation)
- `merchant_id`: Links to merchant account
- `transaction_id`: M-Pesa TransactionID (UNIQUE - primary idempotency key)
- `receipt_number`: M-Pesa ReceiptNumber (UNIQUE - backup idempotency key)
- `request_id`: M-Pesa RequestID (indexed for duplicate detection)
- `account_type`, `account_number`: Denormalized for fast lookups
- `amount`, `currency`: Payment amount and currency
- `phone_number`: Payer phone in E.164 format
- `transaction_time`: When payment occurred
- `status`: Payment status
- `metadata`: Full M-Pesa payload (JSON) for audit
- `sms_sent`, `sms_sent_at`: SMS delivery tracking
- `sms_notification_id`: Link to SMS notification record

**Idempotency Strategy:**
1. `transaction_id` - UNIQUE constraint (primary check)
2. `receipt_number` - UNIQUE constraint (backup check)
3. `request_id` - INDEXED (duplicate detection)

---

### 1.4 `sms_templates` (Message Templates)

Reusable SMS message templates that can be assigned to merchants or users.

```sql
CREATE TABLE `sms_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'NULL = global template, otherwise user-specific',
    `name` VARCHAR(255) NOT NULL COMMENT 'Template name',
    `message` TEXT NOT NULL COMMENT 'Template message with placeholders',
    `placeholders` JSON NULL COMMENT 'Available placeholders: {amount}, {receipt}, {account}, {time}, etc.',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `is_default` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Default template for user',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_templates_user` (`user_id`, `is_active`),
    INDEX `idx_templates_default` (`user_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `user_id`: NULL for global templates, user_id for tenant-specific templates
- `name`: Template name
- `message`: Template text with placeholders (e.g., "Payment of KES {amount} received")
- `placeholders`: JSON array of available placeholders
- `is_default`: Marks default template per user

**Template Placeholders:**
- `{amount}` - Payment amount
- `{receipt}` - Receipt number
- `{account}` - Account name/number
- `{time}` - Transaction time
- `{payer}` - Payer name
- `{reference}` - Customer reference

---

### 1.5 `sms_notifications` (SMS Notification Records)

Tracks all SMS notifications sent, including retries and delivery status.

```sql
CREATE TABLE `sms_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_id` BIGINT UNSIGNED NOT NULL COMMENT 'Foreign key to payments',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant isolation',
    `merchant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Merchant account',
    
    -- SMS Details
    `phone_number` VARCHAR(20) NOT NULL COMMENT 'Recipient phone number',
    `message` TEXT NOT NULL COMMENT 'Final SMS message sent',
    `template_id` BIGINT UNSIGNED NULL COMMENT 'Template used',
    
    -- Delivery Status
    `status` ENUM('pending', 'sent', 'delivered', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `provider_message_id` VARCHAR(255) NULL COMMENT 'Talksasa SMS API message ID',
    `provider_response` JSON NULL COMMENT 'Full API response',
    `error_message` TEXT NULL COMMENT 'Error details if failed',
    `sent_at` TIMESTAMP NULL COMMENT 'When SMS was sent',
    `delivered_at` TIMESTAMP NULL COMMENT 'When SMS was delivered (if webhook received)',
    
    -- Retry Information
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of send attempts',
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Maximum retry attempts',
    
    -- Timestamps
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`template_id`) REFERENCES `sms_templates`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_sms_payment` (`payment_id`),
    INDEX `idx_sms_user_status` (`user_id`, `status`),
    INDEX `idx_sms_status_retry` (`status`, `attempt_count`, `max_attempts`),
    INDEX `idx_sms_provider_id` (`provider_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `payment_id`: Links to payment (one payment = one SMS)
- `user_id`: Tenant isolation
- `phone_number`: Recipient phone
- `message`: Final SMS text sent
- `status`: Delivery status
- `provider_message_id`: External SMS provider ID
- `provider_response`: Full API response JSON
- `attempt_count`: Retry tracking

**Constraint:**
- One payment can have only one SMS notification (enforced at application level)

---

### 1.6 `api_tokens` (API Authentication)

API tokens for client authentication and access control.

```sql
CREATE TABLE `api_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Token owner',
    `name` VARCHAR(255) NOT NULL COMMENT 'Token name/description',
    `token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Hashed API token',
    `token_prefix` VARCHAR(8) NOT NULL COMMENT 'First 8 chars for identification',
    `scopes` JSON NULL COMMENT 'Token permissions/scopes',
    `last_used_at` TIMESTAMP NULL COMMENT 'Last usage timestamp',
    `expires_at` TIMESTAMP NULL COMMENT 'Token expiration (NULL = never expires)',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_tokens_user` (`user_id`, `is_active`),
    INDEX `idx_tokens_token` (`token_prefix`),
    INDEX `idx_tokens_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `user_id`: Token owner
- `token`: SHA-256 hashed token (never store plain text)
- `token_prefix`: First 8 characters for quick identification
- `scopes`: JSON array of permissions (e.g., ["payments:read", "payments:write"])
- `expires_at`: Optional expiration date
- `last_used_at`: Last usage tracking

**Security:**
- Store only hashed tokens (SHA-256)
- Use token prefix for quick lookup
- Support token expiration
- Soft delete for audit trail

---

### 1.7 `audit_logs` (System Audit Trail)

Comprehensive audit log for all system operations.

```sql
CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'User who performed action (NULL = system)',
    `action` VARCHAR(100) NOT NULL COMMENT 'Action type (payment.received, sms.sent, etc.)',
    `entity_type` VARCHAR(100) NULL COMMENT 'Entity type (Payment, Merchant, etc.)',
    `entity_id` BIGINT UNSIGNED NULL COMMENT 'Entity ID',
    `description` TEXT NULL COMMENT 'Human-readable description',
    `changes` JSON NULL COMMENT 'Before/after changes (for updates)',
    `metadata` JSON NULL COMMENT 'Additional context data',
    `ip_address` VARCHAR(45) NULL COMMENT 'Request IP address',
    `user_agent` VARCHAR(500) NULL COMMENT 'User agent string',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_audit_user` (`user_id`, `created_at`),
    INDEX `idx_audit_action` (`action`, `created_at`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `id`: Primary key
- `user_id`: User who performed action (NULL for system actions)
- `action`: Action type (e.g., "payment.received", "sms.sent", "merchant.created")
- `entity_type`: Entity type (e.g., "Payment", "Merchant")
- `entity_id`: Entity ID
- `changes`: JSON with before/after values
- `metadata`: Additional context
- `ip_address`, `user_agent`: Request tracking

**Action Types:**
- `payment.received` - Payment notification received
- `payment.processed` - Payment processed successfully
- `sms.sent` - SMS notification sent
- `sms.failed` - SMS sending failed
- `merchant.created` - Merchant account created
- `merchant.updated` - Merchant account updated
- `api.token.created` - API token created
- `api.token.revoked` - API token revoked

---

## 2. Relationships Diagram

```
users (1) ──< (many) merchants
users (1) ──< (many) payments (denormalized user_id)
users (1) ──< (many) sms_templates
users (1) ──< (many) api_tokens
users (1) ──< (many) audit_logs

merchants (1) ──< (many) payments
merchants (many) ──> (1) sms_templates (optional default template)

payments (1) ──> (1) sms_notifications (one-to-one)
payments (1) ──> (1) sms_templates (via merchant or direct)

sms_notifications (many) ──> (1) sms_templates
```

**Key Relationships:**
- **One-to-Many**: User → Merchants, Payments, Templates, Tokens
- **One-to-Many**: Merchant → Payments
- **One-to-One**: Payment → SMS Notification (enforced at app level)
- **Many-to-One**: Merchant → SMS Template (optional default)

---

## 3. Indexing Strategy

### 3.1 Primary Indexes

All tables use `BIGINT UNSIGNED AUTO_INCREMENT` primary keys for:
- Fast joins
- Sequential inserts
- Small index size

### 3.2 Unique Indexes

**Idempotency:**
- `payments.transaction_id` (UNIQUE) - Primary idempotency check
- `payments.receipt_number` (UNIQUE) - Backup idempotency check
- `merchants(account_type, account_number)` (UNIQUE) - Prevent duplicate accounts
- `users.code` (UNIQUE) - Unique tenant identifier
- `users.email` (UNIQUE) - Unique login email
- `api_tokens.token` (UNIQUE) - Unique API tokens

### 3.3 Composite Indexes

**Tenant Isolation Queries:**
```sql
-- Fast tenant-scoped queries
idx_payments_user_status (user_id, status)
idx_merchants_user (user_id, is_active)
idx_sms_user_status (user_id, status)
idx_templates_user (user_id, is_active)
```

**Time-Based Queries:**
```sql
-- Payment history queries
idx_payments_merchant_time (merchant_id, transaction_time)
idx_payments_transaction_time (transaction_time)
idx_audit_created (created_at)
```

**Lookup Performance:**
```sql
-- Fast account resolution
idx_merchants_account_lookup (account_type, account_number, is_active)
idx_payments_account_lookup (account_type, account_number, transaction_time)
```

**SMS Retry Queries:**
```sql
-- Find pending SMS for retry
idx_payments_sms_status (sms_sent, status, sms_retry_count)
idx_sms_status_retry (status, attempt_count, max_attempts)
```

**Duplicate Detection:**
```sql
-- Fast duplicate check
idx_payments_request_id (request_id)
```

### 3.4 Foreign Key Indexes

All foreign keys are automatically indexed by InnoDB:
- `merchants.user_id`
- `payments.user_id`, `payments.merchant_id`
- `sms_notifications.payment_id`, `sms_notifications.user_id`
- `api_tokens.user_id`
- `audit_logs.user_id`

### 3.5 Index Maintenance

**Monitoring:**
- Monitor index usage with `EXPLAIN` queries
- Review slow query log
- Use `SHOW INDEX FROM table_name` to check cardinality

**Optimization:**
- Remove unused indexes
- Add covering indexes for frequent queries
- Consider partitioning for large tables (payments, audit_logs)

---

## 4. Tenant Isolation Enforcement

### 4.1 Database Level

**Foreign Key Constraints:**
- All tenant-scoped tables have `user_id` foreign key
- CASCADE deletes ensure data consistency
- Prevents orphaned records

**Unique Constraints:**
- `users.code` ensures unique tenant identifiers
- Prevents tenant code collisions

### 4.2 Application Level

**Query Scoping Pattern:**

```php
// Always filter by user_id in queries
Payment::where('user_id', $currentUser->id)
    ->where('status', 'completed')
    ->get();

// Repository pattern with automatic scoping
class PaymentRepository
{
    public function getByTenant(int $userId, array $filters = [])
    {
        $query = Payment::where('user_id', $userId);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->get();
    }
}
```

**Middleware Enforcement:**

```php
// TenantMiddleware ensures user_id is set
class TenantMiddleware
{
    public function handle($request, $next)
    {
        $user = $this->resolveUser($request);
        $request->merge(['user_id' => $user->id]);
        return $next($request);
    }
}
```

**Model Scoping:**

```php
// Global scope for automatic tenant filtering
class Payment extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if ($tenantId = request()->get('user_id')) {
                $query->where('user_id', $tenantId);
            }
        });
    }
}
```

### 4.3 Query Examples

**Tenant-Isolated Payment Query:**
```sql
SELECT * FROM payments 
WHERE user_id = ? 
  AND status = 'completed'
  AND transaction_time >= ?
ORDER BY transaction_time DESC;
```

**Account Resolution (Cross-Tenant Safe):**
```sql
SELECT m.*, u.id as user_id 
FROM merchants m
INNER JOIN users u ON m.user_id = u.id
WHERE m.account_type = ?
  AND m.account_number = ?
  AND m.is_active = TRUE
  AND u.is_active = TRUE
  AND u.deleted_at IS NULL;
```

**SMS Retry Query (Tenant-Scoped):**
```sql
SELECT p.* 
FROM payments p
WHERE p.user_id = ?
  AND p.sms_sent = FALSE
  AND p.status = 'completed'
  AND p.sms_retry_count < 3
ORDER BY p.created_at ASC
LIMIT 100;
```

### 4.4 Security Considerations

1. **Never trust user input** - Always validate `user_id` from authenticated session/token
2. **Use parameterized queries** - Prevent SQL injection
3. **Row-level security** - Application enforces tenant boundaries
4. **Audit all access** - Log all queries with tenant context
5. **API token scoping** - Tokens are tenant-specific

---

## 5. Data Integrity Rules

### 5.1 Business Rules

1. **One Payment = One SMS**: Enforced at application level (check `sms_notification_id` before creating)
2. **Account Uniqueness**: (`account_type`, `account_number`) must be unique
3. **Idempotency**: `transaction_id` must be unique (prevents duplicate processing)
4. **Tenant Isolation**: All queries must include `user_id` filter
5. **Soft Deletes**: Use soft deletes for audit trail (users, merchants, templates, tokens)

### 5.2 Referential Integrity

- **CASCADE DELETE**: When user is deleted, all related records are deleted
- **SET NULL**: Template deletion sets `sms_template_id` to NULL (preserves historical data)
- **RESTRICT**: Prevent deletion of merchants with active payments

### 5.3 Data Validation

**Application Level:**
- Validate phone numbers (E.164 format)
- Validate amounts (positive, reasonable limits)
- Validate account numbers (format validation)
- Validate JSON fields (schema validation)

**Database Level:**
- ENUM constraints for status fields
- NOT NULL constraints for required fields
- CHECK constraints (if MySQL 8.0.16+)
- Foreign key constraints

---

## 6. Performance Considerations

### 6.1 Table Sizes

**Expected Growth:**
- `payments`: High volume (thousands per day)
- `sms_notifications`: Same as payments
- `audit_logs`: Very high volume (all operations)
- `users`: Low volume (hundreds)
- `merchants`: Low volume (thousands)

### 6.2 Partitioning Strategy

**Consider Partitioning:**
- `payments` by `transaction_time` (monthly partitions)
- `audit_logs` by `created_at` (monthly partitions)
- `sms_notifications` by `sent_at` (monthly partitions)

**Partition Example:**
```sql
ALTER TABLE payments 
PARTITION BY RANGE (YEAR(transaction_time) * 100 + MONTH(transaction_time)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    -- ... monthly partitions
);
```

### 6.3 Archival Strategy

**Archive Old Data:**
- Move payments older than 2 years to `payments_archive`
- Archive audit logs older than 1 year
- Keep only active merchants in main table

---

## 7. Migration Strategy

### 7.1 Migration Order

1. Create `users` table
2. Create `sms_templates` table (referenced by merchants)
3. Create `merchants` table
4. Create `payments` table
5. Create `sms_notifications` table
6. Create `api_tokens` table
7. Create `audit_logs` table

### 7.2 Data Migration

**From Existing Schema:**
- Map `tenants` → `users`
- Map `payment_accounts` → `merchants`
- Map existing `payments` → new `payments` structure
- Create default SMS templates

---

## 8. Summary

**Key Design Decisions:**

1. **Denormalization**: `user_id` in `payments` for fast tenant filtering
2. **Idempotency**: Multiple unique constraints (`transaction_id`, `receipt_number`)
3. **Soft Deletes**: Used for audit trail and data recovery
4. **JSON Fields**: Flexible metadata storage (M-Pesa payload, settings)
5. **One-to-One**: Payment → SMS (enforced at application level)
6. **Composite Indexes**: Optimized for common query patterns
7. **Tenant Isolation**: Enforced at both database and application level

**This schema provides:**
- ✅ Strict tenant isolation
- ✅ Idempotent payment processing
- ✅ Comprehensive audit trail
- ✅ Flexible SMS templating
- ✅ Scalable architecture
- ✅ Performance optimization
