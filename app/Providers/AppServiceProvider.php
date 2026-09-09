<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->singleton(\App\Repositories\PaymentRepository::class);
        $this->app->singleton(\App\Repositories\PaymentAccountRepository::class);
        $this->app->singleton(\App\Repositories\MerchantRepository::class);
        
        // Register services
        $this->app->singleton(\App\Services\PaymentProcessingService::class);
        $this->app->singleton(\App\Services\PaymentIngestionService::class);
        $this->app->singleton(\App\Services\TalksasaSmsService::class);
        $this->app->singleton(\App\Services\SmsTemplateService::class);
        $this->app->singleton(\App\Services\SmsFailureTrackingService::class);
        $this->app->singleton(\App\Services\CorrelationIdService::class);
        $this->app->singleton(\App\Services\PaymentAuditService::class);
        $this->app->singleton(\App\Services\SmsAttemptService::class);
        $this->app->singleton(\App\Services\AuditLogService::class);
        $this->app->singleton(\App\Services\DataAnonymizationService::class);
        $this->app->singleton(\App\Services\RetentionPolicyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL
        Schema::defaultStringLength(191);
        
        // Prevent lazy loading in production
        Model::preventLazyLoading(!app()->isProduction());
    }
}
