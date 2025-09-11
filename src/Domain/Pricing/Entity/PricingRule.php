<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Weight/dimension-based pricing rules within pricing tables
 * 
 * Defines tier-based pricing rules for different weight ranges
 * and dimensional thresholds within a specific pricing table.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\PricingRuleRepository::class)]
#[ORM\Table(name: 'v2_pricing_rules')]
#[ORM\Index(name: 'IDX_RULE_TABLE', columns: ['pricing_table_id'])]
#[ORM\Index(name: 'IDX_RULE_WEIGHT_RANGE', columns: ['weight_from', 'weight_to'])]
#[ORM\Index(name: 'IDX_RULE_SORT', columns: ['sort_order'])]
#[ORM\UniqueConstraint(name: 'UNQ_PRICING_RULE_TABLE_WEIGHT', columns: ['pricing_table_id', 'weight_from', 'weight_to'])]
class PricingRule
{
    public const CALCULATION_METHOD_FIXED = 'fixed';
    public const CALCULATION_METHOD_PER_KG = 'per_kg';
    public const CALCULATION_METHOD_PER_KG_STEP = 'per_kg_step';
    public const CALCULATION_METHOD_PERCENTAGE = 'percentage';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PricingTable::class, inversedBy: 'pricingRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?PricingTable $pricingTable = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    /**
     * Weight range start in kg
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $weightFrom;

    /**
     * Weight range end in kg (null = unlimited)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $weightTo = null;

    /**
     * Minimum dimensions for this rule [length, width, height] in cm
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dimensionsFrom = null;

    /**
     * Maximum dimensions for this rule [length, width, height] in cm
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dimensionsTo = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::CALCULATION_METHOD_FIXED,
        self::CALCULATION_METHOD_PER_KG,
        self::CALCULATION_METHOD_PER_KG_STEP,
        self::CALCULATION_METHOD_PERCENTAGE
    ])]
    private string $calculationMethod = self::CALCULATION_METHOD_FIXED;

    /**
     * Base price for this weight range
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $price;

    /**
     * Additional price per kg above base weight
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $pricePerKg = null;

    /**
     * Weight increment for stepped pricing (kg)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    #[Assert\Positive]
    private ?string $weightStep = null;

    /**
     * Minimum price for this rule
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minPrice = null;

    /**
     * Maximum price for this rule
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maxPrice = null;

    /**
     * Override tax rate for this rule
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $taxRateOverride = null;

    /**
     * Rule configuration parameters
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        // Set default values for required fields to satisfy NOT NULL constraints
        $this->weightFrom = '0.000';
        $this->price = '0.0000';
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getWeightFrom(): float
    {
        return (float)$this->weightFrom;
    }

    public function setWeightFrom(float|string $weightFrom): static
    {
        $this->weightFrom = number_format((float)$weightFrom, 3, '.', '');
        return $this;
    }

    public function getWeightTo(): ?float
    {
        return $this->weightTo ? (float)$this->weightTo : null;
    }

    public function setWeightTo(float|string|null $weightTo): static
    {
        $this->weightTo = $weightTo ? number_format((float)$weightTo, 3, '.', '') : null;
        return $this;
    }

    public function getDimensionsFrom(): ?array
    {
        return $this->dimensionsFrom;
    }

    public function setDimensionsFrom(?array $dimensionsFrom): static
    {
        $this->dimensionsFrom = $dimensionsFrom;
        return $this;
    }

    public function getDimensionsTo(): ?array
    {
        return $this->dimensionsTo;
    }

    public function setDimensionsTo(?array $dimensionsTo): static
    {
        $this->dimensionsTo = $dimensionsTo;
        return $this;
    }

    public function getCalculationMethod(): string
    {
        return $this->calculationMethod;
    }

    public function setCalculationMethod(string $calculationMethod): static
    {
        $this->calculationMethod = $calculationMethod;
        return $this;
    }

    public function getPrice(): float
    {
        return (float)$this->price;
    }

    public function setPrice(float|string $price): static
    {
        $this->price = number_format((float)$price, 4, '.', '');
        return $this;
    }

    /**
     * Alias for getPrice() - used in flat rate calculations
     */
    public function getFlatRate(): string
    {
        return $this->price;
    }

