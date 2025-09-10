<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pricing for additional services within specific pricing tables
 * 
 * Links additional services to pricing tables with specific rates,
 * allowing different prices for the same service across different zones/carriers.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\AdditionalServicePriceRepository::class)]
#[ORM\Table(name: 'v2_additional_service_prices')]
#[ORM\Index(name: 'IDX_SERVICE_PRICE_TABLE', columns: ['pricing_table_id'])]
#[ORM\Index(name: 'IDX_SERVICE_PRICE_SERVICE', columns: ['additional_service_id'])]
#[ORM\Index(name: 'IDX_SERVICE_PRICE_ACTIVE', columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'UNQ_SERVICE_PRICE_TABLE_SERVICE', columns: ['pricing_table_id', 'additional_service_id'])]
class AdditionalServicePrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PricingTable::class, inversedBy: 'additionalServicePrices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?PricingTable $pricingTable = null;

    #[ORM\ManyToOne(targetEntity: AdditionalService::class, inversedBy: 'servicePrices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?AdditionalService $additionalService = null;

    /**
     * Price override for this service in this pricing table
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $price;

    /**
     * Minimum price override
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minPrice = null;

    /**
     * Maximum price override
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maxPrice = null;

    /**
     * Percentage rate override
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $percentageRate = null;

    /**
     * Service-specific configuration for this pricing table
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    /**
     * Weight-based pricing tiers
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $weightTiers = null;

    /**
     * Value-based pricing tiers (for insurance, COD)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $valueTiers = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPricingTable(): ?PricingTable
    {
        return $this->pricingTable;
    }

    public function setPricingTable(?PricingTable $pricingTable): static
    {
        $this->pricingTable = $pricingTable;
        return $this;
    }

    public function getAdditionalService(): ?AdditionalService
    {
        return $this->additionalService;
    }

    public function setAdditionalService(?AdditionalService $additionalService): static
    {
        $this->additionalService = $additionalService;
        return $this;
    }

    public function getPrice(): float
    {
        return (float)$this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = number_format($price, 4, '.', '');
        return $this;
    }

    /**
     * Get flat rate price - alias for getPrice()
     */
    public function getFlatRate(): string
    {
        return $this->price;
    }

    /**
     * Get calculation method from the associated service
     */
    public function getCalculationMethod(): string
    {
        return $this->additionalService?->getPricingType() ?? 'flat_rate';
    }

    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function setMinPrice(?float $minPrice): static
    {
        $this->minPrice = $minPrice;
        return $this;
    }

    public function getMaxPrice(): ?float
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?float $maxPrice): static
    {
        $this->maxPrice = $maxPrice;
        return $this;
    }

    public function getPercentageRate(): ?float
    {
        return $this->percentageRate;
    }

    public function setPercentageRate(?float $percentageRate): static
    {
        $this->percentageRate = $percentageRate;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfigValue(string $key, mixed $value): static
    {
        if ($this->config === null) {
            $this->config = [];
        }
        $this->config[$key] = $value;
        return $this;
    }

    public function getWeightTiers(): ?array
    {
        return $this->weightTiers;
    }

    public function setWeightTiers(?array $weightTiers): static
    {
        $this->weightTiers = $weightTiers;
        return $this;
    }

    public function getValueTiers(): ?array
    {
        return $this->valueTiers;
    }

    public function setValueTiers(?array $valueTiers): static
    {
        $this->valueTiers = $valueTiers;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Calculate service price based on weight, value, and package count
     */
    public function calculatePrice(?float $baseAmount = null, ?float $weightKg = null, ?float $declaredValue = null, int $packageCount = 1): float
    {
        $service = $this->additionalService;
        if (!$service) {
            return 0.0;
        }

        $price = match ($service->getPricingType()) {
            AdditionalService::PRICING_TYPE_FIXED => $this->price,
            
            AdditionalService::PRICING_TYPE_PERCENTAGE => $this->calculatePercentagePrice($baseAmount),
            
            AdditionalService::PRICING_TYPE_PER_PACKAGE => $this->price * $packageCount,
            
            AdditionalService::PRICING_TYPE_TIER_BASED => $this->calculateTierPrice($weightKg, $declaredValue),
            
            default => $this->price
        };

        // Apply min/max constraints
        if ($this->minPrice !== null) {
            $price = max($price, $this->minPrice);
        }
        
        if ($this->maxPrice !== null) {
            $price = min($price, $this->maxPrice);
        }

        return round($price, 4);
    }

    /**
     * Calculate percentage-based price
     */
    private function calculatePercentagePrice(?float $baseAmount): float
    {
        if ($baseAmount === null) {
            return $this->price;
        }

        $rate = $this->percentageRate ?? $this->additionalService?->getPercentageRate() ?? 0;
        return ($baseAmount * $rate) / 100;
    }

    /**
     * Calculate tier-based price using weight or value tiers
     */
    private function calculateTierPrice(?float $weightKg, ?float $declaredValue): float
    {
        // Try weight-based tiers first
        if ($weightKg !== null && $this->weightTiers) {
            foreach ($this->weightTiers as $tier) {
                $from = $tier['weight_from'] ?? 0;
                $to = $tier['weight_to'] ?? null;
                
                if ($weightKg >= $from && ($to === null || $weightKg <= $to)) {
                    return $tier['price'] ?? $this->price;
                }
            }
        }

        // Try value-based tiers for insurance/COD
        if ($declaredValue !== null && $this->valueTiers) {
            foreach ($this->valueTiers as $tier) {
                $from = $tier['value_from'] ?? 0;
                $to = $tier['value_to'] ?? null;
                
                if ($declaredValue >= $from && ($to === null || $declaredValue <= $to)) {
                    $rate = $tier['rate'] ?? 0;
                    $fixedPrice = $tier['price'] ?? 0;
                    
                    // Calculate based on rate or fixed price
                    if ($rate > 0) {
                        return ($declaredValue * $rate) / 100;
                    } else {
                        return $fixedPrice;
                    }
                }
            }
        }

        return $this->price;
    }

    /**
     * Get effective pricing rate (override or from service)
     */
    public function getEffectivePercentageRate(): ?float
    {
        return $this->percentageRate ?? $this->additionalService?->getPercentageRate();
    }

    /**
     * Get effective minimum price (override or from service)
     */
    public function getEffectiveMinPrice(): ?float
    {
        return $this->minPrice ?? $this->additionalService?->getMinPrice();
    }

    /**
     * Get effective maximum price (override or from service)
     */
    public function getEffectiveMaxPrice(): ?float
    {
        return $this->maxPrice ?? $this->additionalService?->getMaxPrice();
    }

    public function __toString(): string
    {
        return sprintf('%s in %s (%.4f %s)', 
            $this->additionalService?->getName() ?? 'Unknown Service',
            $this->pricingTable?->getName() ?? 'Unknown Table',
            $this->price,
            $this->pricingTable?->getCurrency() ?? 'PLN'
        );
    }
}