<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Factory;

use App\Domain\Pricing\DTO\RuleContext;
use App\Domain\Pricing\Repository\CustomerPricingRepository;
use App\Entity\Customer;

/**
 * Factory for creating RuleContext instances
 * 
 * Simplifies the creation of pricing rule contexts with
 * customer history, seasonal data, and other contextual information
 */
class RuleContextFactory
{
    public function __construct(
        private readonly CustomerPricingRepository $customerPricingRepository
    ) {
    }

    /**
     * Create basic context from shipment parameters
     */
    public function createFromShipmentData(
        float $weightKg,
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        string $serviceType,
        string $zoneCode,
        string $basePrice,
        ?Customer $customer = null
    ): RuleContext {
        return RuleContext::fromShipmentData(
            $weightKg,
            $lengthCm,
            $widthCm,
            $heightCm,
            $serviceType,
            $zoneCode,
            $basePrice,
            $customer
        );
    }

    /**
     * Create enriched context with customer history and analytics
     */
    public function createEnrichedContext(
        float $weightKg,
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        string $serviceType,
        string $zoneCode,
        string $basePrice,
        Customer $customer
    ): RuleContext {
        // Get customer analytics
        $customerHistory = $this->buildCustomerHistory($customer);
        $customerTier = $this->determineCustomerTier($customer);
        $monthlyOrderVolume = $this->getMonthlyOrderVolume($customer);
        $totalOrderValue = $this->getTotalOrderValue($customer);
        $isFirstOrder = $this->isFirstOrder($customer);
        $isReturningCustomer = !$isFirstOrder;

        $basicContext = $this->createFromShipmentData(
            $weightKg,
            $lengthCm,
            $widthCm,
            $heightCm,
            $serviceType,
            $zoneCode,
            $basePrice,
            $customer
        );

        return RuleContext::withCustomerHistory(
            $basicContext,
            $customerHistory,
            $customerTier,
            $monthlyOrderVolume,
            $totalOrderValue,
            $isFirstOrder,
            $isReturningCustomer
        );
    }

    /**
     * Create context for Black Friday promotions
     */
    public function createBlackFridayContext(
        float $weightKg,
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        string $serviceType,
        string $zoneCode,
        string $basePrice,
        ?Customer $customer = null
    ): RuleContext {
        $context = $this->createFromShipmentData(
            $weightKg, $lengthCm, $widthCm, $heightCm,
            $serviceType, $zoneCode, $basePrice, $customer
        );

        return new RuleContext(
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
            customerHistory: $customer ? $this->buildCustomerHistory($customer) : null,
            additionalData: $context->additionalData,
            isBusinessCustomer: $context->isBusinessCustomer,
            customerTier: $customer ? $this->determineCustomerTier($customer) : null,
            monthlyOrderVolume: $customer ? $this->getMonthlyOrderVolume($customer) : null,
            totalOrderValue: $customer ? $this->getTotalOrderValue($customer) : null,
            isFirstOrder: $customer ? $this->isFirstOrder($customer) : true,
            isReturningCustomer: $customer ? !$this->isFirstOrder($customer) : false,
            seasonalPeriod: 'black_friday', // Force Black Friday period
            eligiblePromotions: $this->getBlackFridayPromotions()
        );
    }

    /**
     * Create context for volume discount calculation
     */
    public function createVolumeDiscountContext(
        float $weightKg,
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        string $serviceType,
        string $zoneCode,
        string $basePrice,
        Customer $customer
    ): RuleContext {
        $monthlyOrders = $this->getMonthlyOrderVolume($customer);
        $monthlySpending = $this->getMonthlySpending($customer);

        $context = $this->createFromShipmentData(
            $weightKg, $lengthCm, $widthCm, $heightCm,
            $serviceType, $zoneCode, $basePrice, $customer
        );

        return new RuleContext(
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
            customerHistory: [
                'monthly_order_count' => $monthlyOrders,
                'monthly_spending' => $monthlySpending,
                'lifetime_value' => $this->getCustomerLifetimeValue($customer)
            ],
            additionalData: $context->additionalData,
            isBusinessCustomer: $context->isBusinessCustomer,
            customerTier: $this->determineCustomerTier($customer),
            monthlyOrderVolume: $monthlyOrders,
            totalOrderValue: $this->getTotalOrderValue($customer),
            isFirstOrder: $this->isFirstOrder($customer),
            isReturningCustomer: !$this->isFirstOrder($customer),
            seasonalPeriod: $context->seasonalPeriod,
            eligiblePromotions: $context->eligiblePromotions
        );
    }

