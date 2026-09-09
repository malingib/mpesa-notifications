# Phase 1: Enhanced Payment & Financial Management - Design Document

## Overview
Comprehensive financial management system building on existing payment infrastructure, adding invoicing, reconciliation, and financial reporting capabilities.

---

## 1. Database Schema Design

### 1.1 Customers Table
**Purpose**: Store customer information for invoicing and CRM foundation

```sql
CREATE TABLE `customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant ID',
    `name` VARCHAR(255) NOT NULL COMMENT 'Customer name',
    `email` VARCHAR(255) NULL COMMENT 'Customer email',
    `phone` VARCHAR(20) NULL COMMENT 'Phone number (254XXXXXXXXX)',
    `company` VARCHAR(255) NULL COMMENT 'Company name',
    `tax_id` VARCHAR(50) NULL COMMENT 'Tax ID/KRA PIN',
    `address` TEXT NULL COMMENT 'Physical address',
    `city` VARCHAR(100) NULL,
    `country` VARCHAR(100) DEFAULT 'Kenya',
    `notes` TEXT NULL COMMENT 'Internal notes',
    `tags` JSON NULL COMMENT 'Customer tags for segmentation',
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_customers_user` (`user_id`, `status`),
    INDEX `idx_customers_email` (`email`),
    INDEX `idx_customers_phone` (`phone`),
    INDEX `idx_customers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Invoices Table
**Purpose**: Store invoice information

