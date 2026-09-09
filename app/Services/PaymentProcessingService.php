<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentAccountRepository;
use App\Jobs\SendPaymentSmsJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Payment Processing Service
 * 
 * Core business logic for processing incoming payments.
 * Ensures idempotency and tenant isolation.
 */
class PaymentProcessingService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentAccountRepository $paymentAccountRepository
    ) {}

    /**
     * Process incoming payment notification from M-Pesa
     * 
     * @param array $payload M-Pesa webhook payload
     * @return Payment|null Returns Payment if created, null if duplicate
     * @throws \Exception
     */
    public function processPayment(array $payload): ?Payment
    {
        // Extract payment details from M-Pesa payload
        $transactionId = $payload['TransactionID'] ?? null;
        $receiptNumber = $payload['ReceiptNumber'] ?? null;
        $requestId = $payload['RequestID'] ?? null;
        $accountType = $this->determineAccountType($payload);
        $accountNumber = $this->extractAccountNumber($payload, $accountType);
        $amount = (float) ($payload['Amount'] ?? 0);
        $phoneNumber = $this->normalizePhoneNumber($payload['PhoneNumber'] ?? '');
        $transactionTime = $this->parseTransactionTime($payload['TransactionTime'] ?? '');

        // Validate required fields
        if (!$transactionId || !$accountNumber || !$amount || !$phoneNumber) {
            throw new \InvalidArgumentException('Missing required payment fields');
        }

        // Idempotency check: Check if payment already exists
        $existingPayment = $this->paymentRepository->findByTransactionId($transactionId);
        if ($existingPayment) {
            Log::info('Duplicate payment detected', [
                'transaction_id' => $transactionId,
                'existing_payment_id' => $existingPayment->id,
            ]);
            return null; // Payment already processed
        }

        // Check for duplicate request ID (M-Pesa may send same payment twice)
        if ($requestId) {
            $duplicateByRequest = $this->paymentRepository->findByRequestId($requestId);
            if ($duplicateByRequest) {
                Log::warning('Duplicate payment by request ID', [
                    'request_id' => $requestId,
                    'existing_payment_id' => $duplicateByRequest->id,
                ]);
                return null;
            }
        }

        // Find payment account with tenant isolation
        $paymentAccount = $this->paymentAccountRepository->findByAccount($accountType, $accountNumber);
        if (!$paymentAccount || !$paymentAccount->isActive()) {
            Log::warning('Payment account not found or inactive', [
                'account_type' => $accountType,
                'account_number' => $accountNumber,
            ]);
            throw new \InvalidArgumentException('Payment account not found or inactive');
        }

        // Create payment record in transaction to ensure atomicity
        return DB::transaction(function () use (
            $paymentAccount,
            $transactionId,
            $receiptNumber,
            $requestId,
            $accountType,
            $accountNumber,
            $amount,
            $phoneNumber,
            $transactionTime,
            $payload
        ) {
            $payment = $this->paymentRepository->create([
                'tenant_id' => $paymentAccount->tenant_id,
                'payment_account_id' => $paymentAccount->id,
                'transaction_id' => $transactionId,
                'receipt_number' => $receiptNumber,
                'request_id' => $requestId,
                'account_type' => $accountType,
                'account_number' => $accountNumber,
                'amount' => $amount,
                'currency' => 'KES',
                'phone_number' => $phoneNumber,
                'payer_name' => $payload['FirstName'] . ' ' . ($payload['MiddleName'] ?? '') . ' ' . ($payload['LastName'] ?? ''),
                'transaction_time' => $transactionTime,
                'status' => 'completed',
                'description' => $payload['TransactionDesc'] ?? null,
                'metadata' => $payload, // Store full payload for audit
                'sms_sent' => false,
            ]);

            // Dispatch SMS job asynchronously
            SendPaymentSmsJob::dispatch($payment->id);

            Log::info('Payment processed successfully', [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'tenant_id' => $paymentAccount->tenant_id,
            ]);

            return $payment;
        });
    }

    /**
     * Determine account type from M-Pesa payload
     */
    private function determineAccountType(array $payload): string
    {
        // M-Pesa sends different fields for Paybill vs Till
        // Paybill: BillRefNumber
        // Till: TillNumber
        if (isset($payload['BillRefNumber'])) {
            return 'paybill';
        }
        if (isset($payload['TillNumber'])) {
            return 'till';
        }
        
        // Default fallback
        return 'paybill';
    }

    /**
     * Extract account number from payload
     */
    private function extractAccountNumber(array $payload, string $accountType): ?string
    {
        if ($accountType === 'paybill') {
            return $payload['BusinessShortCode'] ?? null;
        }
        
        return $payload['TillNumber'] ?? null;
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove spaces and special characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Convert Kenyan format (254...) or (07...) to +254...
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        } elseif (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            // Already in correct format
        } else {
            // Assume it's already correct or add 254 prefix
            if (strlen($phoneNumber) === 9) {
                $phoneNumber = '254' . $phoneNumber;
            }
        }
        
        return $phoneNumber;
    }

    /**
     * Parse M-Pesa transaction time format
     */
    private function parseTransactionTime(string $transactionTime): \DateTime
    {
        // M-Pesa format: YYYYMMDDHHmmss
        if (strlen($transactionTime) === 14) {
            $year = substr($transactionTime, 0, 4);
            $month = substr($transactionTime, 4, 2);
            $day = substr($transactionTime, 6, 2);
            $hour = substr($transactionTime, 8, 2);
            $minute = substr($transactionTime, 10, 2);
            $second = substr($transactionTime, 12, 2);
            
            return new \DateTime("{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}", new \DateTimeZone('Africa/Nairobi'));
        }
        
        return new \DateTime('now', new \DateTimeZone('Africa/Nairobi'));
    }
}
