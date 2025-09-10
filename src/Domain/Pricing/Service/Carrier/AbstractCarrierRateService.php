<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service\Carrier;

use App\Domain\Pricing\DTO\ShipmentDTO;
use App\Domain\Pricing\DTO\RateResultDTO;
use App\Domain\Pricing\Exception\ShipmentValidationException;

abstract class AbstractCarrierRateService implements CarrierRateServiceInterface
{
    /**
     * Domyślny mnożnik wagi objętościowej
     */
    protected const VOLUMETRIC_DIVISOR = 5000;

    /**
     * Maksymalna dopuszczalna waga przesyłki w kg
     */
    protected const MAX_WEIGHT = 30.0;

    /**
     * Domyślna implementacja walidacji przesyłki
     */
    public function validateShipment(ShipmentDTO $shipment): bool
    {
        try {
            $this->validateWeight($shipment->weight);
            $this->validateDimensions($shipment->length, $shipment->width, $shipment->height);
            return true;
        } catch (ShipmentValidationException $e) {
            return false;
        }
    }

    /**
     * Walidacja wagi przesyłki
     * 
     * @throws ShipmentValidationException
     */
    protected function validateWeight(float $weight): void
    {
        if ($weight > self::MAX_WEIGHT) {
            throw new ShipmentValidationException(
                sprintf("Przekroczona maksymalna waga przesyłki: %.2f kg (maks. %.2f kg)", 
                    $weight, 
                    self::MAX_WEIGHT
                )
            );
        }
    }

    /**
     * Walidacja wymiarów przesyłki
     * 
     * @throws ShipmentValidationException
     */
    protected function validateDimensions(float $length, float $width, float $height): void
    {
        $maxDimension = 300; // cm
        $sumDimensions = $length + $width + $height;

        if ($length > $maxDimension || $width > $maxDimension || $height > $maxDimension) {
            throw new ShipmentValidationException(
                sprintf("Przekroczone maksymalne wymiary: L:%.0f W:%.0f H:%.0f cm (maks. %d cm)", 
                    $length, $width, $height, $maxDimension
                )
            );
        }

        if ($sumDimensions > 600) {
            throw new ShipmentValidationException(
                sprintf("Suma wymiarów przekracza 600 cm: %.0f cm", $sumDimensions)
            );
        }
    }

    /**
     * Domyślna implementacja wagi objętościowej
     */
    public function calculateVolumetricWeight(float $length, float $width, float $height): float
    {
        return ($length * $width * $height) / self::VOLUMETRIC_DIVISOR;
    }

    /**
     * Domyślna implementacja czasu dostawy (3-5 dni)
     */
    public function getDeliveryTime(ShipmentDTO $shipment): int
    {
        return 4; // Domyślnie 4 dni
    }

    /**
     * Domyślne usługi dodatkowe (pusty zestaw)
     */
    public function getAvailableServices(): array
    {
        return [];
    }

    /**
     * Domyślne usługi dodatkowe (pusty zestaw)
     */
    public function getAdditionalServices(): array
    {
        return [];
    }

    /**
     * Abstrakcyjna metoda do nadpisania przez konkretnych przewoźników
     */
    abstract public function calculateRate(ShipmentDTO $shipment): RateResultDTO;
}