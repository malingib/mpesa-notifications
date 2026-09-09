<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Model
 * 
 * Stores all payment transactions with idempotency support.
 * Tracks SMS notification status and retry attempts.
 */
class Payment extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        // Apply tenant scope globally
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'correlation_id',
        'user_id',
        'merchant_id',
        'transaction_id',
        'receipt_number',
        'request_id',
        'account_type',
        'account_number',
        'amount',
        'currency',
        'phone_number',
        'payer_name',
        'transaction_time',
        'status',
        'description',
        'metadata',
        'sms_sent',
        'sms_sent_at',
        'sms_error',
        'sms_retry_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_time' => 'datetime',
        'sms_sent' => 'boolean',
        'sms_sent_at' => 'datetime',
        'sms_retry_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the merchant account for this payment
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Mark SMS as sent
     */
    public function markSmsSent(): void
    {
        $this->update([
            'sms_sent' => true,
            'sms_sent_at' => now(),
            'sms_error' => null,
        ]);
    }

    /**
     * Record SMS failure
     */
    public function recordSmsFailure(string $error): void
    {
        $this->increment('sms_retry_count');
        $this->update([
            'sms_error' => $error,
        ]);
    }

    /**
     * Check if SMS can be retried
     */
    public function canRetrySms(int $maxRetries = 3): bool
    {
        return !$this->sms_sent && $this->sms_retry_count < $maxRetries;
    }

    /**
     * Get payment history
     */
    public function history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentHistory::class);
    }

    /**
     * Get SMS attempts
     */
    public function smsAttempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsAttempt::class);
    }
}