```sql
CREATE TABLE `invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant ID',
    `customer_id` BIGINT UNSIGNED NULL COMMENT 'Customer ID (nullable for one-off invoices)',
    `invoice_number` VARCHAR(50) NOT NULL COMMENT 'Unique invoice number (e.g., INV-2026-001)',
    `status` ENUM('draft', 'sent', 'viewed', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft',
    `issue_date` DATE NOT NULL COMMENT 'Invoice issue date',
    `due_date` DATE NOT NULL COMMENT 'Payment due date',
    `paid_date` DATE NULL COMMENT 'Date fully paid',
    `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal before tax',
    `tax_rate` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Tax rate percentage (e.g., 16 for VAT)',
    `tax_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Tax amount',
    `discount_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Discount amount',
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount due',
    `paid_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Amount paid so far',
    `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Remaining balance',
    `currency` VARCHAR(3) DEFAULT 'KES',
    `notes` TEXT NULL COMMENT 'Invoice notes visible to customer',
    `terms` TEXT NULL COMMENT 'Payment terms',
    `reference` VARCHAR(255) NULL COMMENT 'Reference number for payment matching',
    `recurring_id` BIGINT UNSIGNED NULL COMMENT 'If part of recurring invoice series',
    `parent_invoice_id` BIGINT UNSIGNED NULL COMMENT 'If credit note or amendment',
    `pdf_path` VARCHAR(500) NULL COMMENT 'Generated PDF path',
    `sent_at` TIMESTAMP NULL COMMENT 'When invoice was sent',
    `viewed_at` TIMESTAMP NULL COMMENT 'When customer viewed invoice',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`recurring_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_invoice_id`) REFERENCES `invoices`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_invoice_number` (`user_id`, `invoice_number`),
    INDEX `idx_invoices_user_status` (`user_id`, `status`),
    INDEX `idx_invoices_customer` (`customer_id`),
    INDEX `idx_invoices_due_date` (`due_date`),
    INDEX `idx_invoices_reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 Invoice Items Table
**Purpose**: Store line items for each invoice

```sql
CREATE TABLE `invoice_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `description` VARCHAR(500) NOT NULL COMMENT 'Item description',
    `quantity` DECIMAL(10, 2) DEFAULT 1.00,
    `unit_price` DECIMAL(15, 2) NOT NULL COMMENT 'Price per unit',
    `tax_rate` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Tax rate for this item',
    `discount_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Discount for this item',
    `total` DECIMAL(15, 2) NOT NULL COMMENT 'Line total (quantity * unit_price - discount)',
    `sort_order` INT DEFAULT 0 COMMENT 'Display order',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    INDEX `idx_invoice_items_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.4 Invoice Payments Table
**Purpose**: Link payments to invoices (many-to-many relationship)

```sql
CREATE TABLE `invoice_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `payment_id` BIGINT UNSIGNED NOT NULL COMMENT 'Link to payments table',
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Amount applied to invoice',
    `payment_date` DATE NOT NULL COMMENT 'Date payment was applied',
    `notes` TEXT NULL COMMENT 'Payment notes',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    INDEX `idx_invoice_payments_invoice` (`invoice_id`),
    INDEX `idx_invoice_payments_payment` (`payment_id`),
    UNIQUE KEY `unique_invoice_payment` (`invoice_id`, `payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.5 Expenses Table
**Purpose**: Track business expenses for cash flow and P&L

```sql
CREATE TABLE `expenses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant ID',
    `category_id` BIGINT UNSIGNED NULL COMMENT 'Expense category',
    `vendor` VARCHAR(255) NULL COMMENT 'Vendor/supplier name',
    `description` VARCHAR(500) NOT NULL COMMENT 'Expense description',
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Expense amount',
    `tax_amount` DECIMAL(15, 2) DEFAULT 0.00 COMMENT 'Tax/VAT amount',
    `total_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Total including tax',
    `payment_method` ENUM('mpesa', 'bank', 'cash', 'card', 'other') DEFAULT 'mpesa',
    `payment_reference` VARCHAR(255) NULL COMMENT 'Payment reference/receipt',
    `expense_date` DATE NOT NULL COMMENT 'Date expense occurred',
    `receipt_path` VARCHAR(500) NULL COMMENT 'Receipt image/document path',
    `status` ENUM('pending', 'paid', 'reimbursed') DEFAULT 'paid',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_expenses_user_date` (`user_id`, `expense_date`),
    INDEX `idx_expenses_category` (`category_id`),
    INDEX `idx_expenses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.6 Expense Categories Table
**Purpose**: Categorize expenses for reporting

```sql
CREATE TABLE `expense_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'NULL = system default, otherwise tenant-specific',
    `name` VARCHAR(100) NOT NULL COMMENT 'Category name',
    `description` TEXT NULL,
    `color` VARCHAR(7) NULL COMMENT 'Hex color for UI',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_expense_categories_user` (`user_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.7 Recurring Invoices Table
**Purpose**: Manage recurring invoice templates

```sql
CREATE TABLE `recurring_invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'Recurring invoice name',
    `frequency` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL COMMENT 'NULL = no end date',
    `next_invoice_date` DATE NOT NULL COMMENT 'Next invoice generation date',
    `invoice_template` JSON NOT NULL COMMENT 'Invoice template data (items, amounts, etc.)',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    INDEX `idx_recurring_user_active` (`user_id`, `is_active`),
    INDEX `idx_recurring_next_date` (`next_invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.8 Payment Reconciliation Table
**Purpose**: Track payment-to-invoice matching

```sql
CREATE TABLE `payment_reconciliations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `invoice_id` BIGINT UNSIGNED NULL COMMENT 'NULL if unmatched',
    `match_type` ENUM('auto', 'manual') DEFAULT 'auto',
    `match_confidence` ENUM('high', 'medium', 'low') NULL COMMENT 'For auto-matches',
    `matched_by` BIGINT UNSIGNED NULL COMMENT 'User ID who matched (NULL for auto)',
    `matched_at` TIMESTAMP NULL COMMENT 'When match occurred',
    `amount_matched` DECIMAL(15, 2) NOT NULL COMMENT 'Amount matched',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`matched_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_reconciliation_payment` (`payment_id`),
    INDEX `idx_reconciliation_invoice` (`invoice_id`),
    INDEX `idx_reconciliation_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Business Logic Design

### 2.1 Invoice Number Generation
**Format**: `INV-{YEAR}-{SEQUENCE}` (e.g., `INV-2026-001`)
- Per-tenant sequence
- Auto-increment per year
- Reset sequence each year

### 2.2 Payment Matching Logic
**Auto-Matching Rules** (priority order):
1. **Reference Number Match**: Payment `AccountReference` matches invoice `reference`
2. **Amount Match**: Payment amount exactly matches invoice balance
3. **Phone Number Match**: Payment phone matches customer phone
4. **Date Proximity**: Payment date within 7 days of invoice due date

**Manual Matching**:
- Admin can manually link payments to invoices
- Supports partial payments
- Supports overpayments (credit balance)

### 2.3 Invoice Status Flow
```
draft → sent → viewed → paid/partial/overdue
  ↓       ↓
cancelled
```

**Status Transitions**:
- `draft` → `sent`: Invoice emailed/SMS sent
- `sent` → `viewed`: Customer viewed invoice
- `sent/viewed` → `paid`: Full payment received
- `sent/viewed` → `partial`: Partial payment received
- `sent/viewed` → `overdue`: Due date passed, not paid
- Any → `cancelled`: Invoice cancelled

### 2.4 Financial Calculations

**Invoice Totals**:
```
subtotal = sum(item.quantity * item.unit_price - item.discount)
tax_amount = subtotal * (tax_rate / 100)
total_amount = subtotal + tax_amount - discount_amount
balance = total_amount - paid_amount
```

**Cash Flow**:
```
Income = sum(payments.amount WHERE payment_date BETWEEN start AND end)
Expenses = sum(expenses.total_amount WHERE expense_date BETWEEN start AND end)
Net Cash Flow = Income - Expenses
```

**Profit & Loss**:
```
Revenue = sum(invoices.total_amount WHERE status = 'paid')
Cost of Goods Sold = sum(expenses.total_amount WHERE category = 'COGS')
Gross Profit = Revenue - COGS
Operating Expenses = sum(expenses.total_amount WHERE category != 'COGS')
Net Profit = Gross Profit - Operating Expenses
```

---

## 3. Service Layer Architecture

### 3.1 InvoiceService
**Responsibilities**:
- Create/update/delete invoices
- Generate invoice numbers
- Calculate invoice totals
- Generate PDF invoices
- Send invoices (email/SMS)
- Update invoice status
- Handle recurring invoices

**Key Methods**:
```php
class InvoiceService {
    public function create(array $data): Invoice
    public function update(Invoice $invoice, array $data): Invoice
    public function delete(Invoice $invoice): bool
    public function generateInvoiceNumber(User $user, int $year): string
    public function calculateTotals(Invoice $invoice): array
    public function generatePdf(Invoice $invoice): string
    public function sendInvoice(Invoice $invoice, string $method = 'email'): bool
    public function markAsPaid(Invoice $invoice, float $amount): Invoice
    public function markAsOverdue(Invoice $invoice): Invoice
    public function createRecurringInvoice(RecurringInvoice $template): Invoice
}
```

### 3.2 PaymentReconciliationService
**Responsibilities**:
- Auto-match payments to invoices
- Manual payment matching
- Unmatch payments
- Handle partial payments
- Generate reconciliation reports

**Key Methods**:
```php
class PaymentReconciliationService {
    public function autoMatchPayment(Payment $payment): ?Invoice
    public function matchPaymentToInvoice(Payment $payment, Invoice $invoice, float $amount): bool
    public function unmatchPayment(Payment $payment): bool
    public function getUnmatchedPayments(User $user, array $filters = []): Collection
    public function getUnpaidInvoices(User $user, array $filters = []): Collection
    public function generateReconciliationReport(User $user, Carbon $startDate, Carbon $endDate): array
}
```

### 3.3 FinancialReportService
**Responsibilities**:
- Generate P&L statements
- Generate cash flow statements
- Generate balance sheets
- Generate tax reports
- Calculate financial metrics

**Key Methods**:
```php
class FinancialReportService {
    public function generateProfitLoss(User $user, Carbon $startDate, Carbon $endDate): array
    public function generateCashFlow(User $user, Carbon $startDate, Carbon $endDate): array
    public function generateBalanceSheet(User $user, Carbon $date): array
    public function generateTaxReport(User $user, int $year): array
    public function getFinancialMetrics(User $user, Carbon $startDate, Carbon $endDate): array
}
```

### 3.4 ExpenseService
**Responsibilities**:
- Create/update/delete expenses
- Categorize expenses
- Track expense receipts
- Generate expense reports

**Key Methods**:
```php
class ExpenseService {
    public function create(array $data): Expense
    public function update(Expense $expense, array $data): Expense
    public function delete(Expense $expense): bool
    public function categorize(Expense $expense, ExpenseCategory $category): Expense
    public function uploadReceipt(Expense $expense, UploadedFile $file): string
    public function getExpensesByCategory(User $user, Carbon $startDate, Carbon $endDate): Collection
}
```

---

## 4. Integration Points

### 4.1 Payment Webhook Integration
**Enhancement**: After payment is processed, attempt auto-reconciliation

```php
// In PaymentIngestionService or PaymentWebhookController
public function processPayment(array $payload): Payment {
    // Existing payment processing...
    $payment = $this->createPayment($payload);
    
    // NEW: Attempt auto-reconciliation
    $reconciliationService = app(PaymentReconciliationService::class);
    $invoice = $reconciliationService->autoMatchPayment($payment);
    
    if ($invoice) {
        // Update invoice status
        $invoiceService = app(InvoiceService::class);
        $invoiceService->markAsPaid($invoice, $payment->amount);
    }
    
    return $payment;
}
```

### 4.2 SMS Integration
**Enhancement**: Send invoice reminders and payment confirmations

- Invoice sent → SMS notification
- Invoice overdue → SMS reminder
- Payment received → SMS confirmation with invoice link

---

## 5. UI/UX Design Considerations

### 5.1 Invoice Management
- **List View**: Table with filters (status, date range, customer)
- **Create/Edit**: Form with line items, tax calculation, preview
- **View**: Invoice preview with PDF download, payment history
- **Send**: Modal with email/SMS options

### 5.2 Financial Dashboard
- **Overview Cards**: Total revenue, expenses, profit, cash flow
- **Charts**: Revenue trend, expense breakdown, profit margin
- **Recent Activity**: Latest payments, invoices, expenses
- **Quick Actions**: Create invoice, add expense, view reports

### 5.3 Payment Reconciliation
- **Unmatched Payments**: List with suggested matches
- **Unpaid Invoices**: List with payment status
- **Manual Matching**: Drag-and-drop or form-based matching
- **Reconciliation Report**: Summary of matched/unmatched items

---

## 6. PDF Invoice Generation

### 6.1 Template Design
- Professional header with company logo
- Invoice number and dates
- Customer information
- Line items table
- Totals section
- Payment terms and notes
- Footer with company details

### 6.2 Library Choice
- **Option 1**: DomPDF (Laravel-friendly, HTML to PDF)
- **Option 2**: TCPDF (More control, steeper learning curve)
- **Option 3**: Snappy (wkhtmltopdf wrapper, best quality)

**Recommendation**: Start with DomPDF for simplicity, upgrade to Snappy if needed.

---

## 7. Security Considerations

### 7.1 Tenant Isolation
- All queries must filter by `user_id`
- Invoice numbers must be tenant-unique
- PDF access must verify tenant ownership

### 7.2 Data Validation
- Invoice amounts must be positive
- Payment matching amounts cannot exceed invoice balance
- Date validations (due date >= issue date)

### 7.3 Audit Trail
- Log all invoice status changes
- Log payment matching/unmatching
- Track who sent invoices
- Track PDF downloads

---

## 8. Performance Considerations

### 8.1 Caching
- Cache financial metrics (daily refresh)
- Cache invoice PDFs (regenerate on update)
- Cache customer list (frequently accessed)

### 8.2 Database Indexes
- Index on `invoices.user_id, status, due_date` for filtering
- Index on `payments.transaction_id` for matching
- Index on `invoice_payments.invoice_id, payment_id` for lookups

### 8.3 Query Optimization
- Eager load relationships (customer, items, payments)
- Use database aggregations for totals
- Paginate large result sets

---

## 9. Testing Strategy

### 9.1 Unit Tests
- Invoice number generation
- Total calculations
- Payment matching logic
- Status transitions

### 9.2 Integration Tests
- Invoice creation flow
- Payment matching flow
- PDF generation
- Email/SMS sending

### 9.3 Feature Tests
- Invoice CRUD operations
- Payment reconciliation
- Financial report generation

---

## 10. Migration Strategy

### 10.1 Data Migration
- Existing payments can be manually matched to invoices
- No breaking changes to existing payment system
- Gradual rollout (feature flag)

### 10.2 Backward Compatibility
- Existing payment webhook continues to work
- Existing payment records remain unchanged
- New features are additive, not replacing

---

## Next Steps

1. ✅ Create database migrations
2. ✅ Create models with relationships
3. ✅ Create services with business logic
4. ✅ Create controllers with API endpoints
5. ✅ Create views with UI
6. ✅ Integrate with payment webhook
7. ✅ Add PDF generation
8. ✅ Add email/SMS sending
9. ✅ Create financial dashboard
10. ✅ Write tests

---

*This design is comprehensive and ready for implementation.*
