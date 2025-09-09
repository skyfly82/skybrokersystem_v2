<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Additional services offered by carriers
 * 
 * Defines extra services like COD, insurance, SMS notifications
 * that can be added to shipments for additional fees.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\AdditionalServiceRepository::class)]
#[ORM\Table(name: 'v2_additional_services')]
#[ORM\Index(name: 'IDX_SERVICE_CARRIER', columns: ['carrier_id'])]
#[ORM\Index(name: 'IDX_SERVICE_TYPE', columns: ['service_type'])]
#[ORM\Index(name: 'IDX_SERVICE_ACTIVE', columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'UNQ_SERVICE_CARRIER_CODE', columns: ['carrier_id', 'code'])]
class AdditionalService
{
    public const SERVICE_TYPE_COD = 'cod';                    // Cash on Delivery
    public const SERVICE_TYPE_INSURANCE = 'insurance';       // Package Insurance
    public const SERVICE_TYPE_SMS = 'sms';                   // SMS Notifications
    public const SERVICE_TYPE_EMAIL = 'email';               // Email Notifications
    public const SERVICE_TYPE_SATURDAY = 'saturday';         // Saturday Delivery
    public const SERVICE_TYPE_RETURN = 'return';             // Return Service
    public const SERVICE_TYPE_FRAGILE = 'fragile';           // Fragile Handling
    public const SERVICE_TYPE_PRIORITY = 'priority';         // Priority Processing
    public const SERVICE_TYPE_PICKUP = 'pickup';             // Package Pickup
    public const SERVICE_TYPE_SIGNATURE = 'signature';       // Signature Required

