<?php

declare(strict_types=1);

namespace App\Courier\InPost\DTO;

class InPostShipmentResponseDTO
{
    public string $trackingNumber;
    public string $shipmentId;
    public string $status;
    public ?string $labelUrl;
    public ?\DateTimeInterface $estimatedDelivery;
    public ?string $paczkomatCode;
    public ?float $totalAmount;
    public ?float $codAmount;
    public array $parcelDimensions;

    public function __construct(
        string $trackingNumber,
        string $shipmentId,
        string $status,
        ?string $labelUrl = null,
        ?\DateTimeInterface $estimatedDelivery = null,
        ?string $paczkomatCode = null,
        ?float $totalAmount = null,
        ?float $codAmount = null,
        array $parcelDimensions = []
    ) {
        $this->trackingNumber = $trackingNumber;
        $this->shipmentId = $shipmentId;
        $this->status = $status;
        $this->labelUrl = $labelUrl;
        $this->estimatedDelivery = $estimatedDelivery;
        $this->paczkomatCode = $paczkomatCode;
        $this->totalAmount = $totalAmount;
        $this->codAmount = $codAmount;
        $this->parcelDimensions = $parcelDimensions;
    }

    public static function fromArray(array $data): self
    {
        $estimatedDelivery = null;
        if (isset($data['estimatedDelivery'])) {
            $estimatedDelivery = $data['estimatedDelivery'] instanceof \DateTimeInterface
                ? $data['estimatedDelivery']
                : new \DateTimeImmutable($data['estimatedDelivery']);
        }

        return new self(
            $data['trackingNumber'] ?? '',
            $data['shipmentId'] ?? '',
            $data['status'] ?? 'created',
            $data['labelUrl'] ?? null,
            $estimatedDelivery,
            $data['paczkomatCode'] ?? null,
            isset($data['totalAmount']) ? (float) $data['totalAmount'] : null,
            isset($data['codAmount']) ? (float) $data['codAmount'] : null,
            $data['parcelDimensions'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'shipmentId' => $this->shipmentId,
            'status' => $this->status,
            'labelUrl' => $this->labelUrl,
            'estimatedDelivery' => $this->estimatedDelivery?->format('Y-m-d H:i:s'),
            'paczkomatCode' => $this->paczkomatCode,
            'totalAmount' => $this->totalAmount,
            'codAmount' => $this->codAmount,
            'parcelDimensions' => $this->parcelDimensions,
        ];
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['created', 'confirmed', 'in_transit', 'delivered'], true);
    }

    public function hasLabel(): bool
    {
        return !empty($this->labelUrl);
    }

    public function hasPaczkomatDelivery(): bool
    {
        return !empty($this->paczkomatCode);
    }
}