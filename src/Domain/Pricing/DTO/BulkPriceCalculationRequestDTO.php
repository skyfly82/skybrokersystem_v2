<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for bulk price calculation
 */
class BulkPriceCalculationRequestDTO
{
    /**
     * @var PriceCalculationRequestDTO[]
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: 100)]
    public array $requests = [];

    public ?int $customerId = null;

    #[Assert\Length(exactly: 3)]
    public string $currency = 'PLN';

    public bool $stopOnFirstError = false;

    public bool $calculateTotalSavings = true;

    /**
     * Optional bulk discount configuration
     */
    public ?array $bulkDiscountConfig = null;

    public function __construct(array $requests = [], string $currency = 'PLN')
    {
        $this->requests = $requests;
        $this->currency = strtoupper($currency);
    }

    public function addRequest(PriceCalculationRequestDTO $request): void
    {
        $this->requests[] = $request;
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }

    public function getTotalWeight(): float
    {
        return array_sum(array_map(fn(PriceCalculationRequestDTO $request) => $request->weightKg, $this->requests));
    }

    public function getTotalChargeableWeight(): float
    {
        return array_sum(array_map(fn(PriceCalculationRequestDTO $request) => $request->getChargeableWeight(), $this->requests));
    }

    public function getUniqueCarriers(): array
    {
        $carriers = array_map(fn(PriceCalculationRequestDTO $request) => $request->carrierCode, $this->requests);
        return array_unique($carriers);
    }

    public function getUniqueZones(): array
    {
        $zones = array_map(fn(PriceCalculationRequestDTO $request) => $request->zoneCode, $this->requests);
        return array_unique($zones);
    }

    public function getRequestsByCarrier(string $carrierCode): array
    {
        return array_filter(
            $this->requests,
            fn(PriceCalculationRequestDTO $request) => $request->carrierCode === strtoupper($carrierCode)
        );
    }

    public function getRequestsByZone(string $zoneCode): array
    {
        return array_filter(
            $this->requests,
            fn(PriceCalculationRequestDTO $request) => $request->zoneCode === strtoupper($zoneCode)
        );
    }

    public function hasBulkDiscount(): bool
    {
        return $this->bulkDiscountConfig !== null;
    }

    public function getBulkDiscountThreshold(): ?int
    {
        return $this->bulkDiscountConfig['threshold'] ?? null;
    }

    public function getBulkDiscountPercentage(): ?float
    {
        return $this->bulkDiscountConfig['percentage'] ?? null;
    }

    public function setBulkDiscount(int $threshold, float $percentage): void
    {
        $this->bulkDiscountConfig = [
            'threshold' => $threshold,
            'percentage' => $percentage,
        ];
    }

    public function qualifiesForBulkDiscount(): bool
    {
        return $this->hasBulkDiscount() && 
               $this->getRequestCount() >= $this->getBulkDiscountThreshold();
    }
}