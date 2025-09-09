<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service;

use App\Domain\Pricing\Contracts\PricingRuleEngineInterface;
use App\Domain\Pricing\DTO\RuleContext;
use App\Domain\Pricing\DTO\RuleResult;
use App\Domain\Pricing\Factory\RuleContextFactory;
use App\Entity\Customer;
use Psr\Log\LoggerInterface;

/**
 * Examples and demonstration of PricingRuleEngine usage
 * 
 * This class provides real-world examples of how to use
 * the pricing rule engine for different scenarios
 */
class PricingRuleEngineExamples
{
    public function __construct(
        private readonly PricingRuleEngineInterface $ruleEngine,
        private readonly RuleContextFactory $contextFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Example 1: Basic weight-based pricing calculation
     */
    public function calculateBasicWeightPricing(): RuleResult
    {
        // Standard 2kg package to domestic zone
        $context = $this->contextFactory->createFromShipmentData(
            weightKg: 2.5,
            lengthCm: 30.0,
            widthCm: 20.0,
            heightCm: 15.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '25.00'
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Basic weight pricing calculated', [
            'original_price' => $result->originalPrice,
            'final_price' => $result->finalPrice,
            'discount' => $result->totalDiscount,
            'applied_rules' => $result->getAppliedRuleNames()
        ]);

        return $result;
    }

    /**
     * Example 2: Volumetric weight calculation for large packages
     */
    public function calculateVolumetricWeightPricing(): RuleResult
    {
        // Large but light package - volumetric weight applies
        $context = $this->contextFactory->createFromShipmentData(
            weightKg: 1.0, // Actual weight
            lengthCm: 60.0, // Large dimensions
            widthCm: 40.0,
            heightCm: 30.0,
            serviceType: 'express',
            zoneCode: 'eu',
            basePrice: '45.00'
        );

        // Calculate volumetric weight
        $volumetricWeight = $this->ruleEngine->calculateVolumetricWeight(
            60.0, 40.0, 30.0, 5000.0
        );

        $this->logger->info('Volumetric weight calculated', [
            'actual_weight' => $context->weightKg,
            'volumetric_weight' => $volumetricWeight,
            'chargeable_weight' => max($context->weightKg, $volumetricWeight),
            'volume_cm3' => $context->getVolumeCm3()
        ]);

        return $this->ruleEngine->applyRules($context);
    }

    /**
     * Example 3: Customer tier-based pricing
     */
    public function calculateTierBasedPricing(Customer $customer): RuleResult
    {
        $context = $this->contextFactory->createEnrichedContext(
            weightKg: 5.0,
            lengthCm: 40.0,
            widthCm: 30.0,
            heightCm: 25.0,
            serviceType: 'express',
            zoneCode: 'domestic',
            basePrice: '35.00',
            customer: $customer
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Tier-based pricing calculated', [
            'customer_id' => $customer->getId(),
            'customer_tier' => $context->customerTier,
            'original_price' => $result->originalPrice,
            'final_price' => $result->finalPrice,
            'tier_discount' => $result->getDiscountByType('tier_discount')
        ]);

