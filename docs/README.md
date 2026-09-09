# Talksasa Payment Notifications System

A production-grade, multi-tenant payment notification system for Talksasa that detects incoming M-Pesa payments and automatically sends confirmation SMS via the Talksasa Bulk SMS API.

## Features

- **Multi-Tenant Architecture**: Strict tenant isolation for all payment operations
- **Idempotent Processing**: Prevents duplicate payment processing using transaction IDs
- **Async SMS Sending**: Queue-based async processing for high-volume SMS delivery
- **Reliable Storage**: All payments are stored even if SMS sending fails
- **Retry Mechanism**: Automatic retry for failed SMS with configurable attempts
- **Clean Architecture**: Service-layer architecture with separation of concerns
- **Production-Ready**: Comprehensive error handling, logging, and monitoring

## Tech Stack

- **Laravel 11** (latest stable)
- **PHP 8.3**
- **MySQL** (database)
- **Guzzle HTTP** (API client)
- **Queue System** (database-driven queues)

## Architecture Overview

### Multi-Tenancy

The system uses a **shared database, shared schema** approach with tenant isolation enforced at the application layer:

- Each client is a `Tenant` with a unique code
- `PaymentAccount` maps Paybill/Till numbers to tenants
- All `Payment` records are scoped to tenants
- Middleware enforces tenant isolation for API requests

### Payment Processing Flow

1. **Webhook Reception**: M-Pesa sends payment notification to `/api/webhooks/mpesa/confirmation`
2. **Idempotency Check**: System checks if payment already exists by `TransactionID`
3. **Account Resolution**: Finds `PaymentAccount` by Paybill/Till number
4. **Payment Storage**: Creates `Payment` record with full transaction details
5. **SMS Dispatch**: Queues `SendPaymentSmsJob` for async processing
6. **SMS Sending**: Job sends SMS via Talksasa API and updates payment status

### Idempotency Strategy

- **Primary Key**: `transaction_id` (M-Pesa TransactionID) - unique constraint
- **Duplicate Detection**: `request_id` (M-Pesa RequestID) - indexed for fast lookup
- **Receipt Tracking**: `receipt_number` (M-Pesa ReceiptNumber) - unique constraint

## Installation

### Prerequisites

- PHP 8.3+
- Composer
- MySQL 8.0+
- Redis (optional, for caching)

### Setup Steps

1. **Clone and Install Dependencies**

```bash
cd /home/zumi/php/mpesa-notifications
composer install
```

2. **Configure Environment**

```bash
cp .env.example .env
php artisan key:generate  # If using Laravel's key generation
```

Edit `.env` with your configuration:

```env
DB_DATABASE=mpesa_notifications
DB_USERNAME=your_username
DB_PASSWORD=your_password

TALKSASA_SMS_API_URL=https://api.talksasa.com/v1/sms
TALKSASA_SMS_API_KEY=your_talksasa_api_key
TALKSASA_SMS_SENDER_ID=TALKSASA

QUEUE_CONNECTION=database
```

3. **Run Migrations**

```bash
php artisan migrate
```

4. **Seed Sample Data (Optional)**

```bash
php artisan db:seed
```

5. **Start Queue Worker**

```bash
php artisan queue:work --tries=3
```

## Configuration

### M-Pesa Webhook URLs

Configure these URLs in your M-Pesa account:

- **Validation URL**: `https://your-domain.com/api/webhooks/mpesa/validation`
- **Confirmation URL**: `https://your-domain.com/api/webhooks/mpesa/confirmation`

### Queue Configuration

The system uses database queues by default. For production, consider:

- **Redis Queues**: Faster and more scalable
- **Supervisor**: For managing queue workers
- **Horizon**: For queue monitoring (Laravel Horizon)

### SMS Template Configuration

Customize SMS templates per payment account:

```php
PaymentAccount::create([
    'tenant_id' => 1,
    'account_type' => 'paybill',
    'account_number' => '123456',
    'sms_template' => [
        'message' => 'Payment of KES {amount} received. Receipt: {receipt}. Account: {account}. Time: {time}. Thank you!'
    ],
]);
```

Available placeholders:
- `{amount}` - Payment amount
- `{receipt}` - Receipt number
- `{account}` - Account name/number
- `{time}` - Transaction time

## API Endpoints

### Webhook Endpoints (No Authentication)

#### M-Pesa Confirmation Webhook
```
POST /api/webhooks/mpesa/confirmation
```

Receives payment confirmations from M-Pesa.

#### M-Pesa Validation Webhook
```
POST /api/webhooks/mpesa/validation
```

Validates payment requests before confirmation.

