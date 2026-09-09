<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice Item Model
 * 
 * Represents a line item on an invoice.
 */
class InvoiceItem extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the invoice that owns this item
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calculate line total before saving
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotal();
        });
    }

    /**
     * Calculate the line total
     */
    public function calculateTotal(): void
    {
        $lineSubtotal = ($this->quantity * $this->unit_price) - $this->discount_amount;
        $this->total = $lineSubtotal;
    }
}
