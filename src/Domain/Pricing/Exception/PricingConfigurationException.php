<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exception;

/**
 * Exception for pricing configuration errors
 */
class PricingConfigurationException extends PricingException
{
    public static function duplicatePricingRule(string $carrierCode, string $zoneCode, float $weightFrom): self
    {
        return new self(sprintf(
            'Duplicate pricing rule found for carrier "%s", zone "%s", weight from %.3f kg',
            $carrierCode,
            $zoneCode,
            $weightFrom
        ));
    }

    public static function invalidWeightRange(float $weightFrom, float $weightTo): self
    {
        return new self(sprintf(
            'Invalid weight range: from %.3f kg to %.3f kg',
            $weightFrom,
            $weightTo
        ));
    }

    public static function overlappingWeightRanges(float $weight1From, float $weight1To, float $weight2From, float $weight2To): self
    {
        return new self(sprintf(
            'Overlapping weight ranges: %.3f-%.3f kg and %.3f-%.3f kg',
            $weight1From,
            $weight1To,
            $weight2From,
            $weight2To
        ));
    }

    public static function missingBasePrice(): self
    {
        return new self('Base price is required for pricing table');
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(sprintf('Invalid currency code "%s"', $currency));
    }

    public static function invalidTaxRate(float $taxRate): self
    {
        return new self(sprintf('Invalid tax rate %.2f%%', $taxRate));
    }

    public static function missingVolumetricDivisor(string $pricingModel): self
    {
        return new self(sprintf(
            'Volumetric divisor is required for pricing model "%s"',
            $pricingModel
        ));
    }
}