        return $result;
    }

    /**
     * Example 4: Black Friday promotional pricing
     */
    public function calculateBlackFridayPricing(?Customer $customer = null): RuleResult
    {
        $context = $this->contextFactory->createBlackFridayContext(
            weightKg: 3.0,
            lengthCm: 35.0,
            widthCm: 25.0,
            heightCm: 20.0,
            serviceType: 'express',
            zoneCode: 'eu',
            basePrice: '50.00',
            customer: $customer
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Black Friday pricing calculated', [
            'customer_id' => $customer?->getId(),
            'seasonal_period' => $context->seasonalPeriod,
            'original_price' => $result->originalPrice,
            'final_price' => $result->finalPrice,
            'seasonal_discount' => $result->getDiscountByType('seasonal_promotion'),
            'total_savings' => $result->getSavings(),
            'discount_percentage' => $result->getDiscountPercentage()
        ]);

        return $result;
    }

    /**
     * Example 5: Volume discount for high-volume customers
     */
    public function calculateVolumeDiscountPricing(Customer $customer): RuleResult
    {
        $context = $this->contextFactory->createVolumeDiscountContext(
            weightKg: 2.0,
            lengthCm: 25.0,
            widthCm: 20.0,
            heightCm: 15.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '20.00',
            customer: $customer
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Volume discount calculated', [
            'customer_id' => $customer->getId(),
            'monthly_orders' => $context->monthlyOrderVolume,
            'monthly_spending' => $context->getCustomerMonthlySpending(),
            'qualifies_for_volume_discount' => $context->qualifiesForVolumeDiscount(),
            'volume_discount' => $result->getDiscountByType('volume_discount'),
            'final_price' => $result->finalPrice
        ]);

        return $result;
    }

    /**
     * Example 6: Oversized package surcharge calculation
     */
    public function calculateOversizedPackagePricing(): RuleResult
    {
        // Package exceeding standard dimensions
        $context = $this->contextFactory->createFromShipmentData(
            weightKg: 8.0,
            lengthCm: 150.0, // Oversized length
            widthCm: 90.0,   // Oversized width
            heightCm: 85.0,  // Oversized height
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '40.00'
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Oversized package pricing calculated', [
            'is_oversized' => $context->isOversized(),
            'dimensions' => [
                'length' => $context->lengthCm,
                'width' => $context->widthCm,
                'height' => $context->heightCm
            ],
            'volume_cm3' => $context->getVolumeCm3(),
            'oversize_surcharge' => $result->hasRuleType('dimension'),
            'original_price' => $result->originalPrice,
            'final_price' => $result->finalPrice
        ]);

        return $result;
    }

    /**
     * Example 7: Progressive discount based on order value
     */
    public function calculateProgressiveDiscountPricing(Customer $customer): RuleResult
    {
        // Create context with high customer lifetime value
        $basicContext = $this->contextFactory->createFromShipmentData(
            weightKg: 4.0,
            lengthCm: 35.0,
            widthCm: 25.0,
            heightCm: 20.0,
            serviceType: 'express',
            zoneCode: 'international',
            basePrice: '75.00',
            customer: $customer
        );

        // Enhance with high order value for progressive discount
        $context = new RuleContext(
            weightKg: $basicContext->weightKg,
            lengthCm: $basicContext->lengthCm,
            widthCm: $basicContext->widthCm,
            heightCm: $basicContext->heightCm,
            serviceType: $basicContext->serviceType,
            zoneCode: $basicContext->zoneCode,
            basePrice: $basicContext->basePrice,
            customer: $basicContext->customer,
            calculationDate: $basicContext->calculationDate,
            currencyCode: $basicContext->currencyCode,
            customerHistory: $basicContext->customerHistory,
            additionalData: $basicContext->additionalData,
            isBusinessCustomer: $basicContext->isBusinessCustomer,
            customerTier: $basicContext->customerTier,
            monthlyOrderVolume: $basicContext->monthlyOrderVolume,
            totalOrderValue: '15000.00', // High total order value for progressive discount
            isFirstOrder: $basicContext->isFirstOrder,
            isReturningCustomer: $basicContext->isReturningCustomer,
            seasonalPeriod: $basicContext->seasonalPeriod,
            eligiblePromotions: $basicContext->eligiblePromotions
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->logger->info('Progressive discount calculated', [
            'customer_id' => $customer->getId(),
            'total_order_value' => $context->totalOrderValue,
            'progressive_discount' => $result->getDiscountByType('progressive_discount'),
            'discount_percentage' => $result->getDiscountPercentage(),
            'final_price' => $result->finalPrice
        ]);

        return $result;
    }

    /**
     * Example 8: Combined discounts with priority handling
     */
    public function calculateCombinedDiscountPricing(Customer $customer): RuleResult
    {
        // Customer eligible for multiple discounts
        $context = $this->contextFactory->createEnrichedContext(
            weightKg: 6.0,
            lengthCm: 45.0,
            widthCm: 35.0,
            heightCm: 30.0,
            serviceType: 'express',
            zoneCode: 'eu',
            basePrice: '85.00',
            customer: $customer
        );

        // Force multiple discount scenarios
        $enrichedContext = new RuleContext(
            weightKg: $context->weightKg,
            lengthCm: $context->lengthCm,
            widthCm: $context->widthCm,
            heightCm: $context->heightCm,
            serviceType: $context->serviceType,
            zoneCode: $context->zoneCode,
            basePrice: $context->basePrice,
            customer: $context->customer,
            calculationDate: $context->calculationDate,
            currencyCode: $context->currencyCode,
            customerHistory: $context->customerHistory,
            additionalData: $context->additionalData,
            isBusinessCustomer: true, // Business customer
            customerTier: 'gold', // Gold tier
            monthlyOrderVolume: 45, // High volume
            totalOrderValue: '8500.00', // High value
            isFirstOrder: false,
            isReturningCustomer: true,
            seasonalPeriod: 'summer', // Seasonal discount
            eligiblePromotions: ['SUMMER_EXPRESS', 'BUSINESS_GOLD']
        );

        $result = $this->ruleEngine->applyRules($enrichedContext);

        $this->logger->info('Combined discounts calculated', [
            'customer_id' => $customer->getId(),
            'applied_discounts' => [
                'tier_discount' => $result->getDiscountByType('tier_discount'),
                'volume_discount' => $result->getDiscountByType('volume_discount'),
                'seasonal_discount' => $result->getDiscountByType('seasonal_promotion'),
                'progressive_discount' => $result->getDiscountByType('progressive_discount')
            ],
            'total_discount' => $result->totalDiscount,
            'discount_percentage' => $result->getDiscountPercentage(),
            'original_price' => $result->originalPrice,
            'final_price' => $result->finalPrice,
            'applied_rules' => $result->getAppliedRuleNames(),
            'discount_breakdown' => $result->discountBreakdown
        ]);

        return $result;
    }

    /**
     * Example 9: Validate rules consistency
     */
    public function validateRulesExample(): array
    {
        // Example array-based rules for validation
        $rules = [
            [
                'type' => 'weight',
                'weight_from' => 0.0,
                'weight_to' => 1.0,
                'price' => 15.00
            ],
            [
                'type' => 'weight',
                'weight_from' => 1.0,
                'weight_to' => 5.0,
                'price' => 25.00
            ],
            [
                'type' => 'dimension',
                'max_length' => 120.0,
                'max_width' => 80.0,
                'max_height' => 80.0,
                'price' => 5.00
            ],
            [
                'type' => 'seasonal',
                'season' => 'black_friday',
                'discount' => 25.0
            ],
            [
                'type' => 'tiered',
                'tiers' => [
                    ['threshold' => 1000.0, 'discount' => 5.0],
                    ['threshold' => 5000.0, 'discount' => 10.0],
                    ['threshold' => 10000.0, 'discount' => 15.0]
                ]
            ]
        ];

        $isValid = $this->ruleEngine->validateRules($rules);

        $this->logger->info('Rules validation completed', [
            'rules_count' => count($rules),
            'is_valid' => $isValid,
            'validated_rules' => array_column($rules, 'type')
        ]);

        return [
            'is_valid' => $isValid,
            'rules' => $rules
        ];
    }

    /**
     * Example 10: Get priority-sorted rules
     */
    public function getPriorityRulesExample(): array
    {
        $rules = [
            ['type' => 'weight', 'priority' => 100],
            ['type' => 'seasonal', 'priority' => 10],
            ['type' => 'volume_based', 'priority' => 50],
            ['type' => 'progressive', 'priority' => 30]
        ];

        $sortedRules = $this->ruleEngine->getPriorityRules($rules);

        $this->logger->info('Rules sorted by priority', [
            'original_order' => array_column($rules, 'type'),
            'priority_order' => array_column($sortedRules, 'type'),
            'priorities' => array_column($sortedRules, 'priority')
        ]);

        return $sortedRules;
    }
}