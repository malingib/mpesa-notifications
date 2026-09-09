<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment History Model
 * 
 * Immutable record of all changes to payment records.
 */
class PaymentHistory extends Model
{
    protected $table = 'payment_history';

    protected $fillable = [
        'payment_id',
        'correlation_id',
        'action',
        'changed_by_type',
        'changed_by_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'ip_address',
        'user_agent',
        'session_id',
        'request_id',
        'metadata',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the payment this history entry belongs to
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user who made the change (if applicable)
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
