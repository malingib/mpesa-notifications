# Authentication & Multi-Tenancy Design

## Overview

This document describes the authentication and multi-tenancy architecture for the Talksasa Payment Notifications System. The design ensures strict tenant isolation, secure API token authentication, role-based access control, and per-client rate limiting.

---

## 1. Authentication Flow

### 1.1 API Token Authentication Flow

```
Client Request
    ↓
API Token in Header (X-API-Token: tks_xxxxx)
    ↓
TokenAuthenticationMiddleware
    ↓
Validate Token (check hash, expiry, active status)
    ↓
Load User/Tenant from token
    ↓
Set Tenant Context (user_id in request)
    ↓
TenantIsolationMiddleware
    ↓
Enforce Tenant Scope (all queries filtered by user_id)
    ↓
Process Request
```

### 1.2 Token Generation Flow

```
Admin Creates Token
    ↓
Generate Random Token (32 bytes)
    ↓
Store Prefix (first 8 chars) + Hash (SHA-256)
    ↓
Associate with User + Scopes
    ↓
Return Full Token to Admin (only shown once)
    ↓
Client Uses Token in API Requests
```

### 1.3 Webhook Authentication Flow

```
M-Pesa Webhook Request
    ↓
WebhookSecurityMiddleware (optional secret validation)
    ↓
Resolve Tenant from Payment Account (account_type + account_number)
    ↓
Set Tenant Context
    ↓
Process Payment (tenant automatically scoped)
```

---

## 2. Database Schema

### 2.1 Enhanced `users` Table

```sql
ALTER TABLE `users` ADD COLUMN `role` ENUM('admin', 'client') NOT NULL DEFAULT 'client';
ALTER TABLE `users` ADD COLUMN `rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 60;
ALTER TABLE `users` ADD COLUMN `rate_limit_per_hour` INT UNSIGNED NOT NULL DEFAULT 1000;
```

### 2.2 Enhanced `api_tokens` Table

Already exists, but ensure it has:
- `token` (hashed SHA-256)
- `token_prefix` (first 8 chars for quick lookup)
- `scopes` (JSON array of permissions)
- `expires_at` (optional expiration)
- `last_used_at` (usage tracking)
- `is_active` (revocation flag)

### 2.3 New `roles` Table (Optional - for complex RBAC)

```sql
CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Role name (admin, client, etc.)',
    `description` TEXT NULL,
    `permissions` JSON NOT NULL COMMENT 'Array of permission strings',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default roles
