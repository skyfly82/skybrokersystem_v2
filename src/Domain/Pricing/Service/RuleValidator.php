<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Entity\PricingRule;
use App\Domain\Pricing\Entity\PromotionalPricing;
use App\Domain\Pricing\Entity\CustomerPricing;

/**
 * Validator for pricing rules consistency and applicability
 */
class RuleValidator
{
    /**
     * Validate rule consistency
     */
    public function validateRules(array $rules): array
    {
        $errors = [];

        foreach ($rules as $index => $rule) {
            $ruleErrors = $this->validateSingleRule($rule, $index);
            $errors = array_merge($errors, $ruleErrors);
        }

        // Check for overlapping weight ranges
        $weightOverlaps = $this->checkWeightRangeOverlaps($rules);
        $errors = array_merge($errors, $weightOverlaps);

        // Check for conflicting rules
        $conflicts = $this->checkRuleConflicts($rules);
        $errors = array_merge($errors, $conflicts);

        return $errors;
    }

    /**
     * Validate single rule
     */
    private function validateSingleRule($rule, int $index): array
    {
        $errors = [];

        if ($rule instanceof PricingRule) {
            return $this->validatePricingRule($rule, $index);
        }

        if ($rule instanceof PromotionalPricing) {
            return $this->validatePromotionalPricing($rule, $index);
        }

        if ($rule instanceof CustomerPricing) {
            return $this->validateCustomerPricing($rule, $index);
        }

        if (is_array($rule)) {
            return $this->validateArrayRule($rule, $index);
        }

        $errors[] = "Rule at index {$index} has unsupported type: " . gettype($rule);
        return $errors;
    }

    /**
     * Validate PricingRule entity
     */
    private function validatePricingRule(PricingRule $rule, int $index): array
    {
        $errors = [];

        // Validate weight range
        $weightFrom = $rule->getWeightFrom();
        $weightTo = $rule->getWeightTo();

        if ($weightFrom < 0) {
            $errors[] = "Rule {$index}: Weight from cannot be negative";
        }

        if ($weightTo !== null && $weightTo <= $weightFrom) {
            $errors[] = "Rule {$index}: Weight to must be greater than weight from";
        }

        // Validate price
        $price = $rule->getPrice();
        if ($price < 0) {
            $errors[] = "Rule {$index}: Price cannot be negative";
        }

        // Validate min/max prices
        $minPrice = $rule->getMinPrice();
        $maxPrice = $rule->getMaxPrice();

        if ($minPrice !== null && $minPrice < 0) {
            $errors[] = "Rule {$index}: Minimum price cannot be negative";
        }

        if ($maxPrice !== null && $maxPrice < 0) {
            $errors[] = "Rule {$index}: Maximum price cannot be negative";
        }

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            $errors[] = "Rule {$index}: Minimum price cannot be greater than maximum price";
        }

        // Validate calculation method specific fields
        $calculationMethod = $rule->getCalculationMethod();
        switch ($calculationMethod) {
            case PricingRule::CALCULATION_METHOD_PER_KG:
            case PricingRule::CALCULATION_METHOD_PER_KG_STEP:
                if ($rule->getPricePerKg() === null) {
                    $errors[] = "Rule {$index}: Price per kg is required for {$calculationMethod} method";
                }
                break;

            case PricingRule::CALCULATION_METHOD_PER_KG_STEP:
                if ($rule->getWeightStep() === null) {
                    $errors[] = "Rule {$index}: Weight step is required for stepped pricing";
                }
                break;
        }

