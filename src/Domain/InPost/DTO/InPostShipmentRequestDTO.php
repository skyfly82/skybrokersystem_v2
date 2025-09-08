<?php

declare(strict_types=1);

namespace App\Domain\InPost\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class InPostShipmentRequestDTO
{
    #[Assert\NotNull]
    #[Assert\Type('array')]
    private array $receiver;

    #[Assert\NotNull]
    #[Assert\Type('array')]
    private array $parcels;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    private string $service;

    private ?string $reference = null;

    private ?array $sender = null;

    private ?string $comments = null;

    private ?array $customAttributes = null;

    public function __construct(array $data)
    {
        $this->receiver = $data['receiver'] ?? [];
        $this->parcels = $data['parcels'] ?? [];
        $this->service = $data['service'] ?? '';
        $this->reference = $data['reference'] ?? null;
        $this->sender = $data['sender'] ?? null;
        $this->comments = $data['comments'] ?? null;
        $this->customAttributes = $data['custom_attributes'] ?? null;
    }

    public function getReceiver(): array
    {
        return $this->receiver;
    }

    public function setReceiver(array $receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getParcels(): array
    {
        return $this->parcels;
    }

    public function setParcels(array $parcels): self
    {
        $this->parcels = $parcels;
        return $this;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getSender(): ?array
    {
        return $this->sender;
    }

    public function setSender(?array $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }

    public function getCustomAttributes(): ?array
    {
        return $this->customAttributes;
    }

    public function setCustomAttributes(?array $customAttributes): self
    {
        $this->customAttributes = $customAttributes;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'receiver' => $this->receiver,
            'parcels' => $this->parcels,
            'service' => $this->service,
        ];

        if ($this->reference !== null) {
            $data['reference'] = $this->reference;
        }

        if ($this->sender !== null) {
            $data['sender'] = $this->sender;
        }

        if ($this->comments !== null) {
            $data['comments'] = $this->comments;
        }

        if ($this->customAttributes !== null) {
            $data['custom_attributes'] = $this->customAttributes;
        }

        return $data;
    }
}