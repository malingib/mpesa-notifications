<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SmsFailure;
use Illuminate\Support\Facades\Log;

/**
 * SMS Failure Tracking Service
 * 
 * Tracks SMS failures for analytics and monitoring.
 * Optional service for detailed failure tracking.
 */
class SmsFailureTrackingService
{
    /**
     * Record SMS failure
     */
    public function recordFailure(
        Payment $payment,
        string $errorMessage,
        ?string $errorCode = null,
        bool $isRetryable = true
    ): SmsFailure {
        $failure = SmsFailure::updateOrCreate(
            [
                'payment_id' => $payment->id,
                'resolved_at' => null, // Only track unresolved failures
            ],
            [
                'user_id' => $payment->user_id,
                'phone_number' => $payment->phone_number,
                'message' => null, // Don't store message for privacy
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'is_retryable' => $isRetryable,
                'attempt_count' => $payment->sms_retry_count,
                'first_failed_at' => $failure->first_failed_at ?? now(),
                'last_failed_at' => now(),
            ]
        );

        Log::info('SMS failure recorded', [
            'failure_id' => $failure->id,
            'payment_id' => $payment->id,
            'is_retryable' => $isRetryable,
        ]);

        return $failure;
    }

    /**
     * Mark failure as resolved
     */
    public function markResolved(int $paymentId): void
    {
        SmsFailure::where('payment_id', $paymentId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /**
     * Get unresolved failures
     */
    public function getUnresolvedFailures(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return SmsFailure::whereNull('resolved_at')
            ->orderBy('last_failed_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
