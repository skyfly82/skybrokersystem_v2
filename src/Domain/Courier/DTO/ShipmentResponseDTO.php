<?php

declare(strict_types=1);

namespace App\Domain\Courier\DTO;

use DateTimeImmutable;

class ShipmentResponseDTO
{
    public string $trackingNumber;
    public string $shipmentId;
    public string $status;
    public ?string $labelUrl;
    public ?DateTimeImmutable $estimatedDelivery;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->trackingNumber = $data['trackingNumber'] ?? '';
        $dto->shipmentId = $data['shipmentId'] ?? '';
        $dto->status = $data['status'] ?? 'pending';
        $dto->labelUrl = $data['labelUrl'] ?? null;
        $dto->estimatedDelivery = isset($data['estimatedDelivery']) 
            ? new DateTimeImmutable($data['estimatedDelivery']) 
            : null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'shipmentId' => $this->shipmentId,
            'status' => $this->status,
            'labelUrl' => $this->labelUrl,
            'estimatedDelivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s')
        ];
    }
}