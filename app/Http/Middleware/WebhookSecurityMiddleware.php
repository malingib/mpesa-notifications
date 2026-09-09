<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Webhook Security Middleware
 * 
 * Validates webhook requests from M-Pesa.
 * Can verify signatures/secrets if configured.
 */
class WebhookSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): JsonResponse|Request
    {
        // Optional: Verify webhook secret if configured
        $webhookSecret = Config::get('mpesa.webhook_secret');
        
        if ($webhookSecret) {
            $providedSecret = $request->header('X-Webhook-Secret');
            
            if ($providedSecret !== $webhookSecret) {
                Log::warning('Webhook request with invalid secret', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }
        }

        // Log webhook request for audit
        Log::info('Webhook request received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);

        return $next($request);
    }
}
