<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pricing\Service;

use App\Domain\Pricing\DTO\RuleContext;
use App\Domain\Pricing\DTO\RuleResult;
use App\Domain\Pricing\Repository\CustomerPricingRepository;
use App\Domain\Pricing\Repository\PricingRuleRepository;
use App\Domain\Pricing\Repository\PromotionalPricingRepository;
use App\Domain\Pricing\Service\PricingRuleEngine;
use App\Domain\Pricing\Service\RuleValidator;
use App\Entity\Customer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PricingRuleEngine
 */
class PricingRuleEngineTest extends TestCase
{
    private PricingRuleEngine $ruleEngine;
    private LoggerInterface|MockObject $logger;
    private RuleValidator|MockObject $ruleValidator;
    private PricingRuleRepository|MockObject $pricingRuleRepository;
    private PromotionalPricingRepository|MockObject $promotionalPricingRepository;
    private CustomerPricingRepository|MockObject $customerPricingRepository;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->ruleValidator = $this->createMock(RuleValidator::class);
        $this->pricingRuleRepository = $this->createMock(PricingRuleRepository::class);
        $this->promotionalPricingRepository = $this->createMock(PromotionalPricingRepository::class);
        $this->customerPricingRepository = $this->createMock(CustomerPricingRepository::class);