INSERT INTO `roles` (`name`, `description`, `permissions`) VALUES
('admin', 'System administrator', '["*"]'),
('client', 'Client user', '["payments:read", "payments:export", "merchants:read", "sms:read"]');
```

### 2.4 New `rate_limits` Table (Track Rate Limiting)

```sql
CREATE TABLE `rate_limits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `api_token_id` BIGINT UNSIGNED NULL COMMENT 'NULL = user-level, otherwise token-level',
    `identifier` VARCHAR(255) NOT NULL COMMENT 'IP address or token prefix',
    `endpoint` VARCHAR(255) NULL COMMENT 'Specific endpoint (NULL = all)',
    `requests_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_start` TIMESTAMP NOT NULL COMMENT 'Start of rate limit window',
    `window_type` ENUM('minute', 'hour', 'day') NOT NULL DEFAULT 'minute',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`api_token_id`) REFERENCES `api_tokens`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_rate_limit` (`user_id`, `api_token_id`, `identifier`, `endpoint`, `window_type`, `window_start`),
    INDEX `idx_rate_limit_user` (`user_id`, `window_start`),
    INDEX `idx_rate_limit_token` (`api_token_id`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Laravel Middleware Design

### 3.1 Middleware Stack

```
Request
    ↓
TrustProxies
    ↓
HandleCors
    ↓
WebhookSecurityMiddleware (for webhook routes only)
    ↓
TokenAuthenticationMiddleware (for API routes)
    ↓
TenantIsolationMiddleware (sets tenant context)
    ↓
RoleAuthorizationMiddleware (checks permissions)
    ↓
RateLimitMiddleware (enforces rate limits)
    ↓
Controller
```

### 3.2 TokenAuthenticationMiddleware

**Purpose**: Authenticate requests using API tokens

**Location**: `app/Http/Middleware/TokenAuthenticationMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TokenAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
    {
        // Skip for webhook routes (they use different auth)
        if ($request->is('api/webhooks/*')) {
            return $next($request);
        }

        // Get token from header
        $token = $request->header('X-API-Token') 
              ?? $request->header('Authorization') 
              ?? $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'API token required',
            ], 401);
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);
        $tokenPrefix = substr($token, 0, 8);

        // Find token by prefix (fast lookup)
        $apiToken = ApiToken::where('token_prefix', $tokenPrefix)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$apiToken) {
            Log::warning('Invalid API token prefix', ['prefix' => $tokenPrefix]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API token',
            ], 401);
        }

        // Verify token hash
        if (!Hash::check($token, $apiToken->token)) {
            Log::warning('API token hash mismatch', [
                'token_id' => $apiToken->id,
                'user_id' => $apiToken->user_id,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API token',
            ], 401);
        }

        // Check expiration
        if ($apiToken->expires_at && $apiToken->expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'API token has expired',
            ], 401);
        }

        // Load user
        $user = $apiToken->user;
        if (!$user || !$user->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'User account is inactive',
            ], 403);
        }

        // Update last used timestamp
        $apiToken->update(['last_used_at' => now()]);

        // Set user and token in request
        $request->merge([
            'user_id' => $user->id,
            'tenant_id' => $user->id, // Alias for clarity
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
```

### 3.3 TenantIsolationMiddleware

**Purpose**: Enforce tenant isolation by setting tenant context

**Location**: `app/Http/Middleware/TenantIsolationMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
    {
        // Get tenant ID from request (set by TokenAuthenticationMiddleware)
        $tenantId = $request->get('user_id') ?? $request->get('tenant_id');

        if (!$tenantId) {
            // For webhooks, resolve tenant from payment account
            if ($request->is('api/webhooks/*')) {
                $tenantId = $this->resolveTenantFromWebhook($request);
            }

            if (!$tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tenant context required',
                ], 403);
            }
        }

        // Verify tenant exists and is active
        $tenant = User::find($tenantId);
        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or inactive tenant',
            ], 403);
        }

        // Set tenant context globally
        app()->instance('tenant', $tenant);
        config(['app.current_tenant_id' => $tenantId]);

        // Add tenant_id to all database queries via global scope
        // This is handled by model global scopes

        return $next($request);
    }

    /**
     * Resolve tenant from webhook payload (payment account)
     */
    private function resolveTenantFromWebhook(Request $request): ?int
    {
        $payload = $request->all();
        
        // Extract account details from M-Pesa payload
        $accountType = isset($payload['BillRefNumber']) ? 'paybill' : 'till';
        $accountNumber = $accountType === 'paybill' 
            ? ($payload['BusinessShortCode'] ?? null)
            : ($payload['TillNumber'] ?? null);

        if (!$accountNumber) {
            return null;
        }

        // Find merchant and return user_id
        $merchant = \App\Models\Merchant::where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->first();

        return $merchant?->user_id;
    }
}
```

### 3.4 RoleAuthorizationMiddleware

**Purpose**: Check user permissions based on role and token scopes

**Location**: `app/Http/Middleware/RoleAuthorizationMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleAuthorizationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$requiredPermissions): JsonResponse|Request
    {
        $user = $request->user();
        $apiToken = $request->attributes->get('api_token');

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required',
            ], 401);
        }

        // Admins have all permissions
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check token scopes
        $tokenScopes = $apiToken?->scopes ?? [];
        
        // Check if user has required permissions
        $hasPermission = $this->checkPermissions($user, $tokenScopes, $requiredPermissions);

        if (!$hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user/token has required permissions
     */
    private function checkPermissions($user, array $tokenScopes, array $requiredPermissions): bool
    {
        // If no permissions required, allow
        if (empty($requiredPermissions)) {
            return true;
        }

        // Check token scopes
        foreach ($requiredPermissions as $permission) {
            // Wildcard permission
            if (in_array('*', $tokenScopes)) {
                return true;
            }

            // Exact permission match
            if (in_array($permission, $tokenScopes)) {
                continue;
            }

            // Check wildcard scopes (e.g., "payments:*" matches "payments:read")
            $permissionParts = explode(':', $permission);
            if (count($permissionParts) === 2) {
                $wildcardScope = $permissionParts[0] . ':*';
                if (in_array($wildcardScope, $tokenScopes)) {
                    continue;
                }
            }

            // Permission not found
            return false;
        }

        return true;
    }
}
```

### 3.5 RateLimitMiddleware

**Purpose**: Enforce rate limiting per client/token

**Location**: `app/Http/Middleware/RateLimitMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\RateLimit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
    {
        $user = $request->user();
        $apiToken = $request->attributes->get('api_token');

        if (!$user) {
            return $next($request);
        }

        // Get rate limits from user or token
        $limits = $this->getRateLimits($user, $apiToken);
        $identifier = $this->getIdentifier($request, $apiToken);

        // Check per-minute limit
        if (!$this->checkRateLimit($identifier, $limits['per_minute'], 'minute')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded (per minute)',
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limits['per_minute'],
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => 60,
            ]);
        }

        // Check per-hour limit
        if (!$this->checkRateLimit($identifier, $limits['per_hour'], 'hour')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded (per hour)',
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limits['per_hour'],
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => 3600,
            ]);
        }

        return $next($request);
    }

    /**
     * Get rate limits for user/token
     */
    private function getRateLimits($user, $apiToken): array
    {
        // Token-specific limits (if set) override user limits
        // For now, use user limits
        return [
            'per_minute' => $user->rate_limit_per_minute ?? 60,
            'per_hour' => $user->rate_limit_per_hour ?? 1000,
        ];
    }

    /**
     * Get identifier for rate limiting (token prefix or IP)
     */
    private function getIdentifier(Request $request, $apiToken): string
    {
        if ($apiToken) {
            return 'token:' . $apiToken->token_prefix;
        }

        return 'ip:' . $request->ip();
    }

    /**
     * Check rate limit using cache
     */
    private function checkRateLimit(string $identifier, int $limit, string $window): bool
    {
        $key = "rate_limit:{$identifier}:{$window}:" . now()->format('Y-m-d-H-i');
        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            return false;
        }

        Cache::put($key, $current + 1, now()->addMinutes($window === 'minute' ? 1 : 60));
        return true;
    }
}
```

---

## 4. Global Tenant Isolation Enforcement

### 4.1 Model Global Scope

**Location**: `app/Models/Concerns/HasTenantScope.php`

```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $query, Model $model): void
    {
        $tenantId = $this->getTenantId();

        if ($tenantId) {
            $query->where($model->getTable() . '.user_id', $tenantId);
        }
    }

    /**
     * Get current tenant ID
     */
    protected function getTenantId(): ?int
    {
        // Get from config (set by middleware)
        return config('app.current_tenant_id') 
            ?? request()->get('user_id')
            ?? request()->get('tenant_id');
    }
}
```

### 4.2 Apply to Models

**Example**: `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        // Apply tenant scope globally
        static::addGlobalScope(new TenantScope);
    }

    // ... rest of model
}
```

### 4.3 Repository Pattern with Tenant Enforcement

**Example**: `app/Repositories/PaymentRepository.php`

```php
<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    /**
     * Get payments for current tenant
     * 
     * Note: Global scope automatically filters by user_id
     */
    public function getByTenant(array $filters = []): Collection
    {
        $query = Payment::query(); // Global scope applies automatically

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('transaction_time', '>=', $filters['date_from']);
        }

        return $query->get();
    }

    /**
     * Find payment by ID (tenant-scoped automatically)
     */
    public function findById(int $id): ?Payment
    {
        return Payment::find($id); // Global scope ensures tenant isolation
    }
}
```

### 4.4 Service Layer Enforcement

**Example**: `app/Services/PaymentProcessingService.php`

```php
<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;

class PaymentProcessingService
{
    /**
     * Process payment - tenant is automatically scoped
     */
    public function processPayment(array $payload): ?Payment
    {
        // Find merchant (tenant-scoped by global scope)
        $merchant = Merchant::where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->first(); // Automatically filtered by tenant

        // Create payment (user_id set automatically from tenant context)
        $payment = Payment::create([
            'user_id' => $this->getCurrentTenantId(), // Explicit for clarity
            'merchant_id' => $merchant->id,
            // ... rest of fields
        ]);

        return $payment;
    }

    /**
     * Get current tenant ID
     */
    protected function getCurrentTenantId(): int
    {
        $tenantId = config('app.current_tenant_id')
            ?? request()->get('user_id')
            ?? request()->get('tenant_id');

        if (!$tenantId) {
            throw new \RuntimeException('Tenant context not set');
        }

        return $tenantId;
    }
}
```

---

## 5. API Token Management

### 5.1 Token Generation Service

**Location**: `app/Services/ApiTokenService.php`

```php
<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiTokenService
{
    /**
     * Create new API token
     */
    public function createToken(User $user, array $data): array
    {
        // Generate random token
        $plainToken = 'tks_' . Str::random(32);
        $tokenHash = Hash::make($plainToken);
        $tokenPrefix = substr($plainToken, 0, 8);

        // Create token record
        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'name' => $data['name'] ?? 'API Token',
            'token' => $tokenHash,
            'token_prefix' => $tokenPrefix,
            'scopes' => $data['scopes'] ?? ['payments:read'],
            'expires_at' => isset($data['expires_at']) ? now()->parse($data['expires_at']) : null,
            'is_active' => true,
        ]);

        // Log token creation
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'api.token.created',
            'entity_type' => 'ApiToken',
            'entity_id' => $apiToken->id,
            'description' => "API token '{$apiToken->name}' created for user {$user->email}",
        ]);

        return [
            'token' => $apiToken,
            'plain_token' => $plainToken, // Only shown once!
        ];
    }

    /**
     * Revoke API token
     */
    public function revokeToken(ApiToken $token): bool
    {
        $token->update([
            'is_active' => false,
            'deleted_at' => now(),
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'api.token.revoked',
            'entity_type' => 'ApiToken',
            'entity_id' => $token->id,
            'description' => "API token '{$token->name}' revoked",
        ]);

        return true;
    }

    /**
     * List tokens for user
     */
    public function getTokensForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ApiToken::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

### 5.2 Token Controller

**Location**: `app/Http/Controllers/ApiTokenController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Services\ApiTokenService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiTokenController extends Controller
{
    public function __construct(
        private ApiTokenService $tokenService
    ) {}

    /**
     * Create new API token
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|in:payments:read,payments:write,merchants:read,sms:read,*',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        $user = $request->user();
        $result = $this->tokenService->createToken($user, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => [
                    'id' => $result['token']->id,
                    'name' => $result['token']->name,
                    'scopes' => $result['token']->scopes,
                    'expires_at' => $result['token']->expires_at?->toIso8601String(),
                    'created_at' => $result['token']->created_at->toIso8601String(),
                ],
                'plain_token' => $result['plain_token'], // Show only once!
            ],
        ], 201);
    }

    /**
     * List user's tokens
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $this->tokenService->getTokensForUser($user);

        return response()->json([
            'status' => 'success',
            'data' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'token_prefix' => $token->token_prefix . '...',
                    'scopes' => $token->scopes,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'is_active' => $token->is_active,
                    'created_at' => $token->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Revoke token
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $token = \App\Models\ApiToken::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->tokenService->revokeToken($token);

        return response()->json([
            'status' => 'success',
            'message' => 'Token revoked successfully',
        ]);
    }
}
```

---

## 6. Route Configuration

### 6.1 API Routes

**Location**: `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MpesaWebhookController;

// Public webhook routes (no token auth, but webhook security)
Route::prefix('webhooks/mpesa')->group(function () {
    Route::post('/confirmation', [MpesaWebhookController::class, 'confirmation']);
    Route::post('/validation', [MpesaWebhookController::class, 'validation']);
});

// Authenticated API routes
Route::middleware(['auth:api', 'tenant.isolation', 'role.authorization'])->group(function () {
    
    // API Token Management
    Route::prefix('tokens')->group(function () {
        Route::get('/', [ApiTokenController::class, 'index'])
            ->middleware('permission:api_tokens:read');
        Route::post('/', [ApiTokenController::class, 'store'])
            ->middleware('permission:api_tokens:create');
        Route::delete('/{id}', [ApiTokenController::class, 'destroy'])
            ->middleware('permission:api_tokens:delete');
    });

    // Payments (tenant-scoped automatically)
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('permission:payments:read', 'rate.limit');
        Route::get('/{id}', [PaymentController::class, 'show'])
            ->middleware('permission:payments:read');
    });

    // Merchants (tenant-scoped automatically)
    Route::prefix('merchants')->group(function () {
        Route::get('/', [MerchantController::class, 'index'])
            ->middleware('permission:merchants:read');
    });
});
```

### 6.2 Middleware Registration

**Location**: `bootstrap/app.php` or `app/Http/Kernel.php`

```php
protected $middlewareAliases = [
    'auth.api' => \App\Http\Middleware\TokenAuthenticationMiddleware::class,
    'tenant.isolation' => \App\Http\Middleware\TenantIsolationMiddleware::class,
    'role.authorization' => \App\Http\Middleware\RoleAuthorizationMiddleware::class,
    'permission' => \App\Http\Middleware\RoleAuthorizationMiddleware::class,
    'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
];
```

---

## 7. Security Best Practices

### 7.1 Token Security

1. **Never store plain tokens** - Only store SHA-256 hashes
2. **Show token only once** - Display plain token only during creation
3. **Token prefix lookup** - Use first 8 chars for fast lookup, then verify hash
4. **Expiration support** - Allow token expiration dates
5. **Revocation** - Soft delete tokens (preserve audit trail)

### 7.2 Tenant Isolation Security

1. **Global scopes** - Automatically filter all queries by tenant
2. **Middleware enforcement** - Verify tenant context on every request
3. **Repository pattern** - Centralize data access with tenant scoping
4. **Audit logging** - Log all tenant-scoped operations
5. **Input validation** - Never trust user-provided tenant IDs

### 7.3 Rate Limiting Security

1. **Per-client limits** - Configurable per user
2. **Token-level limits** - Optional per-token limits
3. **IP fallback** - Rate limit by IP if no token
4. **Cache-based** - Use Redis/cache for performance
5. **Headers** - Return rate limit info in response headers

---

## 8. Summary

### Authentication Flow
1. Client sends API token in `X-API-Token` header
2. TokenAuthenticationMiddleware validates token
3. TenantIsolationMiddleware sets tenant context
4. RoleAuthorizationMiddleware checks permissions
5. RateLimitMiddleware enforces limits
6. Controller processes request with tenant scope

### Tenant Isolation
- **Global Scopes**: All models automatically filter by `user_id`
- **Middleware**: Sets tenant context from authenticated user
- **Repositories**: Explicit tenant scoping in data access layer
- **Services**: Verify tenant context before operations

### Key Features
- ✅ API token authentication
- ✅ Strict tenant isolation
- ✅ Role-based access control (admin/client)
- ✅ Token revocation support
- ✅ Per-client rate limiting
- ✅ Comprehensive audit logging
