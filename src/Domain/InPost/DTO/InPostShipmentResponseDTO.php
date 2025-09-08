<?php

declare(strict_types=1);

namespace App\Domain\InPost\DTO;

class InPostShipmentResponseDTO
{
    private string $id;
    private ?string $trackingNumber;
    private string $status;
    private ?string $reference;
    private string $createdAt;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->rawResponse = $response;
        $this->id = (string) ($response['id'] ?? '');
        $this->trackingNumber = $response['tracking_number'] ?? null;
        $this->status = $response['status'] ?? '';
        $this->reference = $response['reference'] ?? null;
        $this->createdAt = $response['created_at'] ?? '';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
}