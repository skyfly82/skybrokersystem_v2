<?php

declare(strict_types=1);

namespace App\Domain\Courier\DTO;

use DateTimeImmutable;

class TrackingDetailsDTO
{
    public string $trackingNumber;
    public string $status;
    public string $statusDescription;
    public ?DateTimeImmutable $estimatedDelivery;
    public ?DateTimeImmutable $deliveredAt;
    public array $events = [];
    public ?string $currentLocation;
    public ?string $recipientName;
    public ?string $recipientPhone;
    public ?string $deliveryMethod;
    public ?string $lockerName;
    public ?string $lockerAddress;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->trackingNumber = $data['trackingNumber'] ?? '';
        $dto->status = $data['status'] ?? '';
        $dto->statusDescription = $data['statusDescription'] ?? '';
        $dto->estimatedDelivery = isset($data['estimatedDelivery']) 
            ? new DateTimeImmutable($data['estimatedDelivery']) 
            : null;
        $dto->deliveredAt = isset($data['deliveredAt']) 
            ? new DateTimeImmutable($data['deliveredAt']) 
            : null;
        $dto->events = $data['events'] ?? [];
        $dto->currentLocation = $data['currentLocation'] ?? null;
        $dto->recipientName = $data['recipientName'] ?? null;
        $dto->recipientPhone = $data['recipientPhone'] ?? null;
        $dto->deliveryMethod = $data['deliveryMethod'] ?? null;
        $dto->lockerName = $data['lockerName'] ?? null;
        $dto->lockerAddress = $data['lockerAddress'] ?? null;

        return $dto;
    }

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'status' => $this->status,
            'statusDescription' => $this->statusDescription,
            'estimatedDelivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s'),
            'deliveredAt' => $this->deliveredAt?->format('Y-m-d H:i:s'),
            'events' => $this->events,
            'currentLocation' => $this->currentLocation,
            'recipientName' => $this->recipientName,
            'recipientPhone' => $this->recipientPhone,
            'deliveryMethod' => $this->deliveryMethod,
            'lockerName' => $this->lockerName,
            'lockerAddress' => $this->lockerAddress,
        ];
    }
}