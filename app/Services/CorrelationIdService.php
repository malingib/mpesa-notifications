<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Correlation ID Service
 * 
 * Generates and manages correlation IDs for end-to-end tracing.
 */
class CorrelationIdService
{
    /**
     * Generate a new correlation ID
     * 
     * Format: {prefix}-{timestamp}-{random}
     * Example: PAY-20240126143045-abc123def456
     * 
     * @param string $prefix Prefix for the correlation ID (default: PAY)
     * @return string
     */
    public function generate(string $prefix = 'PAY'): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::lower(Str::random(12));
        
        return sprintf('%s-%s-%s', $prefix, $timestamp, $random);
    }

    /**
     * Extract timestamp from correlation ID
     * 
     * @param string $correlationId
     * @return \Carbon\Carbon|null
     */
    public function extractTimestamp(string $correlationId): ?\Carbon\Carbon
    {
        $parts = explode('-', $correlationId);
        
        if (count($parts) < 3) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::createFromFormat('YmdHis', $parts[1]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate correlation ID format
     * 
     * @param string $correlationId
     * @return bool
     */
    public function isValid(string $correlationId): bool
    {
        return preg_match('/^[A-Z]+-\d{14}-[a-z0-9]{12}$/', $correlationId) === 1;
    }
}
