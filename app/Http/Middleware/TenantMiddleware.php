<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Middleware
 * 
 * Enforces tenant isolation for API requests.
 * Extracts tenant from header and sets context.
 */
class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
    {
        $tenantCode = $request->header('X-Tenant-Code');
        $tenantId = $request->header('X-Tenant-ID');

        // Allow webhook endpoints to bypass tenant check (they resolve tenant from payment account)
        if ($request->is('api/webhooks/*')) {
            return $next($request);
        }

        // Require tenant identification for API endpoints
        if (!$tenantCode && !$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant identification required',
            ], 401);
        }

        // Resolve tenant
        $tenant = null;
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        } elseif ($tenantCode) {
            $tenant = Tenant::where('code', $tenantCode)->first();
        }

        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or inactive tenant',
            ], 403);
        }

        // Set tenant in request for use in controllers/services
        $request->merge(['tenant_id' => $tenant->id]);
        $request->setUserResolver(function () use ($tenant) {
            return $tenant;
        });

        return $next($request);
    }
}
