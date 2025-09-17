<?php

declare(strict_types=1);

namespace App\Courier\DHL\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DHLShipmentRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $senderName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $senderEmail;

    #[Assert\NotBlank]
    public string $senderPhone;

    #[Assert\NotBlank]
    public string $senderAddress;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[0-9]{2}-[0-9]{3}$/')]
    public string $senderPostalCode;

    #[Assert\NotBlank]
    public string $senderCity;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    public string $senderCountryCode = 'PL';

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $recipientName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $recipientEmail;

    #[Assert\NotBlank]
    public string $recipientPhone;

    #[Assert\NotBlank]
    public string $recipientAddress;

    #[Assert\NotBlank]
    public string $recipientPostalCode;

    #[Assert\NotBlank]
    public string $recipientCity;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    public string $recipientCountryCode;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\Range(min: 0.1, max: 70)]
    public float $weight;

    #[Assert\Positive]
    public ?float $length = null;

    #[Assert\Positive]
    public ?float $width = null;

    #[Assert\Positive]
    public ?float $height = null;

    #[Assert\Choice(choices: ['N', 'U', 'P', 'Q', 'T', 'W', 'X'])]
    public string $productCode = 'N'; // N = Domestic Express

    public ?string $customerReference = null;

    public ?string $description = 'Package';

    public ?float $insuranceAmount = null;

    public bool $requiresSignature = false;

    public bool $isBusinessDay = true;

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

    public function toArray(): array
    {
        return [
            'senderName' => $this->senderName,
            'senderEmail' => $this->senderEmail,
            'senderPhone' => $this->senderPhone,
            'senderAddress' => $this->senderAddress,
            'senderPostalCode' => $this->senderPostalCode,
            'senderCity' => $this->senderCity,
            'senderCountryCode' => $this->senderCountryCode,
            'recipientName' => $this->recipientName,
            'recipientEmail' => $this->recipientEmail,
            'recipientPhone' => $this->recipientPhone,
            'recipientAddress' => $this->recipientAddress,
            'recipientPostalCode' => $this->recipientPostalCode,
            'recipientCity' => $this->recipientCity,
            'recipientCountryCode' => $this->recipientCountryCode,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'productCode' => $this->productCode,
            'customerReference' => $this->customerReference,
            'description' => $this->description,
            'insuranceAmount' => $this->insuranceAmount,
            'requiresSignature' => $this->requiresSignature,
            'isBusinessDay' => $this->isBusinessDay,
        ];
    }

    public function hasInsurance(): bool
    {
        return $this->insuranceAmount !== null && $this->insuranceAmount > 0;
    }

    public function getDimensions(): array
    {
        return [
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function getVolumetricWeight(): float
    {
        if ($this->length && $this->width && $this->height) {
            return ($this->length * $this->width * $this->height) / 5000; // DHL volumetric weight divisor
        }

        return $this->weight;
    }

    public function isInternational(): bool
    {
        return $this->senderCountryCode !== $this->recipientCountryCode;
    }

    public function isDomestic(): bool
    {
        return !$this->isInternational();
    }
}