<?php

declare(strict_types=1);

namespace App\Domain\Courier\DTO;

use DateTimeImmutable;

class ShipmentResponseDTO
{
    public function __construct(
        public string $trackingNumber,
        public ?string $labelUrl = null,
        public float $cost = 0.0,
        public string $currency = 'EUR',
        public ?DateTimeImmutable $estimatedDelivery = null,
        public string $shipmentId = '',
        public string $status = 'pending'
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            trackingNumber: $data['trackingNumber'] ?? '',
            labelUrl: $data['labelUrl'] ?? null,
            cost: (float) ($data['cost'] ?? 0.0),
            currency: $data['currency'] ?? 'EUR',
            estimatedDelivery: isset($data['estimatedDelivery'])
                ? new DateTimeImmutable($data['estimatedDelivery'])
                : null,
            shipmentId: $data['shipmentId'] ?? '',
            status: $data['status'] ?? 'pending'
        );
    }

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'shipmentId' => $this->shipmentId,
            'status' => $this->status,
            'labelUrl' => $this->labelUrl,
            'cost' => $this->cost,
            'currency' => $this->currency,
            'estimatedDelivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s')
        ];
    }
}