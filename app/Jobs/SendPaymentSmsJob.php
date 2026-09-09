<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\TalksasaSmsService;
use App\Services\SmsTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Send Payment SMS Job
 * 
 * Async job to send payment confirmation SMS.
 * Implements retry logic with exponential backoff and idempotency.
 * 
 * Features:
 * - Fast webhook response (job dispatched immediately)
 * - Idempotent SMS sending (no duplicates)
 * - Exponential backoff retries
 * - Dead-letter handling for permanent failures
 */
class SendPaymentSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: 60s, 300s (5min), 900s (15min)
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $paymentId
    ) {
        // Use dedicated SMS queue for better control
        $this->onQueue('sms');
    }

    /**
     * Get the middleware the job should pass through.
     * 
     * Prevents duplicate jobs from running simultaneously.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('sms_payment_' . $this->paymentId, 60),
        ];
    }

    /**
     * The unique ID of the job.
     * 
     * Prevents duplicate jobs in queue.
     */
    public function uniqueId(): string
    {
        return 'sms_payment_' . $this->paymentId;
    }

    /**
     * Execute the job.
     * 
     * This method is called by the queue worker.
     * It handles SMS sending with idempotency and error handling.
     */
    public function handle(
        TalksasaSmsService $smsService,
        SmsTemplateService $templateService
    ): void {
        $payment = Payment::find($this->paymentId);

        if (!$payment) {
            Log::error('Payment not found for SMS job', [
                'payment_id' => $this->paymentId,
            ]);
            return;
        }

        // Idempotency check: Skip if SMS already sent
        // Use database transaction to prevent race conditions
        DB::transaction(function () use ($payment, $smsService, $templateService) {
            // Reload payment to get latest state (prevent race condition)
            $payment->refresh();

            if ($payment->sms_sent) {
                Log::info('SMS already sent for payment - skipping', [
                    'payment_id' => $payment->id,
                    'sms_sent_at' => $payment->sms_sent_at,
                ]);
                return;
            }

            // Check if SMS should be sent (rules evaluation)
            if (!$templateService->shouldSendSms($payment)) {
                Log::info('SMS skipped based on rules evaluation', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'merchant_id' => $payment->merchant_id,
                ]);
                return;
            }

            // Get rendered message from template service
            $message = $templateService->getMessageForPayment($payment);
            
            if (!$message) {
                Log::warning('No message generated for payment', [
                    'payment_id' => $payment->id,
                ]);
                return;
            }

            // Get user's SMS settings (API token and sender ID)
            $user = $payment->user;
            $settings = $user->settings ?? [];
            $smsSettings = $settings['sms'] ?? [];
            $apiToken = $smsSettings['talksasa_api_token'] ?? null;
            $senderId = $smsSettings['sender_id'] ?? null;

            if (!$apiToken) {
                Log::warning('No Talksasa API token configured for user', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                ]);
                return;
            }

            // Send SMS to customer with user's API token and sender ID
            try {
                Log::info('Sending SMS with user API token', [
                    'payment_id' => $payment->id,
                    'has_api_token' => !empty($apiToken),
                    'api_token_length' => $apiToken ? strlen($apiToken) : 0,
                    'has_sender_id' => !empty($senderId),
                ]);
                
                $customerSuccess = $smsService->sendSms($payment->phone_number, $message, [
                    'api_token' => $apiToken,
                    'sender_id' => $senderId,
                ]);

                if ($customerSuccess) {
                    // Mark SMS as sent atomically
                    $payment->update([
                        'sms_sent' => true,
                        'sms_sent_at' => now(),
                        'sms_error' => null,
                    ]);

                    Log::info('Payment SMS sent successfully to customer', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'phone_number' => $this->maskPhone($payment->phone_number),
                        'message_length' => strlen($message),
                        'attempt' => $this->attempts(),
                    ]);

                    // Send notification SMS to configured numbers
                    $this->sendNotificationSms($payment, $message, $smsService, $apiToken, $senderId);
                } else {
                    throw new \Exception('SMS service returned false');
                }

            } catch (\App\Exceptions\TalksasaSmsException $e) {
                // Handle Talksasa-specific errors
                $this->handleSmsException($payment, $e);
                
                // Re-throw if retryable to trigger job retry
                if ($e->isRetryable()) {
                    throw $e;
                }
                
                // Permanent error - don't retry
                $this->fail($e);

            } catch (\Exception $e) {
                // Handle other exceptions
                $this->handleSmsException($payment, $e);
                
                // Re-throw to trigger retry mechanism
                throw $e;
            }
        });
    }

    /**
     * Send notification SMS to configured phone numbers
     */
    private function sendNotificationSms(
        Payment $payment,
        string $customerMessage,
        TalksasaSmsService $smsService,
        string $apiToken,
        string $senderId
    ): void {
        $user = $payment->user;
        $settings = $user->settings ?? [];
        $smsSettings = $settings['sms'] ?? [];
        $notificationNumbers = $smsSettings['notification_numbers'] ?? [];

        if (empty($notificationNumbers)) {
            Log::debug('No notification numbers configured', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        // Create notification message (can be same as customer message or customized)
        $notificationMessage = $this->createNotificationMessage($payment, $customerMessage);

        foreach ($notificationNumbers as $phoneNumber) {
            $phoneNumber = trim($phoneNumber);
            if (empty($phoneNumber)) {
                continue;
            }

            try {
                $success = $smsService->sendSms($phoneNumber, $notificationMessage, [
                    'api_token' => $apiToken,
                    'sender_id' => $senderId,
                ]);

                if ($success) {
                    Log::info('Notification SMS sent successfully', [
                        'payment_id' => $payment->id,
                        'notification_number' => $this->maskPhone($phoneNumber),
                        'transaction_id' => $payment->transaction_id,
                    ]);
                } else {
                    Log::warning('Failed to send notification SMS', [
                        'payment_id' => $payment->id,
                        'notification_number' => $this->maskPhone($phoneNumber),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error sending notification SMS', [
                    'payment_id' => $payment->id,
                    'notification_number' => $this->maskPhone($phoneNumber),
                    'error' => $e->getMessage(),
                ]);
                // Don't throw - notification failures shouldn't fail the job
            }
        }
    }

    /**
     * Create notification message for admin/notification numbers
     */
    private function createNotificationMessage(Payment $payment, string $customerMessage): string
    {
        // Customize this message format for notifications
        // Includes payment details for admin/notification purposes
        return sprintf(
            "Payment Received\nAmount: KES %s\nFrom: %s\nTransaction: %s%s",
            number_format($payment->amount, 2),
            $payment->payer_name ?? $payment->phone_number,
            $payment->transaction_id,
            $payment->account_number ? "\nAccount: {$payment->account_number}" : ""
        );
    }

    /**
     * Handle SMS exception and update payment record
     */
    private function handleSmsException(Payment $payment, \Throwable $e): void
    {
        $payment->increment('sms_retry_count');
        $payment->update([
            'sms_error' => $e->getMessage(),
        ]);

        Log::error('Failed to send payment SMS', [
            'payment_id' => $payment->id,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $e->getMessage(),
            'error_class' => get_class($e),
            'is_retryable' => $e instanceof \App\Exceptions\TalksasaSmsException ? $e->isRetryable() : true,
        ]);
    }

    /**
     * Handle a job failure after all retries exhausted.
     * 
     * This is called when the job has failed permanently.
     */
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
                'transaction_id' => $payment->transaction_id,
                'attempts' => $this->tries,
                'error' => $exception->getMessage(),
                'error_class' => get_class($exception),
            ]);

            // Optional: Send alert to admin
            // Notification::send($admin, new SmsFailureAlert($payment, $exception));
        }
    }

    /**
     * Calculate backoff delay for retry
     * 
     * Override default backoff if needed
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Don't retry after 24 hours
        return now()->addHours(24);
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) > 7) {
            return substr($phone, 0, 3) . '****' . substr($phone, -3);
        }
        return '****';
    }
}
