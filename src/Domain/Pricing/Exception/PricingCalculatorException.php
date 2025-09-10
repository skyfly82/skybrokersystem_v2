<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exception;

/**
 * Exception for PricingCalculator service
 */
class PricingCalculatorException extends PricingException
{
    public static function noCarriersAvailable(string $zoneCode): self
    {
        return new self(sprintf('No carriers available for zone "%s"', $zoneCode));
    }

    public static function allCarrierCalculationsFailed(string $zoneCode): self
    {
        return new self(sprintf('All carrier price calculations failed for zone "%s"', $zoneCode));
    }

    public static function bulkCalculationPartialFailure(int $successful, int $failed): self
    {
        return new self(sprintf(
            'Bulk calculation completed with partial failures: %d successful, %d failed',
            $successful,
            $failed
        ));
    }

    public static function bulkCalculationCompleteFailure(): self
    {
        return new self('All bulk price calculations failed');
    }

    public static function invalidBulkRequest(string $reason): self
    {
        return new self(sprintf('Invalid bulk price calculation request: %s', $reason));
    }

    public static function promotionApplicationFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Failed to apply promotions: %s', $reason), 0, $previous);
    }

    public static function additionalServiceCalculationFailed(string $serviceCode, string $reason): self
    {
        return new self(sprintf(
            'Failed to calculate additional service "%s": %s',
            $serviceCode,
            $reason
        ));
    }

    public static function carrierValidationFailed(string $carrierCode, string $reason): self
    {
        return new self(sprintf(
            'Carrier "%s" validation failed: %s',
            $carrierCode,
            $reason
        ));
    }

    public static function comparisonRequestValidationFailed(string $reason): self
    {
        return new self(sprintf('Price comparison request validation failed: %s', $reason));
    }

    public static function timeoutError(float $timeoutSeconds): self
    {
        return new self(sprintf(
            'Price calculation timeout after %.2f seconds',
            $timeoutSeconds
        ));
    }

    public static function concurrencyLimitReached(int $limit): self
    {
        return new self(sprintf(
            'Pricing calculation concurrency limit reached: %d',
            $limit
        ));
    }

    public static function serviceUnavailable(string $service, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Pricing service "%s" is temporarily unavailable', $service), 0, $previous);
    }

    public static function invalidCalculationResult(string $carrierCode, string $reason): self
    {
        return new self(sprintf(
            'Invalid calculation result from carrier "%s": %s',
            $carrierCode,
            $reason
        ));
    }
}