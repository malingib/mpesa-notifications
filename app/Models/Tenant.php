<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant Model
 * 
 * Represents a client who owns Paybill/Till numbers.
 * Provides strict tenant isolation for all payment operations.
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get all payment accounts for this tenant
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * Get all payments for this tenant
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed();
    }
}
