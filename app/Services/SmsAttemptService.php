<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SmsAttempt;
use Illuminate\Support\Facades\Log;

/**
 * SMS Attempt Service
 * 
 * Tracks every SMS send attempt for complete audit trail.
 */
class SmsAttemptService
{
    /**
     * Record SMS attempt start
     */
    public function recordAttempt(
        Payment $payment,
        string $phoneNumber,
        ?string $message = null,
        ?string $jobId = null,
        int $attemptNumber = 1
    ): SmsAttempt {
        return SmsAttempt::create([
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'correlation_id' => $payment->correlation_id,
            'job_id' => $jobId,
            'attempt_number' => $attemptNumber,
            'phone_number' => $phoneNumber,
            'message' => $message, // Optional for privacy
            'message_length' => $message ? strlen($message) : null,
            'status' => 'pending',
        ]);
    }

    /**
     * Mark SMS attempt as sent
     */
    public function markSent(
        SmsAttempt $attempt,
        ?array $providerResponse = null
    ): void {
        $attempt->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_response' => $providerResponse,
            'error_code' => null,
            'error_message' => null,
        ]);

        Log::info('SMS attempt marked as sent', [
            'attempt_id' => $attempt->id,
            'payment_id' => $attempt->payment_id,
            'correlation_id' => $attempt->correlation_id,
        ]);
    }

    /**
     * Mark SMS attempt as failed
     */
    public function markFailed(
        SmsAttempt $attempt,
        string $errorMessage,
        ?string $errorCode = null,
        ?array $providerResponse = null
    ): void {
        $attempt->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'provider_response' => $providerResponse,
        ]);

        Log::warning('SMS attempt failed', [
            'attempt_id' => $attempt->id,
            'payment_id' => $attempt->payment_id,
            'correlation_id' => $attempt->correlation_id,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempt_number' => $attempt->attempt_number,
        ]);
    }

    /**
     * Mark SMS attempt as skipped
     */
    public function markSkipped(
        SmsAttempt $attempt,
        string $reason
    ): void {
        $attempt->update([
            'status' => 'skipped',
            'error_message' => $reason,
        ]);

        Log::info('SMS attempt skipped', [
            'attempt_id' => $attempt->id,
            'payment_id' => $attempt->payment_id,
            'correlation_id' => $attempt->correlation_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Get SMS attempts for payment
     */
    public function getAttempts(Payment $payment): \Illuminate\Database\Eloquent\Collection
    {
        return SmsAttempt::where('payment_id', $payment->id)
            ->orderBy('attempt_number', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get SMS attempts by correlation ID
     */
    public function getAttemptsByCorrelationId(string $correlationId): \Illuminate\Database\Eloquent\Collection
    {
        return SmsAttempt::where('correlation_id', $correlationId)
            ->orderBy('attempt_number', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get latest attempt for payment
     */
    public function getLatestAttempt(Payment $payment): ?SmsAttempt
    {
        return SmsAttempt::where('payment_id', $payment->id)
            ->orderBy('attempt_number', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
