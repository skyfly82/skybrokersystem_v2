<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Contracts\PricingRuleEngineInterface;
use App\Domain\Pricing\DTO\PriceCalculationResponseDTO;
use App\Domain\Pricing\DTO\RuleContext;
use App\Domain\Pricing\DTO\RuleResult;
use App\Domain\Pricing\Entity\CustomerPricing;
use App\Domain\Pricing\Entity\PromotionalPricing;
use App\Domain\Pricing\Entity\PricingRule;
use App\Domain\Pricing\Repository\PricingRuleRepository;
use App\Domain\Pricing\Repository\PromotionalPricingRepository;
use App\Domain\Pricing\Repository\CustomerPricingRepository;
use App\Entity\Customer;
use Psr\Log\LoggerInterface;

/**
 * Advanced pricing rule engine for courier system
 *
 * Supports multiple rule types:
 * - Weight-based rules
 * - Dimensional rules  
 * - Volumetric weight calculations
 * - Tiered pricing
 * - Progressive discounts
 * - Seasonal promotions
 * - Volume-based discounts
 */
class PricingRuleEngine implements PricingRuleEngineInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RuleValidator $ruleValidator,
        private readonly PricingRuleRepository $pricingRuleRepository,
        private readonly PromotionalPricingRepository $promotionalPricingRepository,
        private readonly CustomerPricingRepository $customerPricingRepository
    ) {}

    /**
     * Calculate customer discount based on customer pricing rules
     */
    public function calculateCustomerDiscount(
        CustomerPricing $customerPricing,
        PriceCalculationResponseDTO $response
    ): ?string {
        if (!$this->isCustomerPricingApplicable($customerPricing, $response)) {
            return null;
        }

        $discountType = $customerPricing->getDiscountType();
        $discountValue = $customerPricing->getDiscountValue();

        return match ($discountType) {
            'percentage' => $this->calculatePercentageDiscount($response->basePrice, (float)$discountValue),
            'fixed_amount' => $discountValue,
            'tiered' => $this->calculateTieredCustomerDiscount($customerPricing, $response),
            default => null,
        };
    }

    /**
     * Check if promotion is applicable to the calculation
     */
    public function isPromotionApplicable(
        PromotionalPricing $promotion,
        PriceCalculationResponseDTO $response
    ): bool {
        // Check if promotion is active
        if (!$promotion->isActive()) {
            return false;
        }

        // Check date validity
        $now = new \DateTimeImmutable();
        if ($promotion->getValidFrom() && $now < $promotion->getValidFrom()) {
            return false;
        }
        if ($promotion->getValidTo() && $now > $promotion->getValidTo()) {
            return false;
        }

        // Check minimum order value
        if ($promotion->getMinOrderValue() !== null) {
            $minValue = (float)$promotion->getMinOrderValue();
            $currentValue = (float)$response->subtotal;
            
            if ($currentValue < $minValue) {
                return false;
            }
        }

        // Check maximum discount per order
        if ($promotion->getMaxDiscountPerOrder() !== null) {
            // This would need to be tracked per order/customer
            // For now, we'll assume it's always applicable
        }

        // Check service type eligibility
        if (!empty($promotion->getEligibleServiceTypes()) && 
            !in_array($response->serviceType, $promotion->getEligibleServiceTypes())) {
            return false;
        }

        // Check zone eligibility
        if (!empty($promotion->getEligibleZones()) && 
            !in_array($response->zoneCode, $promotion->getEligibleZones())) {
            return false;
        }

        return true;
    }

    /**
     * Calculate promotion discount
     */
    public function calculatePromotionDiscount(
        PromotionalPricing $promotion,
        PriceCalculationResponseDTO $response
    ): string {
        $discountType = $promotion->getDiscountType();
        $discountValue = $promotion->getDiscountValue();
        $baseAmount = $response->subtotal;

        $discount = match ($discountType) {
            'percentage' => $this->calculatePercentageDiscount($baseAmount, (float)$discountValue),
            'fixed_amount' => $discountValue,
            'buy_x_get_y' => $this->calculateBuyXGetYDiscount($promotion, $response),
            'free_shipping' => $response->basePrice, // Free shipping = discount equals base price
            default => '0.00',
        };

        // Apply maximum discount limit if set
        if ($promotion->getMaxDiscountPerOrder() !== null) {
            $maxDiscount = $promotion->getMaxDiscountPerOrder();
            if (bccomp($discount, $maxDiscount, 2) > 0) {
                $discount = $maxDiscount;
            }
        }

        $this->logger->debug('Promotion discount calculated', [
            'promotion_id' => $promotion->getId(),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'calculated_discount' => $discount,
            'base_amount' => $baseAmount,
        ]);

        return $discount;
    }

    /**
     * Check if customer pricing is applicable
     */
    private function isCustomerPricingApplicable(
        CustomerPricing $customerPricing,
        PriceCalculationResponseDTO $response
    ): bool {
        // Check if pricing is active
        if (!$customerPricing->isActive()) {
            return false;
        }

        // Check date validity
        $now = new \DateTimeImmutable();
        if ($customerPricing->getValidFrom() && $now < $customerPricing->getValidFrom()) {
            return false;
        }
        if ($customerPricing->getValidTo() && $now > $customerPricing->getValidTo()) {
            return false;
        }

        // Check minimum order value
        if ($customerPricing->getMinOrderValue() !== null) {
            $minValue = (float)$customerPricing->getMinOrderValue();
            $currentValue = (float)$response->subtotal;
            
            if ($currentValue < $minValue) {
                return false;
            }
        }

        // Check service type eligibility
        if (!empty($customerPricing->getEligibleServiceTypes()) && 
            !in_array($response->serviceType, $customerPricing->getEligibleServiceTypes())) {
            return false;
        }

        // Check zone eligibility
        if (!empty($customerPricing->getEligibleZones()) && 
            !in_array($response->zoneCode, $customerPricing->getEligibleZones())) {
            return false;
        }

        return true;
    }

    /**
     * Calculate percentage discount
     */
    private function calculatePercentageDiscount(string $baseAmount, float $percentage): string
    {
        $discountAmount = bcmul($baseAmount, (string)($percentage / 100), 4);
        return bcadd('0', $discountAmount, 2); // Round to 2 decimal places
    }

    /**
     * Calculate tiered customer discount
     */
    private function calculateTieredCustomerDiscount(
        CustomerPricing $customerPricing,
        PriceCalculationResponseDTO $response
    ): string {
        $tiers = $customerPricing->getTierConfiguration();
        
        if (empty($tiers)) {
            return '0.00';
        }

        $orderValue = (float)$response->subtotal;
        $applicableTier = null;

        // Find the highest applicable tier
        foreach ($tiers as $tier) {
            $minValue = $tier['min_value'] ?? 0;
            if ($orderValue >= $minValue) {
                if ($applicableTier === null || $minValue > ($applicableTier['min_value'] ?? 0)) {
                    $applicableTier = $tier;
                }
            }
        }

        if ($applicableTier === null) {
            return '0.00';
        }

        $discountType = $applicableTier['discount_type'] ?? 'percentage';
        $discountValue = $applicableTier['discount_value'] ?? 0;

        return match ($discountType) {
            'percentage' => $this->calculatePercentageDiscount($response->subtotal, (float)$discountValue),
            'fixed_amount' => number_format($discountValue, 2, '.', ''),
            default => '0.00',
        };
    }

    /**
     * Calculate Buy X Get Y discount (e.g., buy 2 get 1 free)
     */
    private function calculateBuyXGetYDiscount(
        PromotionalPricing $promotion,
        PriceCalculationResponseDTO $response
    ): string {
        // This is a simplified implementation
        // In a real scenario, this would depend on the number of items/shipments
        $configuration = $promotion->getPromotionConfiguration();
        
        $buyQuantity = $configuration['buy_quantity'] ?? 2;
        $getQuantity = $configuration['get_quantity'] ?? 1;
        $discountPercentage = $configuration['discount_percentage'] ?? 100; // 100% = free
        
        // For now, assume single shipment, so no Buy X Get Y discount applies
        return '0.00';
    }

    /**
     * Validate discount amount against business rules
     */
    public function validateDiscountAmount(
        string $discountAmount,
        string $originalAmount,
        ?string $maxAllowedDiscount = null
    ): string {
        // Ensure discount doesn't exceed original amount
        if (bccomp($discountAmount, $originalAmount, 2) > 0) {
            $discountAmount = $originalAmount;
        }

        // Ensure discount doesn't exceed maximum allowed
        if ($maxAllowedDiscount !== null && bccomp($discountAmount, $maxAllowedDiscount, 2) > 0) {
            $discountAmount = $maxAllowedDiscount;
        }

        // Ensure discount is not negative
        if (bccomp($discountAmount, '0.00', 2) < 0) {
            $discountAmount = '0.00';
        }

        return $discountAmount;
    }

    /**
     * Calculate compound discount (multiple discounts applied together)
     */
    public function calculateCompoundDiscount(array $discounts, string $baseAmount): string
    {
        $totalDiscount = '0.00';
        $currentAmount = $baseAmount;

        foreach ($discounts as $discount) {
            if ($discount['type'] === 'percentage') {
                $discountAmount = bcmul($currentAmount, (string)($discount['value'] / 100), 2);
            } else {
                $discountAmount = $discount['value'];
            }

            $totalDiscount = bcadd($totalDiscount, $discountAmount, 2);
            $currentAmount = bcsub($currentAmount, $discountAmount, 2);

            // Prevent negative amount
            if (bccomp($currentAmount, '0.00', 2) < 0) {
                $currentAmount = '0.00';
                break;
            }
        }

        return $totalDiscount;
    }

    /**
     * Apply all applicable rules to calculate final price
     */
    public function applyRules(RuleContext $context): RuleResult
    {
        try {
            $debugInfo = ['started_at' => new \DateTimeImmutable()];
            $appliedRules = [];
            $appliedPromotions = [];
            $discountBreakdown = [];
            $currentPrice = $context->basePrice;

            // Calculate volumetric and chargeable weight
            $volumetricWeight = $this->calculateVolumetricWeight(
                $context->lengthCm,
                $context->widthCm,
                $context->heightCm
            );
            $chargeableWeight = max($context->weightKg, $volumetricWeight);

            $debugInfo['weights'] = [
                'actual' => $context->weightKg,
                'volumetric' => $volumetricWeight,
                'chargeable' => $chargeableWeight
            ];

            // Get applicable rules from database
            $pricingRules = $this->getPricingRulesForContext($context);
            $promotionalPricings = $this->getPromotionalPricingsForContext($context);
            $customerPricings = $this->getCustomerPricingsForContext($context);

            // Validate all rules
            $allRules = array_merge($pricingRules, $promotionalPricings, $customerPricings);
            $validationErrors = $this->validateRules($allRules);

            if (!empty($validationErrors)) {
                return RuleResult::error($context->basePrice, $validationErrors);
            }

            // Apply weight-based rules
            $weightResult = $this->applyWeightRules($pricingRules, $context);
            if (!$weightResult->hasErrors) {
                $currentPrice = $weightResult->finalPrice;
                $appliedRules = array_merge($appliedRules, $weightResult->appliedRules);
                $discountBreakdown = array_merge($discountBreakdown, $weightResult->discountBreakdown);
            }

            // Apply dimensional rules
            $contextWithNewPrice = new RuleContext(
                $context->weightKg,
                $context->lengthCm,
                $context->widthCm,
                $context->heightCm,
                $context->serviceType,
                $context->zoneCode,
                $currentPrice,
                $context->customer,
                $context->calculationDate,
                $context->currencyCode,
                $context->customerHistory,
                $context->additionalData,
                $context->isBusinessCustomer,
                $context->customerTier,
                $context->monthlyOrderVolume,
                $context->totalOrderValue,
                $context->isFirstOrder,
                $context->isReturningCustomer,
                $context->seasonalPeriod,
                $context->eligiblePromotions
            );

            $dimensionResult = $this->applyDimensionRules($pricingRules, $contextWithNewPrice);
            if (!$dimensionResult->hasErrors) {
                $currentPrice = $dimensionResult->finalPrice;
                $appliedRules = array_merge($appliedRules, $dimensionResult->appliedRules);
                $discountBreakdown = array_merge($discountBreakdown, $dimensionResult->discountBreakdown);
            }

            // Apply promotional and customer pricing
            $promotionResult = $this->combinePromotions($promotionalPricings, $contextWithNewPrice);
            if (!$promotionResult->hasErrors) {
                $currentPrice = bcsub($currentPrice, $promotionResult->totalDiscount, 2);
                $appliedPromotions = array_merge($appliedPromotions, $promotionResult->appliedPromotions);
                $discountBreakdown = array_merge($discountBreakdown, $promotionResult->discountBreakdown);
            }

            // Apply seasonal rules
            $seasonalResult = $this->applySeasonalRules([], $contextWithNewPrice);
            if (!$seasonalResult->hasErrors) {
                $currentPrice = bcsub($currentPrice, $seasonalResult->totalDiscount, 2);
                $appliedRules = array_merge($appliedRules, $seasonalResult->appliedRules);
                $discountBreakdown = array_merge($discountBreakdown, $seasonalResult->discountBreakdown);
            }

            // Apply volume-based discounts
            $volumeResult = $this->applyVolumeBasedRules([], $contextWithNewPrice);
            if (!$volumeResult->hasErrors) {
                $currentPrice = bcsub($currentPrice, $volumeResult->totalDiscount, 2);
                $appliedRules = array_merge($appliedRules, $volumeResult->appliedRules);
                $discountBreakdown = array_merge($discountBreakdown, $volumeResult->discountBreakdown);
            }

            // Ensure price doesn't go negative
            if (bccomp($currentPrice, '0.00', 2) < 0) {
                $currentPrice = '0.00';
            }

            $totalDiscount = bcsub($context->basePrice, $currentPrice, 2);

            $debugInfo['completed_at'] = new \DateTimeImmutable();

            return RuleResult::withWeightDetails(
                $context->basePrice,
                $currentPrice,
                $totalDiscount,
                (string) $context->weightKg,
                (string) $volumetricWeight,
                (string) $chargeableWeight,
                $appliedRules
            )->withDebugInfo($debugInfo);

        } catch (\Throwable $e) {
            $this->logger->error('Error applying pricing rules', [
                'exception' => $e->getMessage(),
                'context' => [
                    'weight' => $context->weightKg,
                    'service_type' => $context->serviceType,
                    'customer_id' => $context->customer?->getId()
                ]
            ]);

            return RuleResult::error($context->basePrice, [$e->getMessage()]);
        }
    }

    /**
     * Calculate discount based on applied rules
     */
    public function calculateDiscount(RuleContext $context): string
    {
        $result = $this->applyRules($context);
        return $result->totalDiscount;
    }

    /**
     * Validate rule consistency and applicability
     */
    public function validateRules(array $rules): bool
    {
        $errors = $this->ruleValidator->validateRules($rules);
        return empty($errors);
    }

    /**
     * Get rules sorted by priority
     */
    public function getPriorityRules(array $rules): array
    {
        // Sort by priority (higher priority = lower number)
        usort($rules, function ($a, $b) {
            $priorityA = $this->getRulePriority($a);
            $priorityB = $this->getRulePriority($b);
            
            return $priorityA <=> $priorityB;
        });

        return $rules;
    }

    /**
     * Combine multiple promotions with priority handling
     */
    public function combinePromotions(array $promotions, RuleContext $context): RuleResult
    {
        $appliedPromotions = [];
        $discountBreakdown = [];
        $totalDiscount = '0.00';
        $currentAmount = $context->basePrice;

        // Sort promotions by priority
        $sortedPromotions = $this->getPriorityRules($promotions);

        foreach ($sortedPromotions as $promotion) {
            if ($promotion instanceof PromotionalPricing) {
                if ($this->isPromotionApplicable($promotion, $this->convertContextToResponse($context))) {
                    $discount = $this->calculatePromotionDiscount($promotion, $this->convertContextToResponse($context));
                    
                    if (bccomp($discount, '0.00', 2) > 0) {
                        $totalDiscount = bcadd($totalDiscount, $discount, 2);
                        $currentAmount = bcsub($currentAmount, $discount, 2);
                        
                        $appliedPromotions[] = [
                            'id' => $promotion->getId(),
                            'name' => $promotion->getName(),
                            'type' => $promotion->getDiscountType(),
                            'discount' => $discount
                        ];
                        
                        $discountBreakdown[] = [
                            'type' => 'promotion',
                            'source' => $promotion->getName(),
                            'amount' => $discount
                        ];
                    }
                }
            }
        }

        $finalPrice = max('0.00', $currentAmount);

        return RuleResult::success(
            $context->basePrice,
            $finalPrice,
            $totalDiscount,
            [],
            $appliedPromotions,
            $discountBreakdown
        );
    }

    /**
     * Calculate volumetric weight
     */
    public function calculateVolumetricWeight(
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        float $divisor = 5000.0
    ): float {
        return ($lengthCm * $widthCm * $heightCm) / $divisor;
    }

    /**
     * Apply weight-based pricing rules
     */
    public function applyWeightRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $chargeableWeight = $context->getChargeableWeight();
        $basePrice = $context->basePrice;
        $currentPrice = $basePrice;

        foreach ($rules as $rule) {
            if ($rule instanceof PricingRule && $rule->matchesWeight($chargeableWeight)) {
                $rulePrice = (string) $rule->calculatePrice($chargeableWeight);
                
                // If this rule gives a better price, use it
                if (bccomp($rulePrice, $currentPrice, 2) < 0) {
                    $discount = bcsub($currentPrice, $rulePrice, 2);
                    $currentPrice = $rulePrice;
                    
                    $appliedRules[] = [
                        'id' => $rule->getId(),
                        'name' => $rule->getName() ?? 'Weight Rule',
                        'type' => 'weight',
                        'weight_from' => $rule->getWeightFrom(),
                        'weight_to' => $rule->getWeightTo(),
                        'calculation_method' => $rule->getCalculationMethod(),
                        'price' => $rulePrice,
                        'discount' => $discount
                    ];
                    
                    $discountBreakdown[] = [
                        'type' => 'weight_rule',
                        'source' => $rule->getName() ?? 'Weight Rule',
                        'amount' => $discount
                    ];
                }
            }
        }

        $totalDiscount = bcsub($basePrice, $currentPrice, 2);

        return RuleResult::success(
            $basePrice,
            $currentPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Apply dimensional pricing rules
     */
    public function applyDimensionRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $basePrice = $context->basePrice;
        $currentPrice = $basePrice;

        // Check if package is oversized
        if ($context->isOversized()) {
            $oversizeSurcharge = $this->calculateOversizeSurcharge($context);
            
            if (bccomp($oversizeSurcharge, '0.00', 2) > 0) {
                $currentPrice = bcadd($currentPrice, $oversizeSurcharge, 2);
                
                $appliedRules[] = [
                    'name' => 'Oversize Surcharge',
                    'type' => 'dimension',
                    'surcharge' => $oversizeSurcharge,
                    'dimensions' => [
                        'length' => $context->lengthCm,
                        'width' => $context->widthCm,
                        'height' => $context->heightCm
                    ]
                ];
            }
        }

        foreach ($rules as $rule) {
            if ($rule instanceof PricingRule && 
                $rule->matchesDimensions((int) $context->lengthCm, (int) $context->widthCm, (int) $context->heightCm)) {
                
                $rulePrice = (string) $rule->getPrice();
                
                // Apply dimensional rule if it affects pricing
                if (bccomp($rulePrice, $context->basePrice, 2) !== 0) {
                    $priceDiff = bcsub($rulePrice, $context->basePrice, 2);
                    $currentPrice = bcadd($currentPrice, $priceDiff, 2);
                    
                    $appliedRules[] = [
                        'id' => $rule->getId(),
                        'name' => $rule->getName() ?? 'Dimension Rule',
                        'type' => 'dimension',
                        'price_adjustment' => $priceDiff
                    ];
                }
            }
        }

        $totalChange = bcsub($currentPrice, $basePrice, 2);
        $isDiscount = bccomp($totalChange, '0.00', 2) < 0;
        $totalDiscount = $isDiscount ? bcmul($totalChange, '-1', 2) : '0.00';

        return RuleResult::success(
            $basePrice,
            $currentPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Apply tiered pricing rules
     */
    public function applyTieredRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $basePrice = $context->basePrice;
        $totalDiscount = '0.00';

        // Example tiered rule based on order value or customer tier
        if ($context->customerTier) {
            $tierDiscount = $this->calculateTierDiscount($context->customerTier, $basePrice);
            
            if (bccomp($tierDiscount, '0.00', 2) > 0) {
                $totalDiscount = bcadd($totalDiscount, $tierDiscount, 2);
                
                $appliedRules[] = [
                    'name' => 'Customer Tier Discount',
                    'type' => 'tiered',
                    'tier' => $context->customerTier,
                    'discount' => $tierDiscount
                ];
                
                $discountBreakdown[] = [
                    'type' => 'tier_discount',
                    'source' => "Tier: {$context->customerTier}",
                    'amount' => $tierDiscount
                ];
            }
        }

        $finalPrice = bcsub($basePrice, $totalDiscount, 2);

        return RuleResult::success(
            $basePrice,
            $finalPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Apply progressive discount rules
     */
    public function applyProgressiveRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $basePrice = $context->basePrice;
        $totalDiscount = '0.00';

        // Progressive discount based on order value
        if ($context->totalOrderValue) {
            $progressiveDiscount = $this->calculateProgressiveDiscount(
                $context->totalOrderValue,
                $basePrice
            );
            
            if (bccomp($progressiveDiscount, '0.00', 2) > 0) {
                $totalDiscount = bcadd($totalDiscount, $progressiveDiscount, 2);
                
                $appliedRules[] = [
                    'name' => 'Progressive Volume Discount',
                    'type' => 'progressive',
                    'order_value' => $context->totalOrderValue,
                    'discount' => $progressiveDiscount
                ];
                
                $discountBreakdown[] = [
                    'type' => 'progressive_discount',
                    'source' => 'Volume Progression',
                    'amount' => $progressiveDiscount
                ];
            }
        }

        $finalPrice = bcsub($basePrice, $totalDiscount, 2);

        return RuleResult::success(
            $basePrice,
            $finalPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Apply seasonal promotions
     */
    public function applySeasonalRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $basePrice = $context->basePrice;
        $totalDiscount = '0.00';

        if ($context->seasonalPeriod) {
            $seasonalDiscount = $this->calculateSeasonalDiscount(
                $context->seasonalPeriod,
                $basePrice
            );
            
            if (bccomp($seasonalDiscount, '0.00', 2) > 0) {
                $totalDiscount = bcadd($totalDiscount, $seasonalDiscount, 2);
                
                $appliedRules[] = [
                    'name' => 'Seasonal Promotion',
                    'type' => 'seasonal',
                    'season' => $context->seasonalPeriod,
                    'discount' => $seasonalDiscount
                ];
                
                $discountBreakdown[] = [
                    'type' => 'seasonal_promotion',
                    'source' => "Season: {$context->seasonalPeriod}",
                    'amount' => $seasonalDiscount
                ];
            }
        }

        $finalPrice = bcsub($basePrice, $totalDiscount, 2);

        return RuleResult::success(
            $basePrice,
            $finalPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Apply volume-based discounts
     */
    public function applyVolumeBasedRules(array $rules, RuleContext $context): RuleResult
    {
        $appliedRules = [];
        $discountBreakdown = [];
        $basePrice = $context->basePrice;
        $totalDiscount = '0.00';

        if ($context->qualifiesForVolumeDiscount()) {
            $volumeDiscount = $this->calculateVolumeDiscount(
                $context->monthlyOrderVolume ?? 0,
                $context->getCustomerMonthlySpending(),
                $basePrice
            );
            
            if (bccomp($volumeDiscount, '0.00', 2) > 0) {
                $totalDiscount = bcadd($totalDiscount, $volumeDiscount, 2);
                
                $appliedRules[] = [
                    'name' => 'Volume Discount',
                    'type' => 'volume_based',
                    'monthly_orders' => $context->monthlyOrderVolume,
                    'monthly_spending' => $context->getCustomerMonthlySpending(),
                    'discount' => $volumeDiscount
                ];
                
                $discountBreakdown[] = [
                    'type' => 'volume_discount',
                    'source' => 'Monthly Volume',
                    'amount' => $volumeDiscount
                ];
            }
        }

        $finalPrice = bcsub($basePrice, $totalDiscount, 2);

        return RuleResult::success(
            $basePrice,
            $finalPrice,
            $totalDiscount,
            $appliedRules,
            [],
            $discountBreakdown
        );
    }

    /**
     * Get rule priority for sorting
     */
    private function getRulePriority($rule): int
    {
        if ($rule instanceof PricingRule) {
            return $rule->getSortOrder();
        }

        if ($rule instanceof PromotionalPricing) {
            return $rule->getPriority() ?? 100;
        }

        if ($rule instanceof CustomerPricing) {
            return 50; // Medium priority
        }

        if (is_array($rule)) {
            return $rule['priority'] ?? 100;
        }

        return 1000; // Lowest priority
    }

    /**
     * Get applicable pricing rules from database
     */
    private function getPricingRulesForContext(RuleContext $context): array
    {
        // This would query the database for applicable rules
        // For now, return empty array as this is a complex query
        return [];
    }

    /**
     * Get applicable promotional pricings from database
     */
    private function getPromotionalPricingsForContext(RuleContext $context): array
    {
        $now = new \DateTime();
        return $this->promotionalPricingRepository->findActivePromotions($now);
    }

    /**
     * Get applicable customer pricings from database
     */
    private function getCustomerPricingsForContext(RuleContext $context): array
    {
        if (!$context->customer) {
            return [];
        }

        return $this->customerPricingRepository->findActiveForCustomer($context->customer);
    }

    /**
     * Calculate oversize surcharge
     */
    private function calculateOversizeSurcharge(RuleContext $context): string
    {
        // Base surcharge for oversized packages
        $baseSurcharge = '10.00';
        
        // Additional surcharge based on how much oversized
        $volume = $context->getVolumeCm3();
        $standardVolume = 120 * 80 * 80; // Standard max dimensions
        
        if ($volume > $standardVolume) {
            $volumeRatio = $volume / $standardVolume;
            $additionalSurcharge = bcmul($baseSurcharge, (string) ($volumeRatio - 1), 2);
            $baseSurcharge = bcadd($baseSurcharge, $additionalSurcharge, 2);
        }
        
        return $baseSurcharge;
    }

    /**
     * Calculate tier-based discount
     */
    private function calculateTierDiscount(string $tier, string $basePrice): string
    {
        $discountPercentages = [
            'bronze' => 5.0,
            'silver' => 10.0,
            'gold' => 15.0,
            'platinum' => 20.0
        ];

        $percentage = $discountPercentages[strtolower($tier)] ?? 0.0;
        
        return $this->calculatePercentageDiscount($basePrice, $percentage);
    }

    /**
     * Calculate progressive discount based on total order value
     */
    private function calculateProgressiveDiscount(string $totalOrderValue, string $basePrice): string
    {
        $orderValue = (float) $totalOrderValue;
        
        // Progressive tiers
        if ($orderValue >= 10000) {
            return $this->calculatePercentageDiscount($basePrice, 15.0);
        } elseif ($orderValue >= 5000) {
            return $this->calculatePercentageDiscount($basePrice, 10.0);
        } elseif ($orderValue >= 2000) {
            return $this->calculatePercentageDiscount($basePrice, 7.5);
        } elseif ($orderValue >= 1000) {
            return $this->calculatePercentageDiscount($basePrice, 5.0);
        }
        
        return '0.00';
    }

    /**
     * Calculate seasonal discount
     */
    private function calculateSeasonalDiscount(string $season, string $basePrice): string
    {
        $seasonalDiscounts = [
            'black_friday' => 25.0,
            'christmas' => 15.0,
            'summer' => 10.0,
            'winter' => 5.0
        ];

        $percentage = $seasonalDiscounts[$season] ?? 0.0;
        
        return $this->calculatePercentageDiscount($basePrice, $percentage);
    }

    /**
     * Calculate volume-based discount
     */
    private function calculateVolumeDiscount(int $monthlyOrders, string $monthlySpending, string $basePrice): string
    {
        $spendingAmount = (float) $monthlySpending;
        $discountPercentage = 0.0;
        
        // Volume tiers
        if ($monthlyOrders >= 100 && $spendingAmount >= 10000) {
            $discountPercentage = 20.0;
        } elseif ($monthlyOrders >= 50 && $spendingAmount >= 5000) {
            $discountPercentage = 15.0;
        } elseif ($monthlyOrders >= 25 && $spendingAmount >= 2500) {
            $discountPercentage = 10.0;
        } elseif ($monthlyOrders >= 10 && $spendingAmount >= 1000) {
            $discountPercentage = 5.0;
        }
        
        return $this->calculatePercentageDiscount($basePrice, $discountPercentage);
    }

    /**
     * Convert RuleContext to PriceCalculationResponseDTO for compatibility
     */
    private function convertContextToResponse(RuleContext $context): PriceCalculationResponseDTO
    {
        return new PriceCalculationResponseDTO(
            basePrice: $context->basePrice,
            subtotal: $context->basePrice,
            tax: '0.00',
            total: $context->basePrice,
            serviceType: $context->serviceType,
            zoneCode: $context->zoneCode,
            currencyCode: $context->currencyCode ?? 'PLN'
        );
    }
}