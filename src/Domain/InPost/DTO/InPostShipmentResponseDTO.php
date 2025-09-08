<?php

declare(strict_types=1);

namespace App\Domain\InPost\DTO;

use App\Domain\Courier\DTO\ShipmentResponseDTO;
use DateTimeImmutable;

class InPostShipmentResponseDTO extends ShipmentResponseDTO
{
    public ?string $paczkomatCode = null;
    public ?string $paczkomatName = null;
    public ?string $paczkomatAddress = null;
    public ?string $openCode = null;
    public ?string $qrCode = null;
    public ?float $totalAmount = null;
    public ?float $codAmount = null;
    public array $parcelDimensions = [];
    public ?DateTimeImmutable $expiryDate = null;
    public array $additionalServices = [];
    
    public static function fromArray(array $data): self
    {
        $dto = new self();
        
        // Set parent properties
        $dto->trackingNumber = $data['trackingNumber'] ?? '';
        $dto->shipmentId = $data['shipmentId'] ?? '';
        $dto->status = $data['status'] ?? 'pending';
        $dto->labelUrl = $data['labelUrl'] ?? null;
        $dto->estimatedDelivery = isset($data['estimatedDelivery']) 
            ? new DateTimeImmutable($data['estimatedDelivery']) 
            : null;

        // Set InPost-specific properties
        $dto->paczkomatCode = $data['paczkomatCode'] ?? null;
        $dto->paczkomatName = $data['paczkomatName'] ?? null;
        $dto->paczkomatAddress = $data['paczkomatAddress'] ?? null;
        $dto->openCode = $data['openCode'] ?? null;
        $dto->qrCode = $data['qrCode'] ?? null;
        $dto->totalAmount = $data['totalAmount'] ?? null;
        $dto->codAmount = $data['codAmount'] ?? null;
        $dto->parcelDimensions = $data['parcelDimensions'] ?? [];
        $dto->expiryDate = isset($data['expiryDate']) 
            ? new DateTimeImmutable($data['expiryDate']) 
            : null;
        $dto->additionalServices = $data['additionalServices'] ?? [];

        return $dto;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'paczkomatCode' => $this->paczkomatCode,
            'paczkomatName' => $this->paczkomatName,
            'paczkomatAddress' => $this->paczkomatAddress,
            'openCode' => $this->openCode,
            'qrCode' => $this->qrCode,
            'totalAmount' => $this->totalAmount,
            'codAmount' => $this->codAmount,
            'parcelDimensions' => $this->parcelDimensions,
            'expiryDate' => $this->expiryDate?->format('Y-m-d H:i:s'),
            'additionalServices' => $this->additionalServices,
        ]);
    }

    public function isPackzomatDelivery(): bool
    {
        return !empty($this->paczkomatCode);
    }

    public function hasCashOnDelivery(): bool
    {
        return $this->codAmount > 0;
    }

    public function hasOpenCode(): bool
    {
        return !empty($this->openCode);
    }

    public function isExpired(): bool
    {
        if (!$this->expiryDate) {
            return false;
        }

        return $this->expiryDate < new DateTimeImmutable();
    }
}