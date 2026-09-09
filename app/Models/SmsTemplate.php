<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SMS Template Model
 * 
 * Reusable SMS message templates.
 * NULL user_id = global template, otherwise user-specific.
 */
class SmsTemplate extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        // Apply tenant scope globally (only for user-specific templates)
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'user_id',
        'name',
        'message',
        'placeholders',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns this template (NULL for global templates)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if template is global
     */
    public function isGlobal(): bool
    {
        return is_null($this->user_id);
    }
}
