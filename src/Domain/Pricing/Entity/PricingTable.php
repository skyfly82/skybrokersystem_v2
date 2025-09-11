<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\SystemUser;

/**
 * Main pricing tables for courier services
 * 
 * Each pricing table defines rates for specific carrier-zone combinations
 * with weight/dimension thresholds and base/tier pricing structures.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\PricingTableRepository::class)]
#[ORM\Table(name: 'v2_pricing_tables')]
#[ORM\Index(name: 'IDX_PRICING_CARRIER_ZONE', columns: ['carrier_id', 'zone_id'])]
#[ORM\Index(name: 'IDX_PRICING_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_PRICING_EFFECTIVE', columns: ['effective_from', 'effective_until'])]
#[ORM\Index(name: 'IDX_PRICING_SERVICE', columns: ['service_type'])]
#[ORM\UniqueConstraint(name: 'UNQ_PRICING_CARRIER_ZONE_SERVICE_VERSION', columns: ['carrier_id', 'zone_id', 'service_type', 'version'])]
class PricingTable
{
    public const SERVICE_TYPE_STANDARD = 'standard';
    public const SERVICE_TYPE_EXPRESS = 'express';
    public const SERVICE_TYPE_OVERNIGHT = 'overnight';
    public const SERVICE_TYPE_ECONOMY = 'economy';
    public const SERVICE_TYPE_PREMIUM = 'premium';

    public const PRICING_MODEL_WEIGHT = 'weight';
    public const PRICING_MODEL_VOLUMETRIC = 'volumetric';
    public const PRICING_MODEL_HYBRID = 'hybrid';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class, inversedBy: 'pricingTables')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(targetEntity: PricingZone::class, inversedBy: 'pricingTables')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?PricingZone $zone = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::SERVICE_TYPE_STANDARD,
        self::SERVICE_TYPE_EXPRESS,
        self::SERVICE_TYPE_OVERNIGHT,
        self::SERVICE_TYPE_ECONOMY,
        self::SERVICE_TYPE_PREMIUM
    ])]
    private string $serviceType = self::SERVICE_TYPE_STANDARD;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::PRICING_MODEL_WEIGHT,
        self::PRICING_MODEL_VOLUMETRIC,
        self::PRICING_MODEL_HYBRID
    ])]
    private string $pricingModel = self::PRICING_MODEL_WEIGHT;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Positive]
    private int $version = 1;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Base price for the minimum weight/size
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $basePrice;

    /**
     * Minimum weight threshold in kg
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3)]
    #[Assert\NotNull]
    #[Assert\Positive]
    private string $minWeightKg = '0.100';

    /**
     * Maximum weight threshold in kg (null = unlimited)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maxWeightKg = null;

    /**
     * Minimum dimensions [length, width, height] in cm
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $minDimensionsCm = null;

    /**
     * Maximum dimensions [length, width, height] in cm
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $maxDimensionsCm = null;

    /**
     * Volumetric weight divisor (e.g., 5000 for DHL)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $volumetricDivisor = null;

    /**
     * Currency code (ISO 4217)
     */
    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    private string $currency = 'PLN';

    /**
     * Tax rate as percentage (e.g., 23 for 23% VAT)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $taxRate = null;

    /**
     * Additional configuration parameters
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveUntil = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $createdBy = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $updatedBy = null;

    /**
     * @var Collection<int, PricingRule>
     */
    #[ORM\OneToMany(mappedBy: 'pricingTable', targetEntity: PricingRule::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['weightFrom' => 'ASC', 'sortOrder' => 'ASC'])]
    private Collection $pricingRules;

    /**
     * @var Collection<int, AdditionalServicePrice>
     */
    #[ORM\OneToMany(mappedBy: 'pricingTable', targetEntity: AdditionalServicePrice::class, cascade: ['persist', 'remove'])]
    private Collection $additionalServicePrices;

    /**
     * @var Collection<int, CustomerPricing>
     */
    #[ORM\OneToMany(mappedBy: 'basePricingTable', targetEntity: CustomerPricing::class)]
    private Collection $customerPricings;

    public function __construct(?Carrier $carrier = null, ?PricingZone $zone = null, ?string $serviceType = null)
    {
        $this->pricingRules = new ArrayCollection();
        $this->additionalServicePrices = new ArrayCollection();
        $this->customerPricings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->effectiveFrom = new \DateTimeImmutable();
        
        if ($carrier !== null) {
            $this->carrier = $carrier;
        }
        if ($zone !== null) {
            $this->zone = $zone;
        }
        if ($serviceType !== null) {
            $this->serviceType = $serviceType;
        }
        
        // Set default values that are required
        $this->basePrice = '0.0000';
        $this->name = '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): static
    {
        $this->carrier = $carrier;
        return $this;
    }

    public function getZone(): ?PricingZone
    {
        return $this->zone;
    }

    public function setZone(?PricingZone $zone): static
    {
        $this->zone = $zone;
        return $this;
    }

    public function getServiceType(): string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): static
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getPricingModel(): string
    {
        return $this->pricingModel;
    }

    public function setPricingModel(string $pricingModel): static
    {
        $this->pricingModel = $pricingModel;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getBasePrice(): float
    {
        return (float) $this->basePrice;
    }

    public function setBasePrice(float $basePrice): static
    {
        $this->basePrice = (string) $basePrice;
        return $this;
    }

    public function getMinWeightKg(): float
    {
        return (float) $this->minWeightKg;
    }

    public function setMinWeightKg(float $minWeightKg): static
    {
        $this->minWeightKg = (string) $minWeightKg;
        return $this;
    }

    public function getMaxWeightKg(): ?float
    {
        return $this->maxWeightKg ? (float) $this->maxWeightKg : null;
    }

    public function setMaxWeightKg(?float $maxWeightKg): static
    {
        $this->maxWeightKg = $maxWeightKg !== null ? (string) $maxWeightKg : null;
        return $this;
    }

    public function getMinDimensionsCm(): ?array
    {
        return $this->minDimensionsCm;
    }

    public function setMinDimensionsCm(?array $minDimensionsCm): static
    {
        $this->minDimensionsCm = $minDimensionsCm;
        return $this;
    }

    public function getMaxDimensionsCm(): ?array
    {
        return $this->maxDimensionsCm;
    }

    public function setMaxDimensionsCm(?array $maxDimensionsCm): static
    {
        $this->maxDimensionsCm = $maxDimensionsCm;
        return $this;
    }

    public function getVolumetricDivisor(): ?int
    {
        return $this->volumetricDivisor;
    }

    public function setVolumetricDivisor(?int $volumetricDivisor): static
    {
        $this->volumetricDivisor = $volumetricDivisor;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate ? (float) $this->taxRate : null;
    }

    public function setTaxRate(?float $taxRate): static
    {
        $this->taxRate = $taxRate !== null ? (string) $taxRate : null;
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

    public function getEffectiveFrom(): \DateTimeInterface
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeInterface $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;
        return $this;
    }

    public function getEffectiveUntil(): ?\DateTimeInterface
    {
        return $this->effectiveUntil;
    }

    public function setEffectiveUntil(?\DateTimeInterface $effectiveUntil): static
    {
        $this->effectiveUntil = $effectiveUntil;
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

    public function getCreatedBy(): ?SystemUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?SystemUser $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?SystemUser
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?SystemUser $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, PricingRule>
     */
    public function getPricingRules(): Collection
    {
        return $this->pricingRules;
    }

    public function addPricingRule(PricingRule $pricingRule): static
    {
        if (!$this->pricingRules->contains($pricingRule)) {
            $this->pricingRules->add($pricingRule);
            $pricingRule->setPricingTable($this);
        }

        return $this;
    }

    public function removePricingRule(PricingRule $pricingRule): static
    {
        if ($this->pricingRules->removeElement($pricingRule)) {
            if ($pricingRule->getPricingTable() === $this) {
                $pricingRule->setPricingTable(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdditionalServicePrice>
     */
    public function getAdditionalServicePrices(): Collection
    {
        return $this->additionalServicePrices;
    }

    public function addAdditionalServicePrice(AdditionalServicePrice $additionalServicePrice): static
    {
        if (!$this->additionalServicePrices->contains($additionalServicePrice)) {
            $this->additionalServicePrices->add($additionalServicePrice);
            $additionalServicePrice->setPricingTable($this);
        }

        return $this;
    }

    public function removeAdditionalServicePrice(AdditionalServicePrice $additionalServicePrice): static
    {
        if ($this->additionalServicePrices->removeElement($additionalServicePrice)) {
            if ($additionalServicePrice->getPricingTable() === $this) {
                $additionalServicePrice->setPricingTable(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CustomerPricing>
     */
    public function getCustomerPricings(): Collection
    {
        return $this->customerPricings;
    }

    public function addCustomerPricing(CustomerPricing $customerPricing): static
    {
        if (!$this->customerPricings->contains($customerPricing)) {
            $this->customerPricings->add($customerPricing);
            $customerPricing->setBasePricingTable($this);
        }

        return $this;
    }

    public function removeCustomerPricing(CustomerPricing $customerPricing): static
    {
        if ($this->customerPricings->removeElement($customerPricing)) {
            if ($customerPricing->getBasePricingTable() === $this) {
                $customerPricing->setBasePricingTable(null);
            }
        }

        return $this;
    }

    public function isCurrentlyActive(): bool
    {
        $now = new \DateTime();
        return $this->isActive && 
               $this->effectiveFrom <= $now && 
               ($this->effectiveUntil === null || $this->effectiveUntil >= $now);
    }

    public function canHandleWeight(float $weightKg): bool
    {
        return $weightKg >= $this->minWeightKg && 
               ($this->maxWeightKg === null || $weightKg <= $this->maxWeightKg);
    }

    public function calculateVolumetricWeight(int $lengthCm, int $widthCm, int $heightCm): ?float
    {
        if ($this->volumetricDivisor === null) {
            return null;
        }

        return ($lengthCm * $widthCm * $heightCm) / $this->volumetricDivisor;
    }

    public function isStandard(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_STANDARD;
    }

    public function isExpress(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_EXPRESS;
    }

    public function isOvernight(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_OVERNIGHT;
    }

    public function isEconomy(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_ECONOMY;
    }

    public function isPremium(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_PREMIUM;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s %s (%s)', 
            $this->carrier?->getName() ?? 'Unknown Carrier',
            $this->zone?->getName() ?? 'Unknown Zone',
            $this->serviceType,
            $this->version
        );
    }
}