    public const PRICING_TYPE_FIXED = 'fixed';
    public const PRICING_TYPE_PERCENTAGE = 'percentage';
    public const PRICING_TYPE_PER_PACKAGE = 'per_package';
    public const PRICING_TYPE_TIER_BASED = 'tier_based';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class, inversedBy: 'additionalServices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Carrier $carrier = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[A-Z_]+$/', message: 'Service code must contain only uppercase letters and underscores')]
    private string $code;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::SERVICE_TYPE_COD,
        self::SERVICE_TYPE_INSURANCE,
        self::SERVICE_TYPE_SMS,
        self::SERVICE_TYPE_EMAIL,
        self::SERVICE_TYPE_SATURDAY,
        self::SERVICE_TYPE_RETURN,
        self::SERVICE_TYPE_FRAGILE,
        self::SERVICE_TYPE_PRIORITY,
        self::SERVICE_TYPE_PICKUP,
        self::SERVICE_TYPE_SIGNATURE
    ])]
    private string $serviceType;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::PRICING_TYPE_FIXED,
        self::PRICING_TYPE_PERCENTAGE,
        self::PRICING_TYPE_PER_PACKAGE,
        self::PRICING_TYPE_TIER_BASED
    ])]
    private string $pricingType = self::PRICING_TYPE_FIXED;

    /**
     * Default price for this service
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $defaultPrice = null;

    /**
     * Minimum value for percentage-based or tier-based pricing
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minPrice = null;

    /**
     * Maximum value for percentage-based or tier-based pricing
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maxPrice = null;

    /**
     * Percentage rate for percentage-based pricing
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $percentageRate = null;

    /**
     * Service configuration parameters
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    /**
     * Required for this service (e.g., phone number for SMS)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredFields = null;

    /**
     * Supported zones for this service
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $supportedZones = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, AdditionalServicePrice>
     */
    #[ORM\OneToMany(mappedBy: 'additionalService', targetEntity: AdditionalServicePrice::class)]
    private Collection $servicePrices;

    public function __construct()
    {
        $this->servicePrices = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
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

    public function getServiceType(): string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): static
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getPricingType(): string
    {
        return $this->pricingType;
    }

    public function setPricingType(string $pricingType): static
    {
        $this->pricingType = $pricingType;
        return $this;
    }

    public function getDefaultPrice(): ?string
    {
        return $this->defaultPrice;
    }

    public function setDefaultPrice(?string $defaultPrice): static
    {
        $this->defaultPrice = $defaultPrice;
        return $this;
    }

    public function getMinPrice(): ?string
    {
        return $this->minPrice;
    }

    public function setMinPrice(?string $minPrice): static
    {
        $this->minPrice = $minPrice;
        return $this;
    }

    public function getMaxPrice(): ?string
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?string $maxPrice): static
    {
        $this->maxPrice = $maxPrice;
        return $this;
    }

    public function getPercentageRate(): ?string
    {
        return $this->percentageRate;
    }

    public function setPercentageRate(?string $percentageRate): static
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

    public function getRequiredFields(): ?array
    {
        return $this->requiredFields;
    }

    public function setRequiredFields(?array $requiredFields): static
    {
        $this->requiredFields = $requiredFields;
        return $this;
    }

    public function getSupportedZones(): ?array
    {
        return $this->supportedZones;
    }

    public function setSupportedZones(?array $supportedZones): static
    {
        $this->supportedZones = $supportedZones;
        return $this;
    }

    public function supportsZone(string $zoneCode): bool
    {
        return $this->supportedZones === null || in_array($zoneCode, $this->supportedZones);
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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
     * @return Collection<int, AdditionalServicePrice>
     */
    public function getServicePrices(): Collection
    {
        return $this->servicePrices;
    }

    public function addServicePrice(AdditionalServicePrice $servicePrice): static
    {
        if (!$this->servicePrices->contains($servicePrice)) {
            $this->servicePrices->add($servicePrice);
            $servicePrice->setAdditionalService($this);
        }

        return $this;
    }

    public function removeServicePrice(AdditionalServicePrice $servicePrice): static
    {
        if ($this->servicePrices->removeElement($servicePrice)) {
            if ($servicePrice->getAdditionalService() === $this) {
                $servicePrice->setAdditionalService(null);
            }
        }

        return $this;
    }

    public function isCOD(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_COD;
    }

    public function isInsurance(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_INSURANCE;
    }

    public function isSMS(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_SMS;
    }

    public function isEmail(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_EMAIL;
    }

    public function isSaturdayDelivery(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_SATURDAY;
    }

    public function isReturnService(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_RETURN;
    }

    public function isFragileHandling(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_FRAGILE;
    }

    public function isPriorityProcessing(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_PRIORITY;
    }

    public function isPickupService(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_PICKUP;
    }

    public function isSignatureRequired(): bool
    {
        return $this->serviceType === self::SERVICE_TYPE_SIGNATURE;
    }

    public function isFixedPricing(): bool
    {
        return $this->pricingType === self::PRICING_TYPE_FIXED;
    }

    public function isPercentagePricing(): bool
    {
        return $this->pricingType === self::PRICING_TYPE_PERCENTAGE;
    }

    public function isPerPackagePricing(): bool
    {
        return $this->pricingType === self::PRICING_TYPE_PER_PACKAGE;
    }

    public function isTierBasedPricing(): bool
    {
        return $this->pricingType === self::PRICING_TYPE_TIER_BASED;
    }

    /**
     * Calculate service price based on base amount
     */
    public function calculatePrice(?float $baseAmount = null, int $packageCount = 1): float
    {
        $price = match ($this->pricingType) {
            self::PRICING_TYPE_FIXED => $this->defaultPrice ?? 0,
            
            self::PRICING_TYPE_PERCENTAGE => $baseAmount && $this->percentageRate 
                ? ($baseAmount * $this->percentageRate / 100)
                : ($this->defaultPrice ?? 0),
            
            self::PRICING_TYPE_PER_PACKAGE => ($this->defaultPrice ?? 0) * $packageCount,
            
            self::PRICING_TYPE_TIER_BASED => $this->defaultPrice ?? 0, // Simplified, should be overridden in specific pricing tables
            
            default => $this->defaultPrice ?? 0
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

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->carrier?->getName() ?? 'Unknown Carrier',
            $this->name,
            $this->code
        );
    }
}