    public function getPricePerKg(): ?float
    {
        return $this->pricePerKg ? (float)$this->pricePerKg : null;
    }

    public function setPricePerKg(float|string|null $pricePerKg): static
    {
        $this->pricePerKg = $pricePerKg !== null ? number_format((float)$pricePerKg, 4, '.', '') : null;
        return $this;
    }

    /**
     * Get minimum weight for this rule
     */
    public function getMinWeight(): float
    {
        return (float)$this->weightFrom;
    }

    public function getWeightStep(): ?float
    {
        return $this->weightStep;
    }

    public function setWeightStep(?float $weightStep): static
    {
        $this->weightStep = $weightStep;
        return $this;
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

    public function getTaxRateOverride(): ?float
    {
        return $this->taxRateOverride;
    }

    public function setTaxRateOverride(?float $taxRateOverride): static
    {
        $this->taxRateOverride = $taxRateOverride;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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
     * Check if this rule applies to given weight
     */
    public function matchesWeight(float $weightKg): bool
    {
        return $weightKg >= $this->weightFrom && 
               ($this->weightTo === null || $weightKg <= $this->weightTo);
    }

    /**
     * Check if this rule applies to given dimensions
     */
    public function matchesDimensions(int $lengthCm, int $widthCm, int $heightCm): bool
    {
        if ($this->dimensionsFrom !== null) {
            if ($lengthCm < ($this->dimensionsFrom[0] ?? 0) ||
                $widthCm < ($this->dimensionsFrom[1] ?? 0) ||
                $heightCm < ($this->dimensionsFrom[2] ?? 0)) {
                return false;
            }
        }

        if ($this->dimensionsTo !== null) {
            if ($lengthCm > ($this->dimensionsTo[0] ?? PHP_INT_MAX) ||
                $widthCm > ($this->dimensionsTo[1] ?? PHP_INT_MAX) ||
                $heightCm > ($this->dimensionsTo[2] ?? PHP_INT_MAX)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate price based on weight and rule configuration
     */
    public function calculatePrice(float $weightKg): float
    {
        $price = match ($this->calculationMethod) {
            self::CALCULATION_METHOD_FIXED => $this->price,
            
            self::CALCULATION_METHOD_PER_KG => $this->price + 
                (max(0, $weightKg - $this->weightFrom) * ($this->pricePerKg ?? 0)),
            
            self::CALCULATION_METHOD_PER_KG_STEP => $this->calculateSteppedPrice($weightKg),
            
            self::CALCULATION_METHOD_PERCENTAGE => $this->price * ($weightKg / 100),
            
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
     * Calculate price using stepped method
     */
    private function calculateSteppedPrice(float $weightKg): float
    {
        $basePrice = $this->price;
        $weightStep = $this->weightStep ?? 1.0;
        $pricePerKg = $this->pricePerKg ?? 0;

        $excessWeight = max(0, $weightKg - $this->weightFrom);
        $steps = ceil($excessWeight / $weightStep);

        return $basePrice + ($steps * $pricePerKg * $weightStep);
    }

    public function getEffectiveTaxRate(): ?float
    {
        return $this->taxRateOverride ?? $this->pricingTable?->getTaxRate();
    }

    public function isFixed(): bool
    {
        return $this->calculationMethod === self::CALCULATION_METHOD_FIXED;
    }

    public function isPerKg(): bool
    {
        return $this->calculationMethod === self::CALCULATION_METHOD_PER_KG;
    }

    public function isPerKgStep(): bool
    {
        return $this->calculationMethod === self::CALCULATION_METHOD_PER_KG_STEP;
    }

    public function isPercentage(): bool
    {
        return $this->calculationMethod === self::CALCULATION_METHOD_PERCENTAGE;
    }

    public function __toString(): string
    {
        $weightRange = sprintf('%.3f kg', $this->weightFrom);
        if ($this->weightTo !== null) {
            $weightRange .= sprintf(' - %.3f kg', $this->weightTo);
        } else {
            $weightRange .= '+';
        }

        return sprintf('%s: %s (%.4f %s)', 
            $this->name ?? 'Rule',
            $weightRange,
            $this->price,
            $this->pricingTable?->getCurrency() ?? 'PLN'
        );
    }
}