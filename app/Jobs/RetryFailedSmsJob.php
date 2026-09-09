<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Retry Failed SMS Job
 * 
 * Retries sending SMS for payments that previously failed.
 * Can be triggered manually or scheduled.
 */
class RetryFailedSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes for batch processing

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $paymentId = null, // Specific payment ID, or null for batch
        public int $limit = 100 // Batch size
    ) {
        $this->onQueue('sms-retry');
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentRepository $paymentRepository): void
    {
        if ($this->paymentId) {
            // Retry specific payment
            $this->retryPayment($this->paymentId);
        } else {
            // Batch retry failed payments
            $this->retryFailedPayments($paymentRepository);
        }
    }

    /**
     * Retry specific payment
     */
    private function retryPayment(int $paymentId): void
    {
        $payment = Payment::find($paymentId);

        if (!$payment) {
            Log::warning('Payment not found for retry', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        // Check if payment is eligible for retry
        if ($payment->sms_sent) {
            Log::info('Payment SMS already sent - skipping retry', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        if ($payment->sms_retry_count >= 3) {
            Log::warning('Payment exceeded max retry count', [
                'payment_id' => $paymentId,
                'retry_count' => $payment->sms_retry_count,
            ]);
            return;
        }

        // Dispatch new SMS job
        SendPaymentSmsJob::dispatch($paymentId)
            ->onQueue('sms');

        Log::info('Payment SMS retry dispatched', [
            'payment_id' => $paymentId,
            'previous_retry_count' => $payment->sms_retry_count,
        ]);
    }

    /**
     * Batch retry failed payments
     */
    private function retryFailedPayments(PaymentRepository $paymentRepository): void
    {
        $payments = $paymentRepository->getPendingSms($this->limit);

        if ($payments->isEmpty()) {
            Log::info('No payments found for SMS retry');
            return;
        }

        $dispatched = 0;
        foreach ($payments as $payment) {
            if ($payment->canRetrySms()) {
                SendPaymentSmsJob::dispatch($payment->id)
                    ->onQueue('sms');
                $dispatched++;
            }
        }

        Log::info('Batch SMS retry dispatched', [
            'total_found' => $payments->count(),
            'dispatched' => $dispatched,
        ]);
    }
}
