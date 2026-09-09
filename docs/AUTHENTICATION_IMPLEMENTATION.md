# Authentication & Multi-Tenancy Implementation Guide

## Quick Start

### 1. Run Migrations

```bash
php artisan migrate
```

This will create:
- `users` table with role and rate limit fields
- `api_tokens` table (already exists)
- All other tables from the normalized schema

### 2. Create Admin User

```bash
php artisan tinker
```

```php
$admin = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@talksasa.com',
    'password' => \Hash::make('password'),
    'code' => 'ADMIN001',
    'role' => 'admin',
    'is_active' => true,
    'rate_limit_per_minute' => 1000,
    'rate_limit_per_hour' => 100000,
]);
```

### 3. Create Client User

```php
$client = \App\Models\User::create([
    'name' => 'Sample Client',
    'email' => 'client@example.com',
    'password' => \Hash::make('password'),
    'code' => 'CLIENT001',
    'role' => 'client',
    'is_active' => true,
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
]);
```

### 4. Create API Token

```php
$tokenService = new \App\Services\ApiTokenService();
$result = $tokenService->createToken($client, [
    'name' => 'Production API Token',
    'scopes' => ['payments:read', 'payments:write'],
]);

// Save the plain_token - it won't be shown again!
echo $result['plain_token'];
```

### 5. Test API Request

```bash
curl -H "X-API-Token: tks_YOUR_TOKEN_HERE" \
     http://localhost/api/payments
```

## Middleware Stack

The authentication flow uses this middleware stack:

1. **TokenAuthenticationMiddleware** - Validates API token
2. **TenantIsolationMiddleware** - Sets tenant context
3. **RoleAuthorizationMiddleware** - Checks permissions
4. **RateLimitMiddleware** - Enforces rate limits

## Tenant Isolation

### How It Works

1. **Middleware Sets Context**: `TenantIsolationMiddleware` sets `config('app.current_tenant_id')`
2. **Global Scope Filters**: `TenantScope` automatically filters all queries by `user_id`
3. **Repositories Enforce**: Explicit tenant scoping in repository methods

### Example Query

```php
// This query automatically filters by tenant_id
Payment::where('status', 'completed')->get();

// Equivalent to:
Payment::where('user_id', config('app.current_tenant_id'))
    ->where('status', 'completed')
    ->get();
```

## API Token Format

- **Prefix**: `tks_` (Talksasa)
- **Length**: 32 random characters
- **Storage**: SHA-256 hash (never store plain text)
- **Lookup**: First 8 chars after prefix for fast lookup

Example: `tks_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6`

## Permissions/Scopes

Available scopes:
- `payments:read` - Read payments
- `payments:write` - Create/update payments
- `merchants:read` - Read merchants
- `merchants:write` - Create/update merchants
- `sms:read` - Read SMS notifications
- `sms:write` - Send SMS
- `api_tokens:read` - List tokens
- `api_tokens:create` - Create tokens
- `api_tokens:delete` - Revoke tokens
- `*` - All permissions (admin only)

## Rate Limiting

Rate limits are configured per user:
- `rate_limit_per_minute` - Default: 60
- `rate_limit_per_hour` - Default: 1000

Rate limit headers in response:
- `X-RateLimit-Limit-Minute`
- `X-RateLimit-Remaining-Minute`
- `X-RateLimit-Limit-Hour`
- `X-RateLimit-Remaining-Hour`

## Webhook Authentication

Webhooks don't use API tokens. Instead:
1. `WebhookSecurityMiddleware` validates optional secret
2. `TenantIsolationMiddleware` resolves tenant from payment account
3. Payment processing automatically scoped to tenant

## Security Best Practices

1. ✅ **Never log plain tokens** - Only log token prefixes
2. ✅ **Show token only once** - Display plain token only during creation
3. ✅ **Use HTTPS** - Always use HTTPS in production
4. ✅ **Rotate tokens** - Regularly rotate API tokens
5. ✅ **Monitor usage** - Track token usage via `last_used_at`
6. ✅ **Set expiration** - Use `expires_at` for temporary tokens
7. ✅ **Revoke compromised tokens** - Immediately revoke if compromised

## Troubleshooting

### "API token required"
- Ensure `X-API-Token` header is set
- Check token format starts with `tks_`

### "Invalid API token"
- Token may be revoked (`is_active = false`)
- Token may be expired (`expires_at` in past)
- Token hash mismatch (token was modified)

### "Tenant context required"
- Token authentication failed
- Webhook couldn't resolve tenant from account

### "Insufficient permissions"
- Token doesn't have required scope
- User role doesn't have permission
- Check token `scopes` array

### Rate limit exceeded
- Check user's `rate_limit_per_minute` and `rate_limit_per_hour`
- Wait for rate limit window to reset
- Consider increasing limits for high-volume clients
