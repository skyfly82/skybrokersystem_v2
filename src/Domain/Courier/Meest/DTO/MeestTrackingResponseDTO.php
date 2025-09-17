<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\DTO;

use App\Domain\Courier\Meest\Enum\MeestTrackingStatus;

/**
 * DTO for MEEST tracking response
 */
readonly class MeestTrackingResponseDTO
{
    public function __construct(
        public string $trackingNumber,
        public MeestTrackingStatus $status,
        public string $statusDescription,
        public \DateTimeImmutable $lastUpdated,
        public ?string $location = null,
        public ?\DateTimeImmutable $estimatedDelivery = null,
        public ?\DateTimeImmutable $deliveredAt = null,
        public ?string $signedBy = null,
        public array $trackingEvents = [],
        public ?array $metadata = null
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === MeestTrackingStatus::DELIVERED;
    }

    public function hasIssue(): bool
    {
        return $this->status->hasIssue();
    }

    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    public function getLatestEvent(): ?array
    {
        return empty($this->trackingEvents) ? null : end($this->trackingEvents);
    }

    public static function fromApiResponse(array $response): self
    {
        $status = MeestTrackingStatus::fromApiStatus($response['status'] ?? 'unknown')
                 ?? MeestTrackingStatus::CREATED;

        $lastUpdated = isset($response['last_updated'])
            ? new \DateTimeImmutable($response['last_updated'])
            : new \DateTimeImmutable();

        $estimatedDelivery = isset($response['estimated_delivery'])
            ? new \DateTimeImmutable($response['estimated_delivery'])
            : null;

        $deliveredAt = isset($response['delivered_at'])
            ? new \DateTimeImmutable($response['delivered_at'])
            : null;

        $trackingEvents = array_map(
            fn(array $event) => [
                'timestamp' => new \DateTimeImmutable($event['timestamp']),
                'status' => $event['status'],
                'description' => $event['description'],
                'location' => $event['location'] ?? null,
            ],
            $response['tracking_events'] ?? []
        );

        return new self(
            trackingNumber: $response['tracking_number'] ?? '',
            status: $status,
            statusDescription: $response['status_description'] ?? $status->getDescription(),
            lastUpdated: $lastUpdated,
            location: $response['location'] ?? null,
            estimatedDelivery: $estimatedDelivery,
            deliveredAt: $deliveredAt,
            signedBy: $response['signed_by'] ?? null,
            trackingEvents: $trackingEvents,
            metadata: $response['metadata'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status->value,
            'status_description' => $this->statusDescription,
            'last_updated' => $this->lastUpdated->format('Y-m-d H:i:s'),
            'location' => $this->location,
            'estimated_delivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->deliveredAt?->format('Y-m-d H:i:s'),
            'signed_by' => $this->signedBy,
            'tracking_events' => array_map(
                fn(array $event) => [
                    'timestamp' => $event['timestamp']->format('Y-m-d H:i:s'),
                    'status' => $event['status'],
                    'description' => $event['description'],
                    'location' => $event['location'],
                ],
                $this->trackingEvents
            ),
            'metadata' => $this->metadata
        ];
    }
}