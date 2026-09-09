<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Role Authorization Middleware
 * 
 * Checks user permissions based on role and token scopes.
 * Admins have all permissions, clients are restricted by scopes.
 */
class RoleAuthorizationMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * @param string ...$requiredPermissions Permission strings (e.g., 'payments:read')
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
        
        // If no permissions required, allow
        if (empty($requiredPermissions)) {
            return $next($request);
        }

        // Check if user has required permissions
        $hasPermission = $this->checkPermissions($user, $tokenScopes, $requiredPermissions);

        if (!$hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions',
                'required' => $requiredPermissions,
                'granted' => $tokenScopes,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user/token has required permissions
     */
    private function checkPermissions($user, array $tokenScopes, array $requiredPermissions): bool
    {
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
