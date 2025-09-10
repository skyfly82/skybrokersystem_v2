<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

/**
 * Response DTO for bulk price calculation
 */
class BulkPriceCalculationResponseDTO
{
    public BulkPriceCalculationRequestDTO $request;

    /**
     * @var PriceCalculationResponseDTO[]
     */
    public array $prices = [];

    /**
     * @var array
     */
    public array $errors = [];

    public int $totalRequests = 0;
    public int $successfulCalculations = 0;
    public int $failedCalculations = 0;

    public string $totalAmount = '0.00';
    public string $totalBaseAmount = '0.00';
    public string $totalAdditionalServicesAmount = '0.00';
    public string $totalTaxAmount = '0.00';
    public string $currency = 'PLN';

    public ?string $bulkDiscountAmount = null;
    public ?float $bulkDiscountPercentage = null;
    public bool $bulkDiscountApplied = false;

    public \DateTimeImmutable $calculatedAt;
    public ?float $calculationTimeMs = null;

    public function __construct(BulkPriceCalculationRequestDTO $request)
    {
        $this->request = $request;
        $this->currency = $request->currency;
        $this->totalRequests = $request->getRequestCount();
        $this->calculatedAt = new \DateTimeImmutable();
    }

    public function addPrice(PriceCalculationResponseDTO $price): void
    {
        $this->prices[] = $price;
        $this->successfulCalculations++;
        $this->recalculateTotals();
    }

    public function addError(int $requestIndex, string $error): void
    {
        $this->errors[] = [
            'request_index' => $requestIndex,
            'error' => $error,
        ];
        $this->failedCalculations++;
    }

    public function setCalculationTime(float $timeMs): void
    {
        $this->calculationTimeMs = $timeMs;
    }

    public function applyBulkDiscount(): void
    {
        if (!$this->request->qualifiesForBulkDiscount()) {
            return;
        }

        $this->bulkDiscountPercentage = $this->request->getBulkDiscountPercentage();
        $this->bulkDiscountAmount = bcmul(
            $this->totalAmount,
            (string)($this->bulkDiscountPercentage / 100),
            2
        );

        $this->totalAmount = bcsub($this->totalAmount, $this->bulkDiscountAmount, 2);
        $this->bulkDiscountApplied = true;
    }

    private function recalculateTotals(): void
    {
        $this->totalAmount = '0.00';
        $this->totalBaseAmount = '0.00';
        $this->totalAdditionalServicesAmount = '0.00';
        $this->totalTaxAmount = '0.00';

        foreach ($this->prices as $price) {
            $this->totalAmount = bcadd($this->totalAmount, $price->totalPrice, 2);
            $this->totalBaseAmount = bcadd($this->totalBaseAmount, $price->basePrice, 2);
            $this->totalAdditionalServicesAmount = bcadd($this->totalAdditionalServicesAmount, $price->additionalServicesPrice, 2);
            $this->totalTaxAmount = bcadd($this->totalTaxAmount, $price->taxAmount, 2);
        }
    }

    public function getSuccessRate(): float
    {
        if ($this->totalRequests === 0) {
            return 0.0;
        }

        return ($this->successfulCalculations / $this->totalRequests) * 100;
    }

    public function getFailureRate(): float
    {
        return 100.0 - $this->getSuccessRate();
    }

    public function getAveragePrice(): ?string
    {
        if ($this->successfulCalculations === 0) {
            return null;
        }

        return bcdiv($this->totalAmount, (string)$this->successfulCalculations, 2);
    }

    public function getPricesByCarrier(): array
    {
        $result = [];
        foreach ($this->prices as $price) {
            if (!isset($result[$price->carrierCode])) {
                $result[$price->carrierCode] = [
                    'prices' => [],
                    'total_amount' => '0.00',
                    'count' => 0,
                ];
            }
            
            $result[$price->carrierCode]['prices'][] = $price;
            $result[$price->carrierCode]['total_amount'] = bcadd(
                $result[$price->carrierCode]['total_amount'],
                $price->totalPrice,
                2
            );
            $result[$price->carrierCode]['count']++;
        }

        return $result;
    }

    public function getPricesByZone(): array
    {
        $result = [];
        foreach ($this->prices as $price) {
            if (!isset($result[$price->zoneCode])) {
                $result[$price->zoneCode] = [
                    'prices' => [],
                    'total_amount' => '0.00',
                    'count' => 0,
                ];
            }
            
            $result[$price->zoneCode]['prices'][] = $price;
            $result[$price->zoneCode]['total_amount'] = bcadd(
                $result[$price->zoneCode]['total_amount'],
                $price->totalPrice,
                2
            );
            $result[$price->zoneCode]['count']++;
        }

        return $result;
    }

    public function getSavingsSummary(): array
    {
        if (empty($this->prices)) {
            return [];
        }

        $totalSavings = '0.00';
        $totalCustomerDiscounts = '0.00';
        $totalPromotionalDiscounts = '0.00';

        foreach ($this->prices as $price) {
            if ($price->customerDiscount !== null) {
                $totalCustomerDiscounts = bcadd($totalCustomerDiscounts, $price->customerDiscount, 2);
            }
            if ($price->promotionalDiscount !== null) {
                $totalPromotionalDiscounts = bcadd($totalPromotionalDiscounts, $price->promotionalDiscount, 2);
            }
        }

        $totalSavings = bcadd($totalCustomerDiscounts, $totalPromotionalDiscounts, 2);

        if ($this->bulkDiscountApplied && $this->bulkDiscountAmount) {
            $totalSavings = bcadd($totalSavings, $this->bulkDiscountAmount, 2);
        }

        return [
            'total_savings' => $totalSavings,
            'customer_discounts' => $totalCustomerDiscounts,
            'promotional_discounts' => $totalPromotionalDiscounts,
            'bulk_discount' => $this->bulkDiscountAmount ?? '0.00',
            'bulk_discount_applied' => $this->bulkDiscountApplied,
            'currency' => $this->currency,
        ];
    }

    public function toArray(): array
    {
        return [
            'request_summary' => [
                'total_requests' => $this->totalRequests,
                'unique_carriers' => $this->request->getUniqueCarriers(),
                'unique_zones' => $this->request->getUniqueZones(),
                'total_weight_kg' => $this->request->getTotalWeight(),
                'total_chargeable_weight_kg' => $this->request->getTotalChargeableWeight(),
            ],
            'results' => [
                'successful_calculations' => $this->successfulCalculations,
                'failed_calculations' => $this->failedCalculations,
                'success_rate' => $this->getSuccessRate(),
                'failure_rate' => $this->getFailureRate(),
            ],
            'pricing' => [
                'total_amount' => $this->totalAmount,
                'total_base_amount' => $this->totalBaseAmount,
                'total_additional_services_amount' => $this->totalAdditionalServicesAmount,
                'total_tax_amount' => $this->totalTaxAmount,
                'average_price' => $this->getAveragePrice(),
                'currency' => $this->currency,
            ],
            'bulk_discount' => [
                'applied' => $this->bulkDiscountApplied,
                'percentage' => $this->bulkDiscountPercentage,
                'amount' => $this->bulkDiscountAmount,
            ],
            'savings' => $this->getSavingsSummary(),
            'prices' => array_map(fn(PriceCalculationResponseDTO $price) => $price->toArray(), $this->prices),
            'errors' => $this->errors,
            'performance' => [
                'calculation_time_ms' => $this->calculationTimeMs,
                'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
            ],
        ];
    }
}