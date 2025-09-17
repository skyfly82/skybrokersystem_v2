<?php

declare(strict_types=1);

namespace App\Courier\InPost\Exception;

class InPostIntegrationException extends \Exception
{
    private ?string $errorCode;
    private array $context;
    private ?int $httpStatusCode;

    public function __construct(
        string $message = '',
        ?string $errorCode = null,
        array $context = [],
        ?int $httpStatusCode = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public static function authenticationFailed(): self
    {
        return new self(
            'InPost API authentication failed. Check your API key.',
            'AUTHENTICATION_FAILED',
            [],
            401
        );
    }

    public static function invalidPostalCode(string $postalCode): self
    {
        return new self(
            "Invalid Polish postal code: {$postalCode}. Expected format: XX-XXX",
            'INVALID_POSTAL_CODE',
            ['postal_code' => $postalCode]
        );
    }

    public static function invalidPhoneNumber(string $phoneNumber): self
    {
        return new self(
            "Invalid Polish phone number: {$phoneNumber}. Expected format: +48XXXXXXXXX",
            'INVALID_PHONE_NUMBER',
            ['phone_number' => $phoneNumber]
        );
    }

    public static function invalidPaczkomat(string $paczkomatCode): self
    {
        return new self(
            "Invalid Paczkomat code: {$paczkomatCode}",
            'INVALID_PACZKOMAT',
            ['paczkomat_code' => $paczkomatCode]
        );
    }

    public static function paczkomatNotAvailable(string $paczkomatCode): self
    {
        return new self(
            "Paczkomat {$paczkomatCode} is not available for selected parcel size",
            'PACZKOMAT_NOT_AVAILABLE',
            ['paczkomat_code' => $paczkomatCode]
        );
    }

    public static function parcelTooLarge(array $dimensions, string $parcelSize): self
    {
        return new self(
            "Parcel dimensions exceed limits for size category '{$parcelSize}'",
            'PARCEL_TOO_LARGE',
            ['dimensions' => $dimensions, 'parcel_size' => $parcelSize]
        );
    }

    public static function shipmentAlreadyDispatched(string $trackingNumber): self
    {
        return new self(
            "Shipment {$trackingNumber} cannot be cancelled as it has already been dispatched",
            'SHIPMENT_ALREADY_DISPATCHED',
            ['tracking_number' => $trackingNumber]
        );
    }

    public static function apiRateLimit(int $retryAfter): self
    {
        return new self(
            "API rate limit exceeded. Retry after {$retryAfter} seconds.",
            'API_RATE_LIMIT',
            ['retry_after' => $retryAfter],
            429
        );
    }

    public static function trackingNumberNotFound(string $trackingNumber): self
    {
        return new self(
            "Tracking number {$trackingNumber} not found",
            'TRACKING_NOT_FOUND',
            ['tracking_number' => $trackingNumber],
            404
        );
    }

    public static function apiRequestFailed(string $endpoint, int $statusCode, string $responseBody): self
    {
        return new self(
            "InPost API request to {$endpoint} failed with status {$statusCode}",
            'API_REQUEST_FAILED',
            ['endpoint' => $endpoint, 'status_code' => $statusCode, 'response' => $responseBody],
            $statusCode
        );
    }
}
