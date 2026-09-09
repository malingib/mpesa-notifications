<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Reconciliation Model
 * 
 * Tracks payment-to-invoice matching.
 */
class PaymentReconciliation extends Model
{
    protected $fillable = [
        'user_id',
        'payment_id',
        'invoice_id',
        'match_type',
        'match_confidence',
        'matched_by',
        'matched_at',
        'amount_matched',
        'notes',
    ];

    protected $casts = [
        'amount_matched' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    /**
     * Get the user (tenant)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the invoice (nullable if unmatched)
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who matched this (nullable for auto-matches)
     */
    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    /**
     * Scope to get matched reconciliations
     */
    public function scopeMatched($query)
    {
        return $query->whereNotNull('invoice_id');
    }

    /**
     * Scope to get unmatched reconciliations
     */
    public function scopeUnmatched($query)
    {
        return $query->whereNull('invoice_id');
    }
}
