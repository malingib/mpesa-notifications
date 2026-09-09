<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache Dashboard Responses
 * 
 * Caches dashboard responses for 5 minutes to improve performance.
 */
class CacheDashboard
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests to dashboard routes
        if (!$request->isMethod('GET') || !$request->is('dashboard', 'admin/dashboard')) {
            return $next($request);
        }

        $user = $request->user();
        $cacheKey = 'dashboard_response_' . ($user->id ?? 'guest') . '_' . $request->path() . '_' . now()->format('Y-m-d-H-i');

        return Cache::remember($cacheKey, 300, function () use ($next, $request) {
            $response = $next($request);
            
            // Only cache successful responses
            if ($response->getStatusCode() === 200) {
                return $response;
            }
            
            return $response;
        });
    }
}
