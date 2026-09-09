<?php

namespace App\Services;

use App\DTOs\PaymentDto;
use App\Models\Payment;
use App\Jobs\SendPaymentSmsJob;
use App\Repositories\PaymentRepository;
use App\Repositories\MerchantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Ingestion Service
 * 
 * Handles payment ingestion with idempotency and async processing.
 * Ensures fast response times by deferring heavy operations.
 */
class PaymentIngestionService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private MerchantRepository $merchantRepository
    ) {}

    /**
     * Ingest payment notification
     * 
     * Processes payment with idempotency checks and async SMS dispatch.
     * 
     * @param PaymentDto $dto Normalized payment data
     * @return Payment|null Returns Payment if created, null if duplicate
     * @throws \InvalidArgumentException If merchant not found
     * @throws \Exception For database errors
     */
    public function ingest(PaymentDto $dto): ?Payment
    {
        // Idempotency check: transaction_id (primary)
        $existingPayment = $this->paymentRepository->findByTransactionId($dto->transactionId);
        if ($existingPayment) {
            Log::info('Duplicate payment detected by transaction_id', [
                'transaction_id' => $dto->transactionId,
                'existing_payment_id' => $existingPayment->id,
            ]);
            return null;
        }

        // Idempotency check: receipt_number (backup)
        if ($dto->receiptNumber) {
            $existingByReceipt = $this->paymentRepository->findByReceiptNumber($dto->receiptNumber);
            if ($existingByReceipt) {
                Log::info('Duplicate payment detected by receipt_number', [
                    'receipt_number' => $dto->receiptNumber,
                    'existing_payment_id' => $existingByReceipt->id,
                ]);
                return null;
            }
        }

        // Idempotency check: request_id (duplicate detection)
        if ($dto->requestId) {
            $duplicateByRequest = $this->paymentRepository->findByRequestId($dto->requestId);
            if ($duplicateByRequest) {
                Log::warning('Duplicate payment by request_id', [
                    'request_id' => $dto->requestId,
                    'existing_payment_id' => $duplicateByRequest->id,
                ]);
                return null;
            }
        }

        // Find merchant account
        $merchant = $this->merchantRepository->findByAccount(
            $dto->accountType,
            $dto->accountNumber
        );

        if (!$merchant) {
            throw new \InvalidArgumentException(
                "Merchant account not found: {$dto->accountType} {$dto->accountNumber}"
            );
        }

        if (!$merchant->isActive()) {
            throw new \InvalidArgumentException(
                "Merchant account is inactive: {$dto->accountType} {$dto->accountNumber}"
            );
        }

        // Create payment in transaction for atomicity
        return DB::transaction(function () use ($dto, $merchant) {
            $payment = Payment::create([
                'user_id' => $merchant->user_id,
                'merchant_id' => $merchant->id,
                'transaction_id' => $dto->transactionId,
                'receipt_number' => $dto->receiptNumber,
                'request_id' => $dto->requestId,
                'conversation_id' => $dto->conversationId,
                'account_type' => $dto->accountType,
                'account_number' => $dto->accountNumber,
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'phone_number' => $dto->phoneNumber,
                'payer_name' => $dto->payerName,
                'transaction_time' => $dto->transactionTime,
                'status' => $dto->status,
                'description' => $dto->description,
                'reference' => $dto->reference,
                'metadata' => $dto->rawPayload, // Store full payload for audit
                'sms_sent' => false,
            ]);

            // Dispatch SMS job asynchronously (non-blocking)
            SendPaymentSmsJob::dispatch($payment->id);

            Log::info('Payment ingested successfully', [
                'payment_id' => $payment->id,
                'transaction_id' => $dto->transactionId,
                'merchant_id' => $merchant->id,
                'user_id' => $merchant->user_id,
                'amount' => $dto->amount,
            ]);

            return $payment;
        });
    }
}
