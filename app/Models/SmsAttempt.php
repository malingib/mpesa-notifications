<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SMS Attempt Model
 * 
 * Tracks every SMS send attempt for complete audit trail.
 */
class SmsAttempt extends Model
{
    protected $fillable = [
        'payment_id',
        'user_id',
        'correlation_id',
        'job_id',
        'attempt_number',
        'phone_number',
        'message',
        'message_length',
        'status',
        'error_code',
        'error_message',
        'provider_response',
        'sent_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'message_length' => 'integer',
        'provider_response' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the payment this attempt belongs to
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user this attempt belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if attempt was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if attempt failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if attempt was skipped
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
