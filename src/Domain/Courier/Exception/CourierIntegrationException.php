<?php

declare(strict_types=1);

namespace App\Domain\Courier\Exception;

use RuntimeException;
use Throwable;

class CourierIntegrationException extends RuntimeException
{
    private ?array $context = null;

    public function __construct(
        string $message = '', 
        int $code = 0, 
        ?Throwable $previous = null,
        ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'trace' => $this->getTraceAsString(),
            'context' => $this->getContext()
        ];
    }
}