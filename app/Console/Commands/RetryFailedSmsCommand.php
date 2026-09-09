<?php

namespace App\Console\Commands;

use App\Jobs\RetryFailedSmsJob;
use App\Repositories\PaymentRepository;
use Illuminate\Console\Command;

/**
 * Retry Failed SMS Command
 * 
 * Retries sending SMS for payments that failed.
 * Can be run manually or scheduled.
 */
class RetryFailedSmsCommand extends Command
{
    protected $signature = 'sms:retry 
                            {--payment-id= : Retry specific payment ID}
                            {--limit=100 : Number of payments to retry}
                            {--force : Force retry even if max attempts reached}';

    protected $description = 'Retry sending SMS for failed payment notifications';

    public function __construct(
        private PaymentRepository $paymentRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paymentId = $this->option('payment-id');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        if ($paymentId) {
            $this->info("Retrying SMS for payment ID: {$paymentId}");
            RetryFailedSmsJob::dispatch((int) $paymentId);
        } else {
            $this->info("Retrying SMS for up to {$limit} failed payments...");
            
            $payments = $this->paymentRepository->getPendingSms($limit);
            
            if ($payments->isEmpty()) {
                $this->info('No payments found with pending SMS.');
                return Command::SUCCESS;
            }

            $this->info("Found {$payments->count()} payments. Dispatching retry jobs...");

            $dispatched = 0;
            foreach ($payments as $payment) {
                if ($payment->canRetrySms() || $force) {
                    RetryFailedSmsJob::dispatch($payment->id);
                    $dispatched++;
                }
            }

            $this->info("Dispatched {$dispatched} retry jobs.");
        }

        return Command::SUCCESS;
    }
}
