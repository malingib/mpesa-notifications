<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recurring Invoice Model
 * 
 * Manages recurring invoice templates.
 */
class RecurringInvoice extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'name',
        'frequency',
        'start_date',
        'end_date',
        'next_invoice_date',
        'invoice_template',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_invoice_date' => 'date',
        'invoice_template' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user (tenant) that owns this recurring invoice
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all invoices generated from this template
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurring_id');
    }

    /**
     * Check if recurring invoice is active
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Calculate next invoice date based on frequency
     */
    public function calculateNextInvoiceDate(): \Carbon\Carbon
    {
        $nextDate = $this->next_invoice_date->copy();

        switch ($this->frequency) {
            case 'daily':
                $nextDate->addDay();
                break;
            case 'weekly':
                $nextDate->addWeek();
                break;
            case 'monthly':
                $nextDate->addMonth();
                break;
            case 'quarterly':
                $nextDate->addMonths(3);
                break;
            case 'yearly':
                $nextDate->addYear();
                break;
        }

        return $nextDate;
    }
}
