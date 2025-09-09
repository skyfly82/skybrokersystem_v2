<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Customer;
use App\Entity\SystemUser;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * B2B customer-specific pricing agreements
 * 
 * Defines negotiated rates for business customers, including
 * volume discounts, custom pricing rules, and special rates.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\CustomerPricingRepository::class)]
#[ORM\Table(name: 'v2_customer_pricing')]
#[ORM\Index(name: 'IDX_CUSTOMER_PRICING_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_PRICING_TABLE', columns: ['base_pricing_table_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_PRICING_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_CUSTOMER_PRICING_EFFECTIVE', columns: ['effective_from', 'effective_until'])]
#[ORM\UniqueConstraint(name: 'UNQ_CUSTOMER_PRICING_CUSTOMER_TABLE', columns: ['customer_id', 'base_pricing_table_id'])]
class CustomerPricing
{
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_FIXED = 'fixed';
    public const DISCOUNT_TYPE_VOLUME = 'volume';
    public const DISCOUNT_TYPE_CUSTOM_RULES = 'custom_rules';

    public const VOLUME_PERIOD_DAILY = 'daily';
    public const VOLUME_PERIOD_WEEKLY = 'weekly';
    public const VOLUME_PERIOD_MONTHLY = 'monthly';
    public const VOLUME_PERIOD_QUARTERLY = 'quarterly';
    public const VOLUME_PERIOD_YEARLY = 'yearly';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: PricingTable::class, inversedBy: 'customerPricings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?PricingTable $basePricingTable = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $contractName;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $contractNumber = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::DISCOUNT_TYPE_PERCENTAGE,
        self::DISCOUNT_TYPE_FIXED,
        self::DISCOUNT_TYPE_VOLUME,
        self::DISCOUNT_TYPE_CUSTOM_RULES
    ])]
    private string $discountType = self::DISCOUNT_TYPE_PERCENTAGE;

    /**
     * Base discount percentage applied to all services
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $baseDiscount = null;

    /**
     * Fixed amount discount per shipment
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $fixedDiscount = null;

    /**
     * Minimum shipment value for pricing to apply
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minimumOrderValue = null;

    /**
     * Maximum shipment value for pricing to apply
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maximumOrderValue = null;

    /**
     * Volume discount configuration
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $volumeDiscounts = null;

    /**
     * Volume tracking period
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: [
        self::VOLUME_PERIOD_DAILY,
        self::VOLUME_PERIOD_WEEKLY,
        self::VOLUME_PERIOD_MONTHLY,
        self::VOLUME_PERIOD_QUARTERLY,
        self::VOLUME_PERIOD_YEARLY
    ])]
    private ?string $volumePeriod = self::VOLUME_PERIOD_MONTHLY;

    /**
     * Custom pricing rules override
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customRules = null;

    /**
     * Additional service discounts
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $serviceDiscounts = null;

    /**
     * Payment terms configuration
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $paymentTerms = null;

    /**
     * Free shipment threshold
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $freeShippingThreshold = null;

    /**
     * Custom tax rate override
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $taxRateOverride = null;

    /**
     * Currency override
     */
    #[ORM\Column(length: 3, nullable: true)]
    #[Assert\Length(exactly: 3)]
    #[Assert\Currency]
    private ?string $currencyOverride = null;

    /**
     * Priority level for customer
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 5)]
    private int $priorityLevel = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveUntil = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $autoRenewal = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $createdBy = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $updatedBy = null;

    /**
     * @var Collection<int, PromotionalPricing>
     */
    #[ORM\OneToMany(mappedBy: 'customerPricing', targetEntity: PromotionalPricing::class)]
    private Collection $promotionalPricings;

    /**
     * @var Collection<int, CustomerPricingAudit>
     */
    #[ORM\OneToMany(mappedBy: 'customerPricing', targetEntity: CustomerPricingAudit::class)]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->promotionalPricings = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->effectiveFrom = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;
        return $this;
    }

    public function getBasePricingTable(): ?PricingTable
    {
        return $this->basePricingTable;
    }

    public function setBasePricingTable(?PricingTable $basePricingTable): static
    {
        $this->basePricingTable = $basePricingTable;
        return $this;
    }

    public function getContractName(): string
    {
        return $this->contractName;
    }

    public function setContractName(string $contractName): static
    {
        $this->contractName = $contractName;
        return $this;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(?string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;
        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;
        return $this;
    }

    public function getBaseDiscount(): ?float
    {
        return $this->baseDiscount;
    }

    public function setBaseDiscount(?float $baseDiscount): static
    {
        $this->baseDiscount = $baseDiscount;
        return $this;
    }

    public function getFixedDiscount(): ?float
    {
        return $this->fixedDiscount;
    }

    public function setFixedDiscount(?float $fixedDiscount): static
    {
        $this->fixedDiscount = $fixedDiscount;
        return $this;
    }

    public function getMinimumOrderValue(): ?float
    {
        return $this->minimumOrderValue;
    }

    public function setMinimumOrderValue(?float $minimumOrderValue): static
    {
        $this->minimumOrderValue = $minimumOrderValue;
        return $this;
    }

    public function getMaximumOrderValue(): ?float
    {
        return $this->maximumOrderValue;
    }

    public function setMaximumOrderValue(?float $maximumOrderValue): static
    {
        $this->maximumOrderValue = $maximumOrderValue;
        return $this;
    }

    public function getVolumeDiscounts(): ?array
    {
        return $this->volumeDiscounts;
    }

    public function setVolumeDiscounts(?array $volumeDiscounts): static
    {
        $this->volumeDiscounts = $volumeDiscounts;
        return $this;
    }

    public function getVolumePeriod(): ?string
    {
        return $this->volumePeriod;
    }

    public function setVolumePeriod(?string $volumePeriod): static
    {
        $this->volumePeriod = $volumePeriod;
        return $this;
    }

    public function getCustomRules(): ?array
    {
        return $this->customRules;
    }

    public function setCustomRules(?array $customRules): static
    {
        $this->customRules = $customRules;
        return $this;
    }

    public function getServiceDiscounts(): ?array
    {
        return $this->serviceDiscounts;
    }

    public function setServiceDiscounts(?array $serviceDiscounts): static
    {
        $this->serviceDiscounts = $serviceDiscounts;
        return $this;
    }

    public function getPaymentTerms(): ?array
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?array $paymentTerms): static
    {
        $this->paymentTerms = $paymentTerms;
        return $this;
    }

    public function getFreeShippingThreshold(): ?float
    {
        return $this->freeShippingThreshold;
    }

    public function setFreeShippingThreshold(?float $freeShippingThreshold): static
    {
        $this->freeShippingThreshold = $freeShippingThreshold;
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

    public function getCurrencyOverride(): ?string
    {
        return $this->currencyOverride;
    }

    public function setCurrencyOverride(?string $currencyOverride): static
    {
        $this->currencyOverride = $currencyOverride ? strtoupper($currencyOverride) : null;
        return $this;
    }

    public function getPriorityLevel(): int
    {
        return $this->priorityLevel;
    }

    public function setPriorityLevel(int $priorityLevel): static
    {
        $this->priorityLevel = $priorityLevel;
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

    public function isAutoRenewal(): bool
    {
        return $this->autoRenewal;
    }

    public function setAutoRenewal(bool $autoRenewal): static
    {
        $this->autoRenewal = $autoRenewal;
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
     * @return Collection<int, PromotionalPricing>
     */
    public function getPromotionalPricings(): Collection
    {
        return $this->promotionalPricings;
    }

    public function addPromotionalPricing(PromotionalPricing $promotionalPricing): static
    {
        if (!$this->promotionalPricings->contains($promotionalPricing)) {
            $this->promotionalPricings->add($promotionalPricing);
            $promotionalPricing->setCustomerPricing($this);
        }

        return $this;
    }

    public function removePromotionalPricing(PromotionalPricing $promotionalPricing): static
    {
        if ($this->promotionalPricings->removeElement($promotionalPricing)) {
            if ($promotionalPricing->getCustomerPricing() === $this) {
                $promotionalPricing->setCustomerPricing(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CustomerPricingAudit>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(CustomerPricingAudit $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setCustomerPricing($this);
        }

        return $this;
    }

    public function removeAuditLog(CustomerPricingAudit $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            if ($auditLog->getCustomerPricing() === $this) {
                $auditLog->setCustomerPricing(null);
            }
        }

        return $this;
    }

    /**
     * Check if pricing is currently active
     */
    public function isCurrentlyActive(): bool
    {
        $now = new \DateTime();
        return $this->isActive && 
               $this->effectiveFrom <= $now && 
               ($this->effectiveUntil === null || $this->effectiveUntil >= $now);
    }

    /**
     * Check if order value qualifies for this pricing
     */
    public function qualifiesForPricing(float $orderValue): bool
    {
        if ($this->minimumOrderValue !== null && $orderValue < $this->minimumOrderValue) {
            return false;
        }

        if ($this->maximumOrderValue !== null && $orderValue > $this->maximumOrderValue) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount for given base price
     */
    public function calculateDiscount(float $basePrice): float
    {
        return match ($this->discountType) {
            self::DISCOUNT_TYPE_PERCENTAGE => $basePrice * ($this->baseDiscount ?? 0) / 100,
            self::DISCOUNT_TYPE_FIXED => min($this->fixedDiscount ?? 0, $basePrice),
            self::DISCOUNT_TYPE_VOLUME => $this->calculateVolumeDiscount($basePrice),
            self::DISCOUNT_TYPE_CUSTOM_RULES => $this->calculateCustomDiscount($basePrice),
            default => 0.0
        };
    }

    /**
     * Calculate volume-based discount
     */
    private function calculateVolumeDiscount(float $basePrice): float
    {
        // This would require tracking customer's volume statistics
        // Implementation depends on volume tracking system
        return 0.0;
    }

    /**
     * Calculate custom rules-based discount
     */
    private function calculateCustomDiscount(float $basePrice): float
    {
        // Implementation would evaluate custom rules
        // This is a simplified version
        return 0.0;
    }

    /**
     * Get effective currency (override or from base pricing table)
     */
    public function getEffectiveCurrency(): string
    {
        return $this->currencyOverride ?? $this->basePricingTable?->getCurrency() ?? 'PLN';
    }

    /**
     * Get effective tax rate (override or from base pricing table)
     */
    public function getEffectiveTaxRate(): ?float
    {
        return $this->taxRateOverride ?? $this->basePricingTable?->getTaxRate();
    }

    public function isPercentageDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_PERCENTAGE;
    }

    public function isFixedDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_FIXED;
    }

    public function isVolumeDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_VOLUME;
    }

    public function isCustomRulesDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_CUSTOM_RULES;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->customer?->getCompanyName() ?? 'Unknown Customer',
            $this->contractName,
            $this->discountType
        );
    }
}