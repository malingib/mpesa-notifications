<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaWebhookController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\SmsTemplateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Public webhook routes (no token auth, no tenant isolation, but webhook security)
// Webhooks bypass tenant isolation - they resolve tenant from payment account
Route::prefix('webhooks/mpesa')->withoutMiddleware([\App\Http\Middleware\TenantIsolationMiddleware::class])->group(function () {
    // New unified payment webhook endpoint
    Route::post('/payment', [PaymentWebhookController::class, 'handle'])
        ->name('webhooks.mpesa.payment')
        ->middleware('throttle:100,1'); // Rate limit: 100 requests per minute
    
    // GET handler for testing and M-Pesa validation (if they send GET)
    Route::get('/payment', [PaymentWebhookController::class, 'test'])
        ->name('webhooks.mpesa.payment.test');
    
    // Legacy endpoints (for backward compatibility)
    Route::post('/confirmation', [MpesaWebhookController::class, 'confirmation'])
        ->name('webhooks.mpesa.confirmation');
    
    Route::post('/validation', [MpesaWebhookController::class, 'validation'])
        ->name('webhooks.mpesa.validation');
    
    // GET handlers for legacy endpoints (for testing)
    Route::get('/confirmation', [MpesaWebhookController::class, 'test']);
    Route::get('/validation', [MpesaWebhookController::class, 'test']);
});

// Authenticated API routes
Route::middleware(['auth.api', 'tenant.isolation', 'role.authorization'])->group(function () {
    
    // API Token Management
    Route::prefix('tokens')->group(function () {
        Route::get('/', [ApiTokenController::class, 'index'])
            ->middleware('permission:api_tokens:read');
        Route::post('/', [ApiTokenController::class, 'store'])
            ->middleware('permission:api_tokens:create');
        Route::delete('/{id}', [ApiTokenController::class, 'destroy'])
            ->middleware('permission:api_tokens:delete');
    });

    // Payments (tenant-scoped automatically via global scope)
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('permission:payments:read', 'rate.limit');
        Route::get('/{id}', [PaymentController::class, 'show'])
            ->middleware('permission:payments:read');
    });

    // SMS Templates
    Route::prefix('templates')->group(function () {
        Route::get('/', [SmsTemplateController::class, 'index'])
            ->middleware('permission:sms:read');
        Route::post('/', [SmsTemplateController::class, 'store'])
            ->middleware('permission:sms:write');
        Route::get('/{id}/preview', [SmsTemplateController::class, 'preview'])
            ->middleware('permission:sms:read');
        Route::put('/{id}', [SmsTemplateController::class, 'update'])
            ->middleware('permission:sms:write');
        Route::delete('/{id}', [SmsTemplateController::class, 'destroy'])
            ->middleware('permission:sms:write');
    });
});

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');