### Payment API Endpoints (Require Tenant Authentication)

#### List Payments
```
GET /api/payments
Headers:
  X-Tenant-ID: 1
  OR
  X-Tenant-Code: SAMPLE001

Query Parameters:
  - status: pending|completed|failed|cancelled
  - date_from: YYYY-MM-DD
  - date_to: YYYY-MM-DD
```

#### Get Payment
```
GET /api/payments/{id}
Headers:
  X-Tenant-ID: 1
```

### Health Check
```
GET /api/health
```

## Console Commands

### Retry Failed SMS

Retry sending SMS for payments that failed:

```bash
php artisan payments:retry-sms --limit=100
```

Schedule this command in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('payments:retry-sms')
        ->everyFiveMinutes();
}
```

## Database Schema

### Tenants Table
- `id` - Primary key
- `name` - Client name
- `code` - Unique tenant identifier
- `is_active` - Active status
- `settings` - JSON settings

### Payment Accounts Table
- `id` - Primary key
- `tenant_id` - Foreign key to tenants
- `account_type` - 'paybill' or 'till'
- `account_number` - Paybill/Till number
- `account_name` - Display name
- `is_active` - Active status
- `sms_template` - Custom SMS template (JSON)

### Payments Table
- `id` - Primary key
- `tenant_id` - Foreign key to tenants
- `payment_account_id` - Foreign key to payment_accounts
- `transaction_id` - M-Pesa TransactionID (unique)
- `receipt_number` - M-Pesa ReceiptNumber (unique)
- `request_id` - M-Pesa RequestID (for duplicate detection)
- `account_type` - 'paybill' or 'till'
- `account_number` - Paybill/Till number
- `amount` - Payment amount
- `currency` - Currency code (default: KES)
- `phone_number` - Payer phone number
- `payer_name` - Payer name
- `transaction_time` - When payment occurred
- `status` - Payment status
- `description` - Transaction description
- `metadata` - Full M-Pesa payload (JSON)
- `sms_sent` - SMS sent flag
- `sms_sent_at` - When SMS was sent
- `sms_error` - SMS error message
- `sms_retry_count` - Number of retry attempts

## Design Decisions

### Why Shared Database Multi-Tenancy?

- **Simplicity**: Easier to manage and scale
- **Cost-Effective**: Single database instance
- **Performance**: Shared connection pool
- **Isolation**: Enforced at application layer with indexes

### Why Database Queues?

- **Simplicity**: No additional infrastructure required
- **Reliability**: Transactions ensure job persistence
- **Scalability**: Can migrate to Redis/SQS later

### Why Store Full Payload?

- **Audit Trail**: Complete transaction history
- **Debugging**: Easier troubleshooting
- **Compliance**: Regulatory requirements
- **Future Features**: Enable advanced analytics

### Why Idempotency Checks?

- **M-Pesa Behavior**: May send duplicate notifications
- **Network Issues**: Retries can cause duplicates
- **Data Integrity**: Prevents double-counting
- **SMS Prevention**: Avoids duplicate SMS charges

## Security Considerations

1. **Webhook Security**: Configure `WEBHOOK_SECRET_KEY` in `.env`
2. **Tenant Isolation**: Middleware enforces tenant boundaries
3. **Input Validation**: All webhook payloads are validated
4. **SQL Injection**: Using Eloquent ORM prevents SQL injection
5. **Rate Limiting**: Consider adding rate limiting middleware

## Monitoring & Logging

All critical operations are logged:

- Payment processing (info)
- SMS sending (info/error)
- Webhook reception (info)
- Errors (error with stack traces)

Check logs in `storage/logs/laravel.log`.

## Testing

Run tests:

```bash
php artisan test
```

## Production Deployment

1. **Set Environment**: `APP_ENV=production`, `APP_DEBUG=false`
2. **Optimize**: `php artisan config:cache`, `php artisan route:cache`
3. **Queue Workers**: Use Supervisor to manage queue workers
4. **Monitoring**: Set up error tracking (Sentry, Bugsnag)
5. **Backups**: Regular database backups
6. **SSL**: Use HTTPS for webhook endpoints

## Troubleshooting

### Payments Not Processing

- Check webhook URL configuration in M-Pesa
- Verify payment account exists and is active
- Check logs for errors

### SMS Not Sending

- Verify Talksasa API credentials
- Check queue worker is running
- Review `sms_error` field in payments table
- Run `payments:retry-sms` command

### Duplicate Payments

- Check `transaction_id` uniqueness constraint
- Review idempotency logic in `PaymentProcessingService`
- Verify M-Pesa is not sending duplicates

## License

MIT

## Support

For issues and questions, contact the development team.
