<?php

declare(strict_types=1);

namespace App\Courier\DHL\Exception;

use App\Domain\Courier\Exception\CourierIntegrationException;

class DHLIntegrationException extends CourierIntegrationException
{
    public static function authenticationFailed(): self
    {
        return new self(
            'DHL API authentication failed. Please check your API credentials.',
            ['service' => 'DHL', 'error_type' => 'authentication'],
            401
        );
    }

    public static function trackingNotFound(string $trackingNumber): self
    {
        return new self(
            "Tracking information not found for DHL shipment: {$trackingNumber}",
            ['service' => 'DHL', 'tracking_number' => $trackingNumber, 'error_type' => 'not_found'],
            404
        );
    }

    public static function invalidAddress(string $message): self
    {
        return new self(
            "Invalid address for DHL shipment: {$message}",
            ['service' => 'DHL', 'error_type' => 'validation', 'field' => 'address'],
            400
        );
    }

    public static function invalidWeight(float $weight): self
    {
        return new self(
            "Invalid weight for DHL shipment: {$weight}kg. Weight must be between 0.1kg and 70kg.",
            ['service' => 'DHL', 'error_type' => 'validation', 'field' => 'weight', 'value' => $weight],
            400
        );
    }

    public static function apiRateLimit(int $retryAfter = 60): self
    {
        return new self(
            "DHL API rate limit exceeded. Please retry after {$retryAfter} seconds.",
            ['service' => 'DHL', 'error_type' => 'rate_limit', 'retry_after' => $retryAfter],
            429
        );
    }

    public static function shipmentCreationFailed(string $reason): self
    {
        return new self(
            "Failed to create DHL shipment: {$reason}",
            ['service' => 'DHL', 'error_type' => 'creation_failed'],
            400
        );
    }

    public static function connectionTimeout(): self
    {
        return new self(
            'Connection timeout while communicating with DHL API.',
            ['service' => 'DHL', 'error_type' => 'timeout'],
            408
        );
    }

    public static function serviceUnavailable(): self
    {
        return new self(
            'DHL API service is currently unavailable. Please try again later.',
            ['service' => 'DHL', 'error_type' => 'service_unavailable'],
            503
        );
    }
}