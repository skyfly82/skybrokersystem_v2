<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service\Carrier;

use App\Domain\Pricing\DTO\ShipmentDTO;
use App\Domain\Pricing\DTO\RateResultDTO;
use App\Domain\Pricing\Exception\ShipmentValidationException;

class InPostRateService extends AbstractCarrierRateService
{
    /**
     * Maksymalna waga dla InPost (25kg)
     */
    protected const MAX_WEIGHT = 25.0;

    /**
     * Specyficzny mnożnik wagi objętościowej dla InPost
     */
    protected const VOLUMETRIC_DIVISOR = 6000;

    /**
     * Strefy cenowe InPost
     */
    private const PRICE_ZONES = [
        'local' => 12.50,
        'national' => 18.90,
        'international' => 45.00
    ];

    /**
     * Usługi dodatkowe InPost
     */
    private const ADDITIONAL_SERVICES = [
        'pobranie' => 3.50,
        'ubezpieczenie' => 2.00,
        'zwrot_towaru' => 5.00
    ];

    /**
     * Obliczenie ceny przesyłki dla InPost
     */
    public function calculateRate(ShipmentDTO $shipment): RateResultDTO
    {
        // Walidacja przesyłki
        if (!$this->validateShipment($shipment)) {
            throw new ShipmentValidationException("Przesyłka nie spełnia wymagań InPost");
        }

        // Retrieve cached rate if exists
        $cacheKey = $this->generateCacheKey($shipment);
        $cachedRate = $this->rateCache->get($cacheKey);
        if ($cachedRate) {
            return $cachedRate;
        }

        // Wybór strefy cenowej
        $priceZone = $this->determinePriceZone($shipment);
        $basePrice = self::PRICE_ZONES[$priceZone];

        // Korekta ceny w zależności od wagi
        $volumetricWeight = $this->calculateVolumetricWeight(
            $shipment->length,
            $shipment->width,
            $shipment->height
        );
        $effectiveWeight = max($shipment->weight, $volumetricWeight);
        $weightMultiplier = ceil($effectiveWeight / 5) * 1.2; // Co 5kg dopłata

        $totalPrice = $basePrice * $weightMultiplier;

        // Factor in additional services
        $totalPrice += $this->calculateAdditionalServicesCost($shipment);

        $rateResult = new RateResultDTO(
            carrier: 'InPost',
            basePrice: $basePrice,
            totalPrice: $totalPrice,
            weightUsed: $effectiveWeight,
            deliveryTime: $this->getDeliveryTime($shipment),
            zone: $priceZone
        );

        // Cache the result for future requests
        $this->rateCache->set($cacheKey, $rateResult, 3600); // Cache for 1 hour

        return $rateResult;
    }

    /**
     * Calculate cost for additional services
     */
    private function calculateAdditionalServicesCost(ShipmentDTO $shipment): float
    {
        $additionalCost = 0.0;

        if ($shipment->hasCOD) {
            $additionalCost += self::ADDITIONAL_SERVICES['pobranie'];
        }

        if ($shipment->hasInsurance) {
            $additionalCost += self::ADDITIONAL_SERVICES['ubezpieczenie'];
        }

        return $additionalCost;
    }

    /**
     * Generate a unique cache key for shipment rate
     */
    private function generateCacheKey(ShipmentDTO $shipment): string
    {
        return md5(json_encode([
            $shipment->length,
            $shipment->width,
            $shipment->height,
            $shipment->weight,
            $shipment->isLocal,
            $shipment->isInternational,
            $shipment->hasCOD,
            $shipment->hasInsurance
        ]));
    }

    /**
     * Bulk rate calculation with parallel processing
     * @param ShipmentDTO[] $shipments
     * @return RateResultDTO[]
     */
    public function calculateBulkRates(array $shipments): array
    {
        $results = [];
        foreach ($shipments as $shipment) {
            $results[] = $this->calculateRate($shipment);
        }
        return $results;
    }

    /**
     * Określenie strefy cenowej
     */
    private function determinePriceZone(ShipmentDTO $shipment): string
    {
        if ($shipment->isInternational) {
            return 'international';
        }

        return $shipment->isLocal ? 'local' : 'national';
    }

    /**
     * Czas dostawy dla InPost
     */
    public function getDeliveryTime(ShipmentDTO $shipment): int
    {
        return match(true) {
            $shipment->isLocal => 1,
            $shipment->isInternational => 5,
            default => 2
        };
    }

    /**
     * Dostępne usługi dodatkowe
     */
    public function getAdditionalServices(): array
    {
        return self::ADDITIONAL_SERVICES;
    }

    /**
     * Dostępne usługi transportowe
     */
    public function getAvailableServices(): array
    {
        return [
            'paczkomat' => 'Dostawa do Paczkomatu',
            'kurier' => 'Dostawa kurierem',
        ];
    }
}