<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\SystemUser;

/**
 * Temporary promotions and time-limited pricing offers
 * 
 * Manages seasonal discounts, limited-time offers, and special campaigns
 * that can be applied to standard pricing tables or customer-specific rates.
 */
#[ORM\Entity(repositoryClass: \App\Domain\Pricing\Repository\PromotionalPricingRepository::class)]
#[ORM\Table(name: 'v2_promotional_pricing')]
#[ORM\Index(name: 'IDX_PROMO_PRICING_TABLE', columns: ['pricing_table_id'])]
#[ORM\Index(name: 'IDX_PROMO_CUSTOMER_PRICING', columns: ['customer_pricing_id'])]
#[ORM\Index(name: 'IDX_PROMO_CODE', columns: ['promo_code'])]
#[ORM\Index(name: 'IDX_PROMO_ACTIVE', columns: ['is_active'])]
#[ORM\Index(name: 'IDX_PROMO_PERIOD', columns: ['valid_from', 'valid_until'])]
class PromotionalPricing
{
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const DISCOUNT_TYPE_FREE_SHIPPING = 'free_shipping';
    public const DISCOUNT_TYPE_BUY_X_GET_Y = 'buy_x_get_y';
    public const DISCOUNT_TYPE_TIER_DISCOUNT = 'tier_discount';

    public const TARGET_TYPE_ALL = 'all';
    public const TARGET_TYPE_CARRIER = 'carrier';
    public const TARGET_TYPE_ZONE = 'zone';
    public const TARGET_TYPE_SERVICE_TYPE = 'service_type';
    public const TARGET_TYPE_CUSTOMER = 'customer';
    public const TARGET_TYPE_CUSTOMER_GROUP = 'customer_group';

    public const USAGE_LIMIT_TYPE_TOTAL = 'total';
    public const USAGE_LIMIT_TYPE_PER_CUSTOMER = 'per_customer';
    public const USAGE_LIMIT_TYPE_PER_DAY = 'per_day';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PricingTable::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PricingTable $pricingTable = null;

