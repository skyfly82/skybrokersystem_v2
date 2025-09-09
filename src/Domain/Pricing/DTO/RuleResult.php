<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

/**
 * Result of pricing rule calculations
 * 
 * Contains detailed breakdown of:
 * - Applied rules and their effects
 * - Discounts and their sources
 * - Final calculated prices
 * - Debug information for transparency
 */
final readonly class RuleResult
{
    public function __construct(
        public string $originalPrice,
        public string $finalPrice,
        public string $totalDiscount,
        public array $appliedRules = [],
        public array $appliedPromotions = [],
        public array $discountBreakdown = [],
        public ?string $effectiveWeight = null,
        public ?string $volumetricWeight = null,
        public ?string $chargeableWeight = null,
        public ?array $debugInfo = null,
        public bool $hasErrors = false,
        public array $errors = [],
        public array $warnings = [],
        public ?string $currencyCode = 'PLN'
    ) {
    }

    /**
     * Create success result
     */
    public static function success(
        string $originalPrice,
        string $finalPrice,
        string $totalDiscount,
        array $appliedRules = [],
        array $appliedPromotions = [],
        array $discountBreakdown = []
    ): self {
        return new self(
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            totalDiscount: $totalDiscount,
            appliedRules: $appliedRules,
            appliedPromotions: $appliedPromotions,
            discountBreakdown: $discountBreakdown,
            hasErrors: false
        );
    }

    /**
     * Create error result
     */
    public static function error(
        string $originalPrice,
        array $errors,
        array $warnings = []
    ): self {
        return new self(
            originalPrice: $originalPrice,
            finalPrice: $originalPrice,
            totalDiscount: '0.00',
            hasErrors: true,
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Create result with weight calculations
     */
    public static function withWeightDetails(
        string $originalPrice,
        string $finalPrice,
        string $totalDiscount,
        string $effectiveWeight,
        string $volumetricWeight,
        string $chargeableWeight,
        array $appliedRules = []
    ): self {
        return new self(
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            totalDiscount: $totalDiscount,
            appliedRules: $appliedRules,
            effectiveWeight: $effectiveWeight,
            volumetricWeight: $volumetricWeight,
            chargeableWeight: $chargeableWeight
        );
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): string
    {
        if (bccomp($this->originalPrice, '0.00', 2) === 0) {
            return '0.00';
        }

        $percentage = bcmul(
            bcdiv($this->totalDiscount, $this->originalPrice, 6),
            '100.00',
            2
        );

        return $percentage;
    }

    /**
     * Get savings amount
     */
    public function getSavings(): string
    {
        return $this->totalDiscount;
    }

    /**
     * Check if any discounts were applied
     */
    public function hasDiscounts(): bool
    {
        return bccomp($this->totalDiscount, '0.00', 2) > 0;
    }

    /**
     * Get applied rule names
     */
    public function getAppliedRuleNames(): array
    {
        return array_map(
            fn($rule) => $rule['name'] ?? $rule['type'] ?? 'Unknown Rule',
            $this->appliedRules
        );
    }

    /**
     * Get applied promotion names
     */
    public function getAppliedPromotionNames(): array
    {
        return array_map(
            fn($promotion) => $promotion['name'] ?? 'Unknown Promotion',
            $this->appliedPromotions
        );
    }

    /**
     * Get discount by type
     */
    public function getDiscountByType(string $type): string
    {
        $totalForType = '0.00';

        foreach ($this->discountBreakdown as $breakdown) {
            if ($breakdown['type'] === $type) {
                $totalForType = bcadd($totalForType, $breakdown['amount'], 2);
            }
        }

        return $totalForType;
    }

    /**
     * Check if specific rule type was applied
     */
    public function hasRuleType(string $ruleType): bool
    {
        foreach ($this->appliedRules as $rule) {
            if (($rule['type'] ?? null) === $ruleType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get rule result by type
     */
    public function getRuleResult(string $ruleType): ?array
    {
        foreach ($this->appliedRules as $rule) {
            if (($rule['type'] ?? null) === $ruleType) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Add debug information
     */
    public function withDebugInfo(array $debugInfo): self
    {
        return new self(
            originalPrice: $this->originalPrice,
            finalPrice: $this->finalPrice,
            totalDiscount: $this->totalDiscount,
            appliedRules: $this->appliedRules,
            appliedPromotions: $this->appliedPromotions,
            discountBreakdown: $this->discountBreakdown,
            effectiveWeight: $this->effectiveWeight,
            volumetricWeight: $this->volumetricWeight,
            chargeableWeight: $this->chargeableWeight,
            debugInfo: array_merge($this->debugInfo ?? [], $debugInfo),
            hasErrors: $this->hasErrors,
            errors: $this->errors,
            warnings: $this->warnings,
            currencyCode: $this->currencyCode
        );
    }

    /**
     * Add warning
     */
    public function withWarning(string $warning): self
    {
        return new self(
            originalPrice: $this->originalPrice,
            finalPrice: $this->finalPrice,
            totalDiscount: $this->totalDiscount,
            appliedRules: $this->appliedRules,
            appliedPromotions: $this->appliedPromotions,
            discountBreakdown: $this->discountBreakdown,
            effectiveWeight: $this->effectiveWeight,
            volumetricWeight: $this->volumetricWeight,
            chargeableWeight: $this->chargeableWeight,
            debugInfo: $this->debugInfo,
            hasErrors: $this->hasErrors,
            errors: $this->errors,
            warnings: array_merge($this->warnings, [$warning]),
            currencyCode: $this->currencyCode
        );
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'total_discount' => $this->totalDiscount,
            'discount_percentage' => $this->getDiscountPercentage(),
            'savings' => $this->getSavings(),
            'currency' => $this->currencyCode,
            'applied_rules' => $this->appliedRules,
            'applied_promotions' => $this->appliedPromotions,
            'discount_breakdown' => $this->discountBreakdown,
            'weight_details' => [
                'effective_weight' => $this->effectiveWeight,
                'volumetric_weight' => $this->volumetricWeight,
                'chargeable_weight' => $this->chargeableWeight,
            ],
            'has_discounts' => $this->hasDiscounts(),
            'has_errors' => $this->hasErrors,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'debug_info' => $this->debugInfo
        ];
    }
}