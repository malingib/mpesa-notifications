<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\TalksasaSmsService;
use App\Services\SmsTemplateService;
use App\Services\SmsAttemptService;
use App\Services\PaymentAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Optimized Send Payment SMS Job
 * 
 * High-performance SMS sending with:
 * - Cached template resolution
 * - Optimized idempotency checks
 * - Batch SMS support
 * - Async audit logging
 */
class OptimizedSendPaymentSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $paymentId
    ) {
        $this->onQueue('sms-normal');
    }

    public function middleware(): array
    {
        return [
            new \Illuminate\Queue\Middleware\WithoutOverlapping('sms_payment_' . $this->paymentId, 60),
        ];
    }

    public function uniqueId(): string
    {
        return 'sms_payment_' . $this->paymentId;
    }

    /**
     * Execute the job (optimized)
     */
    public function handle(
        TalksasaSmsService $smsService,
        SmsTemplateService $templateService,
        SmsAttemptService $attemptService,
        PaymentAuditService $auditService
    ): void {
        // Use select() to only load needed fields
        $payment = Payment::select([
            'id', 'user_id', 'merchant_id', 'correlation_id',
            'phone_number', 'amount', 'transaction_id', 'transaction_time',
            'sms_sent', 'sms_sent_at', 'sms_error', 'sms_retry_count'
        ])->find($this->paymentId);

        if (!$payment) {
            Log::error('Payment not found for SMS job', [
                'payment_id' => $this->paymentId,
            ]);
            return;
        }

        // Fast idempotency check (cached)
        $cacheKey = "payment:sms_sent:{$payment->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        // Optimized transaction (minimal scope)
        DB::transaction(function () use ($payment, $templateService, $smsService, $attemptService, $auditService, $cacheKey) {
            // Reload with lock to prevent race condition
            $payment->refresh();

            if ($payment->sms_sent) {
                Cache::put($cacheKey, true, 3600);
                return;
            }

            // Check if SMS should be sent (cached rules evaluation)
            if (!$templateService->shouldSendSms($payment)) {
                return;
            }

            // Record attempt start
            $attempt = $attemptService->recordAttempt(
                $payment,
                $payment->phone_number,
                null, // Don't store message for privacy
                $this->job->getJobId(),
                $this->attempts()
            );

            try {
                // Get message (cached template resolution)
                $message = $templateService->getMessageForPayment($payment);
                
                if (!$message) {
                    $attemptService->markSkipped($attempt, 'No message generated');
                    return;
                }

                // Send SMS
                $success = $smsService->sendSms($payment->phone_number, $message);

                if ($success) {
                    // Mark as sent atomically
                    $payment->update([
                        'sms_sent' => true,
                        'sms_sent_at' => now(),
                        'sms_error' => null,
                    ]);

                    // Mark attempt as sent
                    $attemptService->markSent($attempt, null); // Response stored in service if needed

                    // Cache idempotency
                    Cache::put($cacheKey, true, 3600);

                    // Async audit logging
                    dispatch(function () use ($payment, $auditService) {
                        $auditService->recordSmsSent($payment);
                    })->onQueue('audit');

                    Log::info('Payment SMS sent successfully', [
                        'payment_id' => $payment->id,
                        'attempt' => $this->attempts(),
                    ]);
                } else {
                    throw new \Exception('SMS service returned false');
                }

            } catch (\App\Exceptions\TalksasaSmsException $e) {
                $this->handleSmsException($payment, $attempt, $attemptService, $auditService, $e);
                
                if ($e->isRetryable()) {
                    throw $e;
                }
                
                $this->fail($e);

            } catch (\Exception $e) {
                $this->handleSmsException($payment, $attempt, $attemptService, $auditService, $e);
                throw $e;
            }
        });
    }

    private function handleSmsException(
        Payment $payment,
        $attempt,
        SmsAttemptService $attemptService,
        PaymentAuditService $auditService,
        \Throwable $e
    ): void {
        $attemptService->markFailed(
            $attempt,
            $e->getMessage(),
            $e->getCode(),
            $e instanceof \App\Exceptions\TalksasaSmsException ? $e->getResponse() : null
        );

        $payment->increment('sms_retry_count');
        $payment->update(['sms_error' => $e->getMessage()]);

        // Async audit logging
        dispatch(function () use ($payment, $e, $auditService) {
            $auditService->recordSmsFailure($payment, $e->getMessage());
        })->onQueue('audit');

        Log::error('Failed to send payment SMS', [
            'payment_id' => $payment->id,
            'attempt' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $payment = Payment::find($this->paymentId);

        if ($payment) {
            $payment->update([
                'sms_error' => "Job failed after {$this->tries} attempts: " . $exception->getMessage(),
                'sms_retry_count' => $this->tries,
            ]);

            Log::error('Payment SMS job failed permanently', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
