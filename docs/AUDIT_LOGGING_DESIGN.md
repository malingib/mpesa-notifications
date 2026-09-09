# Audit & Logging System Design

## Overview

A comprehensive audit and logging system for financial transactions that ensures immutability, traceability, and GDPR compliance.

## Core Principles

1. **Immutability**: Payment records are never modified, only appended to
2. **Complete Traceability**: Every action is logged with correlation IDs
3. **End-to-End Tracking**: Trace a payment from webhook to SMS delivery
4. **GDPR Compliance**: Safe soft deletion with anonymization
5. **Retention Policies**: Automatic data archival and deletion

---

## 1. Immutable Payment Records

### Strategy: Event Sourcing Pattern

**Approach:**
- Payment table stores current state
- Payment history table stores all changes (immutable)
- No direct updates to payment records
- All changes create history entries

### Payment History Table

**Purpose:** Track every change to a payment record

**Fields:**
- `id` - Unique history entry ID
- `payment_id` - Reference to payment
- `correlation_id` - End-to-end trace ID
- `action` - Type of change (created, updated, status_changed, etc.)
- `changed_by_type` - System, User, API
- `changed_by_id` - User ID or system identifier
- `field_name` - Field that changed
- `old_value` - Previous value (JSON)
- `new_value` - New value (JSON)
- `reason` - Why the change was made
- `ip_address` - Source IP
- `user_agent` - User agent
- `created_at` - When change occurred

**Indexes:**
- `payment_id` + `created_at` (for payment timeline)
- `correlation_id` (for end-to-end tracing)
- `action` + `created_at` (for audit queries)

---

## 2. SMS Send Attempt History

### SMS Attempts Table

**Purpose:** Track every SMS send attempt (success or failure)

**Fields:**
- `id` - Unique attempt ID
- `payment_id` - Reference to payment
- `correlation_id` - End-to-end trace ID
- `job_id` - Queue job ID
- `attempt_number` - Retry attempt (1, 2, 3, etc.)
- `phone_number` - Recipient phone (masked for GDPR)
- `message` - SMS message content (optional, for debugging)
- `message_length` - Character count
- `status` - pending, sent, failed, skipped
- `error_code` - Error code if failed
- `error_message` - Error details
- `provider_response` - Raw API response (JSON)
- `sent_at` - When SMS was sent
- `created_at` - When attempt was created

**Indexes:**
- `payment_id` + `created_at` (for payment SMS history)
- `correlation_id` (for end-to-end tracing)
- `status` + `created_at` (for failure analysis)
- `job_id` (for job correlation)

---

## 3. Correlation IDs

### Purpose

Enable end-to-end tracing of a payment from webhook receipt to SMS delivery.

### Implementation

**Correlation ID Format:**
```
{prefix}-{timestamp}-{random}
Example: PAY-20240126143045-abc123def456
```

**Where Used:**
1. Payment webhook receipt
2. Payment creation
3. SMS job dispatch
4. SMS send attempts
5. All audit logs

**Storage:**
- `payments.correlation_id` - Primary correlation ID
- `payment_history.correlation_id` - Same ID for all related events
- `sms_attempts.correlation_id` - Same ID for SMS events
- `audit_logs.correlation_id` - Same ID for audit events

**Query Pattern:**
```sql
-- Get complete payment journey
SELECT * FROM payment_history 
WHERE correlation_id = 'PAY-20240126143045-abc123def456'
ORDER BY created_at;

-- Get all SMS attempts
SELECT * FROM sms_attempts 
WHERE correlation_id = 'PAY-20240126143045-abc123def456'
ORDER BY created_at;
```

---

## 4. Enhanced Audit Logging

### Audit Logs Table (Enhanced)

**Existing:** `audit_logs` table (already created)

**Enhancements:**
- Add `correlation_id` for tracing
- Add `session_id` for user session tracking
- Add `request_id` for HTTP request tracking
- Add `severity` (info, warning, error, critical)
- Add `category` (payment, sms, auth, system)

**Fields:**
- `id` - Unique log ID
- `correlation_id` - End-to-end trace ID
- `user_id` - User who performed action
- `action` - Action type
- `entity_type` - Entity type (Payment, Merchant, etc.)
- `entity_id` - Entity ID
- `description` - Human-readable description
- `changes` - Before/after changes (JSON)
- `metadata` - Additional context (JSON)
- `severity` - Log severity level
- `category` - Log category
- `ip_address` - Source IP
- `user_agent` - User agent
- `session_id` - Session identifier
- `request_id` - HTTP request ID
- `created_at` - When event occurred

---

## 5. GDPR-Safe Soft Deletion

### Strategy

**Two-Stage Deletion:**
1. **Soft Delete**: Mark as deleted, hide from queries
2. **Anonymization**: After retention period, anonymize PII

### Anonymization Fields

**Payment Table:**
- `phone_number` → `[REDACTED]`
- `payer_name` → `[REDACTED]`
- `metadata` → Remove PII, keep structure

