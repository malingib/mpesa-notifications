<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit Log Model
 * 
 * Comprehensive audit log for all system operations.
 */
class AuditLog extends Model
{
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'correlation_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'changes',
        'metadata',
        'severity',
        'category',
        'ip_address',
        'user_agent',
        'session_id',
        'request_id',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
