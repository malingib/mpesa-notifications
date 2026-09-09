<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Payment Data Transfer Object
 * 
 * Normalized representation of payment data.
 * Converts M-Pesa payloads to internal format.
 * Immutable DTO for data integrity.
 */
class PaymentDto
{
    public function __construct(
        public readonly string $transactionId,
        public readonly ?string $receiptNumber,
        public readonly ?string $requestId,
        public readonly ?string $conversationId,
        public readonly string $accountType, // 'paybill' or 'till'
        public readonly string $accountNumber,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $phoneNumber,
        public readonly ?string $payerName,
        public readonly Carbon $transactionTime,
        public readonly string $status, // 'completed', 'pending', etc.
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly array $rawPayload, // Full M-Pesa payload for audit
    ) {}

    /**
     * Create PaymentDto from M-Pesa payload
     * 
     * @param array $payload Raw M-Pesa webhook payload
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function fromMpesaPayload(array $payload): self
    {
        // Determine account type
        $accountType = isset($payload['BillRefNumber']) || isset($payload['BusinessShortCode'])
            ? 'paybill'
            : 'till';
        
        // Extract account number
        $accountNumber = $accountType === 'paybill'
            ? ($payload['BusinessShortCode'] ?? '')
            : ($payload['TillNumber'] ?? '');
        
        if (empty($accountNumber)) {
            throw new \InvalidArgumentException('Account number not found in payload');
        }
        
        // Parse transaction time
        $transactionTime = self::parseTransactionTime($payload['TransactionTime'] ?? '');
        
        // Build payer name
        $payerName = trim(
            ($payload['FirstName'] ?? '') . ' ' .
            ($payload['MiddleName'] ?? '') . ' ' .
            ($payload['LastName'] ?? '')
        ) ?: null;
        
        return new self(
            transactionId: $payload['TransactionID'] ?? '',
            receiptNumber: $payload['ReceiptNumber'] ?? null,
            requestId: $payload['RequestID'] ?? null,
            conversationId: $payload['ConversationID'] ?? null,
            accountType: $accountType,
            accountNumber: $accountNumber,
            amount: (float) ($payload['Amount'] ?? 0),
            currency: 'KES', // M-Pesa always uses KES
            phoneNumber: $payload['PhoneNumber'] ?? '',
            payerName: $payerName,
            transactionTime: $transactionTime,
            status: 'completed', // M-Pesa only sends completed payments
            description: $payload['TransactionDesc'] ?? null,
            reference: $payload['BillRefNumber'] ?? null,
            rawPayload: $payload,
        );
    }

    /**
     * Parse M-Pesa transaction time format (YYYYMMDDHHmmss)
     * 
     * @param string $timeString M-Pesa transaction time string
     * @return Carbon
     */
    private static function parseTransactionTime(string $timeString): Carbon
    {
        if (strlen($timeString) === 14) {
            $year = substr($timeString, 0, 4);
            $month = substr($timeString, 4, 2);
            $day = substr($timeString, 6, 2);
            $hour = substr($timeString, 8, 2);
            $minute = substr($timeString, 10, 2);
            $second = substr($timeString, 12, 2);
            
            try {
                return Carbon::create(
                    (int) $year,
                    (int) $month,
                    (int) $day,
                    (int) $hour,
                    (int) $minute,
                    (int) $second,
                    'Africa/Nairobi'
                );
            } catch (\Exception $e) {
                // Fallback to current time if parsing fails
                return Carbon::now('Africa/Nairobi');
            }
        }
        
        return Carbon::now('Africa/Nairobi');
    }

    /**
     * Convert to array for storage
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'receipt_number' => $this->receiptNumber,
            'request_id' => $this->requestId,
            'conversation_id' => $this->conversationId,
            'account_type' => $this->accountType,
            'account_number' => $this->accountNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'phone_number' => $this->phoneNumber,
            'payer_name' => $this->payerName,
            'transaction_time' => $this->transactionTime->toDateTimeString(),
            'status' => $this->status,
            'description' => $this->description,
            'reference' => $this->reference,
            'metadata' => $this->rawPayload,
        ];
    }
}
