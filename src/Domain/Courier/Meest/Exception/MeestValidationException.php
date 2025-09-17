<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Exception;

/**
 * Exception for MEEST validation errors
 */
class MeestValidationException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $validationErrors = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }

    public static function withErrors(array $errors): self
    {
        $message = 'Validation failed: ' . implode(', ', $errors);
        return new self($message, 422, null, $errors);
    }

    public static function requiredField(string $fieldName): self
    {
        return new self("Field '{$fieldName}' is required", 422);
    }

    public static function invalidFormat(string $fieldName, string $expectedFormat): self
    {
        return new self("Field '{$fieldName}' has invalid format. Expected: {$expectedFormat}", 422);
    }

    public static function invalidValue(string $fieldName, $value, array $allowedValues = []): self
    {
        $message = "Invalid value '{$value}' for field '{$fieldName}'";

        if (!empty($allowedValues)) {
            $message .= '. Allowed values: ' . implode(', ', $allowedValues);
        }

        return new self($message, 422);
    }
}