<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exception;

/**
 * Base exception for pricing domain
 */
class PricingException extends \Exception
{
    protected ?array $context = null;

    public static function carrierNotFound(string $carrierCode): self
    {
        return new self(sprintf('Carrier with code "%s" not found', $carrierCode));
    }

    public static function zoneNotFound(string $zoneCode): self
    {
        return new self(sprintf('Pricing zone with code "%s" not found', $zoneCode));
    }

    public static function pricingTableNotFound(string $carrierCode, string $zoneCode, string $serviceType): self
    {
        return new self(sprintf(
            'Pricing table not found for carrier "%s", zone "%s", service type "%s"',
            $carrierCode,
            $zoneCode,
            $serviceType
        ));
    }

    public static function noPricingRuleFound(float $weight): self
    {
        return new self(sprintf('No pricing rule found for weight %.3f kg', $weight));
    }

    public static function weightExceedsLimit(float $weight, float $maxWeight): self
    {
        return new self(sprintf(
            'Weight %.3f kg exceeds maximum allowed weight %.3f kg',
            $weight,
            $maxWeight
        ));
    }

    public static function dimensionsExceedLimit(array $dimensions, array $maxDimensions): self
    {
        return new self(sprintf(
            'Dimensions %dx%dx%d cm exceed maximum allowed dimensions %dx%dx%d cm',
            $dimensions[0] ?? 0,
            $dimensions[1] ?? 0,
            $dimensions[2] ?? 0,
            $maxDimensions[0] ?? 0,
            $maxDimensions[1] ?? 0,
            $maxDimensions[2] ?? 0
        ));
    }

    public static function carrierDoesNotSupportZone(string $carrierCode, string $zoneCode): self
    {
        return new self(sprintf(
            'Carrier "%s" does not support zone "%s"',
            $carrierCode,
            $zoneCode
        ));
    }

    public static function invalidPricingConfiguration(string $reason): self
    {
        return new self(sprintf('Invalid pricing configuration: %s', $reason));
    }

    public static function customerPricingNotFound(int $customerId, string $carrierCode): self
    {
        return new self(sprintf(
            'Customer pricing not found for customer ID %d and carrier "%s"',
            $customerId,
            $carrierCode
        ));
    }

    public static function additionalServiceNotFound(string $serviceCode): self
    {
        return new self(sprintf('Additional service with code "%s" not found', $serviceCode));
    }

    public static function calculationError(string $reason, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Price calculation error: %s', $reason), 0, $previous);
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}