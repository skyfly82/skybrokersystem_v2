<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Service\Carrier;

use App\Domain\Pricing\DTO\ShipmentDTO;
use App\Domain\Pricing\DTO\RateResultDTO;

interface CarrierRateServiceInterface
{
    /**
     * Oblicza cenę przesyłki dla danego przewoźnika
     * 
     * @param ShipmentDTO $shipment Dane przesyłki
     * @return RateResultDTO Wynik kalkulacji ceny
     */
    public function calculateRate(ShipmentDTO $shipment): RateResultDTO;

    /**
     * Oblicza wagę objętościową zgodnie z regułami przewoźnika
     * 
     * @param float $length Długość paczki w cm
     * @param float $width Szerokość paczki w cm 
     * @param float $height Wysokość paczki w cm
     * @return float Waga objętościowa w kg
     */
    public function calculateVolumetricWeight(float $length, float $width, float $height): float;

    /**
     * Zwraca dostępne usługi dla przewoźnika
     * 
     * @return array Lista dostępnych usług
     */
    public function getAvailableServices(): array;

    /**
     * Waliduje poprawność przesyłki zgodnie z ograniczeniami przewoźnika
     * 
     * @param ShipmentDTO $shipment Dane przesyłki
     * @return bool Czy przesyłka spełnia wymagania
     */
    public function validateShipment(ShipmentDTO $shipment): bool;

    /**
     * Szacuje czas dostawy
     * 
     * @param ShipmentDTO $shipment Dane przesyłki
     * @return int Szacowany czas dostawy w dniach
     */
    public function getDeliveryTime(ShipmentDTO $shipment): int;

    /**
     * Zwraca dostępne usługi dodatkowe
     * 
     * @return array Lista usług dodatkowych
     */
    public function getAdditionalServices(): array;
}