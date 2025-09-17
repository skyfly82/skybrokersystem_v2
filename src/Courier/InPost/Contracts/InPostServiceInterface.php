<?php

declare(strict_types=1);

namespace App\Courier\InPost\Contracts;

use App\Courier\InPost\DTO\InPostShipmentRequestDTO;
use App\Courier\InPost\DTO\InPostShipmentResponseDTO;
use App\Courier\InPost\DTO\LockerDetailsDTO;
use App\Domain\Courier\DTO\TrackingDetailsDTO;
use App\Courier\InPost\Exception\InPostIntegrationException;

interface InPostServiceInterface
{
    /**
     * Create shipment in InPost system
     *
     * @throws InPostIntegrationException
     */
    public function createInPostShipment(InPostShipmentRequestDTO $request): InPostShipmentResponseDTO;

    /**
     * Get shipment tracking details
     *
     * @throws InPostIntegrationException
     */
    public function getTrackingDetails(string $trackingNumber): TrackingDetailsDTO;

    /**
     * Get available Paczkomaty within radius
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $radiusKm
     * @param string|null $parcelSize Filter by parcel size support
     * @return LockerDetailsDTO[]
     * @throws InPostIntegrationException
     */
    public function findNearbyPaczkomaty(
        float $latitude,
        float $longitude,
        int $radiusKm = 5,
        ?string $parcelSize = null
    ): array;

    /**
     * Get specific Paczkomat details
     *
     * @throws InPostIntegrationException
     */
    public function getPaczkomatDetails(string $paczkomatCode): LockerDetailsDTO;

    /**
     * Validate Polish postal code
     */
    public function validatePolishPostalCode(string $postalCode): bool;

    /**
     * Validate Paczkomat code format
     */
    public function validatePaczkomatCode(string $code): bool;

    /**
     * Generate shipping label for InPost shipment
     *
     * @param string $trackingNumber
     * @param string $format 'pdf' or 'zpl'
     * @return string Base64 encoded label or file path
     * @throws InPostIntegrationException
     */
    public function generateInPostLabel(string $trackingNumber, string $format = 'pdf'): string;

    /**
     * Cancel shipment
     *
     * @throws InPostIntegrationException
     */
    public function cancelShipment(string $trackingNumber): bool;

    /**
     * Get shipment delivery status
     *
     * @throws InPostIntegrationException
     */
    public function getDeliveryStatus(string $trackingNumber): array;

    /**
     * Bulk create shipments
     *
     * @param InPostShipmentRequestDTO[] $requests
     * @return InPostShipmentResponseDTO[]
     * @throws InPostIntegrationException
     */
    public function createBulkShipments(array $requests): array;

    /**
     * Get shipment cost estimate
     *
     * @throws InPostIntegrationException
     */
    public function getShipmentCost(InPostShipmentRequestDTO $request): float;
}