<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment Account Model
 * 
 * Maps Paybill/Till numbers to tenants.
 * Each account can have custom SMS templates.
 */
class PaymentAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'account_type',
        'account_number',
        'account_name',
        'is_active',
        'sms_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sms_template' => 'array',
    ];

    /**
     * Get the tenant that owns this payment account
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all payments for this account
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->is_active 
            && !$this->trashed() 
            && $this->tenant?->isActive();
    }

    /**
     * Get SMS template or default
     */
    public function getSmsTemplate(): ?array
    {
        return $this->sms_template;
    }
}
