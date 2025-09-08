<?php

declare(strict_types=1);

namespace App\Domain\Courier\Contracts;

use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\DTO\ShipmentResponseDTO;
use App\Domain\Courier\DTO\TrackingDetailsDTO;
use App\Domain\Courier\Exception\CourierIntegrationException;

interface CourierIntegrationInterface
{
    /**
     * Create a new shipment with the courier
     *
     * @throws CourierIntegrationException
     */
    public function createShipment(ShipmentRequestDTO $shipmentRequest): ShipmentResponseDTO;

    /**
     * Get tracking details for a shipment
     *
     * @throws CourierIntegrationException
     */
    public function getTrackingDetails(string $trackingNumber): TrackingDetailsDTO;

    /**
     * Generate a tracking number
     */
    public function generateTrackingNumber(): string;

    /**
     * Validate a tracking number format
     */
    public function validateTrackingNumber(string $trackingNumber): bool;

    /**
     * Generate shipping label
     *
     * @throws CourierIntegrationException
     * @return string PDF file path or base64 encoded label
     */
    public function generateLabel(string $trackingNumber): string;

    /**
     * Process webhook callback from courier
     */
    public function processWebhook(array $payload): bool;
}