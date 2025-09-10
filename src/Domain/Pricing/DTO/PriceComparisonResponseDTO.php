<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTO;

/**
 * Response DTO for price comparison across carriers
 */
class PriceComparisonResponseDTO
{
    public PriceComparisonRequestDTO $request;

    /**
     * @var PriceCalculationResponseDTO[]
     */
    public array $prices = [];

    /**
     * @var array
     */
    public array $unavailableCarriers = [];

    public int $totalCarriersChecked = 0;
    public int $availableCarriersCount = 0;
    public \DateTimeImmutable $calculatedAt;

    public function __construct(PriceComparisonRequestDTO $request)
    {
        $this->request = $request;
        $this->calculatedAt = new \DateTimeImmutable();
    }

    public function addPrice(PriceCalculationResponseDTO $price): void
    {
        $this->prices[] = $price;
        $this->availableCarriersCount++;
    }

    public function addUnavailableCarrier(string $carrierCode, string $reason): void
    {
        $this->unavailableCarriers[] = [
            'carrier_code' => $carrierCode,
            'reason' => $reason,
        ];
    }

    public function setTotalCarriersChecked(int $total): void
    {
        $this->totalCarriersChecked = $total;
    }

    public function getBestPrice(): ?PriceCalculationResponseDTO
    {
        if (empty($this->prices)) {
            return null;
        }

        return array_reduce($this->prices, function (?PriceCalculationResponseDTO $best, PriceCalculationResponseDTO $current) {
            if ($best === null) {
                return $current;
            }

            return bccomp($current->totalPrice, $best->totalPrice, 2) < 0 ? $current : $best;
        });
    }

    public function getCheapestPrice(): ?PriceCalculationResponseDTO
    {
        return $this->getBestPrice();
    }

    public function getMostExpensivePrice(): ?PriceCalculationResponseDTO
    {
        if (empty($this->prices)) {
            return null;
        }

        return array_reduce($this->prices, function (?PriceCalculationResponseDTO $highest, PriceCalculationResponseDTO $current) {
            if ($highest === null) {
                return $current;
            }

            return bccomp($current->totalPrice, $highest->totalPrice, 2) > 0 ? $current : $highest;
        });
    }

    public function getAveragePrice(): ?string
    {
        if (empty($this->prices)) {
            return null;
        }

        $total = array_reduce($this->prices, function (string $sum, PriceCalculationResponseDTO $price) {
            return bcadd($sum, $price->totalPrice, 2);
        }, '0.00');

        return bcdiv($total, (string)count($this->prices), 2);
    }

    public function getPricesByCarrier(): array
    {
        $result = [];
        foreach ($this->prices as $price) {
            $result[$price->carrierCode] = $price;
        }
        return $result;
    }

    public function getPriceForCarrier(string $carrierCode): ?PriceCalculationResponseDTO
    {
        foreach ($this->prices as $price) {
            if ($price->carrierCode === strtoupper($carrierCode)) {
                return $price;
            }
        }
        return null;
    }

    public function sortPricesByTotal(string $direction = 'asc'): void
    {
        usort($this->prices, function (PriceCalculationResponseDTO $a, PriceCalculationResponseDTO $b) use ($direction) {
            $comparison = bccomp($a->totalPrice, $b->totalPrice, 2);
            return $direction === 'desc' ? -$comparison : $comparison;
        });
    }

    public function getSavingsPotential(): ?array
    {
        $cheapest = $this->getCheapestPrice();
        $mostExpensive = $this->getMostExpensivePrice();

        if ($cheapest === null || $mostExpensive === null || $cheapest === $mostExpensive) {
            return null;
        }

        $savings = bcsub($mostExpensive->totalPrice, $cheapest->totalPrice, 2);
        $savingsPercentage = bcdiv(
            bcmul($savings, '100', 2),
            $mostExpensive->totalPrice,
            2
        );

        return [
            'cheapest_carrier' => $cheapest->carrierCode,
            'cheapest_price' => $cheapest->totalPrice,
            'most_expensive_carrier' => $mostExpensive->carrierCode,
            'most_expensive_price' => $mostExpensive->totalPrice,
            'savings_amount' => $savings,
            'savings_percentage' => $savingsPercentage,
            'currency' => $cheapest->currency,
        ];
    }

    public function toArray(): array
    {
        return [
            'request' => [
                'zone_code' => $this->request->zoneCode,
                'service_type' => $this->request->serviceType,
                'weight_kg' => $this->request->weightKg,
                'dimensions_cm' => $this->request->dimensionsCm,
                'currency' => $this->request->currency,
                'additional_services' => $this->request->additionalServices,
                'customer_id' => $this->request->customerId,
            ],
            'prices' => array_map(fn(PriceCalculationResponseDTO $price) => $price->toArray(), $this->prices),
            'unavailable_carriers' => $this->unavailableCarriers,
            'statistics' => [
                'total_carriers_checked' => $this->totalCarriersChecked,
                'available_carriers_count' => $this->availableCarriersCount,
                'average_price' => $this->getAveragePrice(),
                'savings_potential' => $this->getSavingsPotential(),
            ],
            'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}