        return $errors;
    }

    /**
     * Validate PromotionalPricing entity
     */
    private function validatePromotionalPricing(PromotionalPricing $promotion, int $index): array
    {
        $errors = [];

        // Validate date ranges
        $validFrom = $promotion->getValidFrom();
        $validTo = $promotion->getValidTo();

        if ($validFrom && $validTo && $validFrom > $validTo) {
            $errors[] = "Promotion {$index}: Valid from date cannot be after valid to date";
        }

        // Validate discount value
        $discountValue = $promotion->getDiscountValue();
        $discountType = $promotion->getDiscountType();

        if ($discountValue < 0) {
            $errors[] = "Promotion {$index}: Discount value cannot be negative";
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            $errors[] = "Promotion {$index}: Percentage discount cannot exceed 100%";
        }

        // Validate minimum order value
        $minOrderValue = $promotion->getMinOrderValue();
        if ($minOrderValue !== null && $minOrderValue < 0) {
            $errors[] = "Promotion {$index}: Minimum order value cannot be negative";
        }

        // Validate maximum discount per order
        $maxDiscountPerOrder = $promotion->getMaxDiscountPerOrder();
        if ($maxDiscountPerOrder !== null && $maxDiscountPerOrder < 0) {
            $errors[] = "Promotion {$index}: Maximum discount per order cannot be negative";
        }

        return $errors;
    }

    /**
     * Validate CustomerPricing entity
     */
    private function validateCustomerPricing(CustomerPricing $customerPricing, int $index): array
    {
        $errors = [];

        // Similar validations as promotional pricing
        $validFrom = $customerPricing->getValidFrom();
        $validTo = $customerPricing->getValidTo();

        if ($validFrom && $validTo && $validFrom > $validTo) {
            $errors[] = "Customer pricing {$index}: Valid from date cannot be after valid to date";
        }

        $discountValue = $customerPricing->getDiscountValue();
        $discountType = $customerPricing->getDiscountType();

        if ($discountValue < 0) {
            $errors[] = "Customer pricing {$index}: Discount value cannot be negative";
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            $errors[] = "Customer pricing {$index}: Percentage discount cannot exceed 100%";
        }

        return $errors;
    }

    /**
     * Validate array-based rule
     */
    private function validateArrayRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['type'])) {
            $errors[] = "Rule {$index}: Missing 'type' field";
            return $errors;
        }

        $type = $rule['type'];

        switch ($type) {
            case 'weight':
                $errors = array_merge($errors, $this->validateWeightRule($rule, $index));
                break;

            case 'dimension':
                $errors = array_merge($errors, $this->validateDimensionRule($rule, $index));
                break;

            case 'volumetric':
                $errors = array_merge($errors, $this->validateVolumetricRule($rule, $index));
                break;

            case 'tiered':
                $errors = array_merge($errors, $this->validateTieredRule($rule, $index));
                break;

            case 'progressive':
                $errors = array_merge($errors, $this->validateProgressiveRule($rule, $index));
                break;

            case 'seasonal':
                $errors = array_merge($errors, $this->validateSeasonalRule($rule, $index));
                break;

            case 'volume_based':
                $errors = array_merge($errors, $this->validateVolumeBasedRule($rule, $index));
                break;

            default:
                $errors[] = "Rule {$index}: Unknown rule type '{$type}'";
        }

        return $errors;
    }

    /**
     * Validate weight rule
     */
    private function validateWeightRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['weight_from'])) {
            $errors[] = "Weight rule {$index}: Missing 'weight_from' field";
        } elseif ($rule['weight_from'] < 0) {
            $errors[] = "Weight rule {$index}: 'weight_from' cannot be negative";
        }

        if (isset($rule['weight_to']) && $rule['weight_to'] <= $rule['weight_from']) {
            $errors[] = "Weight rule {$index}: 'weight_to' must be greater than 'weight_from'";
        }

        if (!isset($rule['price'])) {
            $errors[] = "Weight rule {$index}: Missing 'price' field";
        } elseif ($rule['price'] < 0) {
            $errors[] = "Weight rule {$index}: 'price' cannot be negative";
        }

        return $errors;
    }

    /**
     * Validate dimension rule
     */
    private function validateDimensionRule(array $rule, int $index): array
    {
        $errors = [];

        $requiredFields = ['max_length', 'max_width', 'max_height', 'price'];
        foreach ($requiredFields as $field) {
            if (!isset($rule[$field])) {
                $errors[] = "Dimension rule {$index}: Missing '{$field}' field";
            } elseif ($rule[$field] < 0) {
                $errors[] = "Dimension rule {$index}: '{$field}' cannot be negative";
            }
        }

        return $errors;
    }

    /**
     * Validate volumetric rule
     */
    private function validateVolumetricRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['divisor'])) {
            $errors[] = "Volumetric rule {$index}: Missing 'divisor' field";
        } elseif ($rule['divisor'] <= 0) {
            $errors[] = "Volumetric rule {$index}: 'divisor' must be positive";
        }

        return $errors;
    }

    /**
     * Validate tiered rule
     */
    private function validateTieredRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['tiers']) || !is_array($rule['tiers'])) {
            $errors[] = "Tiered rule {$index}: Missing or invalid 'tiers' field";
            return $errors;
        }

        foreach ($rule['tiers'] as $tierIndex => $tier) {
            if (!isset($tier['threshold'])) {
                $errors[] = "Tiered rule {$index}, tier {$tierIndex}: Missing 'threshold' field";
            }

            if (!isset($tier['discount'])) {
                $errors[] = "Tiered rule {$index}, tier {$tierIndex}: Missing 'discount' field";
            } elseif ($tier['discount'] < 0) {
                $errors[] = "Tiered rule {$index}, tier {$tierIndex}: 'discount' cannot be negative";
            }
        }

        return $errors;
    }

    /**
     * Validate progressive rule
     */
    private function validateProgressiveRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['progression_rate'])) {
            $errors[] = "Progressive rule {$index}: Missing 'progression_rate' field";
        } elseif ($rule['progression_rate'] < 0 || $rule['progression_rate'] > 100) {
            $errors[] = "Progressive rule {$index}: 'progression_rate' must be between 0 and 100";
        }

        return $errors;
    }

    /**
     * Validate seasonal rule
     */
    private function validateSeasonalRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['season'])) {
            $errors[] = "Seasonal rule {$index}: Missing 'season' field";
        } else {
            $validSeasons = ['spring', 'summer', 'autumn', 'winter', 'christmas', 'black_friday'];
            if (!in_array($rule['season'], $validSeasons, true)) {
                $errors[] = "Seasonal rule {$index}: Invalid season '{$rule['season']}'";
            }
        }

        if (!isset($rule['discount'])) {
            $errors[] = "Seasonal rule {$index}: Missing 'discount' field";
        }

        return $errors;
    }

    /**
     * Validate volume-based rule
     */
    private function validateVolumeBasedRule(array $rule, int $index): array
    {
        $errors = [];

        if (!isset($rule['min_orders'])) {
            $errors[] = "Volume-based rule {$index}: Missing 'min_orders' field";
        } elseif ($rule['min_orders'] < 1) {
            $errors[] = "Volume-based rule {$index}: 'min_orders' must be at least 1";
        }

        if (!isset($rule['discount'])) {
            $errors[] = "Volume-based rule {$index}: Missing 'discount' field";
        }

        return $errors;
    }

    /**
     * Check for overlapping weight ranges in PricingRules
     */
    private function checkWeightRangeOverlaps(array $rules): array
    {
        $errors = [];
        $pricingRules = array_filter($rules, fn($rule) => $rule instanceof PricingRule);

        for ($i = 0; $i < count($pricingRules); $i++) {
            for ($j = $i + 1; $j < count($pricingRules); $j++) {
                $rule1 = $pricingRules[$i];
                $rule2 = $pricingRules[$j];

                if ($this->weightRangesOverlap($rule1, $rule2)) {
                    $errors[] = "Weight range overlap detected between rules {$i} and {$j}";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if two pricing rules have overlapping weight ranges
     */
    private function weightRangesOverlap(PricingRule $rule1, PricingRule $rule2): bool
    {
        $from1 = $rule1->getWeightFrom();
        $to1 = $rule1->getWeightTo();
        $from2 = $rule2->getWeightFrom();
        $to2 = $rule2->getWeightTo();

        // Handle unlimited ranges (null to values)
        if ($to1 === null) $to1 = PHP_FLOAT_MAX;
        if ($to2 === null) $to2 = PHP_FLOAT_MAX;

        // Check for overlap
        return !($to1 <= $from2 || $to2 <= $from1);
    }

    /**
     * Check for conflicting rules
     */
    private function checkRuleConflicts(array $rules): array
    {
        $errors = [];

        // Group rules by type for conflict detection
        $rulesByType = [];
        foreach ($rules as $index => $rule) {
            $type = $this->getRuleType($rule);
            $rulesByType[$type][] = ['rule' => $rule, 'index' => $index];
        }

        // Check for conflicts within each type
        foreach ($rulesByType as $type => $rulesOfType) {
            if (count($rulesOfType) > 1) {
                $typeErrors = $this->checkTypeSpecificConflicts($type, $rulesOfType);
                $errors = array_merge($errors, $typeErrors);
            }
        }

        return $errors;
    }

    /**
     * Get rule type for grouping
     */
    private function getRuleType($rule): string
    {
        if ($rule instanceof PricingRule) {
            return 'pricing_rule';
        }

        if ($rule instanceof PromotionalPricing) {
            return 'promotional_pricing';
        }

        if ($rule instanceof CustomerPricing) {
            return 'customer_pricing';
        }

        if (is_array($rule) && isset($rule['type'])) {
            return $rule['type'];
        }

        return 'unknown';
    }

    /**
     * Check for type-specific conflicts
     */
    private function checkTypeSpecificConflicts(string $type, array $rulesOfType): array
    {
        $errors = [];

        switch ($type) {
            case 'promotional_pricing':
                $errors = array_merge($errors, $this->checkPromotionalConflicts($rulesOfType));
                break;

            case 'seasonal':
                $errors = array_merge($errors, $this->checkSeasonalConflicts($rulesOfType));
                break;
        }

        return $errors;
    }

    /**
     * Check for promotional pricing conflicts
     */
    private function checkPromotionalConflicts(array $promotions): array
    {
        $errors = [];

        for ($i = 0; $i < count($promotions); $i++) {
            for ($j = $i + 1; $j < count($promotions); $j++) {
                $promo1 = $promotions[$i]['rule'];
                $promo2 = $promotions[$j]['rule'];
                $index1 = $promotions[$i]['index'];
                $index2 = $promotions[$j]['index'];

                if ($promo1 instanceof PromotionalPricing && $promo2 instanceof PromotionalPricing) {
                    if ($this->promotionPeriodsOverlap($promo1, $promo2)) {
                        $errors[] = "Promotional periods overlap between promotions {$index1} and {$index2}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if promotion periods overlap
     */
    private function promotionPeriodsOverlap(PromotionalPricing $promo1, PromotionalPricing $promo2): bool
    {
        $from1 = $promo1->getValidFrom() ?: new \DateTime('1970-01-01');
        $to1 = $promo1->getValidTo() ?: new \DateTime('2099-12-31');
        $from2 = $promo2->getValidFrom() ?: new \DateTime('1970-01-01');
        $to2 = $promo2->getValidTo() ?: new \DateTime('2099-12-31');

        return !($to1 <= $from2 || $to2 <= $from1);
    }

    /**
     * Check for seasonal conflicts
     */
    private function checkSeasonalConflicts(array $seasonalRules): array
    {
        $errors = [];
        $seasonCounts = [];

        foreach ($seasonalRules as $ruleData) {
            $rule = $ruleData['rule'];
            if (is_array($rule) && isset($rule['season'])) {
                $season = $rule['season'];
                if (!isset($seasonCounts[$season])) {
                    $seasonCounts[$season] = [];
                }
                $seasonCounts[$season][] = $ruleData['index'];
            }
        }

        foreach ($seasonCounts as $season => $indices) {
            if (count($indices) > 1) {
                $errors[] = "Multiple seasonal rules defined for season '{$season}': " . implode(', ', $indices);
            }
        }

        return $errors;
    }
}