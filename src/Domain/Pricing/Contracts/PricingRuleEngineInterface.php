<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Contracts;

use App\Domain\Pricing\DTO\RuleContext;
use App\Domain\Pricing\DTO\RuleResult;
use App\Domain\Pricing\Entity\PricingRule;

/**
 * Interface for pricing rule engine
 * 
 * Defines contract for applying various pricing rules including:
 * - Weight-based rules
 * - Dimensional rules  
 * - Volumetric weight calculations
 * - Tiered pricing
 * - Progressive discounts
 * - Seasonal promotions
 * - Volume-based discounts
 */
interface PricingRuleEngineInterface
{
    /**
     * Apply all applicable rules to calculate final price
     */
    public function applyRules(RuleContext $context): RuleResult;

    /**
     * Calculate discount based on applied rules
     */
    public function calculateDiscount(RuleContext $context): string;

    /**
     * Validate rule consistency and applicability
     */
    public function validateRules(array $rules): bool;

    /**
     * Get rules sorted by priority
     */
    public function getPriorityRules(array $rules): array;

    /**
     * Combine multiple promotions with priority handling
     */
    public function combinePromotions(array $promotions, RuleContext $context): RuleResult;

    /**
     * Calculate volumetric weight
     */
    public function calculateVolumetricWeight(
        float $lengthCm, 
        float $widthCm, 
        float $heightCm, 
        float $divisor = 5000.0
    ): float;

    /**
     * Apply weight-based pricing rules
     */
    public function applyWeightRules(array $rules, RuleContext $context): RuleResult;

    /**
     * Apply dimensional pricing rules
     */
    public function applyDimensionRules(array $rules, RuleContext $context): RuleResult;

    /**
     * Apply tiered pricing rules
     */
    public function applyTieredRules(array $rules, RuleContext $context): RuleResult;

    /**
     * Apply progressive discount rules
     */
    public function applyProgressiveRules(array $rules, RuleContext $context): RuleResult;

    /**
     * Apply seasonal promotions
     */
    public function applySeasonalRules(array $rules, RuleContext $context): RuleResult;

    /**
     * Apply volume-based discounts
     */
    public function applyVolumeBasedRules(array $rules, RuleContext $context): RuleResult;
}