<?php

namespace App\Exceptions;

use Exception;

/**
 * Talksasa SMS Exception
 * 
 * Custom exception for Talksasa SMS API errors.
 * Includes retryability flag for error handling.
 */
class TalksasaSmsException extends Exception
{
    protected bool $isRetryable;
    protected ?array $context;

    public function __construct(
        string $message = "",
        int $code = 0,
        bool $isRetryable = false,
        ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->isRetryable = $isRetryable;
        $this->context = $context ?? [];
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }

    /**
     * Get error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for transient error
     */
    public static function transient(string $message, int $code = 500, ?array $context = null, ?\Throwable $previous = null): self
    {
        return new self($message, $code, true, $context, $previous);
    }

    /**
     * Create exception for permanent error
     */
    public static function permanent(string $message, int $code = 400, ?array $context = null, ?\Throwable $previous = null): self
    {
        return new self($message, $code, false, $context, $previous);
    }
}
