<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use App\Entity\Customer;

/**
 * Context for pricing rule calculations
 * 
 * Contains all necessary information for rule engine to:
 * - Calculate appropriate pricing
 * - Apply discounts and promotions
 * - Consider customer history and preferences
 */
final readonly class RuleContext
{
    public function __construct(
        public float $weightKg,
        public float $lengthCm,
        public float $widthCm,
        public float $heightCm,
        public string $serviceType,
        public string $zoneCode,
        public string $basePrice,
        public ?Customer $customer = null,
        public ?\DateTimeInterface $calculationDate = null,
        public ?string $currencyCode = 'PLN',
        public ?array $customerHistory = null,
        public ?array $additionalData = null,
        public bool $isBusinessCustomer = false,
        public ?string $customerTier = null,
        public ?int $monthlyOrderVolume = null,
        public ?string $totalOrderValue = null,
        public bool $isFirstOrder = true,
        public bool $isReturningCustomer = false,
        public ?string $seasonalPeriod = null,
        public ?array $eligiblePromotions = null
    ) {
    }

    /**
     * Create context from basic shipment data
     */
    public static function fromShipmentData(
        float $weightKg,
        float $lengthCm,
        float $widthCm,
        float $heightCm,
        string $serviceType,
        string $zoneCode,
        string $basePrice,
        ?Customer $customer = null
    ): self {
        $calculationDate = new \DateTimeImmutable();
        $seasonalPeriod = self::determineSeason($calculationDate);

        return new self(
            weightKg: $weightKg,
            lengthCm: $lengthCm,
            widthCm: $widthCm,
            heightCm: $heightCm,
            serviceType: $serviceType,
            zoneCode: $zoneCode,
            basePrice: $basePrice,
            customer: $customer,
            calculationDate: $calculationDate,
            isBusinessCustomer: $customer?->isBusiness() ?? false,
            seasonalPeriod: $seasonalPeriod
        );
    }

    /**
     * Create context with customer history
     */
    public static function withCustomerHistory(
        self $context,
        array $customerHistory,
        ?string $customerTier = null,
        ?int $monthlyOrderVolume = null,
        ?string $totalOrderValue = null,
        bool $isFirstOrder = true,
        bool $isReturningCustomer = false
    ): self {
        return new self(
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
            customerHistory: $customerHistory,
            additionalData: $context->additionalData,
            isBusinessCustomer: $context->isBusinessCustomer,
            customerTier: $customerTier,
            monthlyOrderVolume: $monthlyOrderVolume,
            totalOrderValue: $totalOrderValue,
            isFirstOrder: $isFirstOrder,
            isReturningCustomer: $isReturningCustomer,
            seasonalPeriod: $context->seasonalPeriod,
            eligiblePromotions: $context->eligiblePromotions
        );
    }

    /**
     * Get volumetric weight calculation
     */
    public function getVolumetricWeight(float $divisor = 5000.0): float
    {
        return ($this->lengthCm * $this->widthCm * $this->heightCm) / $divisor;
    }

    /**
     * Get chargeable weight (max of actual and volumetric)
     */
    public function getChargeableWeight(float $divisor = 5000.0): float
    {
        return max($this->weightKg, $this->getVolumetricWeight($divisor));
    }

    /**
     * Check if package is oversized
     */
    public function isOversized(float $maxLength = 120, float $maxWidth = 80, float $maxHeight = 80): bool
    {
        return $this->lengthCm > $maxLength || 
               $this->widthCm > $maxWidth || 
               $this->heightCm > $maxHeight;
    }

    /**
     * Get total volume in cubic cm
     */
    public function getVolumeCm3(): float
    {
        return $this->lengthCm * $this->widthCm * $this->heightCm;
    }

    /**
     * Get customer monthly spending
     */
    public function getCustomerMonthlySpending(): string
    {
        if ($this->customerHistory === null) {
            return '0.00';
        }

        return $this->customerHistory['monthly_spending'] ?? '0.00';
    }

    /**
     * Get customer order count for current month
     */
    public function getCustomerMonthlyOrderCount(): int
    {
        if ($this->customerHistory === null) {
            return 0;
        }

        return $this->customerHistory['monthly_order_count'] ?? 0;
    }

    /**
     * Get customer lifetime value
     */
    public function getCustomerLifetimeValue(): string
    {
        if ($this->customerHistory === null) {
            return '0.00';
        }

        return $this->customerHistory['lifetime_value'] ?? '0.00';
    }

    /**
     * Determine seasonal period from date
     */
    private static function determineSeason(\DateTimeInterface $date): string
    {
        $month = (int)$date->format('n');
        $day = (int)$date->format('j');

        // Holiday seasons
        if (($month === 11 && $day >= 20) || $month === 12 || ($month === 1 && $day <= 7)) {
            return 'christmas';
        }

        if ($month === 11 && $day >= 24 && $day <= 30) {
            return 'black_friday';
        }

        // Regular seasons
        return match ($month) {
            12, 1, 2 => 'winter',
            3, 4, 5 => 'spring',
            6, 7, 8 => 'summer',
            9, 10, 11 => 'autumn',
            default => 'regular'
        };
    }

    /**
     * Check if it's Black Friday period
     */
    public function isBlackFridayPeriod(): bool
    {
        return $this->seasonalPeriod === 'black_friday';
    }

    /**
     * Check if it's Christmas period
     */
    public function isChristmasPeriod(): bool
    {
        return $this->seasonalPeriod === 'christmas';
    }

    /**
     * Check if customer qualifies for volume discounts
     */
    public function qualifiesForVolumeDiscount(int $minMonthlyOrders = 10, string $minMonthlySpending = '1000.00'): bool
    {
        return $this->getCustomerMonthlyOrderCount() >= $minMonthlyOrders &&
               bccomp($this->getCustomerMonthlySpending(), $minMonthlySpending, 2) >= 0;
    }
}