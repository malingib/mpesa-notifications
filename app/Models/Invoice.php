<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invoice Model
 * 
 * Represents invoices with line items and payment tracking.
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'paid_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'currency',
        'notes',
        'terms',
        'reference',
        'recurring_id',
        'parent_invoice_id',
        'pdf_path',
        'sent_at',
        'viewed_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    /**
     * Get the user (tenant) that owns this invoice
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer for this invoice
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all line items for this invoice
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Get all payments linked to this invoice
     */
    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Get all payments through invoice_payments pivot
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, InvoicePayment::class, 'invoice_id', 'id', 'id', 'payment_id');
    }

    /**
     * Get the recurring invoice template (if applicable)
     */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class, 'recurring_id');
    }

    /**
     * Get the parent invoice (if this is a credit note or amendment)
     */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Get child invoices (credit notes, amendments)
     */
    public function childInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid' 
            && $this->status !== 'cancelled'
            && $this->due_date->isPast()
            && $this->balance > 0;
    }

    /**
     * Check if invoice is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->balance <= 0;
    }

    /**
     * Check if invoice is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->balance > 0;
    }

    /**
     * Update invoice status based on payment
     */
    public function updateStatus(): void
    {
        if ($this->balance <= 0) {
            $this->status = 'paid';
            $this->paid_date = Carbon::today();
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->isOverdue()) {
            $this->status = 'overdue';
        } elseif (in_array($this->status, ['draft', 'sent', 'viewed'])) {
            // Keep current status if not paid and not overdue
        }

        $this->save();
    }

    /**
     * Scope to get unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['sent', 'viewed', 'partial', 'overdue'])
            ->where('balance', '>', 0);
    }

    /**
     * Scope to get overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', Carbon::today())
            ->where('balance', '>', 0);
    }
}