**SMS Attempts:**
- `phone_number` → `[REDACTED]`
- `message` → `[REDACTED]`

**Audit Logs:**
- `ip_address` → `[REDACTED]`
- `user_agent` → `[REDACTED]`

### Anonymization Table

**Purpose:** Track anonymization operations

**Fields:**
- `id` - Unique anonymization record
- `entity_type` - Payment, User, etc.
- `entity_id` - Entity ID
- `anonymized_at` - When anonymized
- `anonymized_by` - System or user
- `reason` - Why anonymized (GDPR request, retention policy)
- `fields_anonymized` - List of fields (JSON)

---

## 6. Retention Policies

### Policy Structure

**Payment Records:**
- Active: 7 years (financial regulation)
- Archived: 10 years (long-term storage)
- Deleted: After 10 years

**SMS Attempts:**
- Active: 2 years
- Archived: 5 years
- Deleted: After 5 years

**Audit Logs:**
- Active: 1 year
- Archived: 3 years
- Deleted: After 3 years

### Implementation

**Archival Process:**
1. Move old records to archive tables
2. Compress archive data
3. Store in cold storage (S3, etc.)

**Deletion Process:**
1. Anonymize PII before deletion
2. Log anonymization operation
3. Delete anonymized records
4. Verify deletion

---

## 7. Logging Strategy

### Log Levels

**Info:**
- Payment received
- SMS sent successfully
- Status changes

**Warning:**
- SMS retry
- Rate limit hit
- Validation failures

**Error:**
- SMS send failure
- Payment processing error
- API errors

**Critical:**
- Data integrity issues
- Security breaches
- System failures

### Log Categories

1. **Payment**: All payment-related events
2. **SMS**: All SMS-related events
3. **Auth**: Authentication and authorization
4. **System**: System-level events
5. **API**: External API interactions

### Structured Logging

**Format:**
```json
{
  "correlation_id": "PAY-20240126143045-abc123",
  "timestamp": "2024-01-26T14:30:45Z",
  "level": "info",
  "category": "payment",
  "action": "payment.received",
  "entity_type": "Payment",
  "entity_id": 123,
  "user_id": 456,
  "metadata": {
    "amount": 1000.00,
    "transaction_id": "ABC123"
  }
}
```

---

## 8. Query Patterns

### Trace Payment End-to-End

```sql
-- Get payment with all history
SELECT 
    p.*,
    ph.action,
    ph.field_name,
    ph.old_value,
    ph.new_value,
    ph.created_at as change_time
FROM payments p
LEFT JOIN payment_history ph ON p.id = ph.payment_id
WHERE p.correlation_id = ?
ORDER BY ph.created_at;
```

### Get SMS Attempt History

```sql
-- Get all SMS attempts for payment
SELECT *
FROM sms_attempts
WHERE payment_id = ?
ORDER BY attempt_number, created_at;
```

### Get Complete Audit Trail

```sql
-- Get all events for correlation ID
SELECT 
    'payment' as source,
    created_at,
    action,
    description
FROM payment_history
WHERE correlation_id = ?

UNION ALL

SELECT 
    'sms' as source,
    created_at,
    status as action,
    error_message as description
FROM sms_attempts
WHERE correlation_id = ?

UNION ALL

SELECT 
    'audit' as source,
    created_at,
    action,
    description
FROM audit_logs
WHERE correlation_id = ?

ORDER BY created_at;
```

---

## 9. Performance Considerations

### Indexing Strategy

**Payment History:**
- `(payment_id, created_at)` - Payment timeline
- `(correlation_id, created_at)` - End-to-end trace
- `(action, created_at)` - Audit queries

**SMS Attempts:**
- `(payment_id, created_at)` - Payment SMS history
- `(correlation_id, created_at)` - End-to-end trace
- `(status, created_at)` - Failure analysis

**Audit Logs:**
- `(correlation_id, created_at)` - End-to-end trace
- `(user_id, created_at)` - User activity
- `(action, created_at)` - Action queries

### Partitioning

**Large Tables:**
- Partition by date (monthly or yearly)
- Archive old partitions
- Query only active partitions

---

## 10. Security & Compliance

### Access Control

- Audit logs: Admin only
- Payment history: Tenant-scoped
- SMS attempts: Tenant-scoped

### Data Protection

- Encrypt sensitive fields at rest
- Mask PII in logs
- Secure audit log access
- Regular security audits

### Compliance

- GDPR: Right to deletion, anonymization
- Financial regulations: 7-year retention
- PCI DSS: Secure payment data handling

---

## Summary

✅ **Immutable Records** - Payment history table tracks all changes
✅ **SMS History** - Every attempt logged separately
✅ **End-to-End Tracing** - Correlation IDs across all events
✅ **Complete Audit Trail** - Who did what and when
✅ **GDPR Compliance** - Safe soft deletion with anonymization
✅ **Retention Policies** - Automatic archival and deletion
