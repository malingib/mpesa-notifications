<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Token Authentication Middleware
 * 
 * Authenticates API requests using API tokens from X-API-Token header.
 * Sets user context and tenant context for subsequent middleware.
 */
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
        
        // Validate token format (should start with tks_)
        if (!str_starts_with($token, 'tks_')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API token format',
            ], 401);
        }

        $tokenPrefix = substr($token, 4, 8); // Skip 'tks_' prefix, get next 8 chars

        // Find token by prefix (fast lookup)
        $apiToken = ApiToken::where('token_prefix', $tokenPrefix)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with('user')
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
