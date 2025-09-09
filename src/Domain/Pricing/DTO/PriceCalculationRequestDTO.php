<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for price calculation
 */
class PriceCalculationRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $carrierCode;

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

    public function __construct(
        string $carrierCode,
        string $zoneCode,
        float $weightKg,
        array $dimensionsCm,
        string $serviceType = 'standard',
        string $currency = 'PLN'
    ) {
        $this->carrierCode = strtoupper($carrierCode);
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
}