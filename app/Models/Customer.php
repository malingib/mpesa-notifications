<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer Model
 * 
 * Represents customers/clients for invoicing and CRM.
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'company',
        'tax_id',
        'address',
        'city',
        'country',
        'notes',
        'tags',
        'status',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * Get the user (tenant) that owns this customer
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all invoices for this customer
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all payments for this customer (via invoices)
     */
    public function payments(): HasMany
    {
        return $this->hasManyThrough(Payment::class, Invoice::class, 'customer_id', 'id', 'id', 'id')
            ->whereHas('invoicePayments');
    }

    /**
     * Check if customer is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->trashed();
    }

    /**
     * Get total amount owed by this customer
     */
    public function getTotalOwedAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['sent', 'viewed', 'partial', 'overdue'])
            ->sum('balance');
    }

    /**
     * Get total amount paid by this customer
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['paid', 'partial'])
            ->sum('paid_amount');
    }
}
