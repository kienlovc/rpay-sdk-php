<?php

declare(strict_types=1);

namespace RPay\SDK;

use RuntimeException;
use Throwable;

/**
 * RPayException represents a structured error thrown by the RPAY PHP SDK.
 */
class RPayException extends RuntimeException
{
    /** @var string */
    private string $errorCode;

    /** @var int */
    private int $statusCode;

    /** @var array<string, mixed> */
    private array $details;

    /**
     * @param string $message
     * @param string $errorCode
     * @param int $statusCode
     * @param array<string, mixed> $details
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        string $errorCode = 'RPAY_ERROR',
        int $statusCode = 0,
        array $details = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
