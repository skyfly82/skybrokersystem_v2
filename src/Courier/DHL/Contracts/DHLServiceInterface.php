<?php

declare(strict_types=1);

namespace App\Courier\DHL\Contracts;

use App\Courier\DHL\DTO\DHLShipmentRequestDTO;
use App\Courier\DHL\DTO\DHLShipmentResponseDTO;

interface DHLServiceInterface
{
    /**
     * Create a DHL shipment
     */
    public function createDHLShipment(DHLShipmentRequestDTO $request): DHLShipmentResponseDTO;

    /**
     * Get DHL service availability for given route
     */
    public function getServiceAvailability(string $originCountry, string $destinationCountry): array;

    /**
     * Calculate shipping rates for DHL services
     */
    public function calculateRates(DHLShipmentRequestDTO $request): array;

    /**
     * Validate DHL shipping address
     */
    public function validateAddress(array $address): bool;

    /**
     * Get available DHL services for route
     */
    public function getAvailableServices(string $originCountry, string $destinationCountry): array;

    /**
     * Get delivery time estimates
     */
    public function getDeliveryEstimate(string $originCountry, string $destinationCountry, string $serviceCode): ?int;

    /**
     * Check if international shipping is supported
     */
    public function supportsInternationalShipping(): bool;
}