        $this->ruleEngine = new PricingRuleEngine(
            $this->logger,
            $this->ruleValidator,
            $this->pricingRuleRepository,
            $this->promotionalPricingRepository,
            $this->customerPricingRepository
        );
    }

    public function testCalculateVolumetricWeight(): void
    {
        // Test volumetric weight calculation
        $volumetricWeight = $this->ruleEngine->calculateVolumetricWeight(
            lengthCm: 30.0,
            widthCm: 20.0,
            heightCm: 15.0,
            divisor: 5000.0
        );

        $expectedWeight = (30.0 * 20.0 * 15.0) / 5000.0; // = 1.8 kg
        $this->assertEquals($expectedWeight, $volumetricWeight);
    }

    public function testCalculateVolumetricWeightWithCustomDivisor(): void
    {
        // Test with DHL divisor (5000)
        $volumetricWeightDHL = $this->ruleEngine->calculateVolumetricWeight(
            50.0, 40.0, 30.0, 5000.0
        );

        // Test with InPost divisor (6000)
        $volumetricWeightInPost = $this->ruleEngine->calculateVolumetricWeight(
            50.0, 40.0, 30.0, 6000.0
        );

        $this->assertEquals(12.0, $volumetricWeightDHL); // 60000/5000
        $this->assertEquals(10.0, $volumetricWeightInPost); // 60000/6000
        $this->assertGreaterThan($volumetricWeightInPost, $volumetricWeightDHL);
    }

    public function testApplyRulesWithBasicContext(): void
    {
        // Setup mocks
        $this->ruleValidator->expects($this->once())
            ->method('validateRules')
            ->willReturn([]);

        // Note: getPricingRulesForContext returns empty array by default
        
        $this->promotionalPricingRepository->expects($this->once())
            ->method('findActivePromotions')
            ->with('ALL')
            ->willReturn([]);

        // findActiveForCustomer won't be called since there's no customer in context

        // Create test context
        $context = RuleContext::fromShipmentData(
            weightKg: 2.5,
            lengthCm: 30.0,
            widthCm: 20.0,
            heightCm: 15.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '25.00'
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertEquals('25.00', $result->originalPrice);
        $this->assertFalse($result->hasErrors); // Should be false because validation returns no errors
    }

    public function testApplyRulesWithCustomer(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(1);

        // Setup mocks
        $this->ruleValidator->expects($this->once())
            ->method('validateRules')
            ->willReturn([]);

        // Note: getPricingRulesForContext returns empty array by default

        $this->promotionalPricingRepository->expects($this->once())
            ->method('findActivePromotions')
            ->with('ALL')
            ->willReturn([]);

        $this->customerPricingRepository->expects($this->once())
            ->method('findActiveForCustomer')
            ->with($customer)
            ->willReturn([]);

        $context = RuleContext::fromShipmentData(
            weightKg: 3.0,
            lengthCm: 35.0,
            widthCm: 25.0,
            heightCm: 20.0,
            serviceType: 'express',
            zoneCode: 'eu',
            basePrice: '45.00',
            customer: $customer
        );

        $result = $this->ruleEngine->applyRules($context);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertEquals('45.00', $result->originalPrice);
        $this->assertFalse($result->hasErrors); // Should be false because validation returns no errors
    }

    public function testCalculateDiscountDirectly(): void
    {
        // Setup mocks for discount calculation - validateRules is called internally by applyRules
        $this->ruleValidator->expects($this->atLeastOnce())
            ->method('validateRules')
            ->willReturn([]); // Return no errors

        // Note: getPricingRulesForContext returns empty array by default

        $this->promotionalPricingRepository->expects($this->atLeastOnce())
            ->method('findActivePromotions')
            ->with('ALL')
            ->willReturn([]);

        // findActiveForCustomer won't be called since there's no customer in context

        $context = RuleContext::fromShipmentData(
            weightKg: 1.5,
            lengthCm: 25.0,
            widthCm: 15.0,
            heightCm: 10.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '20.00'
        );

        $discount = $this->ruleEngine->calculateDiscount($context);

        $this->assertIsString($discount);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $discount); // Format: 12.34
    }

    public function testValidateRulesSuccess(): void
    {
        $rules = [
            ['type' => 'weight', 'weight_from' => 0.0, 'weight_to' => 1.0, 'price' => 15.00],
            ['type' => 'seasonal', 'season' => 'summer', 'discount' => 10.0]
        ];

        $this->ruleValidator->expects($this->once())
            ->method('validateRules')
            ->with($rules)
            ->willReturn([]);

        $isValid = $this->ruleEngine->validateRules($rules);

        $this->assertTrue($isValid);
    }

    public function testValidateRulesWithErrors(): void
    {
        $rules = [
            ['type' => 'weight', 'weight_from' => -1.0], // Invalid negative weight
        ];

        $this->ruleValidator->expects($this->once())
            ->method('validateRules')
            ->with($rules)
            ->willReturn(['Rule 0: Weight from cannot be negative']);

        $isValid = $this->ruleEngine->validateRules($rules);

        $this->assertFalse($isValid);
    }

    public function testGetPriorityRules(): void
    {
        $rules = [
            ['type' => 'weight', 'priority' => 100],
            ['type' => 'seasonal', 'priority' => 10],
            ['type' => 'volume_based', 'priority' => 50]
        ];

        $sortedRules = $this->ruleEngine->getPriorityRules($rules);

        // Should be sorted by priority (ascending)
        $this->assertEquals('seasonal', $sortedRules[0]['type']); // priority 10
        $this->assertEquals('volume_based', $sortedRules[1]['type']); // priority 50
        $this->assertEquals('weight', $sortedRules[2]['type']); // priority 100
    }

    public function testApplyWeightRulesWithEmptyRules(): void
    {
        $context = RuleContext::fromShipmentData(
            weightKg: 2.0,
            lengthCm: 30.0,
            widthCm: 20.0,
            heightCm: 15.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '25.00'
        );

        $result = $this->ruleEngine->applyWeightRules([], $context);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertEquals('25.00', $result->originalPrice);
        $this->assertEquals('25.00', $result->finalPrice);
        $this->assertEquals('0.00', $result->totalDiscount);
        $this->assertEmpty($result->appliedRules);
    }

    public function testApplyDimensionRulesWithOversizedPackage(): void
    {
        // Create oversized package context
        $context = RuleContext::fromShipmentData(
            weightKg: 5.0,
            lengthCm: 150.0, // Oversized
            widthCm: 90.0,   // Oversized
            heightCm: 85.0,  // Oversized
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '40.00'
        );

        $result = $this->ruleEngine->applyDimensionRules([], $context);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertEquals('40.00', $result->originalPrice);
        // Final price should be higher due to oversize surcharge
        $this->assertGreaterThan('40.00', $result->finalPrice);
        $this->assertTrue($result->hasRuleType('dimension'));
    }

    public function testApplySeasonalRulesWithBlackFriday(): void
    {
        // Create Black Friday context
        $context = new RuleContext(
            weightKg: 2.0,
            lengthCm: 30.0,
            widthCm: 20.0,
            heightCm: 15.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '50.00',
            seasonalPeriod: 'black_friday'
        );

        $result = $this->ruleEngine->applySeasonalRules([], $context);

        $this->assertInstanceOf(RuleResult::class, $result);
        $this->assertEquals('50.00', $result->originalPrice);
        // Should have Black Friday discount
        $this->assertGreaterThan('0.00', $result->totalDiscount);
        $this->assertTrue($result->hasRuleType('seasonal'));
    }

    public function testContextVolumetricWeightCalculation(): void
    {
        $context = RuleContext::fromShipmentData(
            weightKg: 1.0,
            lengthCm: 50.0,
            widthCm: 40.0,
            heightCm: 30.0,
            serviceType: 'standard',
            zoneCode: 'domestic',
            basePrice: '30.00'
        );

        $volumetricWeight = $context->getVolumetricWeight();
        $chargeableWeight = $context->getChargeableWeight();

        $expectedVolumetric = (50.0 * 40.0 * 30.0) / 5000.0; // = 12.0 kg
        
        $this->assertEquals($expectedVolumetric, $volumetricWeight);
        $this->assertEquals($expectedVolumetric, $chargeableWeight); // Volumetric > actual
        $this->assertFalse($context->isOversized()); // 50x40x30 does NOT exceed standard dimensions (120x80x80)
    }
}