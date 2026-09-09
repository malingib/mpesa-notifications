<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment Repository
 * 
 * Handles data access for payments with idempotency checks.
 */
class PaymentRepository
{
    /**
     * Find payment by transaction ID (idempotency check)
     * 
     * @param string $transactionId M-Pesa TransactionID
     * @return Payment|null
     */
    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    /**
     * Find payment by receipt number
     * 
     * @param string $receiptNumber M-Pesa ReceiptNumber
     * @return Payment|null
     */
    public function findByReceiptNumber(string $receiptNumber): ?Payment
    {
        return Payment::where('receipt_number', $receiptNumber)->first();
    }

    /**
     * Find payment by request ID (duplicate detection)
     * 
     * @param string $requestId M-Pesa RequestID
     * @return Payment|null
     */
    public function findByRequestId(string $requestId): ?Payment
    {
        return Payment::where('request_id', $requestId)->first();
    }

    /**
     * Create a new payment record
     * 
     * @param array $data
     * @return Payment
     */
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Get payments pending SMS notification
     * 
     * @param int $limit
     * @return Collection
     */
    public function getPendingSms(int $limit = 100): Collection
    {
        return Payment::where('sms_sent', false)
            ->where('status', 'completed')
            ->where('sms_retry_count', '<', 3)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payments by tenant
     * 
     * @param int $tenantId
     * @param array $filters
     * @return Collection
     */
    public function getByTenant(int $tenantId, array $filters = []): Collection
    {
        $query = Payment::where('tenant_id', $tenantId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('transaction_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('transaction_time', '<=', $filters['date_to']);
        }

        return $query->orderBy('transaction_time', 'desc')->get();
    }
}
