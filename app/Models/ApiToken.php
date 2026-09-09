<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API Token Model
 * 
 * Stores API tokens for client authentication.
 * Tokens are hashed (SHA-256) and never stored in plain text.
 */
class ApiToken extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'token_prefix',
        'scopes',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if token is active
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->trashed() && !$this->isExpired();
    }
}