    #[ORM\ManyToOne(targetEntity: CustomerPricing::class, inversedBy: 'promotionalPricings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?CustomerPricing $customerPricing = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[A-Z0-9_-]+$/', message: 'Promo code must contain only uppercase letters, numbers, underscores and dashes')]
    private ?string $promoCode = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::DISCOUNT_TYPE_PERCENTAGE,
        self::DISCOUNT_TYPE_FIXED_AMOUNT,
        self::DISCOUNT_TYPE_FREE_SHIPPING,
        self::DISCOUNT_TYPE_BUY_X_GET_Y,
        self::DISCOUNT_TYPE_TIER_DISCOUNT
    ])]
    private string $discountType = self::DISCOUNT_TYPE_PERCENTAGE;

    /**
     * Discount value (percentage for percentage type, amount for fixed)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private string $discountValue;

    /**
     * Minimum order value to qualify for promotion
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minimumOrderValue = null;

    /**
     * Maximum discount amount for percentage-based discounts
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maximumDiscountAmount = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::TARGET_TYPE_ALL,
        self::TARGET_TYPE_CARRIER,
        self::TARGET_TYPE_ZONE,
        self::TARGET_TYPE_SERVICE_TYPE,
        self::TARGET_TYPE_CUSTOMER,
        self::TARGET_TYPE_CUSTOMER_GROUP
    ])]
    private string $targetType = self::TARGET_TYPE_ALL;

    /**
     * Target values (carrier codes, zone codes, customer IDs, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $targetValues = null;

    /**
     * Promotion configuration (buy X get Y settings, tier rules, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $promotionConfig = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $validFrom;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $validUntil;

    /**
     * Total usage limit for this promotion
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $usageLimit = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: [
        self::USAGE_LIMIT_TYPE_TOTAL,
        self::USAGE_LIMIT_TYPE_PER_CUSTOMER,
        self::USAGE_LIMIT_TYPE_PER_DAY
    ])]
    private ?string $usageLimitType = self::USAGE_LIMIT_TYPE_TOTAL;

    /**
     * Current usage count
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $usageCount = 0;

    /**
     * Priority level (higher number = higher priority)
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 100)]
    private int $priority = 1;

    /**
     * Can be combined with other promotions
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $stackable = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

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

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->validFrom = new \DateTime();
        $this->validUntil = (new \DateTime())->add(new \DateInterval('P30D')); // Default 30 days
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

    public function getCustomerPricing(): ?CustomerPricing
    {
        return $this->customerPricing;
    }

    public function setCustomerPricing(?CustomerPricing $customerPricing): static
    {
        $this->customerPricing = $customerPricing;
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

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): static
    {
        $this->promoCode = $promoCode ? strtoupper($promoCode) : null;
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

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }

    public function setDiscountValue(float $discountValue): static
    {
        $this->discountValue = $discountValue;
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

    public function getMaximumDiscountAmount(): ?float
    {
        return $this->maximumDiscountAmount;
    }

    public function setMaximumDiscountAmount(?float $maximumDiscountAmount): static
    {
        $this->maximumDiscountAmount = $maximumDiscountAmount;
        return $this;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;
        return $this;
    }

    public function getTargetValues(): ?array
    {
        return $this->targetValues;
    }

    public function setTargetValues(?array $targetValues): static
    {
        $this->targetValues = $targetValues;
        return $this;
    }

    public function getPromotionConfig(): ?array
    {
        return $this->promotionConfig;
    }

    public function setPromotionConfig(?array $promotionConfig): static
    {
        $this->promotionConfig = $promotionConfig;
        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->promotionConfig[$key] ?? $default;
    }

    public function setConfigValue(string $key, mixed $value): static
    {
        if ($this->promotionConfig === null) {
            $this->promotionConfig = [];
        }
        $this->promotionConfig[$key] = $value;
        return $this;
    }

    public function getValidFrom(): \DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): \DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(?int $usageLimit): static
    {
        $this->usageLimit = $usageLimit;
        return $this;
    }

    public function getUsageLimitType(): ?string
    {
        return $this->usageLimitType;
    }

    public function setUsageLimitType(?string $usageLimitType): static
    {
        $this->usageLimitType = $usageLimitType;
        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): static
    {
        $this->stackable = $stackable;
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
     * Check if promotion is currently valid
     */
    public function isCurrentlyValid(): bool
    {
        $now = new \DateTime();
        return $this->isActive && 
               $this->validFrom <= $now && 
               $this->validUntil >= $now &&
               ($this->usageLimit === null || $this->usageCount < $this->usageLimit);
    }

    /**
     * Check if promotion can be used (not at usage limit)
     */
    public function canBeUsed(): bool
    {
        return $this->usageLimit === null || $this->usageCount < $this->usageLimit;
    }

    /**
     * Check if order qualifies for this promotion
     */
    public function qualifiesOrder(float $orderValue): bool
    {
        return $this->minimumOrderValue === null || $orderValue >= $this->minimumOrderValue;
    }

    /**
     * Check if target matches the promotion criteria
     */
    public function matchesTarget(string $targetType, mixed $targetValue): bool
    {
        if ($this->targetType === self::TARGET_TYPE_ALL) {
            return true;
        }

        if ($this->targetType !== $targetType) {
            return false;
        }

        return $this->targetValues === null || in_array($targetValue, $this->targetValues);
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount(float $basePrice, int $quantity = 1): float
    {
        if (!$this->qualifiesOrder($basePrice * $quantity)) {
            return 0.0;
        }

        $discount = match ($this->discountType) {
            self::DISCOUNT_TYPE_PERCENTAGE => ($basePrice * $quantity) * ($this->discountValue / 100),
            self::DISCOUNT_TYPE_FIXED_AMOUNT => min($this->discountValue, $basePrice * $quantity),
            self::DISCOUNT_TYPE_FREE_SHIPPING => $basePrice * $quantity, // Full shipping discount
            self::DISCOUNT_TYPE_BUY_X_GET_Y => $this->calculateBuyXGetYDiscount($basePrice, $quantity),
            self::DISCOUNT_TYPE_TIER_DISCOUNT => $this->calculateTierDiscount($basePrice * $quantity),
            default => 0.0
        };

        // Apply maximum discount constraint
        if ($this->maximumDiscountAmount !== null) {
            $discount = min($discount, $this->maximumDiscountAmount);
        }

        return round($discount, 4);
    }

    /**
     * Calculate Buy X Get Y discount
     */
    private function calculateBuyXGetYDiscount(float $basePrice, int $quantity): float
    {
        $buyQuantity = $this->getConfigValue('buy_quantity', 1);
        $getQuantity = $this->getConfigValue('get_quantity', 1);
        $discountPercent = $this->getConfigValue('discount_percent', 100); // Default 100% (free)

        $freeItems = intval($quantity / $buyQuantity) * $getQuantity;
        $discountableItems = min($freeItems, $quantity);

        return ($basePrice * $discountableItems) * ($discountPercent / 100);
    }

    /**
     * Calculate tier-based discount
     */
    private function calculateTierDiscount(float $totalValue): float
    {
        $tiers = $this->getConfigValue('tiers', []);
        
        foreach ($tiers as $tier) {
            $minValue = $tier['min_value'] ?? 0;
            $maxValue = $tier['max_value'] ?? null;
            
            if ($totalValue >= $minValue && ($maxValue === null || $totalValue <= $maxValue)) {
                $discountType = $tier['type'] ?? 'percentage';
                $discountValue = $tier['value'] ?? 0;
                
                return $discountType === 'percentage' 
                    ? $totalValue * ($discountValue / 100)
                    : min($discountValue, $totalValue);
            }
        }

        return 0.0;
    }

    public function isPercentageDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_PERCENTAGE;
    }

    public function isFixedAmountDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_FIXED_AMOUNT;
    }

    public function isFreeShipping(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_FREE_SHIPPING;
    }

    public function isBuyXGetY(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_BUY_X_GET_Y;
    }

    public function isTierDiscount(): bool
    {
        return $this->discountType === self::DISCOUNT_TYPE_TIER_DISCOUNT;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s%s)', 
            $this->name,
            $this->isPercentageDiscount() ? $this->discountValue . '%' : $this->discountValue,
            $this->promoCode ? ' - ' . $this->promoCode : ''
        );
    }
}