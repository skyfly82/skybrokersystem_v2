<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\Exception;

use App\Domain\Courier\Exception\CourierIntegrationException;

/**
 * Exception for MEEST API integration errors
 */
class MeestIntegrationException extends CourierIntegrationException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $apiResponse = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getApiResponse(): ?array
    {
        return $this->apiResponse;
    }

    public static function authenticationFailed(string $message = 'MEEST authentication failed'): self
    {
        return new self($message, 401);
    }

    public static function invalidCredentials(): self
    {
        return new self('Invalid MEEST API credentials', 401);
    }

    public static function rateLimitExceeded(): self
    {
        return new self('MEEST API rate limit exceeded', 429);
    }

    public static function invalidCountry(string $country): self
    {
        return new self("Country '{$country}' is not supported by MEEST", 400);
    }

    public static function shipmentNotFound(string $trackingNumber): self
    {
        return new self("Shipment with tracking number '{$trackingNumber}' not found", 404);
    }

    public static function labelGenerationFailed(string $trackingNumber): self
    {
        return new self("Failed to generate label for tracking number '{$trackingNumber}'", 500);
    }

    public static function fromApiResponse(array $response, int $httpCode = 500): self
    {
        $message = $response['error']['message'] ?? $response['message'] ?? 'Unknown MEEST API error';
        $errorCode = $response['error']['code'] ?? $httpCode;

        return new self($message, $errorCode, null, $response);
    }

    public static function nonUniqueParcelNumber(): self
    {
        return new self('Non-unique parcel number', 409);
    }

    public static function routingNotFound(): self
    {
        return new self('Any routing not found for the destination', 422);
    }

    public function isRetryable(): bool
    {
        return in_array($this->getCode(), [409, 429, 500, 502, 503, 504]);
    }

    public function isValidationError(): bool
    {
        return $this->getCode() === 422;
    }

    public function isAuthenticationError(): bool
    {
        return $this->getCode() === 401;
    }
}