# Message Templating & Rules Engine Design

## Overview

A flexible, secure message templating system that allows clients to customize SMS notifications with variable substitution, while enforcing business rules for SMS sending.

## Architecture Principles

1. **Security First**: No code execution in templates
2. **Flexibility**: Support multiple template types and variables
3. **Rule-Based**: Enable/disable at multiple levels
4. **Performance**: Fast template rendering
5. **Extensibility**: Easy to add new variables and rules

---

## 1. Template Storage Design

### Database Schema

**Existing `sms_templates` table:**
```sql
CREATE TABLE `sms_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'NULL = global template',
    `name` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL COMMENT 'Template with {variables}',
    `placeholders` JSON NULL COMMENT 'Available placeholders',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `is_default` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL
);
```

**Enhanced `merchants` table:**
```sql
ALTER TABLE `merchants` ADD COLUMN `sms_enabled` BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE `merchants` ADD COLUMN `sms_template_id` BIGINT UNSIGNED NULL;
```

**Enhanced `users` table:**
```sql
ALTER TABLE `users` ADD COLUMN `sms_enabled` BOOLEAN NOT NULL DEFAULT TRUE;
```

### Template Hierarchy

1. **Merchant-Specific Template** (highest priority)
   - `merchants.sms_template_id` → `sms_templates.id`
   
2. **User Default Template**
   - `sms_templates` where `user_id = X` AND `is_default = TRUE`
   
3. **Global Default Template**
   - `sms_templates` where `user_id IS NULL` AND `is_default = TRUE`

---

## 2. Template Variable System

### Supported Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `{amount}` | Payment amount | `KES 1,234.56` |
| `{amount_raw}` | Raw amount (no formatting) | `1234.56` |
| `{currency}` | Currency code | `KES` |
| `{receipt}` | Receipt number | `ABC123XYZ` |
| `{transaction_id}` | Transaction ID | `TXN123456` |
| `{account_name}` | Merchant account name | `My Business` |
| `{account_number}` | Paybill/Till number | `123456` |
| `{payer_name}` | Payer full name | `John Doe` |
| `{payer_phone}` | Payer phone number | `254712345678` |
| `{reference}` | Customer reference | `INV001` |
| `{date}` | Transaction date | `26/01/2024` |
| `{time}` | Transaction time | `14:30` |
| `{datetime}` | Full date and time | `26/01/2024 14:30` |
| `{description}` | Transaction description | `Payment for invoice` |

### Variable Formatting

- **Amount**: Auto-formatted with currency and thousand separators
- **Date**: Configurable format (default: `d/m/Y`)
- **Time**: Configurable format (default: `H:i`)
- **Phone**: Masked for privacy (optional)

---

## 3. Template Rendering Logic

### Rendering Service

**Location**: `app/Services/SmsTemplateService.php`

**Responsibilities:**
- Resolve template hierarchy
- Extract variables from payment data
- Render template with variable substitution
- Validate template syntax
- Sanitize output

### Rendering Flow

```
Payment Received
    ↓
Get Template (hierarchy resolution)
    ↓
Extract Variables from Payment
    ↓
Validate Template Syntax
    ↓
Render Template (safe substitution)
    ↓
Sanitize Output
    ↓
Return Final Message
```

---

## 4. Rules Engine

### Rule Evaluation Flow

```
Should Send SMS?
    ↓
Check User SMS Enabled? → No → Skip SMS
    ↓ Yes
Check Merchant SMS Enabled? → No → Skip SMS
    ↓ Yes
Check Template Exists? → No → Use Default Template
    ↓ Yes
Check Template Active? → No → Use Default Template
    ↓ Yes
Render Template
    ↓
Send SMS
```

### Rule Levels

1. **Global Level**: System-wide SMS feature toggle
2. **User Level**: `users.sms_enabled` (per client)
3. **Merchant Level**: `merchants.sms_enabled` (per account)
4. **Template Level**: `sms_templates.is_active` (per template)

---

## 5. Service Class Design

### SmsTemplateService

**Extensible Design:**
- Pluggable variable extractors
- Custom formatters
- Template validators
- Rule evaluators

**Methods:**
- `resolveTemplate()` - Get template from hierarchy
- `extractVariables()` - Get variables from payment
- `render()` - Render template with variables
- `validate()` - Validate template syntax
- `shouldSendSms()` - Evaluate rules

---

## 6. Security Considerations

### Safe Template Rendering

1. **Whitelist Variables**: Only allow predefined variables
2. **No Code Execution**: No PHP code in templates
3. **Input Sanitization**: Escape special characters
4. **Length Limits**: Prevent SMS length abuse
5. **Variable Validation**: Validate variable values

### Template Validation

- Check for unknown variables
- Validate variable syntax `{variable_name}`
- Prevent nested variables
- Limit template length
- Check for injection attempts

---

## 7. Extensibility Points

### Adding New Variables

1. Add to `PaymentDto` or extractor
2. Register in `SmsTemplateService`
3. Add formatter if needed
4. Update documentation

### Adding New Rules

1. Create rule evaluator class
2. Register in rules engine
3. Add to evaluation flow
4. Update database schema if needed

---

## 8. Performance Considerations

- **Template Caching**: Cache resolved templates
- **Variable Extraction**: Lazy loading
- **Validation**: Pre-validate templates on save
- **Rendering**: Fast string replacement

---

## 9. Example Templates

### Default Template
```
Payment of {amount} received. Receipt: {receipt}. Account: {account_name}. Time: {datetime}. Thank you!
```

### Custom Template
```
Hi {payer_name}! We received KES {amount_raw} for {reference} on {date} at {time}. Receipt: {receipt}. Thanks!
```

### Minimal Template
```
KES {amount} received. Ref: {receipt}
```

---

## 10. API Design

### Template Management

- `GET /api/templates` - List templates
- `POST /api/templates` - Create template
- `PUT /api/templates/{id}` - Update template
- `DELETE /api/templates/{id}` - Delete template
- `POST /api/templates/{id}/preview` - Preview template

### Settings Management

- `PUT /api/settings/sms` - Update SMS settings
- `PUT /api/merchants/{id}/sms` - Update merchant SMS settings
