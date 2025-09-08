<?php

declare(strict_types=1);

namespace App\Domain\InPost\Exception;

use App\Domain\Courier\Exception\CourierIntegrationException;

class InPostIntegrationException extends CourierIntegrationException
{
    public const ERROR_INVALID_PACZKOMAT = 'INVALID_PACZKOMAT';
    public const ERROR_PARCEL_TOO_LARGE = 'PARCEL_TOO_LARGE';
    public const ERROR_INVALID_POSTAL_CODE = 'INVALID_POSTAL_CODE';
    public const ERROR_INVALID_PHONE_NUMBER = 'INVALID_PHONE_NUMBER';
    public const ERROR_COD_NOT_SUPPORTED = 'COD_NOT_SUPPORTED';
    public const ERROR_PACZKOMAT_NOT_AVAILABLE = 'PACZKOMAT_NOT_AVAILABLE';
    public const ERROR_SHIPMENT_ALREADY_CANCELED = 'SHIPMENT_ALREADY_CANCELED';
    public const ERROR_SHIPMENT_ALREADY_DISPATCHED = 'SHIPMENT_ALREADY_DISPATCHED';
    public const ERROR_API_RATE_LIMIT = 'API_RATE_LIMIT';
    public const ERROR_AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    public const ERROR_INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';

    private ?string $errorCode = null;
    private array $errorDetails = [];

    public function __construct(
        string $message,
        ?string $errorCode = null,
        array $errorDetails = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public static function invalidPaczkomat(string $paczkomatCode): self
    {
        return new self(
            "Invalid Paczkomat code: {$paczkomatCode}",
            self::ERROR_INVALID_PACZKOMAT,
            ['paczkomat_code' => $paczkomatCode]
        );
    }

    public static function parcelTooLarge(array $dimensions, string $maxSize): self
    {
        return new self(
            "Parcel dimensions exceed maximum size: {$maxSize}",
            self::ERROR_PARCEL_TOO_LARGE,
            ['dimensions' => $dimensions, 'max_size' => $maxSize]
        );
    }

    public static function invalidPostalCode(string $postalCode): self
    {
        return new self(
            "Invalid Polish postal code: {$postalCode}",
            self::ERROR_INVALID_POSTAL_CODE,
            ['postal_code' => $postalCode]
        );
    }

    public static function invalidPhoneNumber(string $phoneNumber): self
    {
        return new self(
            "Invalid Polish phone number: {$phoneNumber}",
            self::ERROR_INVALID_PHONE_NUMBER,
            ['phone_number' => $phoneNumber]
        );
    }

    public static function paczkomatNotAvailable(string $paczkomatCode): self
    {
        return new self(
            "Paczkomat {$paczkomatCode} is not available",
            self::ERROR_PACZKOMAT_NOT_AVAILABLE,
            ['paczkomat_code' => $paczkomatCode]
        );
    }

    public static function shipmentAlreadyCanceled(string $trackingNumber): self
    {
        return new self(
            "Shipment {$trackingNumber} is already canceled",
            self::ERROR_SHIPMENT_ALREADY_CANCELED,
            ['tracking_number' => $trackingNumber]
        );
    }

    public static function shipmentAlreadyDispatched(string $trackingNumber): self
    {
        return new self(
            "Shipment {$trackingNumber} is already dispatched and cannot be modified",
            self::ERROR_SHIPMENT_ALREADY_DISPATCHED,
            ['tracking_number' => $trackingNumber]
        );
    }

    public static function apiRateLimit(int $retryAfterSeconds): self
    {
        return new self(
            "API rate limit exceeded. Retry after {$retryAfterSeconds} seconds",
            self::ERROR_API_RATE_LIMIT,
            ['retry_after' => $retryAfterSeconds]
        );
    }

    public static function authenticationFailed(): self
    {
        return new self(
            'InPost API authentication failed',
            self::ERROR_AUTHENTICATION_FAILED
        );
    }

    public static function insufficientFunds(float $balance, float $cost): self
    {
        return new self(
            "Insufficient funds. Balance: {$balance} PLN, Required: {$cost} PLN",
            self::ERROR_INSUFFICIENT_FUNDS,
            ['balance' => $balance, 'required' => $cost]
        );
    }
}