<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\DTO;

use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;

/**
 * DTO for MEEST shipment creation response
 */
readonly class MeestShipmentResponseDTO
{
    public function __construct(
        public string $trackingNumber,
        public string $shipmentId,
        public MeestTrackingStatus $status,
        public float $totalCost,
        public string $currency,
        public string $labelUrl,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $estimatedDelivery = null,
        public ?array $metadata = null
    ) {}

    public function isSuccessful(): bool
    {
        return !empty($this->trackingNumber) && !empty($this->shipmentId);
    }

    public function hasLabel(): bool
    {
        return !empty($this->labelUrl);
    }

    public static function fromApiResponse(array $response): self
    {
        $status = MeestTrackingStatus::fromApiStatus($response['status'] ?? 'created')
                 ?? MeestTrackingStatus::CREATED;

        $createdAt = isset($response['created_at'])
            ? new \DateTimeImmutable($response['created_at'])
            : new \DateTimeImmutable();

        $estimatedDelivery = isset($response['estimated_delivery'])
            ? new \DateTimeImmutable($response['estimated_delivery'])
            : null;

        return new self(
            trackingNumber: $response['tracking_number'] ?? '',
            shipmentId: $response['shipment_id'] ?? $response['id'] ?? '',
            status: $status,
            totalCost: (float) ($response['total_cost'] ?? 0.0),
            currency: $response['currency'] ?? 'EUR',
            labelUrl: $response['label_url'] ?? '',
            createdAt: $createdAt,
            estimatedDelivery: $estimatedDelivery,
            metadata: $response['metadata'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'shipment_id' => $this->shipmentId,
            'status' => $this->status->value,
            'total_cost' => $this->totalCost,
            'currency' => $this->currency,
            'label_url' => $this->labelUrl,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'estimated_delivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata
        ];
    }
}