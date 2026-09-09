<?php

namespace App\Services;

use App\Models\DataAnonymization;
use App\Models\Payment;
use App\Models\SmsAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data Anonymization Service
 * 
 * Handles GDPR-safe anonymization of personal data.
 */
class DataAnonymizationService
{
    /**
     * Anonymize payment data
     */
    public function anonymizePayment(
        Payment $payment,
        string $reason = 'retention_policy',
        ?string $reasonDetails = null
    ): DataAnonymization {
        return DB::transaction(function () use ($payment, $reason, $reasonDetails) {
            // Anonymize payment record
            $fieldsAnonymized = [];
            
            if ($payment->phone_number) {
                $fieldsAnonymized[] = 'phone_number';
                $payment->phone_number = '[REDACTED]';
            }
            
            if ($payment->payer_name) {
                $fieldsAnonymized[] = 'payer_name';
                $payment->payer_name = '[REDACTED]';
            }
            
            // Anonymize metadata (remove PII, keep structure)
            if ($payment->metadata) {
                $fieldsAnonymized[] = 'metadata';
                $metadata = $payment->metadata;
                
                // Remove PII fields from metadata
                $piiFields = ['FirstName', 'MiddleName', 'LastName', 'PhoneNumber', 'BillRefNumber'];
                foreach ($piiFields as $field) {
                    if (isset($metadata[$field])) {
                        $metadata[$field] = '[REDACTED]';
                    }
                }
                
                $payment->metadata = $metadata;
            }
            
            $payment->save();
            
            // Anonymize related SMS attempts
            $smsAttempts = SmsAttempt::where('payment_id', $payment->id)->get();
            foreach ($smsAttempts as $attempt) {
                if ($attempt->phone_number) {
                    $attempt->phone_number = '[REDACTED]';
                }
                if ($attempt->message) {
                    $attempt->message = '[REDACTED]';
                }
                $attempt->save();
            }
            
            // Record anonymization
            $anonymization = DataAnonymization::create([
                'entity_type' => 'Payment',
                'entity_id' => $payment->id,
                'anonymized_at' => now(),
                'anonymized_by_type' => 'system',
                'anonymized_by_id' => null,
                'reason' => $reason,
                'reason_details' => $reasonDetails,
                'fields_anonymized' => $fieldsAnonymized,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'sms_attempts_anonymized' => $smsAttempts->count(),
                ],
            ]);
            
            Log::info('Payment data anonymized', [
                'payment_id' => $payment->id,
                'anonymization_id' => $anonymization->id,
                'reason' => $reason,
                'fields_anonymized' => $fieldsAnonymized,
            ]);
            
            return $anonymization;
        });
    }

    /**
     * Check if payment is anonymized
     */
    public function isAnonymized(int $paymentId): bool
    {
        return DataAnonymization::where('entity_type', 'Payment')
            ->where('entity_id', $paymentId)
            ->exists();
    }

    /**
     * Get anonymization record
     */
    public function getAnonymization(int $paymentId): ?DataAnonymization
    {
        return DataAnonymization::where('entity_type', 'Payment')
            ->where('entity_id', $paymentId)
            ->first();
    }
}
