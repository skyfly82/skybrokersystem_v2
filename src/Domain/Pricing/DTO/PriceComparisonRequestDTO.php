<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for price comparison across carriers
 */
class PriceComparisonRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    public string $zoneCode;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['standard', 'express', 'overnight', 'economy', 'premium'])]
    public string $serviceType = 'standard';

    #[Assert\NotNull]
    #[Assert\Positive]
    public float $weightKg;

    /**
     * @var array{length: int, width: int, height: int}
     */
    #[Assert\NotNull]
    public array $dimensionsCm;

    #[Assert\Length(exactly: 3)]
    public string $currency = 'PLN';

    #[Assert\Type('array')]
    public array $additionalServices = [];

    public ?int $customerId = null;

    /**
     * List of carrier codes to include in comparison
     */
    #[Assert\Type('array')]
    public array $includeCarriers = [];

    /**
     * List of carrier codes to exclude from comparison
     */
    #[Assert\Type('array')]
    public array $excludeCarriers = [];

    public bool $includeInactiveCarriers = false;

    public function __construct(
        string $zoneCode,
        float $weightKg,
        array $dimensionsCm,
        string $serviceType = 'standard',
        string $currency = 'PLN'
    ) {
        $this->zoneCode = strtoupper($zoneCode);
        $this->weightKg = $weightKg;
        $this->dimensionsCm = $dimensionsCm;
        $this->serviceType = $serviceType;
        $this->currency = strtoupper($currency);
    }

    public function getVolumetricWeight(int $divisor = 5000): float
    {
        $volume = $this->dimensionsCm['length'] * $this->dimensionsCm['width'] * $this->dimensionsCm['height'];
        return $volume / $divisor;
    }

    public function getChargeableWeight(int $volumetricDivisor = 5000): float
    {
        return max($this->weightKg, $this->getVolumetricWeight($volumetricDivisor));
    }

    public function hasAdditionalService(string $serviceCode): bool
    {
        return in_array($serviceCode, $this->additionalServices);
    }

    public function shouldIncludeCarrier(string $carrierCode): bool
    {
        if (!empty($this->includeCarriers)) {
            return in_array(strtoupper($carrierCode), array_map('strtoupper', $this->includeCarriers));
        }

        if (!empty($this->excludeCarriers)) {
            return !in_array(strtoupper($carrierCode), array_map('strtoupper', $this->excludeCarriers));
        }

        return true;
    }

    public function toPriceCalculationRequest(string $carrierCode): PriceCalculationRequestDTO
    {
        $request = new PriceCalculationRequestDTO(
            $carrierCode,
            $this->zoneCode,
            $this->weightKg,
            $this->dimensionsCm,
            $this->serviceType,
            $this->currency
        );

        $request->additionalServices = $this->additionalServices;
        $request->customerId = $this->customerId;

        return $request;
    }
}