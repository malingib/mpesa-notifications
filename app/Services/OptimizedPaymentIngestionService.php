<?php

namespace App\Services;

use App\DTOs\PaymentDto;
use App\Models\Payment;
use App\Jobs\SendPaymentSmsJob;
use App\Repositories\PaymentRepository;
use App\Repositories\MerchantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Optimized Payment Ingestion Service
 * 
 * High-performance payment ingestion for 1M+ payments/day.
 * 
 * Optimizations:
 * - Single idempotency check (composite query)
 * - Cached merchant lookups
 * - Optimized database transactions
 * - Async audit logging
 */
class OptimizedPaymentIngestionService
{
    private const MERCHANT_CACHE_TTL = 3600; // 1 hour
    private const IDEMPOTENCY_CACHE_TTL = 300; // 5 minutes (recent transactions)

    public function __construct(
        private PaymentRepository $paymentRepository,
        private MerchantRepository $merchantRepository,
        private CorrelationIdService $correlationIdService,
        private PaymentAuditService $auditService
    ) {}

    /**
     * Ingest payment notification (optimized)
     * 
     * Single idempotency check + cached merchant lookup
     */
    public function ingest(PaymentDto $dto): ?Payment
    {
        // Fast idempotency check (single query with composite index)
        if ($this->isDuplicate($dto)) {
            return null;
        }

        // Cached merchant lookup
        $merchant = $this->getMerchantCached($dto->accountType, $dto->accountNumber);
        
        if (!$merchant || !$merchant->isActive()) {
            Log::warning('Merchant not found or inactive', [
                'account_type' => $dto->accountType,
                'account_number' => $dto->accountNumber,
            ]);
            throw new \InvalidArgumentException("Merchant account not found or inactive");
        }

        // Generate correlation ID
        $correlationId = $this->correlationIdService->generate();

        // Create payment with minimal transaction scope
        $payment = $this->createPayment($dto, $merchant, $correlationId);

        // Cache idempotency check result (prevent duplicates)
        $this->cacheIdempotency($dto);

        // Dispatch SMS job (async, non-blocking)
        SendPaymentSmsJob::dispatch($payment->id)
            ->onQueue('sms-normal');

        // Async audit logging (queue, non-blocking)
        dispatch(function () use ($payment, $dto, $correlationId) {
            $this->auditService->recordCreation($payment, $dto->toArray(), $correlationId);
        })->onQueue('audit');

        return $payment;
    }

    /**
     * Single idempotency check (optimized)
     * 
     * Uses composite index: (transaction_id, receipt_number, request_id)
     */
    private function isDuplicate(PaymentDto $dto): bool
    {
        // Check cache first (recent transactions)
        $cacheKey = $this->getIdempotencyCacheKey($dto);
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Single database query with OR conditions
        // Composite index makes this fast
        $exists = Payment::where(function ($query) use ($dto) {
            $query->where('transaction_id', $dto->transactionId);
            
            if ($dto->receiptNumber) {
                $query->orWhere('receipt_number', $dto->receiptNumber);
            }
            
            if ($dto->requestId) {
                $query->orWhere('request_id', $dto->requestId);
            }
        })->exists();

        if ($exists) {
            // Cache result to prevent duplicate queries
            Cache::put($cacheKey, true, self::IDEMPOTENCY_CACHE_TTL);
        }

        return $exists;
    }

    /**
     * Get merchant with caching
     */
    private function getMerchantCached(string $accountType, string $accountNumber)
    {
        $cacheKey = "merchant:{$accountType}:{$accountNumber}";

        return Cache::remember($cacheKey, self::MERCHANT_CACHE_TTL, function () use ($accountType, $accountNumber) {
            return $this->merchantRepository->findByAccount($accountType, $accountNumber);
        });
    }

    /**
     * Create payment (optimized transaction)
     */
    private function createPayment(PaymentDto $dto, $merchant, string $correlationId): Payment
    {
        // Minimal transaction scope (only payment creation)
        return DB::transaction(function () use ($dto, $merchant, $correlationId) {
            return Payment::create([
                'correlation_id' => $correlationId,
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
                'metadata' => $dto->rawPayload,
                'sms_sent' => false,
            ]);
        });
    }

    /**
     * Cache idempotency check result
     */
    private function cacheIdempotency(PaymentDto $dto): void
    {
        $cacheKey = $this->getIdempotencyCacheKey($dto);
        Cache::put($cacheKey, true, self::IDEMPOTENCY_CACHE_TTL);
    }

    /**
     * Get idempotency cache key
     */
    private function getIdempotencyCacheKey(PaymentDto $dto): string
    {
        return sprintf(
            'payment:idempotency:%s:%s:%s',
            $dto->transactionId,
            $dto->receiptNumber ?? 'null',
            $dto->requestId ?? 'null'
        );
    }

    /**
     * Batch ingest payments (for high-volume periods)
     * 
     * Processes multiple payments in a single transaction
     */
    public function batchIngest(array $dtos): array
    {
        $results = [];
        $merchants = [];
        $correlationIds = [];

        // Pre-load all merchants (batch cache lookup)
        foreach ($dtos as $dto) {
            $cacheKey = "merchant:{$dto->accountType}:{$dto->accountNumber}";
            if (!isset($merchants[$cacheKey])) {
                $merchants[$cacheKey] = $this->getMerchantCached($dto->accountType, $dto->accountNumber);
            }
        }

        // Batch insert payments
        DB::transaction(function () use ($dtos, $merchants, &$results, &$correlationIds) {
            foreach ($dtos as $dto) {
                // Skip duplicates
                if ($this->isDuplicate($dto)) {
                    $results[] = null;
                    continue;
                }

                $cacheKey = "merchant:{$dto->accountType}:{$dto->accountNumber}";
                $merchant = $merchants[$cacheKey] ?? null;

                if (!$merchant || !$merchant->isActive()) {
                    $results[] = null;
                    continue;
                }

                $correlationId = $this->correlationIdService->generate();
                $correlationIds[] = $correlationId;

                $payment = Payment::create([
                    'correlation_id' => $correlationId,
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
                    'metadata' => $dto->rawPayload,
                    'sms_sent' => false,
                ]);

                $results[] = $payment;

                // Cache idempotency
                $this->cacheIdempotency($dto);

                // Dispatch SMS job
                SendPaymentSmsJob::dispatch($payment->id)
                    ->onQueue('sms-normal');
            }
        });

        // Batch audit logging (async)
        foreach ($results as $index => $payment) {
            if ($payment && isset($correlationIds[$index])) {
                dispatch(function () use ($payment, $dtos, $index, $correlationIds) {
                    $this->auditService->recordCreation(
                        $payment,
                        $dtos[$index]->toArray(),
                        $correlationIds[$index]
                    );
                })->onQueue('audit');
            }
        }

        return $results;
    }
}
