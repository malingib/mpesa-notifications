<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rate Limit Middleware
 * 
 * Enforces rate limiting per client/token.
 * Uses cache for tracking requests per minute/hour.
 */
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

        // Get rate limits from user
        $limits = [
            'per_minute' => $user->rate_limit_per_minute ?? 60,
            'per_hour' => $user->rate_limit_per_hour ?? 1000,
        ];

        $identifier = $this->getIdentifier($request, $apiToken);

        // Check per-minute limit
        $minuteKey = "rate_limit:{$identifier}:minute:" . now()->format('Y-m-d-H-i');
        $minuteCount = Cache::get($minuteKey, 0);

        if ($minuteCount >= $limits['per_minute']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded (per minute)',
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limits['per_minute'],
                'X-RateLimit-Remaining' => max(0, $limits['per_minute'] - $minuteCount),
                'Retry-After' => 60,
            ]);
        }

        // Check per-hour limit
        $hourKey = "rate_limit:{$identifier}:hour:" . now()->format('Y-m-d-H');
        $hourCount = Cache::get($hourKey, 0);

        if ($hourCount >= $limits['per_hour']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded (per hour)',
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limits['per_hour'],
                'X-RateLimit-Remaining' => max(0, $limits['per_hour'] - $hourCount),
                'Retry-After' => 3600,
            ]);
        }

        // Increment counters
        Cache::put($minuteKey, $minuteCount + 1, now()->addMinute());
        Cache::put($hourKey, $hourCount + 1, now()->addHour());

        // Add rate limit headers to response
        $response = $next($request);
        
        if ($response instanceof \Illuminate\Http\Response || $response instanceof JsonResponse) {
            $response->headers->set('X-RateLimit-Limit-Minute', $limits['per_minute']);
            $response->headers->set('X-RateLimit-Remaining-Minute', max(0, $limits['per_minute'] - $minuteCount - 1));
            $response->headers->set('X-RateLimit-Limit-Hour', $limits['per_hour']);
            $response->headers->set('X-RateLimit-Remaining-Hour', max(0, $limits['per_hour'] - $hourCount - 1));
        }

        return $response;
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
}
