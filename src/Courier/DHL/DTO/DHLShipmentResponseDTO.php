<?php

declare(strict_types=1);

namespace App\Courier\DHL\DTO;

class DHLShipmentResponseDTO
{
    public function __construct(
        public string $trackingNumber,
        public string $shipmentId,
        public string $status,
        public ?string $labelUrl = null,
        public ?float $totalAmount = null,
        public ?\DateTimeInterface $estimatedDelivery = null,
        public ?string $service = null,
        public ?array $packages = null,
        public ?array $charges = null,
        public ?string $billingReference = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            trackingNumber: $data['trackingNumber'] ?? '',
            shipmentId: $data['shipmentId'] ?? '',
            status: $data['status'] ?? 'created',
            labelUrl: $data['labelUrl'] ?? null,
            totalAmount: $data['totalAmount'] ? (float) $data['totalAmount'] : null,
            estimatedDelivery: $data['estimatedDelivery'] ?
                new \DateTimeImmutable($data['estimatedDelivery']) : null,
            service: $data['service'] ?? null,
            packages: $data['packages'] ?? null,
            charges: $data['charges'] ?? null,
            billingReference: $data['billingReference'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'shipmentId' => $this->shipmentId,
            'status' => $this->status,
            'labelUrl' => $this->labelUrl,
            'totalAmount' => $this->totalAmount,
            'estimatedDelivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s'),
            'service' => $this->service,
            'packages' => $this->packages,
            'charges' => $this->charges,
            'billingReference' => $this->billingReference,
        ];
    }

    public function hasLabel(): bool
    {
        return $this->labelUrl !== null;
    }

    public function isCreated(): bool
    {
        return $this->status === 'created';
    }

    public function hasEstimatedDelivery(): bool
    {
        return $this->estimatedDelivery !== null;
    }

    public function getTotalCost(): ?float
    {
        return $this->totalAmount;
    }

    public function getFormattedCost(string $currency = 'PLN'): ?string
    {
        if ($this->totalAmount === null) {
            return null;
        }

        return number_format($this->totalAmount, 2) . ' ' . $currency;
    }
}