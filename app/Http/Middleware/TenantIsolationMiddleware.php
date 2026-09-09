<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Isolation Middleware
 * 
 * Enforces tenant isolation by setting tenant context globally.
 * For webhooks, resolves tenant from payment account.
 */
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

        Log::debug('Tenant context set', [
            'tenant_id' => $tenantId,
            'tenant_code' => $tenant->code,
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }

    /**
     * Resolve tenant from webhook payload (payment account)
     */
    private function resolveTenantFromWebhook(Request $request): ?int
    {
        $payload = $request->all();
        
        Log::info('Resolving tenant from webhook payload', [
            'payload_keys' => array_keys($payload),
            'url' => $request->fullUrl(),
        ]);
        
        // Extract account details from M-Pesa payload
        // Check for TillNumber first (more common), then BusinessShortCode
        $accountType = null;
        $accountNumber = null;
        
        if (isset($payload['TillNumber']) && !empty($payload['TillNumber'])) {
            $accountType = 'till';
            $accountNumber = $payload['TillNumber'];
        } elseif (isset($payload['BusinessShortCode']) && !empty($payload['BusinessShortCode'])) {
            $accountType = 'paybill';
            $accountNumber = $payload['BusinessShortCode'];
        }

        if (!$accountNumber || !$accountType) {
            Log::warning('Could not extract account number from webhook', [
                'payload_keys' => array_keys($payload),
                'payload' => $payload,
            ]);
            return null;
        }

        Log::info('Looking up merchant', [
            'account_type' => $accountType,
            'account_number' => $accountNumber,
        ]);

        // Find merchant and return user_id
        // Temporarily disable global scope to find merchant
        $merchant = Merchant::withoutGlobalScopes()
            ->where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->first();

        if (!$merchant) {
            Log::warning('Merchant not found for webhook', [
                'account_type' => $accountType,
                'account_number' => $accountNumber,
                'available_merchants' => Merchant::withoutGlobalScopes()
                    ->select('account_type', 'account_number', 'is_active')
                    ->get()
                    ->toArray(),
            ]);
            return null;
        }

        Log::info('Merchant found for webhook', [
            'merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'account_type' => $accountType,
            'account_number' => $accountNumber,
        ]);

        return $merchant->user_id;
    }
}
