<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Merchant Model
 * 
 * Maps Paybill/Till numbers to users.
 * Each account can have custom SMS templates.
 */
class Merchant extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        // Apply tenant scope globally
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'user_id',
        'account_type',
        'account_number',
        'account_name',
        'is_active',
        'sms_enabled',
        'sms_template_id',
        'webhook_url',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sms_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this merchant
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all payments for this merchant
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the default SMS template
     */
    public function smsTemplate(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    /**
     * Check if merchant is active
     */
    public function isActive(): bool
    {
        return $this->is_active 
            && !$this->trashed() 
            && $this->user?->isActive();
    }
}
