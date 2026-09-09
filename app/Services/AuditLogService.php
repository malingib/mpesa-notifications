<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Audit Log Service
 * 
 * Provides structured logging with correlation IDs and categorization.
 */
class AuditLogService
{
    /**
     * Log an audit event
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $category = null,
        ?string $correlationId = null
    ): AuditLog {
        return AuditLog::create([
            'correlation_id' => $correlationId,
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'changes' => null,
            'metadata' => $metadata,
            'severity' => $severity,
            'category' => $category ?? $this->inferCategory($action),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
        ]);
    }

    /**
     * Log payment event
     */
    public function logPayment(
        string $action,
        int $paymentId,
        ?string $description = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $correlationId = null
    ): AuditLog {
        return $this->log(
            $action,
            'Payment',
            $paymentId,
            $description,
            $metadata,
            $severity,
            'payment',
            $correlationId
        );
    }

    /**
     * Log SMS event
     */
    public function logSms(
        string $action,
        ?int $paymentId = null,
        ?string $description = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $correlationId = null
    ): AuditLog {
        return $this->log(
            $action,
            $paymentId ? 'Payment' : null,
            $paymentId,
            $description,
            $metadata,
            $severity,
            'sms',
            $correlationId
        );
    }

    /**
     * Log authentication event
     */
    public function logAuth(
        string $action,
        ?int $userId = null,
        ?string $description = null,
        array $metadata = [],
        string $severity = 'info'
    ): AuditLog {
        return $this->log(
            $action,
            'User',
            $userId,
            $description,
            $metadata,
            $severity,
            'auth'
        );
    }

    /**
     * Log system event
     */
    public function logSystem(
        string $action,
        ?string $description = null,
        array $metadata = [],
        string $severity = 'info'
    ): AuditLog {
        return $this->log(
            $action,
            null,
            null,
            $description,
            $metadata,
            $severity,
            'system'
        );
    }

    /**
     * Get audit logs by correlation ID
     */
    public function getByCorrelationId(string $correlationId): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('correlation_id', $correlationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get audit logs by category
     */
    public function getByCategory(string $category, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('category', $category)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Infer category from action
     */
    private function inferCategory(string $action): ?string
    {
        if (str_starts_with($action, 'payment.')) {
            return 'payment';
        }
        
        if (str_starts_with($action, 'sms.')) {
            return 'sms';
        }
        
        if (str_starts_with($action, 'auth.')) {
            return 'auth';
        }
        
        return 'system';
    }
}
