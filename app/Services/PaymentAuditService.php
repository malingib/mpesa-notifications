<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Payment Audit Service
 * 
 * Tracks all changes to payment records in an immutable history table.
 */
class PaymentAuditService
{
    public function __construct(
        private CorrelationIdService $correlationIdService
    ) {}

    /**
     * Record payment creation
     */
    public function recordCreation(
        Payment $payment,
        array $data,
        ?string $correlationId = null
    ): PaymentHistory {
        $correlationId = $correlationId ?? $payment->correlation_id ?? $this->correlationIdService->generate();
        
        // Ensure payment has correlation ID
        if (!$payment->correlation_id) {
            $payment->update(['correlation_id' => $correlationId]);
        }

        return PaymentHistory::create([
            'payment_id' => $payment->id,
            'correlation_id' => $correlationId,
            'action' => 'created',
            'changed_by_type' => 'system',
            'changed_by_id' => null,
            'field_name' => null,
            'old_value' => null,
            'new_value' => $data,
            'reason' => 'Payment received from webhook',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'metadata' => [
                'source' => 'webhook',
                'merchant_id' => $payment->merchant_id,
            ],
        ]);
    }

    /**
     * Record payment update
     */
    public function recordUpdate(
        Payment $payment,
        string $field,
        $oldValue,
        $newValue,
        ?string $reason = null
    ): PaymentHistory {
        return PaymentHistory::create([
            'payment_id' => $payment->id,
            'correlation_id' => $payment->correlation_id,
            'action' => 'updated',
            'changed_by_type' => Auth::check() ? 'user' : 'system',
            'changed_by_id' => Auth::id(),
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason ?? "Field '{$field}' updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'metadata' => [
                'user_id' => Auth::id(),
            ],
        ]);
    }

    /**
     * Record status change
     */
    public function recordStatusChange(
        Payment $payment,
        string $oldStatus,
        string $newStatus,
        ?string $reason = null
    ): PaymentHistory {
        return PaymentHistory::create([
            'payment_id' => $payment->id,
            'correlation_id' => $payment->correlation_id,
            'action' => 'status_changed',
            'changed_by_type' => 'system',
            'changed_by_id' => null,
            'field_name' => 'status',
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'reason' => $reason ?? "Status changed from {$oldStatus} to {$newStatus}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'metadata' => [
                'payment_id' => $payment->id,
            ],
        ]);
    }

    /**
     * Record SMS sent
     */
    public function recordSmsSent(
        Payment $payment,
        ?string $reason = null
    ): PaymentHistory {
        return PaymentHistory::create([
            'payment_id' => $payment->id,
            'correlation_id' => $payment->correlation_id,
            'action' => 'sms_sent',
            'changed_by_type' => 'system',
            'changed_by_id' => null,
            'field_name' => 'sms_sent',
            'old_value' => false,
            'new_value' => true,
            'reason' => $reason ?? 'SMS notification sent successfully',
            'ip_address' => null,
            'user_agent' => null,
            'session_id' => null,
            'request_id' => null,
            'metadata' => [
                'sms_sent_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Record SMS failure
     */
    public function recordSmsFailure(
        Payment $payment,
        string $error,
        ?string $reason = null
    ): PaymentHistory {
        return PaymentHistory::create([
            'payment_id' => $payment->id,
            'correlation_id' => $payment->correlation_id,
            'action' => 'sms_failed',
            'changed_by_type' => 'system',
            'changed_by_id' => null,
            'field_name' => 'sms_error',
            'old_value' => null,
            'new_value' => $error,
            'reason' => $reason ?? "SMS send failed: {$error}",
            'ip_address' => null,
            'user_agent' => null,
            'session_id' => null,
            'request_id' => null,
            'metadata' => [
                'retry_count' => $payment->sms_retry_count,
            ],
        ]);
    }

    /**
     * Get payment history
     */
    public function getHistory(Payment $payment): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentHistory::where('payment_id', $payment->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get complete audit trail by correlation ID
     */
    public function getAuditTrail(string $correlationId): array
    {
        $history = PaymentHistory::where('correlation_id', $correlationId)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'correlation_id' => $correlationId,
            'events' => $history->map(function ($event) {
                return [
                    'timestamp' => $event->created_at->toIso8601String(),
                    'action' => $event->action,
                    'field' => $event->field_name,
                    'old_value' => $event->old_value,
                    'new_value' => $event->new_value,
                    'reason' => $event->reason,
                    'changed_by' => $event->changed_by_type,
                ];
            }),
        ];
    }
}
