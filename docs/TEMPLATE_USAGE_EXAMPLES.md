# SMS Template Usage Examples

## Template Variables

Available variables for use in templates:

| Variable | Example Output | Description |
|----------|----------------|-------------|
| `{amount}` | `KES 1,234.56` | Formatted amount with currency |
| `{amount_raw}` | `1234.56` | Raw amount without formatting |
| `{currency}` | `KES` | Currency code |
| `{receipt}` | `ABC123XYZ` | Receipt number |
| `{transaction_id}` | `TXN123456` | Transaction ID |
| `{account_name}` | `My Business` | Merchant account name |
| `{account_number}` | `123456` | Paybill/Till number |
| `{payer_name}` | `John Doe` | Payer full name |
| `{payer_phone}` | `254712345678` | Payer phone number |
| `{reference}` | `INV001` | Customer reference |
| `{date}` | `26/01/2024` | Transaction date |
| `{time}` | `14:30` | Transaction time |
| `{datetime}` | `26/01/2024 14:30` | Full date and time |
| `{description}` | `Payment for invoice` | Transaction description |

## Template Examples

### Default Template
```
Payment of {amount} received. Receipt: {receipt}. Account: {account_name}. Time: {datetime}. Thank you!
```

**Output:**
```
Payment of KES 1,234.56 received. Receipt: ABC123. Account: My Business. Time: 26/01/2024 14:30. Thank you!
```

### Minimal Template
```
KES {amount_raw} received. Ref: {receipt}
```

**Output:**
```
KES 1234.56 received. Ref: ABC123
```

### Personalized Template
```
Hi {payer_name}! We received {amount} for {reference} on {date} at {time}. Receipt: {receipt}. Thanks!
```

**Output:**
```
Hi John Doe! We received KES 1,234.56 for INV001 on 26/01/2024 at 14:30. Receipt: ABC123. Thanks!
```

### Business-Focused Template
```
Thank you {payer_name}! Payment of {amount} confirmed for {reference}. Receipt: {receipt}. Date: {date}.
```

**Output:**
```
Thank you John Doe! Payment of KES 1,234.56 confirmed for INV001. Receipt: ABC123. Date: 26/01/2024.
```

## Template Hierarchy

Templates are resolved in this order:

1. **Merchant-Specific Template** (highest priority)
   - Set via `merchants.sms_template_id`
   - Overrides all other templates

2. **User Default Template**
   - `sms_templates` where `user_id = X` AND `is_default = TRUE`
   - Used if no merchant template

3. **Global Default Template**
   - `sms_templates` where `user_id IS NULL` AND `is_default = TRUE`
   - Fallback if no user template

## API Usage

### Create Template

```bash
curl -X POST https://api.example.com/api/templates \
  -H "X-API-Token: tks_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Custom Template",
    "message": "Hi {payer_name}! Payment of {amount} received. Receipt: {receipt}.",
    "is_default": true
  }'
```

### Preview Template

```bash
curl -X GET https://api.example.com/api/templates/1/preview \
  -H "X-API-Token: tks_YOUR_TOKEN"
```

### Update Template

```bash
curl -X PUT https://api.example.com/api/templates/1 \
  -H "X-API-Token: tks_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Updated message: {amount} received. Ref: {receipt}."
  }'
```

## Rules Engine

### Enable/Disable SMS

**User Level:**
```sql
UPDATE users SET sms_enabled = FALSE WHERE id = 1;
```

**Merchant Level:**
```sql
UPDATE merchants SET sms_enabled = FALSE WHERE id = 1;
```

### Rule Evaluation Order

1. User SMS enabled? → No → Skip
2. Merchant SMS enabled? → No → Skip
3. Payment completed? → No → Skip
4. SMS already sent? → Yes → Skip
5. Render template and send

## Security

- ✅ No code execution in templates
- ✅ Variable whitelist only
- ✅ Input sanitization
- ✅ Template validation on save
- ✅ Length limits enforced

## Best Practices

1. **Keep templates short** - SMS has 160 character limit
2. **Use clear variables** - Make it obvious what each variable represents
3. **Test templates** - Use preview endpoint before setting as default
4. **Monitor length** - Check message length in preview
5. **Set defaults** - Mark one template as default per user
