<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SMS Failure Model
 * 
 * Tracks SMS failures for analytics and monitoring.
 * Optional table for detailed failure tracking.
 */
class SmsFailure extends Model
{
    protected $fillable = [
        'payment_id',
        'user_id',
        'phone_number',
        'message',
        'error_message',
        'error_code',
        'is_retryable',
        'attempt_count',
        'first_failed_at',
        'last_failed_at',
        'resolved_at',
    ];

    protected $casts = [
        'is_retryable' => 'boolean',
        'attempt_count' => 'integer',
        'first_failed_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the payment associated with this failure
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user associated with this failure
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if failure is resolved
     */
    public function isResolved(): bool
    {
        return !is_null($this->resolved_at);
    }
}
