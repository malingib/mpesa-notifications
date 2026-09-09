<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Data Anonymization Model
 * 
 * Tracks GDPR anonymization operations.
 */
class DataAnonymization extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'anonymized_at',
        'anonymized_by_type',
        'anonymized_by_id',
        'reason',
        'reason_details',
        'fields_anonymized',
        'metadata',
    ];

    protected $casts = [
        'anonymized_at' => 'datetime',
        'fields_anonymized' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who performed anonymization (if applicable)
     */
    public function anonymizedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'anonymized_by_id');
    }
}