    /**
     * Build comprehensive customer history array
     */
    private function buildCustomerHistory(Customer $customer): array
    {
        return [
            'customer_id' => $customer->getId(),
            'registration_date' => $customer->getCreatedAt()?->format('Y-m-d'),
            'total_orders' => $this->getTotalOrderCount($customer),
            'monthly_order_count' => $this->getMonthlyOrderVolume($customer),
            'monthly_spending' => $this->getMonthlySpending($customer),
            'lifetime_value' => $this->getCustomerLifetimeValue($customer),
            'average_order_value' => $this->getAverageOrderValue($customer),
            'last_order_date' => $this->getLastOrderDate($customer),
            'preferred_service_types' => $this->getPreferredServiceTypes($customer),
            'most_shipped_zones' => $this->getMostShippedZones($customer)
        ];
    }

    /**
     * Determine customer tier based on history and spending
     */
    private function determineCustomerTier(Customer $customer): string
    {
        $lifetimeValue = (float) $this->getCustomerLifetimeValue($customer);
        $monthlyOrders = $this->getMonthlyOrderVolume($customer);

        if ($lifetimeValue >= 50000 && $monthlyOrders >= 100) {
            return 'platinum';
        } elseif ($lifetimeValue >= 25000 && $monthlyOrders >= 50) {
            return 'gold';
        } elseif ($lifetimeValue >= 10000 && $monthlyOrders >= 20) {
            return 'silver';
        } elseif ($lifetimeValue >= 2000 && $monthlyOrders >= 5) {
            return 'bronze';
        }

        return 'standard';
    }

    /**
     * Get monthly order volume for customer
     */
    private function getMonthlyOrderVolume(Customer $customer): int
    {
        // TODO: Implement actual query to orders table
        // This is a placeholder implementation
        return rand(1, 50);
    }

    /**
     * Get total lifetime order value for customer
     */
    private function getTotalOrderValue(Customer $customer): string
    {
        // TODO: Implement actual query to orders table
        return number_format(rand(500, 25000), 2, '.', '');
    }

    /**
     * Check if this is customer's first order
     */
    private function isFirstOrder(Customer $customer): bool
    {
        // TODO: Implement actual query to orders table
        return $this->getTotalOrderCount($customer) === 0;
    }

    /**
     * Get total order count for customer
     */
    private function getTotalOrderCount(Customer $customer): int
    {
        // TODO: Implement actual query to orders table
        return rand(0, 100);
    }

    /**
     * Get monthly spending for customer
     */
    private function getMonthlySpending(Customer $customer): string
    {
        // TODO: Implement actual query to orders table
        return number_format(rand(100, 5000), 2, '.', '');
    }

    /**
     * Get customer lifetime value
     */
    private function getCustomerLifetimeValue(Customer $customer): string
    {
        // TODO: Implement actual calculation based on order history
        return number_format(rand(1000, 50000), 2, '.', '');
    }

    /**
     * Get average order value
     */
    private function getAverageOrderValue(Customer $customer): string
    {
        $totalOrders = $this->getTotalOrderCount($customer);
        if ($totalOrders === 0) {
            return '0.00';
        }

        $lifetimeValue = (float) $this->getCustomerLifetimeValue($customer);
        return number_format($lifetimeValue / $totalOrders, 2, '.', '');
    }

    /**
     * Get last order date
     */
    private function getLastOrderDate(Customer $customer): ?string
    {
        // TODO: Implement actual query to orders table
        return (new \DateTime())->modify('-' . rand(1, 30) . ' days')->format('Y-m-d');
    }

    /**
     * Get customer's preferred service types
     */
    private function getPreferredServiceTypes(Customer $customer): array
    {
        // TODO: Implement actual analysis of order history
        return ['express', 'standard', 'economy'];
    }

    /**
     * Get customer's most shipped zones
     */
    private function getMostShippedZones(Customer $customer): array
    {
        // TODO: Implement actual analysis of shipping history
        return ['domestic', 'eu', 'international'];
    }

    /**
     * Get Black Friday specific promotions
     */
    private function getBlackFridayPromotions(): array
    {
        return [
            'BLACK_FRIDAY_25',
            'BLACK_FRIDAY_EXPRESS',
            'BLACK_FRIDAY_VOLUME'
        ];
    }
}