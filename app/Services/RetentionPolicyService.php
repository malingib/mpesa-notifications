<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SmsAttempt;
use App\Models\AuditLog;
use App\Models\DataAnonymization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retention Policy Service
 * 
 * Handles data retention policies and archival.
 */
class RetentionPolicyService
{
    public function __construct(
        private DataAnonymizationService $anonymizationService
    ) {}

    /**
     * Archive old payments (after 7 years)
     */
    public function archivePayments(int $years = 7): int
    {
        $cutoffDate = now()->subYears($years);
        
        $count = Payment::where('created_at', '<', $cutoffDate)
            ->whereDoesntHave('history', function ($query) {
                $query->where('action', 'archived');
            })
            ->count();

        // In a real implementation, you would:
        // 1. Move to archive table
        // 2. Compress data
        // 3. Store in cold storage (S3, etc.)
        
        Log::info('Payments archived', [
            'count' => $count,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $count;
    }

    /**
     * Archive old SMS attempts (after 2 years)
     */
    public function archiveSmsAttempts(int $years = 2): int
    {
        $cutoffDate = now()->subYears($years);
        
        $count = SmsAttempt::where('created_at', '<', $cutoffDate)
            ->count();

        // Archive logic here
        
        Log::info('SMS attempts archived', [
            'count' => $count,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $count;
    }

    /**
     * Archive old audit logs (after 1 year)
     */
    public function archiveAuditLogs(int $years = 1): int
    {
        $cutoffDate = now()->subYears($years);
        
        $count = AuditLog::where('created_at', '<', $cutoffDate)
            ->count();

        // Archive logic here
        
        Log::info('Audit logs archived', [
            'count' => $count,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $count;
    }

    /**
     * Anonymize old payments (after 10 years)
     */
    public function anonymizeOldPayments(int $years = 10): int
    {
        $cutoffDate = now()->subYears($years);
        
        $payments = Payment::where('created_at', '<', $cutoffDate)
            ->whereDoesntHave('history', function ($query) {
                $query->where('action', 'anonymized');
            })
            ->get();

        $anonymized = 0;
        foreach ($payments as $payment) {
            try {
                $this->anonymizationService->anonymizePayment(
                    $payment,
                    'retention_policy',
                    "Anonymized after {$years} years retention period"
                );
                $anonymized++;
            } catch (\Exception $e) {
                Log::error('Failed to anonymize payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Old payments anonymized', [
            'count' => $anonymized,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $anonymized;
    }

    /**
     * Delete anonymized data (after anonymization + retention period)
     */
    public function deleteAnonymizedData(int $yearsAfterAnonymization = 1): int
    {
        $cutoffDate = now()->subYears($yearsAfterAnonymization);
        
        $anonymizations = DataAnonymization::where('anonymized_at', '<', $cutoffDate)
            ->get();

        $deleted = 0;
        foreach ($anonymizations as $anonymization) {
            try {
                if ($anonymization->entity_type === 'Payment') {
                    Payment::where('id', $anonymization->entity_id)->delete();
                    $deleted++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete anonymized data', [
                    'anonymization_id' => $anonymization->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Anonymized data deleted', [
            'count' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deleted;
    }

    /**
     * Run all retention policies
     */
    public function runAllPolicies(): array
    {
        return [
            'payments_archived' => $this->archivePayments(),
            'sms_attempts_archived' => $this->archiveSmsAttempts(),
            'audit_logs_archived' => $this->archiveAuditLogs(),
            'payments_anonymized' => $this->anonymizeOldPayments(),
            'anonymized_data_deleted' => $this->deleteAnonymizedData(),
        ];
    }
}
