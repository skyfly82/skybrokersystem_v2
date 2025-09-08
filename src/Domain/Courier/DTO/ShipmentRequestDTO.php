<?php

declare(strict_types=1);

namespace App\Domain\Courier\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ShipmentRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $senderName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $senderEmail;

    #[Assert\NotBlank]
    public string $senderAddress;

    #[Assert\NotBlank]
    public string $recipientName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $recipientEmail;

    #[Assert\NotBlank]
    public string $recipientAddress;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public float $weight;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['standard', 'express', 'priority'])]
    public string $serviceType;

    public ?string $specialInstructions = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        return $dto;
    }
}